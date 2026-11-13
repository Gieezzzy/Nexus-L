using System.Text.Json;
using System.Text.Json.Nodes;
using System.Text.RegularExpressions;
using Microsoft.Playwright;

namespace NexusInterlinkService;

public sealed class SignInRequiredException : Exception
{
    public SignInRequiredException(string message) : base(message) { }
}

/// <summary>
/// Does one report in InterLink with an invisible (headless) browser: home, the search grid, the row's Add / Edit menu, the Upload Form,
/// fill, attach the signed PDF, Save. The page logic is the very same one the browser extension runs (Scripts/interlink-core.js is cut
/// out of the extension by tools/build/build-service-scripts.js), so both behave alike. It signs in with the saved session of the shared
/// service account (made once with --signin, which can pass Microsoft's sign-in and MFA); no password is stored anywhere.
/// </summary>
public sealed class InterlinkRunner : IAsyncDisposable
{
    private const string PortalHome = "https://interlink2.intertek.com/";
    private const string SignInAgain = "InterLink asked the service account to sign in again. On the service computer run: NexusInterlinkService --signin";

    /// <summary>Account 1: VF eTRF - Material, VF eTRF - Product, VF eTRF - Product WHOLE SHOE TEST, VF eTRF - bonding</summary>
    public const string AccountLabAsst = "dtp_qcasst@dtshoes.com";
    /// <summary>Account 2: VF eTRF - PULLTESTTemplate</summary>
    public const string AccountLab = "dtp_lab@dtshoes.com";

    private readonly IConfiguration _cfg;
    private readonly Notifier _notifier;
    private readonly ILogger<InterlinkRunner> _log;
    private readonly string _core;
    private IPlaywright? _pw;
    private IBrowser? _browser;

    public InterlinkRunner(IConfiguration cfg, Notifier notifier, ILogger<InterlinkRunner> log)
    {
        _cfg = cfg;
        _notifier = notifier;
        _log = log;
        _core = File.ReadAllText(Path.Combine(AppContext.BaseDirectory, "Scripts", "interlink-core.js"));
    }

    public static string DataDir(IConfiguration cfg)
    {
        var d = cfg["Service:DataDir"] ?? "data";
        if (!Path.IsPathRooted(d)) d = Path.Combine(AppContext.BaseDirectory, d);
        Directory.CreateDirectory(d);
        return d;
    }

    public static string NormalizeAccount(string? raw)
    {
        var s = (raw ?? "").Trim().ToLowerInvariant();
        if (s == "" || s == "default") return "";
        if (s.Contains("labasst") || s.Contains("qcasst") || s.Contains("material") || s.Contains("whole") || s.Contains("bond") || s.Contains("e-trf-mat") || s.Contains("etrf-mat") || s.Contains("etrf-prod"))
            return AccountLabAsst;
        if (s == "lab" || s.StartsWith("dtp_lab@") || s.Contains("pull"))
            return AccountLab;
        return AccountLabAsst;
    }

    public static string ResolveAccountForJob(JobRequest req)
    {
        if (!string.IsNullOrWhiteSpace(req.Account))
            return NormalizeAccount(req.Account);
        var jobAcc = (string?)req.Job["account"] ?? (string?)req.Job["etrf"]?["account_email"];
        if (!string.IsNullOrWhiteSpace(jobAcc))
            return NormalizeAccount(jobAcc);

        var etrfType = ((string?)req.Job["etrf"]?["etrf_type"] ?? "").ToLowerInvariant();
        var tplLabel = ((string?)req.Job["etrf"]?["template_label"] ?? req.ReportNo ?? "").ToLowerInvariant();
        if (etrfType.Contains("pull") || tplLabel.Contains("pull"))
            return AccountLab;
        if (req.Mode == "etrf" || req.Mode == "lov" || req.Mode == "vf_copy" || req.Mode == "vf_grid" || req.Mode == "grid_html" || req.Mode == "vf_pdf")
            return AccountLabAsst;
        return "";
    }

    public static string SessionFile(IConfiguration cfg, string? account = null)
    {
        var norm = NormalizeAccount(account);
        if (norm == AccountLabAsst) return Path.Combine(DataDir(cfg), "interlink-session-labasst.json");
        if (norm == AccountLab) return Path.Combine(DataDir(cfg), "interlink-session-lab.json");
        return Path.Combine(DataDir(cfg), "interlink-session.json");
    }

    public static string ResolveSessionFile(IConfiguration cfg, string? account = null)
    {
        var specific = SessionFile(cfg, account);
        if (File.Exists(specific)) return specific;
        var legacy = Path.Combine(DataDir(cfg), "interlink-session.json");
        if (File.Exists(legacy)) return legacy;
        var altAsst = SessionFile(cfg, AccountLabAsst);
        if (File.Exists(altAsst)) return altAsst;
        var altLab = SessionFile(cfg, AccountLab);
        if (File.Exists(altLab)) return altLab;
        return specific;
    }

    public static bool IsAccountSignedIn(IConfiguration cfg, string account)
    {
        var norm = NormalizeAccount(account);
        var f = SessionFile(cfg, norm);
        if (File.Exists(f)) return true;

        // Fallback if legacy interlink-session.json exists: check if it belongs to this account
        var legacy = Path.Combine(DataDir(cfg), "interlink-session.json");
        if (File.Exists(legacy))
        {
            try
            {
                var content = File.ReadAllText(legacy);
                if (norm == AccountLabAsst && (content.Contains("dtp_qcasst@", StringComparison.OrdinalIgnoreCase) || content.Contains("dtp_labasst@", StringComparison.OrdinalIgnoreCase) || content.Contains("dtp_qcasst%40", StringComparison.OrdinalIgnoreCase)))
                    return true;
                if (norm == AccountLab && (content.Contains("dtp_lab@", StringComparison.OrdinalIgnoreCase) || content.Contains("dtp_lab%40", StringComparison.OrdinalIgnoreCase)))
                    return true;
            }
            catch { }
        }
        return false;
    }

    private readonly SemaphoreSlim _browserGate = new(1, 1);          // loading jobs run side by side: only one of them may start the browser
    private static readonly object SessionWrite = new();               // ... and only one may write the saved session file at a time

    private async Task<IBrowser> BrowserAsync()
    {
        await _browserGate.WaitAsync();
        try
        {
            _pw ??= await Playwright.CreateAsync();
            if (_browser is { IsConnected: true }) return _browser;
            _browser = await BrowserLauncher.LaunchAsync(_pw, _cfg.GetValue("Service:Headless", true), _cfg);
            return _browser;
        }
        finally { _browserGate.Release(); }
    }

    private static bool OnSignIn(string url) =>
        url.Contains("login.microsoftonline.com", StringComparison.OrdinalIgnoreCase) || Regex.IsMatch(url, "/(login|signin|sign-in)", RegexOptions.IgnoreCase);

    /// <summary>runs window.__nx[name](...args) in the page; null when the page changed under it (a navigation), so the caller looks again</summary>
    private static async Task<JsonElement?> EvalAsync(IPage page, string name, params object?[] args)
    {
        try
        {
            // the arguments travel as JSON text (Playwright cannot serialize JsonElement / JsonNode objects itself) and are parsed in the page
            var json = JsonSerializer.Serialize(args);
            return await page.EvaluateAsync<JsonElement>("async ([n, j]) => await window.__nx[n](...JSON.parse(j))", new object?[] { name, json });
        }
        catch (PlaywrightException e) when (e.Message.Contains("context was destroyed") || e.Message.Contains("Target closed") || e.Message.Contains("navigat") || e.Message.Contains("closed"))
        {
            return null;
        }
    }

    private static string Str(JsonElement? e, string prop) =>
        e is { ValueKind: JsonValueKind.Object } o && o.TryGetProperty(prop, out var v) && v.ValueKind == JsonValueKind.String ? v.GetString() ?? "" : "";

    /// <summary>The job's own account first. When its saved session is expired (InterLink asks to sign in) and the other account is still signed in,
    /// the job runs with that one instead of failing: nobody has to log in again, and the job is not lost.</summary>
    public async Task RunAsync(JobRequest req, Action<string> say, CancellationToken stop)
    {
        try { await RunCoreAsync(req, say, stop); }
        catch (SignInRequiredException) when (req.BorrowedAccount == null)
        {
            var own = ResolveAccountForJob(req);
            var other = own == AccountLabAsst ? AccountLab : AccountLabAsst;
            if (File.Exists(SessionFile(_cfg, other)) && other != own)
            {
                say($"{(own == "" ? "The saved" : own)} session has expired - using the signed-in account {other} instead.");
                req.BorrowedAccount = other;
                await RunCoreAsync(req, say, stop);
                return;
            }
            throw;
        }
    }

    private async Task RunCoreAsync(JobRequest req, Action<string> say, CancellationToken stop)
    {
        var accountEmail = req.BorrowedAccount ?? ResolveAccountForJob(req);
        var sessionFile = req.BorrowedAccount != null ? SessionFile(_cfg, req.BorrowedAccount) : ResolveSessionFile(_cfg, accountEmail);
        var targetSaveFile = SessionFile(_cfg, accountEmail);
        var signInMsg = !string.IsNullOrEmpty(accountEmail)
            ? $"InterLink account {accountEmail} needs to sign in. In NexusInterlinkService click 'Login: {accountEmail}'."
            : SignInAgain;
        if (!File.Exists(sessionFile))
            throw new SignInRequiredException(signInMsg);

        var minutes = req.Mode == "lov" ? Math.Max(_cfg.GetValue("Service:JobTimeoutMinutes", 20), 120) : _cfg.GetValue("Service:JobTimeoutMinutes", 20);
        using var cts = CancellationTokenSource.CreateLinkedTokenSource(stop);
        cts.CancelAfter(TimeSpan.FromMinutes(minutes));
        var ct = cts.Token;

        var browser = await BrowserAsync();
        await using var ctx = await browser.NewContextAsync(new BrowserNewContextOptions { StorageStatePath = sessionFile, AcceptDownloads = true, ViewportSize = new ViewportSize { Width = 1500, Height = 950 } });
        await ctx.AddInitScriptAsync(_core);   // window.__nx exists in every InterLink document, the Upload Form popup included
        // InterLink answers a refused Print & Submit by posting the form back and showing a toast ("... Style Information is missing!") for a few
        // seconds on the NEW page: every page records such messages in the tab's session storage, which survives the reload, so the job can quote them.
        await ctx.AddInitScriptAsync(@"(() => { try {
            const KEY = '__nxRefusals';
            const re = /\b(is|are) (missing|required)\b|invalid|must be (entered|selected|filled)|cannot be|not allowed|already exist/i;
            const push = (t) => {
              t = String(t || '').replace(/\s+/g, ' ').trim();
              if (!t || t.length > 300 || !re.test(t)) return;
              const a = JSON.parse(sessionStorage.getItem(KEY) || '[]');
              if (!a.includes(t)) { a.push(t); sessionStorage.setItem(KEY, JSON.stringify(a.slice(-8))); }
            };
            new MutationObserver((ms) => { for (const m of ms) for (const n of m.addedNodes) push(n.textContent); }).observe(document, { childList: true, subtree: true });
          } catch (e) { /* no storage in this frame */ } })();");
        // InterLink's own message boxes (alert / confirm): an invisible browser would dismiss them unseen. They are read, logged, and accepted.
        var dialogs = new List<string>();
        ctx.Dialog += async (_, d) =>
        {
            var text = (d.Message ?? "").Replace("\r", " ").Replace("\n", " ").Trim();
            lock (dialogs) dialogs.Add(text);
            say($"InterLink says: {text}");
            try { await d.AcceptAsync(); } catch (PlaywrightException) { /* already closed */ }
        };
        var grid = await ctx.NewPageAsync();
        var signedIn = false;
        IPage? current = grid;                   // the page a picture is taken of when something goes wrong
        try
        {
            if (req.Mode == "lov")
            {
                await RunLovAsync(grid, req, accountEmail, signInMsg, say, ct);
                signedIn = true;
                return;
            }
            if (req.Mode == "etrf")
            {
                await RunEtrfAsync(grid, ctx, req, accountEmail, signInMsg, say, dialogs, ct);
                signedIn = true;
                return;
            }
            if (req.Mode == "grid_html")
            {
                await RunGridHtmlAsync(grid, req, accountEmail, signInMsg, say, ct);
                signedIn = true;
                return;
            }
            if (req.Mode == "vf_grid")
            {
                await RunVfGridAsync(grid, req, accountEmail, signInMsg, say, ct);
                signedIn = true;
                return;
            }
            if (req.Mode == "vf_pdf")
            {
                await RunVfPdfAsync(grid, ctx, req, accountEmail, signInMsg, say, ct);
                signedIn = true;
                return;
            }
            if (req.Mode == "vf_copy")
            {
                await RunVfCopyAsync(grid, ctx, req, accountEmail, signInMsg, say, dialogs, ct);
                signedIn = true;
                return;
            }

            // 1. home, then the search grid
            say($"Opening InterLink{(accountEmail != "" ? $" ({accountEmail})" : "")}...");
            await ReachGridAsync(grid, accountEmail, signInMsg, say, ct);
            signedIn = true;

            // 2. the application's row: its Add / Edit menu opens the Upload Form in a popup
            say($"Looking for {req.ApplicationNo}...");
            var popupSource = new TaskCompletionSource<IPage>(TaskCreationOptions.RunContinuationsAsynchronously);
            void OnPage(object? s, IPage pg) => popupSource.TrySetResult(pg);
            ctx.Page += OnPage;
            JsonElement? opened = null;
            for (var attempt = 0; attempt < 6; attempt++)
            {
                opened = await EvalAsync(grid, "searchAndOpenUpload", req.ApplicationNo, req.Mode);
                var state = Str(opened, "state");
                if (state == "clicked" || state == "error") break;
                await Task.Delay(2500, ct);   // the page is still changing
            }
            if (Str(opened, "state") == "error") throw new InvalidOperationException(Str(opened, "message"));
            if (Str(opened, "state") != "clicked") throw new InvalidOperationException($"{req.ApplicationNo} could not be opened in InterLink.");
            say(Str(opened, "mode") == "Edit" ? "InterLink: editing the current report..." : "InterLink: adding a new report...");
            var first = await Task.WhenAny(popupSource.Task, Task.Delay(TimeSpan.FromSeconds(40), ct));
            ctx.Page -= OnPage;
            if (first != popupSource.Task) throw new InvalidOperationException("InterLink did not open the Upload Form.");
            var form = popupSource.Task.Result;
            current = form;

            // 3. fill the Upload Form: several page loads (tabs, reading pages) until it reports "filled"
            var navCount = 0;
            form.FrameNavigated += (_, f) => { if (f == form.MainFrame) Interlocked.Increment(ref navCount); };
            await form.WaitForLoadStateAsync(LoadState.DOMContentLoaded);
            await Task.Delay(1500, ct);
            var job = req.Job;
            job["mode"] = req.Mode;
            var items = job["tables"]?["items"]?.AsArray().Count ?? 0;
            var maxLoads = 8 + 3 * items;
            JsonElement? filled = null;
            for (var load = 1; load <= maxLoads && filled == null; load++)
            {
                ct.ThrowIfCancellationRequested();
                if (form.IsClosed) throw new InvalidOperationException("InterLink closed the Upload Form before it was filled.");
                say($"Filling the Upload Form (step {load})...");
                var before = navCount;
                var r = await EvalAsync(form, "fillAndAttach", job);
                if (r is not { ValueKind: JsonValueKind.Object } ro)
                {
                    await SettleAsync(form, () => navCount, before, 6000, ct);   // the page changed while it was being filled
                    continue;
                }
                if (ro.TryGetProperty("patch", out var patch) && patch.ValueKind == JsonValueKind.Object)
                    foreach (var prop in patch.EnumerateObject()) job[prop.Name] = JsonNode.Parse(prop.Value.GetRawText());
                var st = Str(r, "state");
                if (st == "error") throw new InvalidOperationException(Str(r, "message"));
                if (st == "signin") throw new SignInRequiredException(SignInAgain);
                if (st == "posting")
                {
                    await SettleAsync(form, () => navCount, before, 12000, ct);
                    continue;
                }
                filled = r;
            }
            if (filled == null) throw new InvalidOperationException($"InterLink did not finish filling {req.ApplicationNo}.");

            // 4. the approval is asked again right before Save: no approval, no Save
            var (approved, problem) = await _notifier.VerifyAsync(req.VerifyUrl);
            if (!approved) throw new InvalidOperationException($"{req.ApplicationNo} was NOT saved: {problem}");

            // 5. Save
            say("Pressing Save...");
            var navBefore = navCount;
            var save = await EvalAsync(form, "pressCommit");
            if (Str(save, "state") == "error") throw new InvalidOperationException(Str(save, "message"));
            await SettleAsync(form, () => navCount, navBefore, 9000, ct);
            if (navCount == navBefore && !form.IsClosed)
            {
                // the click did not reach InterLink's own handler: its postback for Save, once more
                var again = await EvalAsync(form, "forceSave");
                if (Str(again, "state") == "error") throw new InvalidOperationException(Str(again, "message"));
                await SettleAsync(form, () => navCount, navBefore, 15000, ct);
            }

            // 6. InterLink's answer on the form: the pop-up bubbles it shows after Save ("A report with the entered report number has already
            // exists!") are read, logged, and given as the reason if the report was not kept
            var notices = new List<string>();
            for (var look = 0; look < 2 && !form.IsClosed; look++)
            {
                var found = await EvalAsync(form, "readNotices");
                if (found is { ValueKind: JsonValueKind.Array } arr)
                    foreach (var n in arr.EnumerateArray())
                        if (n.GetString() is { Length: > 0 } text && !notices.Contains(text)) { notices.Add(text); say($"InterLink shows: {text}"); }
                if (look == 0) await Task.Delay(1500, ct);
            }
            await ShotAsync(form, req, "after-save");
            if (!form.IsClosed)
            {
                var check = await EvalAsync(form, "checkSubmitted");
                if (check is { ValueKind: JsonValueKind.Object } c && c.TryGetProperty("ok", out var ok) && ok.ValueKind == JsonValueKind.False)
                    throw new InvalidOperationException($"InterLink did not accept {req.ApplicationNo}: {Str(check, "message")}");
            }

            // 7. proof: InterLink's own search grid must now show the report (a Report No. on the application's row). "No error on the form"
            // is not proof that InterLink kept anything.
            say("Checking in InterLink that the report was kept...");
            var proof = await ctx.NewPageAsync();
            current = proof;
            await ReachGridAsync(proof, accountEmail, signInMsg, say, ct);
            JsonElement? rowInfo = null;
            for (var attempt = 0; attempt < 3; attempt++)
            {
                rowInfo = await EvalAsync(proof, "readGridRow", req.ApplicationNo);
                if (Str(rowInfo, "state") == "ok" && !string.IsNullOrWhiteSpace(Str(rowInfo, "reportNo"))) break;
                await Task.Delay(3000, ct);
                await proof.ReloadAsync(new PageReloadOptions { WaitUntil = WaitUntilState.DOMContentLoaded });
                await ReachGridAsync(proof, accountEmail, signInMsg, say, ct);
            }
            var seenReportNo = Str(rowInfo, "reportNo");
            if (string.IsNullOrWhiteSpace(seenReportNo))
            {
                await ShotAsync(proof, req, "not-kept");
                string said;
                lock (dialogs) said = dialogs.Count > 0 ? " InterLink asked: " + string.Join(" | ", dialogs) + "." : "";
                // the reason InterLink gave comes first: that is what the person has to act on
                var reason = notices.Count > 0 ? $"InterLink refused it: {string.Join(" | ", notices)}" : "InterLink did not keep the report";
                throw new InvalidOperationException($"{reason} (the grid still shows no Report No. for {req.ApplicationNo}, status \"{Str(rowInfo, "status")}\").{said} A picture is in the service's data\\shots folder.");
            }
            say($"InterLink now shows Report No. {seenReportNo} for {req.ApplicationNo}.");
            var notes = "";
            if (filled is { ValueKind: JsonValueKind.Object } f2 && f2.TryGetProperty("notes", out var nn) && nn.ValueKind == JsonValueKind.Array && nn.GetArrayLength() > 0)
                notes = " Not added: " + string.Join("; ", nn.EnumerateArray().Select(x => x.GetString())) + ".";
            say($"{req.ApplicationNo} was saved to InterLink.{notes}");
        }
        catch (Exception) when (current != null && !current.IsClosed)
        {
            await ShotAsync(current, req, "failed");   // the picture of where it stopped
            throw;
        }
        finally
        {
            if (signedIn)
            {
                try
                {
                    var stateJson = await ctx.StorageStateAsync();   // keeps the session fresh for the next job
                    lock (SessionWrite)
                    {
                        var tmp = targetSaveFile + ".tmp";
                        File.WriteAllText(tmp, stateJson);
                        File.Move(tmp, targetSaveFile, true);
                    }
                }
                catch (Exception e) { _log.LogWarning("Could not refresh the saved session: {Msg}", e.Message); }
            }
        }
    }

    private static async Task<bool> ClickSsoTileOrYesAsync(IPage page, string accountEmail)
    {
        return await page.EvaluateAsync<bool>(@"(email) => {
            if (email) {
                const exact = document.querySelector(`[data-test-id=""${email}"" i]`);
                if (exact) { exact.click(); return true; }
                const byText = [...document.querySelectorAll('#tilesHolder .tile-container, .table[role=button], [data-test-id*=""@""]')].find(el => (el.innerText || '').toLowerCase().includes(email.toLowerCase()));
                if (byText) { byText.click(); return true; }
            }
            const tile = document.querySelector('#tilesHolder .tile-container [data-test-id], .table[role=button], [data-test-id*=""@""]');
            if (tile && !email) { tile.click(); return true; }
            const yes = document.querySelector('#idSIButton9, input[type=submit][value=""Yes""]');
            const pass = document.querySelector('input[type=password]');
            if (yes && (!pass || pass.offsetParent === null)) { yes.click(); return true; }
            return false;
        }", accountEmail);
    }

    // lists already read in this sync run (a list shared by several templates is read once), keyed "syncId|listId|parentPath"
    private static readonly HashSet<string> LovDone = new();

    private sealed class LovPicker
    {
        public string Id = "", Field = "", Label = "", ListId = "", ParentField = "", Columns = "";
    }

    /// <summary>
    /// Reads every pick list ("search" button) of one eTRF template and sends it to NEXUS-L. Nothing is saved or submitted in InterLink.
    /// A list that depends on another field (End Use on Product Category, Brand, Test Package, Test Stage ...) is read once per value of
    /// that parent, by choosing the parent value on the form (the form is thrown away afterwards).
    /// </summary>
    private async Task RunLovAsync(IPage page, JobRequest req, string accountEmail, string signInMsg, Action<string> say, CancellationToken ct)
    {
        var etrf = req.Job["etrf"] as JsonObject ?? new JsonObject();
        var tplLabel = (string?)etrf["template_label"] ?? req.ReportNo;
        var url = (string?)req.Job["lov_url"] ?? "";
        var syncId = (string?)req.Job["sync_id"] ?? req.JobUid;
        if (url == "") throw new InvalidOperationException("The job has no lov_url.");

        await OpenEtrfFormAsync(page, etrf, tplLabel, accountEmail, signInMsg, say, ct);
        JsonElement? inv = null;
        for (var i = 0; i < 20; i++)
        {
            ct.ThrowIfCancellationRequested();
            inv = await EvalAsync(page, "lovInventory");
            if (inv is { ValueKind: JsonValueKind.Object } o && o.TryGetProperty("pickers", out var pk) && pk.GetArrayLength() > 0) break;
            await Task.Delay(1500, ct);
        }
        var pickers = new List<LovPicker>();
        if (inv is { ValueKind: JsonValueKind.Object } io && io.TryGetProperty("pickers", out var arr))
            foreach (var e in arr.EnumerateArray())
                pickers.Add(new LovPicker { Id = Str(e, "id"), Field = Str(e, "field"), Label = Str(e, "label"), ListId = Str(e, "list_id"), ParentField = Str(e, "parent_field"), Columns = Str(e, "columns") });
        pickers = pickers.Where(p => p.ListId != "").ToList();
        if (pickers.Count == 0) throw new InvalidOperationException($"{tplLabel}: no pick-list buttons were found on the form.");
        say($"{tplLabel}: {pickers.Count} pick lists found.");

        // the picker that fills a given field: the field is one of its "fields to set" columns
        LovPicker? ParentOf(LovPicker c) => c.ParentField == "" ? null : pickers.FirstOrDefault(p => p != c && (p.Field == c.ParentField || p.Columns.Split(';').Contains(c.ParentField)));
        var children = pickers.ToLookup(p => ParentOf(p)?.Id ?? "");
        var sent = 0;

        async Task<List<List<string>>> ReadAllAsync(LovPicker p, string path)
        {
            var rows = new List<List<string>>();
            var headers = new List<string>();
            var next = 0;
            var empty = false;
            for (var round = 0; round < 80; round++)
            {
                ct.ThrowIfCancellationRequested();
                var r = await EvalAsync(page, "lovHarvest", p.Id, next, 25000);
                if (r == null) { await Task.Delay(1500, ct); continue; }
                var st = Str(r, "state");
                if (st == "error") { say($"{tplLabel} / {p.Label} {p.Field}: {Str(r, "message")}"); break; }
                var el = r.Value;
                headers = el.GetProperty("headers").EnumerateArray().Select(x => x.GetString() ?? "").ToList();
                foreach (var row in el.GetProperty("rows").EnumerateArray()) rows.Add(row.EnumerateArray().Select(x => x.GetString() ?? "").ToList());
                empty = el.TryGetProperty("empty", out var em) && em.ValueKind == JsonValueKind.True;
                next = el.GetProperty("next_page").GetInt32();
                if (st == "done") break;
            }
            var key = $"{syncId}|{p.ListId}|{path}";
            await _notifier.PostJsonAsync(url, new { sync_id = syncId, template = tplLabel, list = new { field = p.Field, label = p.Label, list_id = p.ListId, parent_field = p.ParentField, columns = p.Columns, parent_path = path, headers, rows, empty } });
            lock (LovDone) LovDone.Add(key);
            sent++;
            say($"{tplLabel}: {p.Label} {p.Field} {(path == "" ? "" : "[" + path + "] ")}= {rows.Count} value(s){(empty ? " (nothing until its parent field is set)" : "")}.");
            return rows;
        }

        async Task WalkAsync(LovPicker p, string path)
        {
            var rows = await ReadAllAsync(p, path);
            if (!children[p.Id].Any() || rows.Count == 0 || rows.Count > 60) return;      // only small lists act as a parent
            for (var i = 0; i < rows.Count; i++)
            {
                var text = rows[i][0];
                var pick = await EvalAsync(page, "lovPick", p.Id, text, i / 10);
                if (Str(pick, "state") != "ok") { say($"{tplLabel}: could not choose \"{text}\" in {p.Label}: {Str(pick, "message")}"); continue; }
                foreach (var c in children[p.Id])
                    await WalkAsync(c, (path == "" ? "" : path + ";") + p.Field + "=" + text);
            }
        }

        foreach (var root in children[""])
        {
            ct.ThrowIfCancellationRequested();
            bool done; lock (LovDone) done = LovDone.Contains($"{syncId}|{root.ListId}|");
            if (done && !children[root.Id].Any()) { await _notifier.PostJsonAsync(url, new { sync_id = syncId, template = tplLabel, alias = new { field = root.Field, label = root.Label, list_id = root.ListId, parent_field = root.ParentField, columns = root.Columns } }); continue; }
            await WalkAsync(root, "");
        }
        say($"{tplLabel}: pick lists read ({sent} sent to NEXUS-L). Nothing was saved in InterLink.");
    }

    /// <summary>Home.aspx -> Submit a Request -> the exact eTRF template (never another form); returns when the form page is up.</summary>
    private async Task OpenEtrfFormAsync(IPage page, JsonObject etrf, string tplLabel, string accountEmail, string signInMsg, Action<string> say, CancellationToken ct)
    {
        say($"Opening InterLink Home for eTRF ({tplLabel}) using {(accountEmail != "" ? accountEmail : "service account")}...");
        await page.GotoAsync("https://interlink2.intertek.com/InterLink2/aspx/DlkPage/Portal2/Home.aspx",
            new PageGotoOptions { WaitUntil = WaitUntilState.DOMContentLoaded, Timeout = 60000 });

        // Handle Microsoft SSO tile or "Stay signed in?" if presented
        for (var sso = 0; sso < 16 && OnSignIn(page.Url); sso++)
        {
            ct.ThrowIfCancellationRequested();
            try
            {
                if (await ClickSsoTileOrYesAsync(page, accountEmail))
                    await Task.Delay(2500, ct);
            }
            catch { /* navigating */ }
            await Task.Delay(1000, ct);
        }
        if (OnSignIn(page.Url)) throw new SignInRequiredException(signInMsg);

        // Which InterLink user is this session? The form must be sent by the account it is meant for (Material / Product / bonding /
        // WHOLE SHOE = dtp_qcasst, "Danica Agbuya"; PULLTEST = dtp_lab). A wrong or stale session would otherwise send it as someone else.
        var who = "";
        try
        {
            who = (await page.EvaluateAsync<string>(@"() => { const e = [...document.querySelectorAll('[class*=""user"" i],[id*=""user"" i]')].map(x => (x.innerText || '').trim()).filter(t => t && !/log ?out|sign ?out/i.test(t) && t.length < 60); return e[0] || ''; }")) ?? "";
        }
        catch { /* the page is still changing: the check below just has no name to look at */ }
        if (who != "") say($"InterLink says this session is: {who}.");
        var wantAsst = accountEmail == AccountLabAsst;
        var isDanica = who.Contains("Danica Agbuya", StringComparison.OrdinalIgnoreCase);
        if (who != "" && wantAsst && !isDanica)
            throw new InvalidOperationException($"{tplLabel} must be sent with {AccountLabAsst}, but the saved InterLink session belongs to \"{who}\". In NexusInterlinkService click 'Login: {AccountLabAsst}'.");
        if (who != "" && accountEmail == AccountLab && !who.Contains("Faye Ann Elaysa", StringComparison.OrdinalIgnoreCase))
            throw new InvalidOperationException($"{tplLabel} must be sent with {AccountLab} (Faye Ann Elaysa), but the saved InterLink session belongs to \"{who}\". In NexusInterlinkService click 'Login: {AccountLab}'.");

        var navCount = 0;
        page.FrameNavigated += (_, f) => { if (f == page.MainFrame) Interlocked.Increment(ref navCount); };

        // 1. Open the requested eTRF form from Home.aspx
        say($"Selecting {tplLabel} under Submit a Request ({accountEmail})...");
        var navBefore = navCount;
        JsonElement? openRes = null;
        for (var attempt = 0; attempt < 10; attempt++)
        {
            ct.ThrowIfCancellationRequested();
            openRes = await EvalAsync(page, "openEtrfFromHome", etrf);
            var st = Str(openRes, "state");
            if (st == "signin") throw new SignInRequiredException(signInMsg);
            if (st == "error") throw new InvalidOperationException(Str(openRes, "message"));
            if (st == "on-form" || st == "navigating") break;
            await Task.Delay(1000, ct);
        }
        if (Str(openRes, "state") == "navigating")
        {
            await SettleAsync(page, () => navCount, navBefore, 25000, ct);
        }
    }

    /// <summary>Submits or saves a new eTRF request on InterLink 2.0 (Home.aspx -> Submit a Request -> NormalFormWithHeader.aspx).</summary>
    private async Task RunEtrfAsync(IPage page, IBrowserContext ctx, JobRequest req, string accountEmail, string signInMsg, Action<string> say, List<string> dialogs, CancellationToken ct)
    {
        var etrf = req.Job["etrf"] as JsonObject ?? new JsonObject();
        var tplLabel = (string?)etrf["template_label"] ?? req.ReportNo;
        var action = ((string?)etrf["action"] ?? "submit").ToLowerInvariant();

        // The eTRF is found afterwards in "VF - Test Request/Report Search (Inhouse & 3rd party Lab Upload)". What that grid already holds for this
        // product is noted first: the new Application is the one that was not there before (an older request of the same SKU is never taken).
        var gridKeys = new[] { (string?)etrf["style_color_no"], (string?)etrf["material_code"], (string?)etrf["style_no"], (string?)etrf["color_code"] }
            .Where(k => !string.IsNullOrWhiteSpace(k) && k!.Trim().Length >= 4).Select(k => k!.Trim()).Distinct().ToArray();
        HashSet<string>? knownBefore = null;
        if (action != "save")
        {
            try { knownBefore = (await ReadGridMatchesAsync(ctx, accountEmail, signInMsg, req.LabRefNo ?? "", gridKeys, ct)).Select(x => x.Split('|')[0]).ToHashSet(StringComparer.OrdinalIgnoreCase); }
            catch (SignInRequiredException) { throw; }
            catch (Exception ex) { _log.LogWarning("Could not note the Inhouse grid before the submit: {Msg}", ex.Message); }
        }

        await OpenEtrfFormAsync(page, etrf, tplLabel, accountEmail, signInMsg, say, ct);

        // page changes from here on (Save / Submit steps)
        var navCount = 0;
        page.FrameNavigated += (_, f) => { if (f == page.MainFrame) Interlocked.Increment(ref navCount); };
        var navBefore = navCount;

        // Wait until PH1_NormalFormSelector1_NF1_EGVNF is ready
        say($"Filling {tplLabel} eTRF form...");
        JsonElement? fillRes = null;
        for (var attempt = 0; attempt < 8; attempt++)
        {
            ct.ThrowIfCancellationRequested();
            fillRes = await EvalAsync(page, "fillNormalFormEtrf", etrf);
            var st = Str(fillRes, "state");
            if (st == "signin") throw new SignInRequiredException(signInMsg);
            if (st == "filled") break;
            if (st == "error" && attempt >= 4) throw new InvalidOperationException(Str(fillRes, "message"));
            await Task.Delay(1500, ct);
        }
        if (Str(fillRes, "state") != "filled")
            throw new InvalidOperationException(Str(fillRes, "message") is { Length: > 0 } m ? m : "InterLink did not open the eTRF form in time.");

        // The generated Application No. can show on the THANK YOU page, in the title / address of the printed PDF tab ("A006031883.PDF"), or in the PDF's own address.
        var appFromTabs = "";
        string ScanTabs()
        {
            foreach (var pg in ctx.Pages)
            {
                try
                {
                    var hay = (pg.Url ?? "") + " " + pg.TitleAsync().GetAwaiter().GetResult();
                    var m = Regex.Match(hay, @"\b(A\d{8,12})(?:-REV\d+)?\b", RegexOptions.IgnoreCase);
                    if (m.Success && !(pg.Url ?? "").Contains("Home.aspx", StringComparison.OrdinalIgnoreCase)) return m.Groups[1].Value.ToUpperInvariant();
                }
                catch { /* a tab that just closed */ }
            }
            return "";
        }
        void OnPdfResponse(object? _, IResponse resp)
        {
            try
            {
                if (resp.Headers.TryGetValue("content-type", out var h) && h.Contains("pdf", StringComparison.OrdinalIgnoreCase))
                {
                    var m = Regex.Match(resp.Url ?? "", @"\b(A\d{8,12})\b", RegexOptions.IgnoreCase);
                    if (m.Success) appFromTabs = m.Groups[1].Value.ToUpperInvariant();
                }
            }
            catch { }
        }
        ctx.Response += OnPdfResponse;

        // Vendor / Factory ID: InterLink refuses the form ("Factory ID is missing!") when the bonding template leaves it empty - pick it from the Vendor lookup
        if (action != "save")
        {
            var vf = await EvalAsync(page, "ensureVendorFactory", (string?)etrf["vendor_id"] ?? "");
            if (Str(vf, "state") == "ok") say($"Vendor/Factory ID: {Str(vf, "factoryId")} ({Str(vf, "via")}).");
        }

        // 2. Press Print & Submit (or Save)
        var commitLabel = action == "save" ? "Save" : "Print & Submit";
        say($"Pressing {commitLabel} on {tplLabel}...");
        dialogs.Clear();
        navBefore = navCount;
        var commitRes = await EvalAsync(page, "pressNormalFormCommit", action);
        if (Str(commitRes, "state") == "error") throw new InvalidOperationException(Str(commitRes, "message"));
        await SettleAsync(page, () => navCount, navBefore, 12000, ct);

        var dlgErr = dialogs.FirstOrDefault(d => Regex.IsMatch(d, @"is missing|is required|invalid|cannot\s+be\s+blank", RegexOptions.IgnoreCase));
        if (!string.IsNullOrWhiteSpace(dlgErr))
            throw new InvalidOperationException($"InterLink rejected the eTRF: {dlgErr.Replace("\r", " ").Replace("\n", " ").Trim()}");

        var genAppNo = "";
        var reachedThankYou = false;

        // 3. For Print & Submit, advance through the confirmation checkbox + "Next" view until reaching "THANK YOU! For your online job submission"
        if (action != "save")
        {
            // InterLink can take a while to answer Print & Submit (and again after Continue): wait for it - up to 150 s - instead of a fixed
            // number of looks. Leaving early would close the browser while InterLink is still working, and nothing would be created.
            var waitUntil = DateTime.UtcNow.AddSeconds(150);
            var step = 0;
            while (DateTime.UtcNow < waitUntil)
            {
                step++;
                ct.ThrowIfCancellationRequested();
                navBefore = navCount;
                var adv = await EvalAsync(page, "advanceNormalFormSubmitStep");
                var advState = Str(adv, "state");

                if (advState == "error")
                    throw new InvalidOperationException($"InterLink rejected the eTRF: {Str(adv, "message")}");

                var stepApp = Str(adv, "appNo");
                if (!string.IsNullOrWhiteSpace(stepApp))
                    genAppNo = stepApp;

                if (advState == "thank-you")
                {
                    reachedThankYou = true;
                    if (string.IsNullOrWhiteSpace(genAppNo)) genAppNo = !string.IsNullOrWhiteSpace(appFromTabs) ? appFromTabs : ScanTabs();
                    say($"Reached InterLink THANK YOU! submission screen — Application No: {genAppNo}");
                    await ShotAsync(page, req, "etrf-thank-you");
                    break;
                }

                if (advState == "advanced")
                {
                    var clickedBtn = Str(adv, "clicked");
                    say($"Checked confirmation checkbox & clicked '{clickedBtn}' on InterLink...");
                    await SettleAsync(page, () => navCount, navBefore, 15000, ct);
                    continue;
                }

                if (step % 12 == 1 && adv is { ValueKind: JsonValueKind.Object } waitObj && waitObj.TryGetProperty("buttons", out var btns))
                    say($"Waiting for InterLink to answer ({step} looks){(genAppNo != "" ? $", draft {genAppNo}" : "")}; on screen: {string.Join(" | ", btns.EnumerateArray().Select(b => b.GetString() ?? ""))}");
                await Task.Delay(1200, ct);
            }
        }

        // what the screen shows if the submit did not finish: read BEFORE anything else moves this page away from it
        var screenNow = "";
        if (action != "save" && !reachedThankYou)
        {
            try
            {
                screenNow = await page.EvaluateAsync<string>(@"() => {
                    const vis = (e) => e.offsetParent !== null;
                    const t = (document.body.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 350);
                    const btn = [...document.querySelectorAll('input[id*=""ButtonBar1""]')].filter(vis).map((b) => (b.value || '').trim()).filter(Boolean).join(' | ');
                    let stored = []; try { stored = JSON.parse(sessionStorage.getItem('__nxRefusals') || '[]'); } catch (e) { }
                    const ref = [...stored, ...(window.__nxRefusals || [])].filter((x, i, a) => a.indexOf(x) === i).slice(-4).join(' ; ');
                    // boxes InterLink itself marks as wrong (red, required and empty or invalid): the caption of each
                    const bad = [];
                    try {
                      ASPxClientControl.GetControlCollection().ForEachControl((c) => {
                        try {
                          if (typeof c.GetIsValid === 'function' && c.GetIsValid() === false) {
                            const el = c.GetMainElement ? c.GetMainElement() : null;
                            const cell = el && el.closest ? el.closest('tr,td,div.dxgvEditFormCaption,div') : null;
                            const cap = ((cell && cell.textContent) || '').replace(/\s+/g, ' ').replace(/\*/g, '').trim().slice(0, 30);
                            bad.push(cap || String(c.name || '').replace(/^.*(TpEdr_|EGVNFTP)/, '').slice(0, 30));
                          }
                        } catch (e) { /* not an editor */ }
                      });
                    } catch (e) { /* no client API */ }
                    const empty = [...document.querySelectorAll('input[type=text],textarea')].filter((i) => vis(i) && !i.value && i.closest('div,td') && /dxeInvalid|requiredColumn/.test(i.closest('div,td').className + ' ' + i.className) && /\*/.test((i.closest('tr,div') || {}).innerText || '')).length;
                    // the stored message holds 500 characters: what matters most comes first
                    return (ref ? 'InterLink said: ' + ref.slice(0, 140) + ' || ' : '') + (bad.length ? 'Invalid: ' + [...new Set(bad)].slice(0, 6).join('; ') + ' || ' : '') + 'Bar: ' + btn.slice(0, 60) + ' || Screen: ' + t.slice(60, 140) + (empty ? ' | empty-required:' + empty : '');
                }") ?? "";
                // InterLink's own message boxes (alert / confirm) that were shown while submitting
                string said;
                lock (dialogs) said = dialogs.Count > 0 ? string.Join(" | ", dialogs).Replace("\r", " ").Replace("\n", " ") : "";
                if (said.Length > 0) screenNow = "Message box: " + (said.Length > 150 ? said[..150] : said) + " || " + screenNow;
            }
            catch { /* the page is gone */ }
        }

        // the printed PDF tab may open a moment after the THANK YOU page
        for (var w = 0; w < 6 && string.IsNullOrWhiteSpace(genAppNo) && action != "save"; w++)
        {
            genAppNo = !string.IsNullOrWhiteSpace(appFromTabs) ? appFromTabs : ScanTabs();
            if (string.IsNullOrWhiteSpace(genAppNo)) await Task.Delay(1000, ct);
        }
        ctx.Response -= OnPdfResponse;

        await ShotAsync(page, req, "etrf-after-submit");
        dlgErr = dialogs.FirstOrDefault(d => Regex.IsMatch(d, @"is missing|is required|invalid|cannot\s+be\s+blank", RegexOptions.IgnoreCase));
        if (!string.IsNullOrWhiteSpace(dlgErr))
            throw new InvalidOperationException($"InterLink rejected the eTRF: {dlgErr.Replace("\r", " ").Replace("\n", " ").Trim()}");

        var check = await EvalAsync(page, "checkNormalFormSubmitted");
        if (check is { ValueKind: JsonValueKind.Object } c && c.TryGetProperty("ok", out var ok) && ok.ValueKind == JsonValueKind.False)
            throw new InvalidOperationException($"InterLink rejected the eTRF: {Str(check, "message")}");

        if (string.IsNullOrWhiteSpace(genAppNo))
            genAppNo = Str(check, "appNo");
        if (string.IsNullOrWhiteSpace(genAppNo))
        {
            foreach (var d in dialogs)
            {
                var dm = Regex.Match(d, @"\b(A\d{8,12})\b");
                if (dm.Success) { genAppNo = dm.Groups[1].Value; break; }
            }
        }
        var gridConfirmed = false;
        // the grid is also looked at when only a Draft number was seen: a request that is listed there as a new row WAS submitted
        if (string.IsNullOrWhiteSpace(genAppNo) || (action != "save" && !reachedThankYou))
        {
            try
            {
                say("Reading generated Application No. from InterLink grid...");
                await ReachGridAsync(page, accountEmail, signInMsg, say, ct);
                var labRefText = (req.LabRefNo ?? "").Trim();
                var today = DateTime.Now.Date;
                for (var tryNo = 0; tryNo < 4 && string.IsNullOrWhiteSpace(genAppNo); tryNo++)
                {
                    if (tryNo > 0) { await Task.Delay(5000, ct); await page.ReloadAsync(new PageReloadOptions { WaitUntil = WaitUntilState.DOMContentLoaded }); await Task.Delay(1500, ct); }
                    var found = await page.EvaluateAsync<string[]>(GridMatchesJs, new object[] { labRefText, gridKeys }) ?? Array.Empty<string>();
                    foreach (var f in found)   // newest first
                    {
                        var parts = f.Split('|');
                        var isNew = knownBefore != null ? !knownBefore.Contains(parts[0])
                            : parts.Length > 1 && DateTime.TryParse(parts[1], System.Globalization.CultureInfo.InvariantCulture, System.Globalization.DateTimeStyles.None, out var d) && d.Date == today;
                        if (isNew) { genAppNo = parts[0].ToUpperInvariant(); gridConfirmed = true; break; }
                    }
                }
                if (gridConfirmed) say($"Application No. {genAppNo} found in the Inhouse & 3rd party Lab Upload grid (the new request for this product).");
            }
            catch (Exception ex)
            {
                _log.LogWarning("Could not read top Application No. from search grid: {Msg}", ex.Message);
            }
        }

        // A submit that never reached THANK YOU and is not listed in InterLink is a FAILED job, never a "done" one without a number
        if (action != "save" && !reachedThankYou && !gridConfirmed)
        {
            var draftPart = !string.IsNullOrWhiteSpace(genAppNo)
                ? $"{genAppNo} exists only as a Draft: open it, Print & Submit, Continue. "
                : "Nothing was created. ";
            throw new InvalidOperationException($"Submit not finished. {draftPart}{screenNow}");
        }

        if (!string.IsNullOrWhiteSpace(genAppNo))
        {
            req.ApplicationNo = genAppNo;
            say(reachedThankYou
                ? $"THANK YOU! For your online job submission — Application No: {genAppNo} ({tplLabel})"
                : $"eTRF ({tplLabel}) {(action == "save" ? "saved as draft" : "submitted")} in InterLink — Application No: {genAppNo}.");
        }
        else
        {
            // better an honest message than the lab reference passed off as an Application No.
            say($"eTRF ({tplLabel}) {commitLabel} was pressed in InterLink, but the generated Application No. could NOT be read. Look for the newest request with Remarks 'NEXUS-L LabRefNo: {req.LabRefNo}' in InterLink.");
        }
    }

    /// <summary>first 15 rows (newest first) of the Inhouse grid that hold the lab reference or one of the product codes: "A006032625|12 Nov 2026"</summary>
    private const string GridMatchesJs = @"([lab, keys]) => [...document.querySelectorAll('tr.dxgvDataRow')].slice(0, 15).map((tr) => {
        const raw = tr.innerText || '';
        const t = raw.toLowerCase();
        const hit = (lab && t.includes(lab.toLowerCase())) || keys.some((k) => t.includes(k.toLowerCase()));
        const m = raw.match(/\b(A\d{8,12})\b/);
        const d = raw.match(/\b(\d{1,2} [A-Z][a-z]{2} \d{4})\b/);
        return hit && m ? m[1] + '|' + (d ? d[1] : '') : '';
    }).filter(Boolean)";

    /// <summary>Opens the Inhouse grid in its own tab and returns what it holds for this product (see GridMatchesJs)</summary>
    private async Task<List<string>> ReadGridMatchesAsync(IBrowserContext ctx, string accountEmail, string signInMsg, string lab, string[] keys, CancellationToken ct)
    {
        var pg = await ctx.NewPageAsync();
        try
        {
            await ReachGridAsync(pg, accountEmail, signInMsg, _ => { }, ct);
            await Task.Delay(1500, ct);
            return (await pg.EvaluateAsync<string[]>(GridMatchesJs, new object[] { lab, keys }) ?? Array.Empty<string>()).ToList();
        }
        finally { try { await pg.CloseAsync(); } catch { } }
    }

    /// <summary>InterLink's home page, then its search grid (through the portal's own link)</summary>
    private async Task ReachGridAsync(IPage page, string accountEmail, string signInMsg, Action<string> say, CancellationToken ct)
    {
        say("Opening InterLink...");
        await page.GotoAsync(PortalHome, new PageGotoOptions { WaitUntil = WaitUntilState.DOMContentLoaded, Timeout = 60000 });
        var navigatedAt = DateTime.MinValue;
        for (var i = 0; i < 120; i++)
        {
            ct.ThrowIfCancellationRequested();
            if (OnSignIn(page.Url))
            {
                if (i < 24)
                {
                    try
                    {
                        if (await ClickSsoTileOrYesAsync(page, accountEmail))
                        {
                            await Task.Delay(2500, ct);
                            continue;
                        }
                    }
                    catch { /* navigating */ }
                }
                throw new SignInRequiredException(signInMsg);
            }
            var probe = await EvalAsync(page, "gridProbe");
            if (probe is { ValueKind: JsonValueKind.Object } p)
            {
                if (p.TryGetProperty("grid", out var g) && g.ValueKind == JsonValueKind.True) return;
                if (p.TryGetProperty("home", out var h) && h.ValueKind == JsonValueKind.True && DateTime.UtcNow - navigatedAt > TimeSpan.FromSeconds(10))
                {
                    say("Going to InterLink's search page...");
                    await EvalAsync(page, "goToGridFromHome");
                    navigatedAt = DateTime.UtcNow;
                }
            }
            await Task.Delay(500, ct);
        }
        throw new InvalidOperationException("InterLink did not open its search grid in time.");
    }

    /// <summary>Home -> Search Requests / Reports -> VF - Test Request/Report Search (Applicant)</summary>
    private async Task ReachApplicantGridAsync(IPage page, string accountEmail, string signInMsg, Action<string> say, CancellationToken ct)
    {
        say("Opening InterLink Applicant Test Request grid...");
        await page.GotoAsync("https://interlink2.intertek.com/InterLink2/aspx/DlkPage/Portal2/Home.aspx", new PageGotoOptions { WaitUntil = WaitUntilState.DOMContentLoaded, Timeout = 60000 });
        var navigatedAt = DateTime.MinValue;
        for (var i = 0; i < 120; i++)
        {
            ct.ThrowIfCancellationRequested();
            if (OnSignIn(page.Url))
            {
                if (i < 24)
                {
                    try
                    {
                        if (await ClickSsoTileOrYesAsync(page, accountEmail)) { await Task.Delay(2500, ct); continue; }
                    }
                    catch { /* navigating */ }
                }
                throw new SignInRequiredException(signInMsg);
            }
            var probe = await EvalAsync(page, "gridProbe");
            if (probe is { ValueKind: JsonValueKind.Object } p)
            {
                if (p.TryGetProperty("grid", out var g) && g.ValueKind == JsonValueKind.True) return;
                if (p.TryGetProperty("home", out var h) && h.ValueKind == JsonValueKind.True && DateTime.UtcNow - navigatedAt > TimeSpan.FromSeconds(10))
                {
                    say("Navigating to VF - Test Request/Report Search (Applicant)...");
                    var nav = await EvalAsync(page, "goToApplicantGridFromHome");
                    if (nav is { ValueKind: JsonValueKind.Object } no && no.TryGetProperty("ok", out var okv) && okv.ValueKind == JsonValueKind.False)
                        throw new InvalidOperationException("Link 'VF - Test Request/Report Search (Applicant)' was not found on InterLink Home.");
                    navigatedAt = DateTime.UtcNow;
                }
            }
            await Task.Delay(500, ct);
        }
        throw new InvalidOperationException("InterLink Applicant Test Request grid did not open in time.");
    }

    /// <summary>Scrapes rows for the 4 VF Test Packages (KC Test Package, GB - Full Package, CPSIA Product Testing Package, RSL).</summary>
    /// <summary>InterLink Reports page: returns the HTML of consecutive Inhouse &amp; 3rd party grid pages (15 rows each) so NEXUS-L parses them exactly as the extension used to deliver them - no tab in the user's browser.</summary>
    private async Task RunGridHtmlAsync(IPage grid, JobRequest req, string accountEmail, string signInMsg, Action<string> say, CancellationToken ct)
    {
        var firstPage = int.TryParse(req.Job["first_page"]?.ToString(), out var fp) && fp > 0 ? fp : 1;
        var count = int.TryParse(req.Job["count"]?.ToString(), out var cn) && cn > 0 ? Math.Min(cn, 10) : 1;
        await ReachGridAsync(grid, accountEmail, signInMsg, say, ct);
        var pages = new JsonArray();
        var firstKey = "";
        var cur = await EvalAsync(grid, "inhouseGridHtml");
        if (Str(cur, "state") == "ok") firstKey = Str(cur, "firstKey");
        for (var i = 0; i < count; i++)
        {
            ct.ThrowIfCancellationRequested();
            var pageNo = firstPage + i;
            say($"Reading InterLink page {pageNo}...");
            if (pageNo > 1 || i > 0)
            {
                var go = await EvalAsync(grid, "inhouseGridGoToPage", pageNo - 1, firstKey);
                if (Str(go, "state") == "error") throw new InvalidOperationException(Str(go, "message"));
            }
            var res = await EvalAsync(grid, "inhouseGridHtml");
            if (Str(res, "state") != "ok") throw new InvalidOperationException(Str(res, "message") != "" ? Str(res, "message") : "The InterLink grid could not be read.");
            firstKey = Str(res, "firstKey");
            pages.Add(Str(res, "html"));
        }
        req.ResultJson = new JsonObject { ["pages"] = pages, ["firstPage"] = firstPage }.ToJsonString();
        say($"Read {pages.Count} InterLink page(s).");
    }

    /// <summary>Prints the eTRF of an Application that already exists (opens it from the Applicant grid, presses Print) and hands the PDF back.</summary>
    private async Task RunVfPdfAsync(IPage grid, IBrowserContext ctx, JobRequest req, string accountEmail, string signInMsg, Action<string> say, CancellationToken ct)
    {
        var appNo = ((string?)req.Job["application_no"] ?? req.ApplicationNo ?? "").Trim();
        if (!Regex.IsMatch(appNo, @"^A\d{8,12}", RegexOptions.IgnoreCase)) throw new InvalidOperationException($"'{appNo}' is not an InterLink Application No.");
        say($"Printing the eTRF PDF of {appNo} from InterLink...");
        byte[]? pdf = null;
        for (var t = 1; t <= 2 && pdf == null; t++)
        {
            pdf = await PrintApplicationPdfAsync(ctx, grid, appNo, accountEmail, signInMsg, say, ct);
            if (pdf != null && !IsRealPdf(pdf)) pdf = null;
        }
        if (pdf == null) throw new InvalidOperationException($"InterLink did not give the PDF of {appNo}.");
        req.PdfName = $"{appNo}.pdf";
        req.PdfBase64 = Convert.ToBase64String(pdf);
        say($"Captured InterLink PDF ({pdf.Length / 1024} KB) for {appNo}.");
    }

    private async Task RunVfGridAsync(IPage grid, JobRequest req, string accountEmail, string signInMsg, Action<string> say, CancellationToken ct)
    {
        var pkgs = new[] { "KC Test Package", "GB - Full Package", "CPSIA Product Testing Package", "RSL" };
        if (req.Job["packages"] is JsonArray pa && pa.Count > 0)
            pkgs = pa.Select(x => (string?)x ?? "").Where(x => x != "").ToArray();
        var maxRows = 1500;
        if (int.TryParse(req.Job["max_rows"]?.ToString(), out var mr) && mr > 0) maxRows = Math.Min(mr, 20000);
        var wantedJson = JsonSerializer.Serialize(pkgs);

        // The 4 VF packages (KC, GB - Full, CPSIA, RSL) are requested under "VF - Test Request/Report Search (Applicant)" - the Inhouse & 3rd party grid only holds the lab's own VANS packages
        await ReachApplicantGridAsync(grid, accountEmail, signInMsg, say, ct);
        say("Reading the Applicant search grid...");
        // Page 1 only: a chosen Application No. / Test Package is filtered by InterLink itself, then only the first page is read (never every page)
        var pageOnly = req.Job["page_only"]?.ToString()?.ToLowerInvariant() == "true";
        var qFilter = req.Job["q"]?.ToString() ?? "";
        var pkgFilter = req.Job["package"]?.ToString() ?? "";
        if (pkgFilter != "")
        {
            say($"Filtering InterLink by Test Package {pkgFilter}...");
            var ok1 = await EvalAsync(grid, "filterApplicantGridColumn", "Test Package(s)", pkgFilter);
            if (!(ok1 is { ValueKind: JsonValueKind.True })) await EvalAsync(grid, "filterApplicantGridColumn", "Test Package", pkgFilter);
        }
        if (qFilter != "")
        {
            say($"Filtering InterLink by Application No. {qFilter}...");
            await EvalAsync(grid, "filterApplicantGridColumn", "Application No.", qFilter);
        }
        if (pageOnly) maxRows = 1;   // one page
        if (pageOnly) wantedJson = "[]";   // page mode shows the page exactly as InterLink returns it (InterLink did the filtering); dropping rows here hid whole pages
        var ps = await EvalAsync(grid, "inhouseGridSetPageSize");
        if (Str(ps, "state") == "error") throw new InvalidOperationException(Str(ps, "message"));
        say($"Page size: {(Str(ps, "via") != "" ? Str(ps, "via") : "left at the default")}.");

        var allRows = new JsonArray();
        var seen = new HashSet<string>();
        var scanned = 0;
        var firstKey = "";
        var startPage = pageOnly && int.TryParse(req.Job["page"]?.ToString(), out var sp2) && sp2 > 1 ? sp2 - 1 : 0;   // 0-based page to read
        var curPage = 0; var curPages = 0; var curItems = 0;
        for (var pageIndex = startPage; pageIndex < 2000 && (pageOnly ? pageIndex == startPage : scanned < maxRows); pageIndex++)
        {
            ct.ThrowIfCancellationRequested();
            if (pageIndex > 0)
            {
                var go = await EvalAsync(grid, "inhouseGridGoToPage", pageIndex, firstKey);
                if (Str(go, "state") != "ok") break;   // last page reached or the grid did not change
            }
            var res = await EvalAsync(grid, "inhouseGridReadPage", wantedJson);
            if (res is not { ValueKind: JsonValueKind.Object } ro || Str(res, "state") != "ok") throw new InvalidOperationException(Str(res, "message") != "" ? Str(res, "message") : "The InterLink search grid could not be read.");
            firstKey = Str(res, "firstKey");
            var hList = ro.GetProperty("headers").EnumerateArray().Select(x => x.GetString() ?? "").ToList();
            scanned += ro.TryGetProperty("scanned", out var sc) && sc.ValueKind == JsonValueKind.Number ? sc.GetInt32() : 0;
            foreach (var rEl in ro.GetProperty("rows").EnumerateArray())
            {
                var obj = new JsonObject();
                var cells = rEl.EnumerateArray().Select(x => x.GetString() ?? "").ToList();
                for (var c = 0; c < Math.Min(hList.Count, cells.Count); c++)
                    if (!string.IsNullOrWhiteSpace(hList[c])) obj[hList[c]] = cells[c];
                var app = (string?)obj["Application No."] ?? "";
                if (app != "" && !seen.Add(app)) continue;
                allRows.Add(obj);
            }
            var pages = ro.TryGetProperty("pages", out var pg) && pg.ValueKind == JsonValueKind.Number ? pg.GetInt32() : 0;
            curPage = pageIndex + 1; curPages = pages; curItems = ro.TryGetProperty("items", out var it2) && it2.ValueKind == JsonValueKind.Number ? it2.GetInt32() : 0;
            say($"Read page {pageIndex + 1}{(pages > 0 ? " of " + pages : "")}: {allRows.Count} VF row(s) so far ({scanned} scanned).");
            if (pages > 0 && pageIndex + 1 >= pages) break;
        }
        // rows with no cell text at all mean the grid was changed under us (another job in the same login) or was still loading: not a result
        if (allRows.Count > 0 && !allRows.OfType<JsonObject>().Any(o => o.Count > 0))
            throw new InvalidOperationException("InterLink returned blank rows (the grid was not ready). Try again.");
        req.ResultJson = new JsonObject { ["rows"] = allRows, ["scanned"] = scanned, ["page"] = curPage, ["pages"] = curPages, ["items"] = curItems }.ToJsonString();
        say($"Synced {allRows.Count} VF Test Request row(s) from InterLink ({scanned} rows scanned).");
    }

    /// <summary>VF - Test Request Copy as New -> Fill Form -> Print &amp; Submit -> Print PDF -> return Application No + PDF bytes.</summary>
    private async Task RunVfCopyAsync(IPage grid, IBrowserContext ctx, JobRequest req, string accountEmail, string signInMsg, Action<string> say, List<string> dialogs, CancellationToken ct)
    {
        var etrf = req.Job["etrf"] as JsonObject ?? new JsonObject();
        var srcAppNo = (string?)etrf["source_application_no"] ?? req.Job["source_application_no"]?.ToString() ?? "";
        var fallbackPkg = (string?)etrf["test_package"] ?? (string?)etrf["summary"]?["test_packages"] ?? "CPSIA Product Testing Package";

        // Copy as New is done from "VF - Test Request/Report Search (Applicant)". (The Inhouse & 3rd party Lab Upload grid is only for sending
        // report data and documents.)
        await ReachApplicantGridAsync(grid, accountEmail, signInMsg, say, ct);

        say(srcAppNo != "" ? $"Opening source Application {srcAppNo}..." : $"Opening first row in {fallbackPkg}...");
        var popupTcs = new TaskCompletionSource<IPage>(TaskCreationOptions.RunContinuationsAsynchronously);
        void OnPopup(object? _, IPage p) => popupTcs.TrySetResult(p);
        ctx.Page += OnPopup;
        JsonElement? openRow = null;
        try
        {
            openRow = await EvalAsync(grid, "openApplicationPopup", srcAppNo, fallbackPkg);
            if (Str(openRow, "state") == "error")
                throw new InvalidOperationException(Str(openRow, "message"));
            if (await Task.WhenAny(popupTcs.Task, Task.Delay(TimeSpan.FromSeconds(40), ct)) != popupTcs.Task)
                throw new InvalidOperationException("InterLink did not open the Application popup window.");
        }
        finally { ctx.Page -= OnPopup; }

        var form = popupTcs.Task.Result;
        var navCount = 0;
        form.FrameNavigated += (_, f) => { if (f == form.MainFrame) Interlocked.Increment(ref navCount); };
        await form.WaitForLoadStateAsync(LoadState.DOMContentLoaded);
        await Task.Delay(1200, ct);

        // 1. Click "Copy as New"
        say("Clicking 'Copy as New'...");
        var navBefore = navCount;
        var copyClicked = false;
        for (var a = 0; a < 10 && !copyClicked; a++)
        {
            ct.ThrowIfCancellationRequested();
            var cr = await EvalAsync(form, "clickCopyAsNewButton");
            if (Str(cr, "state") == "clicked") { copyClicked = true; break; }
            await Task.Delay(600, ct);
        }
        if (!copyClicked) throw new InvalidOperationException("The 'Copy as New' button was not found inside the InterLink Application popup.");
        await SettleAsync(form, () => navCount, navBefore, 20000, ct);

        // 2. Fill updated product / cargo fields on the copied form
        say("Filling copied eTRF form details...");
        JsonElement? fillRes = null;
        for (var attempt = 0; attempt < 8; attempt++)
        {
            ct.ThrowIfCancellationRequested();
            fillRes = await EvalAsync(form, "fillNormalFormEtrf", etrf);
            var st = Str(fillRes, "state");
            if (st == "signin") throw new SignInRequiredException(signInMsg);
            if (st == "filled") break;
            if (st == "error" && attempt >= 4) throw new InvalidOperationException(Str(fillRes, "message"));
            await Task.Delay(1500, ct);
        }
        if (Str(fillRes, "state") != "filled")
            throw new InvalidOperationException(Str(fillRes, "message") is { Length: > 0 } fm ? fm : "Could not fill the Copy as New form in time.");

        // 3. Click Print & Submit -> advance confirmation -> THANK YOU!
        say("Submitting via Print & Submit...");
        // InterLink opens/downloads the printed eTRF PDF right after the certify box + Continue: catch it from any tab of this context
        var livePdf = new TaskCompletionSource<byte[]>(TaskCreationOptions.RunContinuationsAsynchronously);
        void OnCtxResponse(object? _, IResponse resp)
        {
            _ = Task.Run(async () =>
            {
                try
                {
                    if (resp.Status == 200 && resp.Headers.TryGetValue("content-type", out var h) && h.Contains("pdf", StringComparison.OrdinalIgnoreCase))
                    {
                        var b = await resp.BodyAsync();
                        _log.LogInformation("PDF-type response {Url}: {Len} bytes, real={Real}", resp.Url.Length > 120 ? resp.Url[..120] : resp.Url, b.Length, IsRealPdf(b));
                        if (IsRealPdf(b)) livePdf.TrySetResult(b);
                    }
                }
                catch { }
            });
        }
        void OnCtxPage(object? _, IPage pg) =>
            pg.Download += async (_, dl) =>
            {
                try { var pth = await dl.PathAsync(); if (!string.IsNullOrEmpty(pth) && File.Exists(pth)) { var fb = await File.ReadAllBytesAsync(pth); if (IsRealPdf(fb)) livePdf.TrySetResult(fb); } } catch { }
            };
        ctx.Response += OnCtxResponse;
        ctx.Page += OnCtxPage;
        form.Download += async (_, dl) =>
        {
            try { var pth = await dl.PathAsync(); if (!string.IsNullOrEmpty(pth) && File.Exists(pth)) { var fb = await File.ReadAllBytesAsync(pth); if (IsRealPdf(fb)) livePdf.TrySetResult(fb); } } catch { }
        };
        dialogs.Clear();
        navBefore = navCount;
        try { await EvalAsync(form, "ensureVendorFactory", (string?)etrf["vendor_id"] ?? ""); } catch { /* the form is still re-posting: the button loop below waits for it */ }
        // the form re-posts after the fill, so the button may not be there yet: look again for up to ~20 s
        JsonElement? commitRes = null;
        for (var cTry = 0; cTry < 10; cTry++)
        {
            ct.ThrowIfCancellationRequested();
            commitRes = await EvalAsync(form, "pressNormalFormCommit", "submit");
            if (Str(commitRes, "state") != "error" || !(commitRes?.TryGetProperty("retry", out var rt) == true && rt.ValueKind == JsonValueKind.True)) break;
            await Task.Delay(2000, ct);
        }
        if (Str(commitRes, "state") == "error") throw new InvalidOperationException(Str(commitRes, "message"));
        await SettleAsync(form, () => navCount, navBefore, 12000, ct);

        var dlgErr = dialogs.FirstOrDefault(d => Regex.IsMatch(d, @"is missing|is required|invalid|cannot\s+be\s+blank", RegexOptions.IgnoreCase));
        if (!string.IsNullOrWhiteSpace(dlgErr))
            throw new InvalidOperationException($"InterLink rejected the submission: {dlgErr}");

        var genAppNo = "";
        // wait for InterLink as long as it needs (up to 150 s): leaving early closes the form while the submit is still running
        var copyWaitUntil = DateTime.UtcNow.AddSeconds(150);
        while (DateTime.UtcNow < copyWaitUntil)
        {
            ct.ThrowIfCancellationRequested();
            navBefore = navCount;
            var adv = await EvalAsync(form, "advanceNormalFormSubmitStep");
            var advState = Str(adv, "state");
            if (advState == "error") throw new InvalidOperationException($"InterLink rejected submission: {Str(adv, "message")}");
            var stepApp = Str(adv, "appNo");
            if (!string.IsNullOrWhiteSpace(stepApp)) genAppNo = stepApp;
            if (advState == "thank-you")
            {
                say($"Submitted! Generated Application No: {genAppNo}");
                await ShotAsync(form, req, "vf-thank-you");
                break;
            }
            if (advState == "advanced")
            {
                await SettleAsync(form, () => navCount, navBefore, 15000, ct);
                continue;
            }
            await Task.Delay(1200, ct);
        }
        // Success only counts when InterLink actually shows THANK YOU. A draft form also contains an A00... number,
        // so never accept a number from the form page itself (that left the request as a Draft).
        var thanked = false;
        try { thanked = await form.EvaluateAsync<bool>("() => /THANK\\s*YOU/i.test((document.body && document.body.innerText) || '')"); } catch { }
        if (!thanked)
        {
            var left = "";
            try { left = await form.EvaluateAsync<string>("() => [...document.querySelectorAll('input[type=submit],input[type=button],button')].filter(b=>b.offsetParent!==null).map(b=>(b.value||b.textContent||'').trim()).filter(Boolean).slice(0,12).join(' | ')"); } catch { }
            throw new InvalidOperationException($"InterLink did not finish the submit - request {genAppNo} is still a Draft (no THANK YOU page). Buttons on screen: {left}. Open it in InterLink and press Print & Submit.");
        }
        if (string.IsNullOrWhiteSpace(genAppNo))
        {
            var chk = await EvalAsync(form, "checkNormalFormSubmitted");
            genAppNo = Str(chk, "appNo");
        }
        if (string.IsNullOrWhiteSpace(genAppNo))
            throw new InvalidOperationException("Submission completed in InterLink, but the generated Application No. could not be captured.");

        req.ApplicationNo = genAppNo;

        // the PDF InterLink printed right after Continue; if it did not open by itself, press Print on the THANK YOU page
        byte[]? directPdf = null;
        try
        {
            if (await Task.WhenAny(livePdf.Task, Task.Delay(6000, ct)) != livePdf.Task)
            {
                try { await EvalAsync(form, "clickPrintButton"); } catch { }
                await Task.WhenAny(livePdf.Task, Task.Delay(15000, ct));
            }
            if (livePdf.Task.IsCompletedSuccessfully && IsRealPdf(livePdf.Task.Result)) directPdf = livePdf.Task.Result;
        }
        catch { }
        finally { ctx.Response -= OnCtxResponse; ctx.Page -= OnCtxPage; }
        try { if (!form.IsClosed) await form.CloseAsync(); } catch { }
        if (directPdf != null)
        {
            req.PdfName = $"{genAppNo}.pdf";
            req.PdfBase64 = Convert.ToBase64String(directPdf);
            say($"Captured InterLink PDF ({directPdf.Length / 1024} KB) for {genAppNo}.");
            return;
        }

        // Report generated Application No. immediately before Print PDF capture so retries never orphan or duplicate it
        await _notifier.ProgressAsync(req, new JobStatus
        {
            JobUid = req.JobUid,
            ApplicationNo = genAppNo,
            State = "running",
            Message = $"Assigned Application No. {genAppNo} in InterLink; printing PDF...",
        });

        // 4. Re-open generated Application No. on Applicant Grid and click "Print" to capture the PDF (retry once on timeout)
        try
        {
            byte[]? pdfBytes = null;
            for (var pTry = 1; pTry <= 2 && pdfBytes == null; pTry++)
            {
                say(pTry == 1 ? $"Printing PDF for {genAppNo}..." : $"Retrying Print PDF for {genAppNo}...");
                pdfBytes = await PrintApplicationPdfAsync(ctx, grid, genAppNo, accountEmail, signInMsg, say, ct);
                if (pdfBytes != null && !IsRealPdf(pdfBytes)) pdfBytes = null;
            }
            if (pdfBytes != null)
            {
                req.PdfName = $"{genAppNo}.pdf";
                req.PdfBase64 = Convert.ToBase64String(pdfBytes);
                say($"Captured InterLink PDF ({pdfBytes.Length / 1024} KB) for {genAppNo}.");
            }
        }
        catch (Exception pex)
        {
            _log.LogWarning("Print PDF capture warning for {App}: {Msg}", genAppNo, pex.Message);
            say($"Submitted {genAppNo} (PDF auto-capture note: {pex.Message})");
        }
    }

    /// <summary>a real PDF: starts with %PDF- and is more than a stub (the printed eTRF is ~150 KB)</summary>
    private static bool IsRealPdf(byte[]? b) => b != null && b.Length > 5000 && b[0] == 0x25 && b[1] == 0x50 && b[2] == 0x44 && b[3] == 0x46;

    private async Task<byte[]?> PrintApplicationPdfAsync(IBrowserContext ctx, IPage grid, string appNo, string accountEmail, string signInMsg, Action<string> say, CancellationToken ct)
    {
        await ReachApplicantGridAsync(grid, accountEmail, signInMsg, say, ct);   // VF - Test Request/Report Search (Applicant): where Copy as New and the printed eTRF live
        var popupTcs = new TaskCompletionSource<IPage>(TaskCreationOptions.RunContinuationsAsynchronously);
        void OnPop(object? _, IPage p) => popupTcs.TrySetResult(p);
        ctx.Page += OnPop;
        try
        {
            var openRes = await EvalAsync(grid, "openApplicationPopup", appNo, "");
            if (Str(openRes, "state") == "error") { say($"Could not open {appNo}: {Str(openRes, "message")}"); return null; }
            if (await Task.WhenAny(popupTcs.Task, Task.Delay(TimeSpan.FromSeconds(30), ct)) != popupTcs.Task) { say($"InterLink did not open the form of {appNo} in 30 s."); return null; }
        }
        finally { ctx.Page -= OnPop; }

        var viewPopup = popupTcs.Task.Result;
        await viewPopup.WaitForLoadStateAsync(LoadState.DOMContentLoaded);
        await Task.Delay(1000, ct);

        var pdfTcs = new TaskCompletionSource<byte[]>(TaskCreationOptions.RunContinuationsAsynchronously);
        void OnCtxPdf(object? _, IResponse resp)
        {
            _ = Task.Run(async () =>
            {
                try
                {
                    if (resp.Status == 200 && resp.Headers.TryGetValue("content-type", out var h) && h.Contains("pdf", StringComparison.OrdinalIgnoreCase))
                    {
                        var b = await resp.BodyAsync();
                        if (IsRealPdf(b)) pdfTcs.TrySetResult(b);
                    }
                }
                catch { }
            });
        }
        ctx.Response += OnCtxPdf;
        void WatchPage(IPage pg)
        {
            pg.Download += async (_, dl) =>
            {
                try
                {
                    var path = await dl.PathAsync();
                    if (!string.IsNullOrEmpty(path) && File.Exists(path))
                        { var fb = await File.ReadAllBytesAsync(path, ct); if (IsRealPdf(fb)) pdfTcs.TrySetResult(fb); }
                }
                catch { }
            };
            pg.Response += async (_, resp) =>
            {
                try
                {
                    if (resp.Status == 200 && resp.Headers.TryGetValue("content-type", out var ctHdr) && ctHdr.Contains("pdf", StringComparison.OrdinalIgnoreCase))
                    {
                        var bytes = await resp.BodyAsync();
                        if (IsRealPdf(bytes)) pdfTcs.TrySetResult(bytes);
                    }
                }
                catch { }
            };
        }
        WatchPage(viewPopup);
        void OnPdfTab(object? _, IPage tab) => WatchPage(tab);
        ctx.Page += OnPdfTab;
        try
        {
            for (var i = 0; i < 8; i++)
            {
                var pr = await EvalAsync(viewPopup, "clickPrintButton");
                if (Str(pr, "state") == "clicked") break;
                await Task.Delay(500, ct);
            }
            if (await Task.WhenAny(pdfTcs.Task, Task.Delay(TimeSpan.FromSeconds(45), ct)) == pdfTcs.Task)
                return pdfTcs.Task.Result;
            return null;
        }
        finally
        {
            ctx.Response -= OnCtxPdf;
            ctx.Page -= OnPdfTab;
            try { if (!viewPopup.IsClosed) await viewPopup.CloseAsync(); } catch { }
        }
    }

    /// <summary>a picture of a page, kept in data/shots (the last 40 are kept)</summary>
    private async Task ShotAsync(IPage page, JobRequest req, string name)
    {
        try
        {
            var dir = Path.Combine(DataDir(_cfg), "shots");
            Directory.CreateDirectory(dir);
            var file = Path.Combine(dir, $"{DateTime.Now:yyyyMMdd-HHmmss}-{req.ApplicationNo}-{name}.png");
            await page.ScreenshotAsync(new PageScreenshotOptions { Path = file, FullPage = true });
            foreach (var old in new DirectoryInfo(dir).GetFiles("*.png").OrderByDescending(f => f.CreationTimeUtc).Skip(40)) old.Delete();
        }
        catch (Exception e)
        {
            _log.LogWarning("Could not take a picture ({Name}): {Msg}", name, e.Message);
        }
    }

    /// <summary>after a click that posts the page: wait for it to navigate and load; if nothing happens within maxMs carry on (the caller looks again)</summary>
    private static async Task SettleAsync(IPage page, Func<int> navCount, int before, int maxMs, CancellationToken ct)
    {
        var until = DateTime.UtcNow.AddMilliseconds(maxMs);
        while (navCount() == before && DateTime.UtcNow < until && !page.IsClosed) await Task.Delay(200, ct);
        if (navCount() != before && !page.IsClosed)
        {
            try { await page.WaitForLoadStateAsync(LoadState.DOMContentLoaded, new PageWaitForLoadStateOptions { Timeout = 30000 }); }
            catch (PlaywrightException) { /* closed or slow: the caller looks again */ }
            await Task.Delay(1200, ct);
        }
    }

    public async ValueTask DisposeAsync()
    {
        if (_browser != null) await _browser.CloseAsync();
        _pw?.Dispose();
    }
}
