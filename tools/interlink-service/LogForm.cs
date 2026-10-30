using System.Drawing;
using System.Text;
using System.Text.Json;

namespace NexusInterlinkService;

/// <summary>The reports that were received (waiting, being submitted, finished, failed - with the reason) and the live log, in one window.</summary>
public sealed class LogForm : Form
{
    private readonly ServiceHost _host;
    private readonly DataGridView _grid = new() { Dock = DockStyle.Fill, ReadOnly = true, AllowUserToAddRows = false, AllowUserToDeleteRows = false, RowHeadersVisible = false, SelectionMode = DataGridViewSelectionMode.FullRowSelect, AutoSizeRowsMode = DataGridViewAutoSizeRowsMode.None, BackgroundColor = Color.White, BorderStyle = BorderStyle.None };
    private readonly TextBox _log = new() { Dock = DockStyle.Fill, Multiline = true, ReadOnly = true, ScrollBars = ScrollBars.Both, WordWrap = false, Font = new Font("Consolas", 9f), BackColor = Color.White };
    private readonly CheckBox _live = new() { Text = "Live", Checked = true, AutoSize = true };
    private readonly System.Windows.Forms.Timer _timer = new() { Interval = 2000 };
    private string _lastLogStamp = "";
    private string _lastGridStamp = "";

    public LogForm(ServiceHost host)
    {
        _host = host;
        Text = "NEXUS-L InterLink service - log";
        StartPosition = FormStartPosition.CenterScreen;
        ClientSize = new Size(1040, 640);
        Font = new Font("Segoe UI", 9f);

        var split = new SplitContainer { Dock = DockStyle.Fill, Orientation = Orientation.Horizontal, SplitterDistance = 270 };
        var top = new Label { Text = "Reports received (newest first; waiting and running ones on top)", Dock = DockStyle.Top, Height = 24, Padding = new Padding(4, 4, 0, 0), Font = new Font(Font, FontStyle.Bold) };
        var bottom = new Label { Text = "Log (service.log)", Dock = DockStyle.Top, Height = 24, Padding = new Padding(4, 4, 0, 0), Font = new Font(Font, FontStyle.Bold) };
        split.Panel1.Controls.Add(_grid);
        split.Panel1.Controls.Add(top);
        split.Panel2.Controls.Add(_log);
        split.Panel2.Controls.Add(bottom);

        var bar = new FlowLayoutPanel { Dock = DockStyle.Bottom, Height = 38, Padding = new Padding(6, 6, 0, 0) };
        var refresh = new Button { Text = "Refresh", Width = 80 }; refresh.Click += (_, _) => Reload(true);
        var open = new Button { Text = "Open log file", Width = 100 }; open.Click += (_, _) => OpenFile(LogPath());
        var copy = new Button { Text = "Copy the log", Width = 100 }; copy.Click += (_, _) => { if (_log.Text.Length > 0) Clipboard.SetText(_log.Text); };
        var copyRow = new Button { Text = "Copy the selected report", Width = 160 }; copyRow.Click += (_, _) => CopySelected();
        bar.Controls.AddRange(new Control[] { refresh, open, copy, copyRow, _live });
        Controls.Add(split);
        Controls.Add(bar);

        _grid.Columns.Add("received", "Received");
        _grid.Columns.Add("app", "Application");
        _grid.Columns.Add("lab", "Lab reference");
        _grid.Columns.Add("report", "Report No.");
        _grid.Columns.Add("user", "Sent by");
        _grid.Columns.Add("mode", "Mode");
        _grid.Columns.Add("state", "State");
        _grid.Columns.Add("msg", "Message");
        _grid.Columns["received"]!.Width = 125;
        _grid.Columns["app"]!.Width = 95;
        _grid.Columns["lab"]!.Width = 150;
        _grid.Columns["report"]!.Width = 120;
        _grid.Columns["user"]!.Width = 110;
        _grid.Columns["mode"]!.Width = 50;
        _grid.Columns["state"]!.Width = 120;
        _grid.Columns["msg"]!.AutoSizeMode = DataGridViewAutoSizeColumnMode.Fill;
        _grid.DefaultCellStyle.WrapMode = DataGridViewTriState.False;
        _grid.CellDoubleClick += (_, e) => { if (e.RowIndex >= 0) MessageBox.Show(string.Join(Environment.NewLine, new[] { "Application: " + _grid.Rows[e.RowIndex].Cells["app"].Value, "State: " + _grid.Rows[e.RowIndex].Cells["state"].Value, "", "" + _grid.Rows[e.RowIndex].Cells["msg"].Value }), "Report"); };

        _timer.Tick += (_, _) => { if (_live.Checked) Reload(false); };
        Shown += (_, _) => { Reload(true); _timer.Start(); };
        FormClosed += (_, _) => _timer.Dispose();
    }

    private static string DataPath() => InterlinkRunner.DataDir(new ConfigurationBuilder().AddJsonFile(AppSettings.FilePath, optional: true).Build());
    private static string LogPath() => Path.Combine(DataPath(), "service.log");
    private static string HistoryPath() => Path.Combine(DataPath(), "history.jsonl");

    private static void OpenFile(string path)
    {
        if (File.Exists(path)) System.Diagnostics.Process.Start(new System.Diagnostics.ProcessStartInfo(path) { UseShellExecute = true });
        else MessageBox.Show("Nothing has been logged yet.", "NEXUS-L InterLink service");
    }

    private void CopySelected()
    {
        if (_grid.CurrentRow == null) return;
        var c = _grid.CurrentRow.Cells;
        Clipboard.SetText($"{c["received"].Value} | {c["app"].Value} | {c["lab"].Value} | {c["report"].Value} | {c["user"].Value} | {c["state"].Value} | {c["msg"].Value}");
    }

    /// <summary>the last lines of a file that another process is still writing to</summary>
    private static string Tail(string path, int lines, out string stamp)
    {
        stamp = "";
        if (!File.Exists(path)) return "";
        var info = new FileInfo(path);
        stamp = info.Length + "|" + info.LastWriteTimeUtc.Ticks;
        using var fs = new FileStream(path, FileMode.Open, FileAccess.Read, FileShare.ReadWrite);
        using var sr = new StreamReader(fs, Encoding.UTF8);
        var q = new Queue<string>();
        string? l;
        while ((l = sr.ReadLine()) != null) { q.Enqueue(l); if (q.Count > lines) q.Dequeue(); }
        return string.Join(Environment.NewLine, q);
    }

    private void Reload(bool force)
    {
        try
        {
            var text = Tail(LogPath(), 400, out var stamp);
            if (force || stamp != _lastLogStamp)
            {
                _lastLogStamp = stamp;
                var atEnd = _log.SelectionStart >= _log.TextLength - 2 || force;
                _log.Text = text;
                if (atEnd) { _log.SelectionStart = _log.TextLength; _log.ScrollToCaret(); }
            }
        }
        catch { /* the log file is busy for a moment */ }

        try
        {
            var rows = new List<HistoryLine>();
            if (_host.Queue is { } q) rows = q.Recent(150);
            else
            {
                var hist = Tail(HistoryPath(), 150, out _);
                foreach (var l in hist.Split('\n', StringSplitOptions.RemoveEmptyEntries).Reverse())
                    if (JsonSerializer.Deserialize<HistoryLine>(l.Trim(), new JsonSerializerOptions()) is { } h) rows.Add(h);
            }
            var stamp = string.Join("|", rows.Take(60).Select(r => r.JobUid + r.State + r.Message.Length));
            if (!force && stamp == _lastGridStamp) return;
            _lastGridStamp = stamp;
            var selected = _grid.CurrentRow?.Cells["app"].Value?.ToString();
            _grid.Rows.Clear();
            foreach (var r in rows)
            {
                var when = (r.FinishedAt ?? r.StartedAt ?? r.ReceivedAt).ToLocalTime().ToString("yyyy-MM-dd HH:mm:ss");
                var i = _grid.Rows.Add(when, r.ApplicationNo, r.LabRefNo, r.ReportNo, r.User, r.Mode, r.State, r.Message);
                var color = r.State switch { "done" => Color.SeaGreen, "failed" => Color.Firebrick, "signin-required" => Color.DarkOrange, "running" => Color.RoyalBlue, _ => Color.DimGray };
                _grid.Rows[i].DefaultCellStyle.ForeColor = color;
            }
            if (selected != null)
                foreach (DataGridViewRow row in _grid.Rows)
                    if ((row.Cells["app"].Value?.ToString()) == selected) { _grid.CurrentCell = row.Cells["app"]; break; }
        }
        catch { /* shown again at the next refresh */ }
    }
}
