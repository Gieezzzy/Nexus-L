using System.Text.Json;
using System.Text.Json.Nodes;

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
    private JobRequest? _running;                                          // the submission being done now (one at a time, FIFO)
    private readonly List<JobRequest> _readers = new();                    // loading / filtering jobs running now (several at once)
    private readonly SemaphoreSlim _readSlots;
    private readonly SemaphoreSlim _pdfSlots;                              // printing the eTRF PDFs of existing Applications: several at once (Service:MaxParallelPdfs, default 5)
    private DateTime _nextPdfStart = DateTime.MinValue;
    private CancellationToken _stop = CancellationToken.None;

    // ONE job at a time per InterLink login. Every job works in the same server-side session of the shared account; two jobs at once
    // (a load and a filter, or a load and a submission) change each other's grid and one of them reads blank rows. Different accounts do not wait for each other.
    private readonly Dictionary<string, SemaphoreSlim> _acct = new();
    private SemaphoreSlim AcctLock(JobRequest job)
    {
        var key = InterlinkRunner.ResolveAccountForJob(job);
        lock (_acct)
        {
            if (!_acct.TryGetValue(key, out var s)) _acct[key] = s = new SemaphoreSlim(1, 1);
            return s;
        }
    }

    /// <summary>one loading / filtering request. The first of its kind is the Leader (it really goes to InterLink); the same request from other
    /// people waits on it and gets its result (Followers), and a result stays usable for CacheSeconds for whoever asks next.</summary>
    private sealed class ReadRun
    {
        public JobRequest Job = null!;
        public JobStatus St = null!;
        public string Key = "";
        public CancellationTokenSource Cts = null!;
        public ReadRun? Leader;                                   // null = this one goes to InterLink itself
        public readonly List<ReadRun> Followers = new();
        public bool Superseded;
        public bool Finished;
    }
    private readonly List<ReadRun> _reads = new();                // loading requests that have not finished
    private readonly Dictionary<string, (string? Json, DateTime At)> _cache = new();
    private int CacheSeconds => Math.Max(0, _cacheSeconds);
    private readonly int _cacheSeconds;

    // gentle on the one shared InterLink account: loads start at least ReadStartGapMs apart, and after repeated failures new loads pause
    private readonly int _readGapMs;
    private readonly object _gapLock = new();
    private DateTime _nextReadStart = DateTime.MinValue;
    private int _readFails;
    private DateTime _pauseUntil = DateTime.MinValue;
    private long _seq;
    private readonly string _dir;
    private readonly string _history;
    private readonly InterlinkRunner _runner;
    private readonly Notifier _notifier;
    private readonly ILogger<JobQueue> _log;
    private readonly List<(JobRequest Job, JobStatus Status)> _interrupted = new();
    private readonly List<ActivityEntry> _activity = new();                 // the last steps, newest at the end (the Activity window)
    private string _step = "";
    private DateTime _stepSince = DateTime.Now;

    private static readonly JsonSerializerOptions Json = new() { WriteIndented = false };

    public JobQueue(IConfiguration cfg, InterlinkRunner runner, Notifier notifier, ILogger<JobQueue> log)
    {
        _runner = runner;
        _notifier = notifier;
        _log = log;
        _readSlots = new SemaphoreSlim(Math.Max(1, cfg.GetValue("Service:MaxParallelReads", 3)));
        _cacheSeconds = cfg.GetValue("Service:ListCacheSeconds", 60);
        _pdfSlots = new SemaphoreSlim(Math.Max(1, cfg.GetValue("Service:MaxParallelPdfs", 5)));
        _readGapMs = Math.Max(0, cfg.GetValue("Service:ReadStartGapMs", 1500));
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
                if (job == null || job.JobUid == "" || IsRead(job.Mode)) { File.Delete(file); continue; }   // a leftover read is stale: the page asks again
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

    /// <summary>loading rows, filtering a list, reading list values: these change nothing in InterLink, so they run at the same time as other work. Everything else (a submission) goes first in, first out.</summary>
    public static bool IsRead(string? mode) => mode is "vf_grid" or "grid_html" or "lov" or "vf_pdf";

    private static JobStatus NewStatus(JobRequest j) => new()
    {
        JobUid = j.JobUid, ApplicationNo = j.ApplicationNo, LabRefNo = j.LabRefNo, ReportNo = j.ReportNo, UserName = j.UserName, CreatedAt = j.ReceivedAt,
    };

    private string FileFor(JobRequest j) => Path.Combine(_dir, $"{j.Seq:D12}_{j.JobUid}.json");

    public int Waiting { get { lock (_gate) return _waiting.Count + (_running != null ? 1 : 0) + _readers.Count; } }

    private void Note(JobRequest j, string text)
    {
        lock (_gate)
        {
            _activity.Add(new ActivityEntry { JobUid = j.JobUid, ApplicationNo = j.ApplicationNo, User = j.UserName, Text = text });
            if (_activity.Count > 200) _activity.RemoveRange(0, _activity.Count - 200);
            if (_running?.JobUid == j.JobUid) { _step = text; _stepSince = DateTime.Now; }
        }
    }

    /// <summary>what the service is doing right now (null job = idle) and the latest steps, newest first</summary>
    public (JobRequest? Job, string Step, DateTime Since, int WaitingBehind, List<ActivityEntry> Recent) Snapshot(int count)
    {
        lock (_gate)
            return (_running, _step, _stepSince, _waiting.Count, _activity.AsEnumerable().Reverse().Take(count).ToList());
    }

    /// <summary>
    /// Takes a job in at the back of the line. When the same application is already waiting or running, that job is returned instead
    /// (Duplicate = true): two clicks, or two people, do not make two submissions of one application.
    /// </summary>
    public (JobStatus Status, bool Duplicate) Enqueue(JobRequest job)
    {
        lock (_gate)
        {
            var read = IsRead(job.Mode);
            if (!read)
            {
                var existing = _status.Values.FirstOrDefault(s => s.ApplicationNo == job.ApplicationNo && s.State is "queued" or "running");
                if (existing != null) return (WithPosition(existing), true);
            }
            job.Seq = ++_seq;
            job.ReceivedAt = DateTime.UtcNow;
            var st = NewStatus(job);
            _status[job.JobUid] = st;
            if (read)
            {
                EnqueueRead(job, st);
                return (WithPosition(st), false);
            }
            Note(job, $"Received {job.ApplicationNo} from {(job.UserName == "" ? "a user" : job.UserName)} ({job.Mode}); {_waiting.Count + (_running != null ? 1 : 0)} ahead of it.");
            File.WriteAllText(FileFor(job), JsonSerializer.Serialize(job, Json));
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
            foreach (var rj in _readers) if (_status.TryGetValue(rj.JobUid, out var rs)) list.Add(WithPosition(rs));
            foreach (var j in _waiting) if (_status.TryGetValue(j.JobUid, out var s)) list.Add(WithPosition(s));
            return list;
        }
    }

    private static string ReadKey(JobRequest job)
    {
        var j = (JsonObject)job.Job.DeepClone();
        j.Remove("fresh");
        return $"{job.Mode}|{job.Account}|{j.ToJsonString()}";
    }

    /// <summary>called with _gate held. 1) a newer request of the same user replaces his older one; 2) a result loaded a moment ago is handed
    /// out at once; 3) the same request already loading is shared; 4) otherwise it goes to InterLink.</summary>
    private void EnqueueRead(JobRequest job, JobStatus st)
    {
        // a PDF job carries a file back: never shared, never answered from a cache, and not held back by the one-job-per-login rule of the list loads
        if (job.Mode == "vf_pdf")
        {
            Note(job, $"Received {job.ApplicationNo} (eTRF PDF); runs with up to {_pdfSlots.CurrentCount + 1}+ others at once.");
            _ = Task.Run(() => RunPdfAsync(job, st));
            return;
        }
        var key = ReadKey(job);
        var fresh = job.Job["fresh"] is JsonValue fv && fv.TryGetValue<bool>(out var fb) && fb;

        // 1. one loading request per person: typing or paging on makes the older one pointless
        if (job.UserName != "")
            foreach (var old in _reads.Where(r => !r.Finished && r.Job.UserName == job.UserName && r.Job.Mode == job.Mode && r.Key != key).ToList())
            {
                if (old.Leader != null)
                {
                    old.Leader.Followers.Remove(old);
                    CancelRead(old, "Replaced by a newer request.");
                }
                else if (old.Followers.Count == 0) { old.Superseded = true; old.Cts.Cancel(); }   // others wait on it: it keeps going
            }

        // 2. a result from the last moments (the Refresh button sends fresh = true and skips this)
        if (!fresh && CacheSeconds > 0 && _cache.TryGetValue(key, out var hit) && (DateTime.UtcNow - hit.At).TotalSeconds < CacheSeconds)
        {
            job.ResultJson = hit.Json;
            st.State = "done";
            st.Message = "Loaded a moment ago (shared result).";
            st.StartedAt = st.FinishedAt = DateTime.UtcNow;
            Note(job, $"Received {job.ApplicationNo} ({job.Mode}); answered from the result loaded {(int)(DateTime.UtcNow - hit.At).TotalSeconds}s ago.");
            _ = Task.Run(() => _notifier.ReportAsync(job, st));
            return;
        }

        // InterLink answered badly three times in a row: stop asking for a minute (the saved copies keep serving the page)
        if (DateTime.UtcNow < _pauseUntil)
        {
            st.State = "failed";
            st.Message = $"InterLink is cooling down after repeated errors; try again in {(int)(_pauseUntil - DateTime.UtcNow).TotalSeconds + 1} s.";
            st.FinishedAt = DateTime.UtcNow;
            Note(job, $"Refused {job.ApplicationNo} ({job.Mode}): cooling down.");
            _ = Task.Run(() => _notifier.ReportAsync(job, st));
            return;
        }

        var run = new ReadRun { Job = job, St = st, Key = key };

        // 3. the same list is already being loaded: wait for it
        var leader = _reads.FirstOrDefault(r => !r.Finished && !r.Superseded && r.Leader == null && r.Key == key);
        if (leader != null)
        {
            run.Leader = leader;
            leader.Followers.Add(run);
            _reads.Add(run);
            st.Message = "The same list is already loading; waiting for it.";
            Note(job, $"Received {job.ApplicationNo} ({job.Mode}); shares the load that is already running.");
            return;
        }

        // 4. a real load, at once, next to whatever else is running
        run.Cts = CancellationTokenSource.CreateLinkedTokenSource(_stop);
        _reads.Add(run);
        Note(job, $"Received {job.ApplicationNo} ({job.Mode}); runs now, alongside other work.");
        _ = Task.Run(() => RunReadAsync(run));
    }

    /// <summary>ends a waiting (follower) request without going to InterLink; _gate is held</summary>
    private void CancelRead(ReadRun r, string why)
    {
        r.Finished = true;
        r.St.State = "failed";
        r.St.Message = why;
        r.St.FinishedAt = DateTime.UtcNow;
        _reads.Remove(r);
        var job = r.Job; var st = r.St;
        _ = Task.Run(() => _notifier.ReportAsync(job, st));
    }

    private async Task RunPdfAsync(JobRequest job, JobStatus st)
    {
        try { await _pdfSlots.WaitAsync(_stop); }
        catch (OperationCanceledException) { return; }
        try
        {
            TimeSpan wait;
            lock (_gapLock)
            {
                var start = DateTime.UtcNow > _nextPdfStart ? DateTime.UtcNow : _nextPdfStart;
                _nextPdfStart = start.AddMilliseconds(2000);   // starts a moment apart
                wait = start - DateTime.UtcNow;
            }
            if (wait > TimeSpan.Zero)
            {
                try { await Task.Delay(wait, _stop); } catch (OperationCanceledException) { return; }
            }
            lock (_gate)
            {
                _readers.Add(job);
                st.State = "running";
                st.StartedAt = DateTime.UtcNow;
                st.Message = "Working in InterLink...";
                Note(job, $"Started {job.ApplicationNo} (eTRF PDF).");
            }
            await ExecuteJobAsync(job, st, _stop, null);
        }
        finally { _pdfSlots.Release(); }
    }

    /// <summary>a loading / filtering job: waits only for a free slot (Service:MaxParallelReads, default 3), never for the submissions</summary>
    private async Task RunReadAsync(ReadRun run)
    {
        var job = run.Job; var st = run.St;
        var got = false;
        var gotAcct = false;
        try
        {
            try { await _readSlots.WaitAsync(run.Cts.Token); got = true; }
            catch (OperationCanceledException)
            {
                if (_stop.IsCancellationRequested) return;
                lock (_gate) { st.State = "failed"; st.Message = "Replaced by a newer request."; st.FinishedAt = DateTime.UtcNow; }
                await _notifier.ReportAsync(job, st);
                return;
            }
            try { await AcctLock(job).WaitAsync(run.Cts.Token); gotAcct = true; }
            catch (OperationCanceledException)
            {
                if (_stop.IsCancellationRequested) return;
                lock (_gate) { st.State = "failed"; st.Message = "Replaced by a newer request."; st.FinishedAt = DateTime.UtcNow; }
                await _notifier.ReportAsync(job, st);
                return;
            }
            // loads start one after the other, a moment apart, never all in the same instant
            TimeSpan wait;
            lock (_gapLock)
            {
                var start = DateTime.UtcNow > _nextReadStart ? DateTime.UtcNow : _nextReadStart;
                _nextReadStart = start.AddMilliseconds(_readGapMs);
                wait = start - DateTime.UtcNow;
            }
            if (wait > TimeSpan.Zero)
            {
                try { await Task.Delay(wait, run.Cts.Token); }
                catch (OperationCanceledException)
                {
                    if (_stop.IsCancellationRequested) return;
                    lock (_gate) { st.State = "failed"; st.Message = "Replaced by a newer request."; st.FinishedAt = DateTime.UtcNow; }
                    await _notifier.ReportAsync(job, st);
                    return;
                }
            }
            lock (_gate)
            {
                _readers.Add(job);
                st.State = "running";
                st.StartedAt = DateTime.UtcNow;
                st.Message = "Working in InterLink...";
                Note(job, $"Started {job.ApplicationNo} ({job.Mode}).");
            }
            await ExecuteJobAsync(job, st, run.Cts.Token, run);
        }
        finally
        {
            if (gotAcct) AcctLock(job).Release();
            if (got) _readSlots.Release();
            List<ReadRun> followers;
            lock (_gate)
            {
                run.Finished = true;
                _reads.Remove(run);
                followers = run.Followers.ToList();
                run.Followers.Clear();
                if (st.State == "done" && CacheSeconds > 0)
                {
                    _cache[run.Key] = (job.ResultJson, DateTime.UtcNow);
                    foreach (var k in _cache.Where(c => (DateTime.UtcNow - c.Value.At).TotalSeconds > CacheSeconds * 5).Select(c => c.Key).ToList()) _cache.Remove(k);
                }
            }
            // the same request from other people gets the same answer
            foreach (var f in followers)
            {
                if (f.Finished) continue;
                f.Finished = true;
                f.Job.ResultJson = job.ResultJson;
                f.St.State = st.State;
                f.St.Message = st.State == "done" ? "Loaded (shared with another user's request)." : st.Message;
                f.St.FinishedAt = DateTime.UtcNow;
                lock (_gate) _reads.Remove(f);
                try { await _notifier.ReportAsync(f.Job, f.St); } catch { }
            }
        }
    }

    protected override async Task ExecuteAsync(CancellationToken stop)
    {
        _stop = stop;
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
                Note(job, $"Started {job.ApplicationNo} ({job.UserName}).");
                try { File.WriteAllText(FileFor(job), JsonSerializer.Serialize(job, Json)); } catch { /* the job still runs */ }
            }
            var acct = AcctLock(job);
            try { await acct.WaitAsync(stop); }
            catch (OperationCanceledException) { lock (_gate) { _running = null; } break; }
            bool keepGoing;
            try { keepGoing = await ExecuteJobAsync(job, st, stop); }
            finally { acct.Release(); }
            if (!keepGoing) break;
        }
    }

    /// <summary>runs one job to its end and tells the user; false when the program is stopping (a submission stays on disk and comes back)</summary>
    private async Task<bool> ExecuteJobAsync(JobRequest job, JobStatus st, CancellationToken stop, ReadRun? run = null)
    {
        var read = IsRead(job.Mode);
        _ = _notifier.ProgressAsync(job, st);                // the user is told it has started

        try
        {
            await _runner.RunAsync(job, msg =>
            {
                st.Message = msg;
                st.ApplicationNo = job.ApplicationNo;
                Note(job, msg);
                _log.LogInformation("[{Job}] {App} ({User}): {Msg}", job.JobUid.Length > 8 ? job.JobUid[..8] : job.JobUid, job.ApplicationNo, job.UserName, msg);
            }, stop);
            st.ApplicationNo = job.ApplicationNo;
            st.State = "done";
        }
        catch (OperationCanceledException) when (_stop.IsCancellationRequested)
        {
            lock (_gate) { if (read) _readers.Remove(job); else _running = null; }
            return false;
        }
        catch (SignInRequiredException e)
        {
            st.State = "signin-required";
            st.Message = e.Message;
        }
        catch (Exception e)
        {
            st.State = "failed";
            st.Message = e is OperationCanceledException ? (run is { Superseded: true } ? "Replaced by a newer request." : "InterLink took too long; the report was not submitted.") : e.Message;
            _log.LogError(e, "Job {Job} failed", job.JobUid);
        }
        st.FinishedAt = DateTime.UtcNow;
        if (read && job.Mode != "vf_pdf" && !(run is { Superseded: true }))
        {
            lock (_gate)
            {
                if (st.State == "done") _readFails = 0;
                else if (st.State == "failed" && ++_readFails >= 3) { _readFails = 0; _pauseUntil = DateTime.UtcNow.AddSeconds(60); Note(job, "Three loads in a row failed: new loads pause for 60 s."); }
            }
        }
        lock (_gate)
        {
            if (read) _readers.Remove(job); else _running = null;
            if (!read) { try { File.Delete(FileFor(job)); } catch { /* left over file: removed at the next start */ } }
        }
        _log.LogInformation("Job {Job} ({App}, {User}) {State}: {Msg}", job.JobUid, job.ApplicationNo, job.UserName, st.State, st.Message);
        WriteHistory(job, st);
        Note(job, st.State == "done" ? (read ? $"Finished {job.ApplicationNo}." : $"Finished {job.ApplicationNo}: submitted.") : $"Stopped {job.ApplicationNo}: {st.Message}");
        if (!read) lock (_gate) { _step = ""; }
        await _notifier.ReportAsync(job, st);                // the user is told how it ended

        lock (_gate)
        {
            // keep the last 300 results only
            if (_status.Count > 300)
                foreach (var old in _status.Values.Where(x => x.FinishedAt != null).OrderBy(x => x.FinishedAt).Take(_status.Count - 300).ToList())
                    _status.Remove(old.JobUid);
        }
        return true;
    }
}
