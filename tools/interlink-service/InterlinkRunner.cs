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

    public static string SessionFile(IConfiguration cfg) => Path.Combine(DataDir(cfg), "interlink-session.json");

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
        var sessionFile = SessionFile(_cfg);
        if (!File.Exists(sessionFile))
            throw new SignInRequiredException("The InterLink service has no sign-in yet. On the service computer run: NexusInterlinkService --signin");

        var minutes = _cfg.GetValue("Service:JobTimeoutMinutes", 20);
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
        IPage? current = null;                   // the page a picture is taken of when something goes wrong
        try
        {
            // 1. home, then the search grid
            say("Opening InterLink...");
            await ReachGridAsync(grid, say, ct);
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
            await ReachGridAsync(proof, say, ct);
            JsonElement? rowInfo = null;
            for (var attempt = 0; attempt < 3; attempt++)
            {
                rowInfo = await EvalAsync(proof, "readGridRow", req.ApplicationNo);
                if (Str(rowInfo, "state") == "ok" && !string.IsNullOrWhiteSpace(Str(rowInfo, "reportNo"))) break;
                await Task.Delay(3000, ct);
                await proof.ReloadAsync(new PageReloadOptions { WaitUntil = WaitUntilState.DOMContentLoaded });
                await ReachGridAsync(proof, say, ct);
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
                try { await ctx.StorageStateAsync(new BrowserContextStorageStateOptions { Path = sessionFile }); }   // keeps the session fresh for the next job
                catch (Exception e) { _log.LogWarning("Could not refresh the saved session: {Msg}", e.Message); }
            }
        }
    }

    /// <summary>InterLink's home page, then its search grid (through the portal's own link)</summary>
    private async Task ReachGridAsync(IPage page, Action<string> say, CancellationToken ct)
    {
        say("Opening InterLink...");
        await page.GotoAsync(PortalHome, new PageGotoOptions { WaitUntil = WaitUntilState.DOMContentLoaded, Timeout = 60000 });
        var navigatedAt = DateTime.MinValue;
        for (var i = 0; i < 120; i++)
        {
            ct.ThrowIfCancellationRequested();
            if (OnSignIn(page.Url)) throw new SignInRequiredException(SignInAgain);
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
