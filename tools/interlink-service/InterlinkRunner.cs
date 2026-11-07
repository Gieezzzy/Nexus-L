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
        if (req.Mode == "etrf" || req.Mode == "lov")
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

    private async Task<IBrowser> BrowserAsync()
    {
        _pw ??= await Playwright.CreateAsync();
        if (_browser is { IsConnected: true }) return _browser;
        _browser = await BrowserLauncher.LaunchAsync(_pw, _cfg.GetValue("Service:Headless", true), _cfg);
        return _browser;
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

    public async Task RunAsync(JobRequest req, Action<string> say, CancellationToken stop)
    {
        var accountEmail = ResolveAccountForJob(req);
        var sessionFile = ResolveSessionFile(_cfg, accountEmail);
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
        await using var ctx = await browser.NewContextAsync(new BrowserNewContextOptions { StorageStatePath = sessionFile, ViewportSize = new ViewportSize { Width = 1500, Height = 950 } });
        await ctx.AddInitScriptAsync(_core);   // window.__nx exists in every InterLink document, the Upload Form popup included
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
                await RunEtrfAsync(grid, req, accountEmail, signInMsg, say, dialogs, ct);
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
                try { await ctx.StorageStateAsync(new BrowserContextStorageStateOptions { Path = targetSaveFile }); }   // keeps the session fresh for the next job
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
    private async Task RunEtrfAsync(IPage page, JobRequest req, string accountEmail, string signInMsg, Action<string> say, List<string> dialogs, CancellationToken ct)
    {
        var etrf = req.Job["etrf"] as JsonObject ?? new JsonObject();
        var tplLabel = (string?)etrf["template_label"] ?? req.ReportNo;
        var action = ((string?)etrf["action"] ?? "submit").ToLowerInvariant();

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
            for (var step = 0; step < 18; step++)
            {
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

                await Task.Delay(1200, ct);
                if (!string.IsNullOrWhiteSpace(genAppNo) && step >= 4)
                    break;
            }
        }

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
        if (string.IsNullOrWhiteSpace(genAppNo))
        {
            try
            {
                say("Reading generated Application No. from InterLink grid...");
                await ReachGridAsync(page, accountEmail, signInMsg, say, ct);
                genAppNo = await page.EvaluateAsync<string>(@"() => {
                    const tr = document.querySelector('tr[id*=""EGV_DXDataRow0""], tr.dxgvDataRow');
                    if (!tr) return '';
                    const m = (tr.innerText || '').match(/\b(A\d{8,12})\b/);
                    return m ? m[1] : '';
                }") ?? "";
            }
            catch (Exception ex)
            {
                _log.LogWarning("Could not read top Application No. from search grid: {Msg}", ex.Message);
            }
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
            say($"eTRF ({tplLabel}) {commitLabel} completed in InterLink.");
        }
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
