using Microsoft.Playwright;

namespace NexusInterlinkService;

/// <summary>
/// Starts the browser the service drives. It prefers the Chrome or Edge already installed on the computer (nothing to download, and it
/// always matches the Windows version), and falls back to the Chromium that Install-Browser.cmd downloads. Each run gets a clean, private
/// profile: nobody's own browser profile or sign-ins are touched.
/// </summary>
public static class BrowserLauncher
{
    public static async Task<IBrowser> LaunchAsync(IPlaywright pw, bool headless, IConfiguration cfg)
    {
        var wanted = cfg["Service:BrowserChannel"];               // "chrome", "msedge", or "chromium" (the downloaded one); empty = try in this order
        var order = string.IsNullOrWhiteSpace(wanted) ? new[] { "chrome", "msedge", "chromium" } : new[] { wanted!.ToLowerInvariant() };
        var errors = new List<string>();
        foreach (var channel in order)
        {
            try
            {
                return await pw.Chromium.LaunchAsync(new BrowserTypeLaunchOptions { Headless = headless, Channel = channel == "chromium" ? null : channel });
            }
            catch (Exception e)
            {
                errors.Add($"{channel}: {e.Message.Split('\n')[0]}");
            }
        }
        throw new InvalidOperationException("No browser could be started (" + string.Join("; ", errors) + "). Install Google Chrome or Microsoft Edge, or run Install-Browser.cmd.");
    }
}
