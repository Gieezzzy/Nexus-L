using Microsoft.Playwright;

namespace NexusInterlinkService;

/// <summary>
/// NexusInterlinkService --signin [account] : opens a visible browser ONCE so the specified InterLink account
/// (dtp_qcasst@dtshoes.com or dtp_lab@dtshoes.com) can sign in to InterLink (Microsoft sign-in, MFA included).
/// The session is saved to data/interlink-session-labasst.json or data/interlink-session-lab.json; the service then works headless with it.
/// </summary>
public static class SignIn
{
    /// <summary>Loads a session exported from a browser that is already signed in (the NEXUS-L InterLink Sender extension, "Export InterLink session")
    /// and saves it as the session of the chosen account. Accepts the extension's file or a Playwright storage-state file.</summary>
    public static string ImportSession(IConfiguration cfg, string account, string path)
    {
        var root = System.Text.Json.Nodes.JsonNode.Parse(File.ReadAllText(path));
        var cookies = root is System.Text.Json.Nodes.JsonArray a ? a : root?["cookies"] as System.Text.Json.Nodes.JsonArray;
        if (cookies == null || cookies.Count == 0) throw new InvalidOperationException("That file has no cookies. Use the file saved by the extension's 'Export InterLink session' button.");
        if (!cookies.Any(c => ((string?)c?["domain"] ?? "").Contains("intertek", StringComparison.OrdinalIgnoreCase) || ((string?)c?["domain"] ?? "").Contains("microsoftonline", StringComparison.OrdinalIgnoreCase)))
            throw new InvalidOperationException("The file has no InterLink / Microsoft sign-in cookies. Export it while signed in to InterLink.");
        var state = new System.Text.Json.Nodes.JsonObject { ["cookies"] = System.Text.Json.Nodes.JsonNode.Parse(cookies.ToJsonString()), ["origins"] = new System.Text.Json.Nodes.JsonArray() };
        var norm = InterlinkRunner.NormalizeAccount(account);
        var file = InterlinkRunner.SessionFile(cfg, norm);
        File.WriteAllText(file, state.ToJsonString());
        try { File.Copy(file, InterlinkRunner.SessionFile(cfg, null), true); } catch { }
        return file;
    }

    public static async Task<int> RunAsync(IConfiguration cfg, string? account = null)
    {
        try
        {
            return await SignInAsync(cfg, account);
        }
        catch (Exception e)
        {
            MessageBox.Show("The InterLink sign-in could not start:" + Environment.NewLine + Environment.NewLine + e.Message, "NEXUS-L InterLink service", MessageBoxButtons.OK, MessageBoxIcon.Error);
            return 2;
        }
    }

    private static async Task<int> SignInAsync(IConfiguration cfg, string? account)
    {
        var normAcc = InterlinkRunner.NormalizeAccount(account);
        if (string.IsNullOrEmpty(normAcc)) normAcc = InterlinkRunner.AccountLabAsst;
        var roleDesc = normAcc == InterlinkRunner.AccountLab
            ? "VF eTRF - PULLTESTTemplate"
            : "VF eTRF - Material / Product / WHOLE SHOE TEST / bonding";

        using var pw = await Playwright.CreateAsync();
        await using var browser = await BrowserLauncher.LaunchAsync(pw, false, cfg);
        var ctx = await browser.NewContextAsync(new BrowserNewContextOptions { ViewportSize = null });
        var page = await ctx.NewPageAsync();
        await page.GotoAsync("https://interlink2.intertek.com/");
        Console.WriteLine($"Sign in to InterLink with {normAcc} ({roleDesc}) in the browser window that opened.");
        Console.WriteLine("The window closes by itself once the InterLink portal home page shows.");

        var until = DateTime.UtcNow.AddMinutes(10);
        var emailFilled = false;
        while (DateTime.UtcNow < until)
        {
            await Task.Delay(1200);
            string url;
            try { url = page.Url; } catch { break; }

            // Show helper banner and pre-fill the target email on Microsoft / InterLink login page
            try
            {
                var didFill = await page.EvaluateAsync<bool>(@"([email, desc, alreadyFilled]) => {
                    if (!document.getElementById('__nx_signin_banner')) {
                        const b = document.createElement('div');
                        b.id = '__nx_signin_banner';
                        b.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:2147483647;background:#1e3a8a;color:#fff;padding:8px 16px;font:600 13px Segoe UI,sans-serif;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.25)';
                        b.textContent = `NEXUS-L InterLink Login — Please sign in as: ${email}  (${desc})`;
                        if (document.body) document.body.appendChild(b);
                    }
                    if (!alreadyFilled) {
                        const inp = document.querySelector('input[type=""email""], input[name=""loginfmt""], input[name*=""User"" i]');
                        if (inp && inp.offsetParent !== null && !inp.value) {
                            inp.value = email;
                            inp.dispatchEvent(new Event('input', { bubbles: true }));
                            inp.dispatchEvent(new Event('change', { bubbles: true }));
                            return true;
                        }
                    }
                    return false;
                }", new object[] { normAcc, roleDesc, emailFilled });
                if (didFill) emailFilled = true;
            }
            catch { /* navigating */ }

            if (url.Contains("interlink2.intertek.com", StringComparison.OrdinalIgnoreCase) && url.Contains("/Portal2/", StringComparison.OrdinalIgnoreCase))
            {
                await Task.Delay(2500);
                var file = InterlinkRunner.SessionFile(cfg, normAcc);
                await ctx.StorageStateAsync(new BrowserContextStorageStateOptions { Path = file });
                var defaultFile = InterlinkRunner.SessionFile(cfg, null);
                try { File.Copy(file, defaultFile, overwrite: true); } catch { }
                Console.WriteLine($"Signed in as {normAcc}. Session saved to {file}");
                MessageBox.Show($"Signed in to InterLink for {normAcc}{Environment.NewLine}({roleDesc}).{Environment.NewLine}{Environment.NewLine}The session is saved; the service can now work in the background.", "NEXUS-L InterLink service");
                return 0;
            }
        }
        Console.WriteLine("The sign-in was not finished in 10 minutes. Run --signin again.");
        MessageBox.Show($"The sign-in for {normAcc} was not finished in 10 minutes. Press the Login button again.", "NEXUS-L InterLink service");
        return 1;
    }
}
