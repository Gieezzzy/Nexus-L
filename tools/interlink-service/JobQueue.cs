using System.Collections.Concurrent;
using System.Threading.Channels;

namespace NexusInterlinkService;

/// <summary>Jobs wait here and are done one at a time (one InterLink session, nothing runs over another).</summary>
public sealed class JobQueue : BackgroundService
{
    private readonly Channel<JobRequest> _channel = Channel.CreateUnbounded<JobRequest>();
    private readonly ConcurrentDictionary<string, JobStatus> _status = new();
    private readonly InterlinkRunner _runner;
    private readonly Notifier _notifier;
    private readonly ILogger<JobQueue> _log;

    public JobQueue(InterlinkRunner runner, Notifier notifier, ILogger<JobQueue> log)
    {
        _runner = runner;
        _notifier = notifier;
        _log = log;
    }

    public int Waiting => _channel.Reader.Count;

    public JobStatus Enqueue(JobRequest job)
    {
        var st = new JobStatus { JobUid = job.JobUid, ApplicationNo = job.ApplicationNo, LabRefNo = job.LabRefNo, ReportNo = job.ReportNo };
        _status[job.JobUid] = st;
        _channel.Writer.TryWrite(job);
        return st;
    }

    public JobStatus? Get(string uid) => _status.TryGetValue(uid, out var s) ? s : null;

    protected override async Task ExecuteAsync(CancellationToken stop)
    {
        await foreach (var job in _channel.Reader.ReadAllAsync(stop))
        {
            var st = _status[job.JobUid];
            st.State = "running";
            st.Message = "Working in InterLink…";
            try
            {
                await _runner.RunAsync(job, (msg) => st.Message = msg, stop);
                st.State = "done";
            }
            catch (SignInRequiredException e)
            {
                st.State = "signin-required";
                st.Message = e.Message;
            }
            catch (Exception e)
            {
                st.State = "failed";
                st.Message = e.Message;
                _log.LogError(e, "Job {Job} failed", job.JobUid);
            }
            st.FinishedAt = DateTime.UtcNow;
            _log.LogInformation("Job {Job} ({App}) {State}: {Msg}", job.JobUid, job.ApplicationNo, st.State, st.Message);
            await _notifier.ReportAsync(job, st);
            // keep the last 200 results only
            if (_status.Count > 200)
                foreach (var old in _status.Values.Where(x => x.FinishedAt != null).OrderBy(x => x.FinishedAt).Take(_status.Count - 200))
                    _status.TryRemove(old.JobUid, out _);
        }
    }
}
