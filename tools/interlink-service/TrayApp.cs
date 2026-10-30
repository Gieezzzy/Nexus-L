using System.Diagnostics;

namespace NexusInterlinkService;

/// <summary>The program while someone is logged in: an icon next to the clock, the service running behind it.</summary>
public sealed class TrayContext : ApplicationContext
{
    private readonly NotifyIcon _icon;
    private readonly ServiceHost _host = new();
    private readonly string[] _args;
    private SettingsForm? _form;
    private LogForm? _logForm;

    public TrayContext(string[] args)
    {
        _args = args;
        var menu = new ContextMenuStrip();
        menu.Items.Add("Settings...", null, (_, _) => ShowSettings());
        menu.Items.Add("Sign in to InterLink...", null, (_, _) => SignInToInterlink());
        menu.Items.Add("Log...", null, (_, _) => ShowLog());
        menu.Items.Add(new ToolStripSeparator());
        menu.Items.Add("Exit", null, async (_, _) => await ExitAsync());
        _icon = new NotifyIcon { Icon = System.Drawing.SystemIcons.Application, Text = "NEXUS-L InterLink service", Visible = true, ContextMenuStrip = menu };
        _icon.DoubleClick += (_, _) => ShowSettings();
        _icon.BalloonTipClicked += (_, _) => ShowLog();

        var s = AppSettings.Load();
        var firstRun = s.ApiKey.Length < 16;
        if (firstRun)
        {
            s.ApiKey = AppSettings.NewKey();    // a fresh key; the Settings window shows it so it can be copied into NEXUS-L
            s.Save();
        }
        _ = StartAsync(firstRun);
    }

    private async Task StartAsync(bool openSettings)
    {
        var s = AppSettings.Load();
        var ok = await _host.StartAsync(_args);
        _icon.Text = ok ? $"NEXUS-L InterLink service - {s.Url}" : "NEXUS-L InterLink service - NOT running";
        if (!ok) _icon.ShowBalloonTip(8000, "InterLink service", "Could not start on " + s.Url + ": " + _host.LastError, ToolTipIcon.Error);
        if (openSettings || !ok) ShowSettings();
    }

    private void ShowSettings()
    {
        if (_form is { IsDisposed: false }) { _form.Activate(); return; }
        _form = new SettingsForm(_host, async () => { await StartAsync(false); return _host.Running; }, SignInToInterlink, ShowLog);
        _form.Show();
    }

    private static void SignInToInterlink()
    {
        try { Process.Start(new ProcessStartInfo(Environment.ProcessPath!, "--signin") { UseShellExecute = true }); }
        catch (Exception e) { MessageBox.Show("Could not start the sign-in: " + e.Message, "NEXUS-L InterLink service"); }
    }

    private void ShowLog()
    {
        if (_logForm is { IsDisposed: false }) { _logForm.Activate(); return; }
        _logForm = new LogForm(_host);
        _logForm.Show();
    }

    private async Task ExitAsync()
    {
        _icon.Visible = false;
        await _host.StopAsync();
        _icon.Dispose();
        ExitThread();
    }
}
