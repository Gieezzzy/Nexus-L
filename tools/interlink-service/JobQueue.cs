using System.Text.Json;

namespace NexusInterlinkService;

/// <summary>
/// Everybody's reports wait here in arrival order (first in, first out) and are done one at a time: all of them go through the one
/// InterLink session of the shared account. Each job is kept on disk (data/queue) until it is done, so a restart of the program or of the
/// computer loses nothing and keeps the order. Every user gets their own replies: queued (with the place in the line), started, finished.
/// </summary>
public sealed class JobQueue : BackgroundService
{
    private readonly object _gate = new();
    private readonly List<JobRequest> _waiting = new();                  // ordered by Seq: the first one is next
    private readonly Dictionary<string, JobStatus> _status = new();
    private readonly SemaphoreSlim _signal = new(0);
    private JobRequest? _running;
    private long _seq;
    private readonly string _dir;
    private readonly string _history;
    private readonly InterlinkRunner _runner;
    private readonly Notifier _notifier;
    private readonly ILogger<JobQueue> _log;
    private readonly List<(JobRequest Job, JobStatus Status)> _interrupted = new();

    private static readonly JsonSerializerOptions Json = new() { WriteIndented = false };

    public JobQueue(IConfiguration cfg, InterlinkRunner runner, Notifier notifier, ILogger<JobQueue> log)
    {
        _runner = runner;
        _notifier = notifier;
        _log = log;
        _dir = Path.Combine(InterlinkRunner.DataDir(cfg), "queue");
        _history = Path.Combine(InterlinkRunner.DataDir(cfg), "history.jsonl");
        Directory.CreateDirectory(_dir);
        Reload();
    }

    /// <summary>jobs that were still waiting (or running) when the program last stopped come back in their old order</summary>
    private void Reload()
    {
        foreach (var file in Directory.GetFiles(_dir, "*.json").OrderBy(f => f, StringComparer.Ordinal))
        {
            try
            {
                var job = JsonSerializer.Deserialize<JobRequest>(File.ReadAllText(file), Json);
                if (job == null || job.JobUid == "") { File.Delete(file); continue; }
                _seq = Math.Max(_seq, job.Seq);
                var st = NewStatus(job);
                _status[job.JobUid] = st;
                if (job.Attempts >= 2)
                {
                    // it was started twice and the program stopped both times: not a third time
                    st.State = "failed";
                    st.Message = "The service stopped while this report was being submitted (twice). Please send it again.";
                    st.FinishedAt = DateTime.UtcNow;
                    _interrupted.Add((job, st));
                    File.Delete(file);
                    continue;
                }
                _waiting.Add(job);
                _signal.Release();
                _log.LogInformation("Job {Job} ({App}) came back from the last run", job.JobUid, job.ApplicationNo);
            }
            catch (Exception e)
            {
                _log.LogWarning("Queue file {File} could not be read: {Msg}", Path.GetFileName(file), e.Message);
            }
        }
    }

    private static JobStatus NewStatus(JobRequest j) => new()
    {
        JobUid = j.JobUid, ApplicationNo = j.ApplicationNo, LabRefNo = j.LabRefNo, ReportNo = j.ReportNo, UserName = j.UserName, CreatedAt = j.ReceivedAt,
    };

    private string FileFor(JobRequest j) => Path.Combine(_dir, $"{j.Seq:D12}_{j.JobUid}.json");

    public int Waiting { get { lock (_gate) return _waiting.Count + (_running != null ? 1 : 0); } }

    /// <summary>
    /// Takes a job in at the back of the line. When the same application is already waiting or running, that job is returned instead
    /// (Duplicate = true): two clicks, or two people, do not make two submissions of one application.
    /// </summary>
    public (JobStatus Status, bool Duplicate) Enqueue(JobRequest job)
    {
        lock (_gate)
        {
            var existing = _status.Values.FirstOrDefault(s => s.ApplicationNo == job.ApplicationNo && s.State is "queued" or "running");
            if (existing != null) return (WithPosition(existing), true);
            job.Seq = ++_seq;
            job.ReceivedAt = DateTime.UtcNow;
            File.WriteAllText(FileFor(job), JsonSerializer.Serialize(job, Json));
            var st = NewStatus(job);
            _status[job.JobUid] = st;
            _waiting.Add(job);
            _signal.Release();
            return (WithPosition(st), false);
        }
    }

    private JobStatus WithPosition(JobStatus s)
    {
        var copy = JsonSerializer.Deserialize<JobStatus>(JsonSerializer.Serialize(s, Json), Json)!;
        if (s.State == "running") copy.Position = 0;
        else if (s.State == "queued")
        {
            var idx = _waiting.FindIndex(j => j.JobUid == s.JobUid);
            copy.Position = Math.Max(idx, 0) + (_running != null ? 1 : 0);   // ahead of it: the one running now plus those before it
        }
        return copy;
    }

    public JobStatus? Get(string uid)
    {
        lock (_gate) return _status.TryGetValue(uid, out var s) ? WithPosition(s) : null;
    }

    private void WriteHistory(JobRequest job, JobStatus st)
    {
        try
        {
            var line = JsonSerializer.Serialize(new HistoryLine
            {
                JobUid = job.JobUid, ApplicationNo = job.ApplicationNo, LabRefNo = job.LabRefNo, ReportNo = job.ReportNo, User = job.UserName, Mode = job.Mode,
                State = st.State, Message = st.Message, ReceivedAt = job.ReceivedAt, StartedAt = st.StartedAt, FinishedAt = st.FinishedAt,
            }, Json);
            lock (_gate) File.AppendAllText(_history, line + Environment.NewLine);
        }
        catch { /* the history is only a list for people to read */ }
    }

    /// <summary>the jobs waiting or running now, then the finished ones, newest first (the Log window and the log list)</summary>
    public List<HistoryLine> Recent(int count)
    {
        var list = new List<HistoryLine>();
        lock (_gate)
        {
            foreach (var s in Overview())
                list.Add(new HistoryLine { JobUid = s.JobUid, ApplicationNo = s.ApplicationNo, LabRefNo = s.LabRefNo, ReportNo = s.ReportNo, User = s.UserName, State = s.State == "queued" ? $"waiting ({s.Position} ahead)" : s.State, Message = s.Message, ReceivedAt = s.CreatedAt, StartedAt = s.StartedAt });
        }
        try
        {
            if (File.Exists(_history))
                foreach (var l in File.ReadLines(_history).Reverse().Take(count))
                    if (JsonSerializer.Deserialize<HistoryLine>(l, Json) is { } h) list.Add(h);
        }
        catch { /* a half written line */ }
        return list;
    }

    /// <summary>everything waiting or running, in order: for NEXUS-L's overview</summary>
    public List<JobStatus> Overview()
    {
        lock (_gate)
        {
            var list = new List<JobStatus>();
            if (_running != null && _status.TryGetValue(_running.JobUid, out var r)) list.Add(WithPosition(r));
            foreach (var j in _waiting) if (_status.TryGetValue(j.JobUid, out var s)) list.Add(WithPosition(s));
            return list;
        }
    }

    protected override async Task ExecuteAsync(CancellationToken stop)
    {
        foreach (var (job, st) in _interrupted) await _notifier.ReportAsync(job, st);
        _interrupted.Clear();

        while (!stop.IsCancellationRequested)
        {
            try { await _signal.WaitAsync(stop); }
            catch (OperationCanceledException) { break; }

            JobRequest job;
            JobStatus st;
            lock (_gate)
            {
                if (_waiting.Count == 0) continue;
                job = _waiting.OrderBy(j => j.Seq).First();       // first in, first out
                _waiting.Remove(job);
                _running = job;
                st = _status[job.JobUid];
                st.State = "running";
                st.StartedAt = DateTime.UtcNow;
                st.Message = "Working in InterLink...";
                job.Attempts++;
                try { File.WriteAllText(FileFor(job), JsonSerializer.Serialize(job, Json)); } catch { /* the job still runs */ }
            }
            _ = _notifier.ProgressAsync(job, st);                // the user is told it has started

            try
            {
                await _runner.RunAsync(job, msg =>
                {
                    st.Message = msg;
                    _log.LogInformation("[{Job}] {App} ({User}): {Msg}", job.JobUid.Length > 8 ? job.JobUid[..8] : job.JobUid, job.ApplicationNo, job.UserName, msg);
                }, stop);
                st.State = "done";
            }
            catch (OperationCanceledException) when (stop.IsCancellationRequested)
            {
                // the program is stopping: the job stays on disk and comes back at the next start
                lock (_gate) { _running = null; }
                break;
            }
            catch (SignInRequiredException e)
            {
                st.State = "signin-required";
                st.Message = e.Message;
            }
            catch (Exception e)
            {
                st.State = "failed";
                st.Message = e is OperationCanceledException ? "InterLink took too long; the report was not submitted." : e.Message;
                _log.LogError(e, "Job {Job} failed", job.JobUid);
            }
            st.FinishedAt = DateTime.UtcNow;
            lock (_gate)
            {
                _running = null;
                try { File.Delete(FileFor(job)); } catch { /* left over file: removed at the next start */ }
            }
            _log.LogInformation("Job {Job} ({App}, {User}) {State}: {Msg}", job.JobUid, job.ApplicationNo, job.UserName, st.State, st.Message);
            WriteHistory(job, st);
            await _notifier.ReportAsync(job, st);                // the user is told how it ended

            lock (_gate)
            {
                // keep the last 300 results only
                if (_status.Count > 300)
                    foreach (var old in _status.Values.Where(x => x.FinishedAt != null).OrderBy(x => x.FinishedAt).Take(_status.Count - 300).ToList())
                        _status.Remove(old.JobUid);
            }
        }
    }
}
