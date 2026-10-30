using NexusInterlinkService;

namespace NexusInterlinkService;

internal static class Program
{
    /// <summary>
    /// (no arguments)  a small tray program: it receives NEXUS-L's payload, with a Settings window for the address (IP + port), the key and NEXUS-L's address
    /// --service       no window: the same service for a Windows service / scheduled start
    /// --signin        opens a visible browser once so the shared InterLink account can sign in
    /// </summary>
    [STAThread]
    private static int Main(string[] args)
    {
        if (args.Contains("--signin"))
        {
            var cfg = new ConfigurationBuilder().AddJsonFile(AppSettings.FilePath, optional: true).Build();
            return SignIn.RunAsync(cfg).GetAwaiter().GetResult();
        }
        if (args.Contains("--service") || !Environment.UserInteractive)
        {
            new ServiceHost().RunBlockingAsync(args).GetAwaiter().GetResult();
            return 0;
        }

        using var mutex = new Mutex(true, "NexusInterlinkService.Tray", out var first);
        if (!first)
        {
            MessageBox.Show("The NEXUS-L InterLink service is already running (look for its icon next to the clock).", "NEXUS-L InterLink service");
            return 0;
        }
        ApplicationConfiguration.Initialize();
        Application.Run(new TrayContext(args));
        return 0;
    }
}
