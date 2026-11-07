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
    private ActivityForm? _activityForm;
    private ToolStripMenuItem? _activityItem;
    private readonly EventWaitHandle _showEvent;
    private readonly SynchronizationContext _uiCtx;

    public TrayContext(string[] args)
    {
        _args = args;
        _uiCtx = SynchronizationContext.Current ?? new WindowsFormsSynchronizationContext();
        SynchronizationContext.SetSynchronizationContext(_uiCtx);

        _showEvent = new EventWaitHandle(false, EventResetMode.AutoReset, "NexusInterlinkService.ShowSettings");
        ThreadPool.RegisterWaitForSingleObject(_showEvent, (_, _) =>
        {
            _uiCtx.Post(_ => ShowSettings(), null);
        }, null, -1, false);

        var menu = new ContextMenuStrip();
        menu.Items.Add("Settings & InterLink Login...", null, (_, _) => ShowSettings());
        menu.Items.Add($"Login: {InterlinkRunner.AccountLabAsst} (Material / Product / Whole Shoe / Bonding)...", null, (_, _) => SignInToInterlink(InterlinkRunner.AccountLabAsst));
        menu.Items.Add($"Login: {InterlinkRunner.AccountLab} (PULLTESTTemplate)...", null, (_, _) => SignInToInterlink(InterlinkRunner.AccountLab));
        _activityItem = new ToolStripMenuItem("Show what it is doing", null, (_, _) => ToggleActivity());
        menu.Items.Add(_activityItem);
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
        var openNow = firstRun || args.Any(a => string.Equals(a, "--open", StringComparison.OrdinalIgnoreCase) || string.Equals(a, "--settings", StringComparison.OrdinalIgnoreCase));
        _ = StartAsync(openNow);
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
        if (_form is { IsDisposed: false })
        {
            if (_form.WindowState == FormWindowState.Minimized) _form.WindowState = FormWindowState.Normal;
            _form.Show();
            _form.BringToFront();
            _form.Activate();
            return;
        }
        _form = new SettingsForm(_host, async () => { await StartAsync(false); return _host.Running; }, SignInToInterlink, ShowLog, ToggleActivity);
        _form.Show();
        _form.BringToFront();
        _form.Activate();
    }

    public static void SignInToInterlink(string account)
    {
        var norm = InterlinkRunner.NormalizeAccount(account);
        if (string.IsNullOrEmpty(norm)) norm = InterlinkRunner.AccountLabAsst;
        try { Process.Start(new ProcessStartInfo(Environment.ProcessPath!, $"--signin {norm}") { UseShellExecute = true }); }
        catch (Exception e) { MessageBox.Show("Could not start the sign-in: " + e.Message, "NEXUS-L InterLink service"); }
    }

    /// <summary>show the live background-task window, or hide it again</summary>
    private void ToggleActivity()
    {
        if (_activityForm == null || _activityForm.IsDisposed)
        {
            _activityForm = new ActivityForm(_host, SignInToInterlink, ShowSettings);
            _activityForm.VisibleChanged += (_, _) => { if (_activityItem != null) _activityItem.Text = _activityForm.Visible ? "Hide what it is doing" : "Show what it is doing"; };
        }
        if (_activityForm.Visible) _activityForm.Hide();
        else { _activityForm.Show(); _activityForm.BringToFront(); }
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
