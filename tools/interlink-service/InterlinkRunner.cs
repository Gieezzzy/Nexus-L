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

        var minutes = _cfg.GetValue("Service:JobTimeoutMinutes", 8);
        using var cts = CancellationTokenSource.CreateLinkedTokenSource(stop);
        cts.CancelAfter(TimeSpan.FromMinutes(minutes));
        var ct = cts.Token;

        var browser = await BrowserAsync();
        await using var ctx = await browser.NewContextAsync(new BrowserNewContextOptions { StorageStatePath = sessionFile, ViewportSize = new ViewportSize { Width = 1500, Height = 950 } });
        await ctx.AddInitScriptAsync(_core);   // window.__nx exists in every InterLink document, the Upload Form popup included
        var grid = await ctx.NewPageAsync();
        var signedIn = false;
        try
        {
            // 1. home, then the search grid
            say("Opening InterLink...");
            await grid.GotoAsync(PortalHome, new PageGotoOptions { WaitUntil = WaitUntilState.DOMContentLoaded, Timeout = 60000 });
            var navigatedAt = DateTime.MinValue;
            var onGrid = false;
            for (var i = 0; i < 120 && !onGrid; i++)
            {
                ct.ThrowIfCancellationRequested();
                if (OnSignIn(grid.Url)) throw new SignInRequiredException(SignInAgain);
                var probe = await EvalAsync(grid, "gridProbe");
                if (probe is { ValueKind: JsonValueKind.Object } p)
                {
                    if (p.TryGetProperty("grid", out var g) && g.ValueKind == JsonValueKind.True) onGrid = true;
                    else if (p.TryGetProperty("home", out var h) && h.ValueKind == JsonValueKind.True && DateTime.UtcNow - navigatedAt > TimeSpan.FromSeconds(10))
                    {
                        say("Going to InterLink's search page...");
                        await EvalAsync(grid, "goToGridFromHome");
                        navigatedAt = DateTime.UtcNow;
                    }
                }
                if (!onGrid) await Task.Delay(500, ct);
            }
            if (!onGrid) throw new InvalidOperationException("InterLink did not open its search grid in time.");
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

            // 6. InterLink's answer
            if (!form.IsClosed)
            {
                var check = await EvalAsync(form, "checkSubmitted");
                if (check is { ValueKind: JsonValueKind.Object } c && c.TryGetProperty("ok", out var ok) && ok.ValueKind == JsonValueKind.False)
                    throw new InvalidOperationException($"InterLink did not accept {req.ApplicationNo}: {Str(check, "message")}");
            }
            var notes = "";
            if (filled is { ValueKind: JsonValueKind.Object } f2 && f2.TryGetProperty("notes", out var nn) && nn.ValueKind == JsonValueKind.Array && nn.GetArrayLength() > 0)
                notes = " Not added: " + string.Join("; ", nn.EnumerateArray().Select(x => x.GetString())) + ".";
            say($"{req.ApplicationNo} was saved to InterLink.{notes}");
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
