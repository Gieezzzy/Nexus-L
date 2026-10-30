using Microsoft.Playwright;

namespace NexusInterlinkService;

/// <summary>
/// NexusInterlinkService --signin : opens a visible browser ONCE so the shared service account can sign in to InterLink (Microsoft sign-in,
/// MFA included). The session is saved to data/interlink-session.json; the service then works headless with it. No password is stored.
/// </summary>
public static class SignIn
{
    public static async Task<int> RunAsync(IConfiguration cfg)
    {
        using var pw = await Playwright.CreateAsync();
        await using var browser = await pw.Chromium.LaunchAsync(new BrowserTypeLaunchOptions { Headless = false });
        var ctx = await browser.NewContextAsync(new BrowserNewContextOptions { ViewportSize = null });
        var page = await ctx.NewPageAsync();
        await page.GotoAsync("https://interlink2.intertek.com/");
        Console.WriteLine("Sign in to InterLink with the service account in the browser window that opened (including any MFA step).");
        Console.WriteLine("The window closes by itself once the InterLink portal home page shows.");
        var until = DateTime.UtcNow.AddMinutes(10);
        while (DateTime.UtcNow < until)
        {
            await Task.Delay(1500);
            string url;
            try { url = page.Url; } catch { break; }
            if (url.Contains("interlink2.intertek.com", StringComparison.OrdinalIgnoreCase) && url.Contains("/Portal2/", StringComparison.OrdinalIgnoreCase))
            {
                await Task.Delay(2500);
                var file = InterlinkRunner.SessionFile(cfg);
                await ctx.StorageStateAsync(new BrowserContextStorageStateOptions { Path = file });
                Console.WriteLine("Signed in. Session saved to " + file);
                MessageBox.Show("Signed in to InterLink. The session is saved; the service can now work in the background.", "NEXUS-L InterLink service");
                return 0;
            }
        }
        Console.WriteLine("The sign-in was not finished in 10 minutes. Run --signin again.");
        MessageBox.Show("The sign-in was not finished in 10 minutes. Press \"Sign in to InterLink\" again.", "NEXUS-L InterLink service");
        return 1;
    }
}
