using System.Drawing;
using System.Net.Http;
using System.Text.Json;

namespace NexusInterlinkService;

/// <summary>The properties of the service: the address that receives NEXUS-L's payload, the key, NEXUS-L's address, and the two InterLink account Login buttons.</summary>
public sealed class SettingsForm : Form
{
    private readonly ServiceHost _host;
    private readonly Func<Task<bool>> _restart;
    private readonly Action<string> _signIn;
    private readonly Action _showLog;
    private readonly Action _toggleActivity;
    private readonly ComboBox _ip = new() { DropDownStyle = ComboBoxStyle.DropDown, Width = 260 };
    private readonly NumericUpDown _port = new() { Minimum = 1024, Maximum = 65535, Width = 90 };
    private readonly TextBox _key = new() { Width = 360, UseSystemPasswordChar = true };
    private readonly TextBox _nexus = new() { Width = 360 };
    private readonly CheckBox _headless = new() { Text = "Invisible browser (untick to watch it work, for testing)", AutoSize = true };
    private readonly CheckBox _startup = new() { Text = "Start with Windows", AutoSize = true };
    private readonly TextBox _envLines = new() { Multiline = true, ReadOnly = true, Width = 480, Height = 50, BackColor = Color.White, Font = new Font("Consolas", 9f) };
    private readonly Label _statusAsst = new() { AutoSize = true, ForeColor = Color.DimGray, Text = "Checking..." };
    private readonly Label _statusLab = new() { AutoSize = true, ForeColor = Color.DimGray, Text = "Checking..." };
    private readonly Label _status = new() { AutoSize = false, Width = 490, Height = 40, ForeColor = Color.DimGray };
    private readonly System.Windows.Forms.Timer _pollTimer = new() { Interval = 3500 };

    public SettingsForm(ServiceHost host, Func<Task<bool>> restart, Action<string> signIn, Action showLog, Action toggleActivity)
    {
        _host = host;
        _showLog = showLog;
        _toggleActivity = toggleActivity;
        _restart = restart;
        _signIn = signIn;
        Text = "NEXUS-L InterLink service - settings & accounts";
        FormBorderStyle = FormBorderStyle.FixedDialog;
        MaximizeBox = false;
        StartPosition = FormStartPosition.CenterScreen;
        ClientSize = new Size(520, 680);
        Font = new Font("Segoe UI", 9f);

        var y = 14;
        Label L(string t, int top) { var l = new Label { Text = t, Left = 16, Top = top, AutoSize = true, Font = new Font(Font, FontStyle.Bold) }; Controls.Add(l); return l; }
        void Put(Control c, int top, int left = 16) { c.Left = left; c.Top = top; Controls.Add(c); }

        L("Receive NEXUS-L's payload on this address (IP)", y); y += 22;
        _ip.Items.Add("0.0.0.0");
        foreach (var a in AppSettings.LocalIPv4()) _ip.Items.Add(a);
        Put(_ip, y); Put(new Label { Text = "Port", AutoSize = true }, y + 4, 290); Put(_port, y, 330); y += 28;
        Put(new Label { Text = "0.0.0.0 = every address of this computer. Pick one address to receive only on it.", AutoSize = true, ForeColor = Color.DimGray }, y); y += 28;

        L("Shared key (the same one goes into NEXUS-L)", y); y += 22;
        Put(_key, y);
        var show = new CheckBox { Text = "Show", AutoSize = true }; show.CheckedChanged += (_, _) => _key.UseSystemPasswordChar = !show.Checked; Put(show, y + 3, 384); y += 28;
        var gen = new Button { Text = "New key", Width = 90 }; gen.Click += (_, _) => { _key.Text = AppSettings.NewKey(); RefreshEnv(); };
        Put(gen, y); y += 34;

        L("NEXUS-L address (where results and approval checks go)", y); y += 22;
        Put(_nexus, y); y += 32;

        Put(_headless, y); y += 24;
        Put(_startup, y); y += 30;

        L("Put these two lines in NEXUS-L's .env file", y); y += 22;
        Put(_envLines, y); y += 54;
        var copy = new Button { Text = "Copy the lines", Width = 120 }; copy.Click += (_, _) => { Clipboard.SetText(_envLines.Text); _status.Text = "Copied."; };
        Put(copy, y); y += 36;

        // Dedicated InterLink Accounts Section
        L("InterLink Accounts (Login per Account)", y); y += 24;

        var btnAsst = new Button { Text = $"Login: {InterlinkRunner.AccountLabAsst}", Width = 230, Height = 30, BackColor = Color.FromArgb(239, 246, 255) };
        btnAsst.Click += (_, _) => _signIn(InterlinkRunner.AccountLabAsst);
        Put(btnAsst, y);
        Put(_statusAsst, y + 7, 256);
        y += 32;
        Put(new Label { Text = "   Used for: VF eTRF - Material, Product, Product WHOLE SHOE TEST, bonding", AutoSize = true, ForeColor = Color.DimGray }, y);
        y += 24;

        var btnLab = new Button { Text = $"Login: {InterlinkRunner.AccountLab}", Width = 230, Height = 30, BackColor = Color.FromArgb(239, 246, 255) };
        btnLab.Click += (_, _) => _signIn(InterlinkRunner.AccountLab);
        Put(btnLab, y);
        Put(_statusLab, y + 7, 256);
        y += 32;
        Put(new Label { Text = "   Used for: VF eTRF - PULLTESTTemplate", AutoSize = true, ForeColor = Color.DimGray }, y);
        y += 28;

        var save = new Button { Text = "Save and restart", Width = 130, Height = 30 }; save.Click += async (_, _) => await SaveAsync();
        var check = new Button { Text = "Check status", Width = 100, Height = 30 }; check.Click += async (_, _) => await CheckAsync();
        var log = new Button { Text = "Log...", Width = 80, Height = 30 }; log.Click += (_, _) => _showLog();
        var act = new Button { Text = "Show / hide activity", Width = 150, Height = 30 }; act.Click += (_, _) => _toggleActivity();
        Put(save, y); Put(check, y, 154); Put(log, y, 262); Put(act, y, 350); y += 38;
        Put(_status, y);

        var s = AppSettings.Load();
        _ip.Text = s.ListenIp;
        _port.Value = Math.Clamp(s.Port, 1024, 65535);
        _key.Text = s.ApiKey;
        _nexus.Text = s.NexusBaseUrl;
        _headless.Checked = s.Headless;
        _startup.Checked = AppSettings.StartsWithWindows;
        foreach (var c in new Control[] { _ip, _port, _key }) { c.TextChanged += (_, _) => RefreshEnv(); }
        _port.ValueChanged += (_, _) => RefreshEnv();
        RefreshEnv();
        _pollTimer.Tick += async (_, _) => { if (Visible) await CheckAsync(true); };
        _pollTimer.Start();
        FormClosed += (_, _) => _pollTimer.Dispose();
        _ = CheckAsync();
    }

    private AppSettings FromForm() => new()
    {
        ListenIp = (_ip.Text.Trim().Split(' ')[0]).Length == 0 ? "0.0.0.0" : _ip.Text.Trim().Split(' ')[0],
        Port = (int)_port.Value,
        ApiKey = _key.Text.Trim(),
        NexusBaseUrl = _nexus.Text.Trim().TrimEnd('/') + "/",
        Headless = _headless.Checked,
    };

    private void RefreshEnv()
    {
        var s = FromForm();
        _envLines.Text = $"interlinkService.url = '{s.AddressForNexus()}'{Environment.NewLine}interlinkService.key = '{s.ApiKey}'";
    }

    private async Task SaveAsync()
    {
        var s = FromForm();
        if (!System.Net.IPAddress.TryParse(s.ListenIp, out _)) { _status.ForeColor = Color.Firebrick; _status.Text = "The IP address is not valid."; return; }
        if (s.ApiKey.Length < 16) { _status.ForeColor = Color.Firebrick; _status.Text = "The key must be at least 16 characters (use New key)."; return; }
        if (!Uri.TryCreate(s.NexusBaseUrl, UriKind.Absolute, out _)) { _status.ForeColor = Color.Firebrick; _status.Text = "The NEXUS-L address is not valid (http://server/NEXUS-L/)."; return; }
        try { s.Save(); AppSettings.StartsWithWindows = _startup.Checked; }
        catch (Exception e) { _status.ForeColor = Color.Firebrick; _status.Text = "Could not save: " + e.Message; return; }
        _status.ForeColor = Color.DimGray;
        _status.Text = "Saved. Restarting...";
        var ok = await _restart();
        _status.ForeColor = ok ? Color.SeaGreen : Color.Firebrick;
        _status.Text = ok ? $"Saved. Receiving on {s.Url}." : "Saved, but it could not start: " + _host.LastError;
        if (ok) await CheckAsync(true);
    }

    private async Task CheckAsync(bool keepText = false)
    {
        var s = AppSettings.Load();
        try
        {
            using var http = new HttpClient { Timeout = TimeSpan.FromSeconds(4) };
            var host = s.ListenIp == "0.0.0.0" ? "127.0.0.1" : s.ListenIp;
            var json = await http.GetStringAsync($"http://{host}:{s.Port}/health");
            var root = JsonDocument.Parse(json).RootElement;
            var signedIn = root.TryGetProperty("signedIn", out var si) && si.GetBoolean();
            var waiting = root.TryGetProperty("waiting", out var w) ? w.GetInt32() : 0;

            var asstOk = signedIn;
            var labOk = signedIn;
            if (root.TryGetProperty("accounts", out var accs) && accs.ValueKind == JsonValueKind.Object)
            {
                if (accs.TryGetProperty(InterlinkRunner.AccountLabAsst, out var a1) && a1.TryGetProperty("signedIn", out var s1))
                    asstOk = s1.GetBoolean();
                if (accs.TryGetProperty(InterlinkRunner.AccountLab, out var a2) && a2.TryGetProperty("signedIn", out var s2))
                    labOk = s2.GetBoolean();
            }

            _statusAsst.ForeColor = asstOk ? Color.SeaGreen : Color.DarkOrange;
            _statusAsst.Text = asstOk ? "Signed in (ready)" : "NOT signed in — click Login";
            _statusLab.ForeColor = labOk ? Color.SeaGreen : Color.DarkOrange;
            _statusLab.Text = labOk ? "Signed in (ready)" : "NOT signed in — click Login";

            _status.ForeColor = (asstOk && labOk) ? Color.SeaGreen : (signedIn ? Color.DarkOrange : Color.Firebrick);
            _status.Text = $"Running on {s.Url}. Waiting jobs: {waiting}. Accounts signed in: {(asstOk ? "labasst " : "")}{(labOk ? "lab" : (!asstOk ? "none" : ""))}.";
        }
        catch
        {
            if (keepText) return;
            _status.ForeColor = Color.Firebrick;
            _status.Text = "The service is not running on " + s.Url + (_host.LastError != null ? ": " + _host.LastError : ".");
        }
    }
}
