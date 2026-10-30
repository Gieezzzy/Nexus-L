using System.Drawing;

namespace NexusInterlinkService;

/// <summary>
/// A small window that shows, live, what the service is doing in the background (opening InterLink, looking for the application, filling the
/// form, pressing Save ...) and the last steps. Show it from the tray menu; Hide puts it away again. It never stops the work.
/// </summary>
public sealed class ActivityForm : Form
{
    private readonly ServiceHost _host;
    private readonly Label _title = new() { AutoSize = false, Height = 22, Dock = DockStyle.Top, Font = new Font("Segoe UI", 9f, FontStyle.Bold), Padding = new Padding(8, 4, 0, 0) };
    private readonly Label _now = new() { AutoSize = false, Height = 52, Dock = DockStyle.Top, Font = new Font("Segoe UI", 11f), Padding = new Padding(8, 4, 8, 0) };
    private readonly Label _meta = new() { AutoSize = false, Height = 20, Dock = DockStyle.Top, ForeColor = Color.DimGray, Padding = new Padding(8, 0, 0, 0) };
    private readonly ProgressBar _bar = new() { Style = ProgressBarStyle.Marquee, Height = 6, Dock = DockStyle.Top, MarqueeAnimationSpeed = 40, Visible = false };
    private readonly ListBox _list = new() { Dock = DockStyle.Fill, BorderStyle = BorderStyle.None, Font = new Font("Consolas", 8.5f), HorizontalScrollbar = true, IntegralHeight = false };
    private readonly Button _hide = new() { Text = "Hide", Width = 80, Height = 28 };
    private readonly System.Windows.Forms.Timer _timer = new() { Interval = 700 };
    private string _lastStamp = "";

    public ActivityForm(ServiceHost host)
    {
        _host = host;
        Text = "NEXUS-L InterLink service - background task";
        FormBorderStyle = FormBorderStyle.FixedToolWindow;
        TopMost = true;
        ShowInTaskbar = false;
        StartPosition = FormStartPosition.Manual;
        ClientSize = new Size(470, 330);
        var wa = Screen.PrimaryScreen?.WorkingArea ?? new Rectangle(0, 0, 1280, 720);
        Location = new Point(wa.Right - Width - 12, wa.Bottom - Height - 12);       // bottom right, like a small status panel

        var foot = new Panel { Dock = DockStyle.Bottom, Height = 40, Padding = new Padding(8, 6, 12, 6) };
        _hide.Dock = DockStyle.Right;
        _hide.Click += (_, _) => Hide();
        foot.Controls.Add(_hide);

        Controls.Add(_list);
        Controls.Add(foot);
        Controls.Add(_bar);
        Controls.Add(_meta);
        Controls.Add(_now);
        Controls.Add(_title);

        _timer.Tick += (_, _) => Refresh_();
        VisibleChanged += (_, _) => { if (Visible) { Refresh_(true); _timer.Start(); } else _timer.Stop(); };
    }

    /// <summary>the cross button puts the window away like Hide (the program keeps running)</summary>
    protected override void OnFormClosing(FormClosingEventArgs e)
    {
        if (e.CloseReason == CloseReason.UserClosing) { e.Cancel = true; Hide(); return; }
        base.OnFormClosing(e);
    }

    private void Refresh_(bool force = false)
    {
        var q = _host.Queue;
        if (q == null)
        {
            _title.Text = "The service is not running";
            _now.Text = "Open Settings to start it.";
            _meta.Text = "";
            _bar.Visible = false;
            return;
        }
        var (job, step, since, behind, recent) = q.Snapshot(40);
        var stamp = (job?.JobUid ?? "") + "|" + step + "|" + behind + "|" + recent.Count + "|" + (recent.Count > 0 ? recent[0].Time.Ticks.ToString() : "");
        if (!force && stamp == _lastStamp && job == null) return;
        _lastStamp = stamp;

        if (job == null)
        {
            _title.Text = behind > 0 ? $"Next in line... ({behind} waiting)" : "Idle - nothing to do";
            _now.Text = behind > 0 ? "A report is about to start." : "Waiting for NEXUS-L to send a report.";
            _meta.Text = "";
            _bar.Visible = false;
        }
        else
        {
            _title.Text = $"Working now: {job.ApplicationNo}" + (behind > 0 ? $"   ({behind} waiting after it)" : "");
            _now.Text = step == "" ? "Starting..." : step;
            var secs = (int)(DateTime.Now - since).TotalSeconds;
            _meta.Text = $"{(job.UserName == "" ? "sent by a user" : "sent by " + job.UserName)}  |  {(job.Mode == "add" ? "adding a new report" : "editing the current report")}  |  this step for {secs}s";
            _bar.Visible = true;
        }

        var top = _list.TopIndex;
        _list.BeginUpdate();
        _list.Items.Clear();
        foreach (var a in recent)
            _list.Items.Add($"{a.Time:HH:mm:ss}  {(a.ApplicationNo == "" ? "" : a.ApplicationNo + ": ")}{a.Text}");
        _list.TopIndex = Math.Min(top, Math.Max(_list.Items.Count - 1, 0));
        _list.EndUpdate();
    }

    protected override void Dispose(bool disposing)
    {
        if (disposing) _timer.Dispose();
        base.Dispose(disposing);
    }
}
