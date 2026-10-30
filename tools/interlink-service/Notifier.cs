using System.Net.Http.Json;
using System.Text.Json;

namespace NexusInterlinkService;

/// <summary>Talks back to NEXUS-L: the approval re-check before Save, and the result of each job (NEXUS-L then shows the toast and sends the Synology message).</summary>
public sealed class Notifier
{
    private readonly HttpClient _http = new() { Timeout = TimeSpan.FromSeconds(30) };
    private readonly string _key;
    private readonly ILogger<Notifier> _log;

    public Notifier(IConfiguration cfg, ILogger<Notifier> log)
    {
        _key = cfg["Service:ApiKey"] ?? "";
        _log = log;
    }

    /// <summary>true when NEXUS-L still holds an approved, signed report for this job (NEXUS-L's verify endpoint)</summary>
    public async Task<(bool Approved, string Problem)> VerifyAsync(string url)
    {
        if (string.IsNullOrWhiteSpace(url)) return (true, "");
        try
        {
            using var req = new HttpRequestMessage(HttpMethod.Get, url);
            req.Headers.Add("X-Service-Key", _key);
            using var res = await _http.SendAsync(req);
            var doc = JsonDocument.Parse(await res.Content.ReadAsStringAsync());
            var root = doc.RootElement;
            if (root.TryGetProperty("approved", out var a) && a.ValueKind == JsonValueKind.True) return (true, "");
            return (false, "the report is not approved (any more) in NEXUS-L.");
        }
        catch (Exception e)
        {
            return (false, "NEXUS-L could not be asked about the approval (" + e.Message + ").");
        }
    }

    /// <summary>"started": the user is told their report is now being submitted (one try; the final answer is the one that matters)</summary>
    public async Task ProgressAsync(JobRequest job, JobStatus st)
    {
        if (string.IsNullOrWhiteSpace(job.CallbackUrl)) return;
        try
        {
            using var req = new HttpRequestMessage(HttpMethod.Post, job.CallbackUrl)
            {
                Content = JsonContent.Create(new { job_uid = job.JobUid, ok = true, state = "running", message = st.Message, application_no = job.ApplicationNo, lab_ref_no = job.LabRefNo, report_no = job.ReportNo }),
            };
            req.Headers.Add("X-Service-Key", _key);
            using var res = await _http.SendAsync(req);
        }
        catch (Exception e)
        {
            _log.LogWarning("NEXUS-L progress call failed for {Job}: {Msg}", job.JobUid, e.Message);
        }
    }

    public async Task ReportAsync(JobRequest job, JobStatus st)
    {
        if (string.IsNullOrWhiteSpace(job.CallbackUrl)) return;
        for (var attempt = 1; attempt <= 3; attempt++)
        {
            try
            {
                using var req = new HttpRequestMessage(HttpMethod.Post, job.CallbackUrl)
                {
                    Content = JsonContent.Create(new
                    {
                        job_uid = job.JobUid,
                        ok = st.State == "done",
                        state = st.State,
                        message = st.Message,
                        application_no = job.ApplicationNo,
                        lab_ref_no = job.LabRefNo,
                        report_no = job.ReportNo,
                    }),
                };
                req.Headers.Add("X-Service-Key", _key);
                using var res = await _http.SendAsync(req);
                if (res.IsSuccessStatusCode) return;
                _log.LogWarning("NEXUS-L callback answered {Code} for {Job}", (int)res.StatusCode, job.JobUid);
            }
            catch (Exception e)
            {
                _log.LogWarning("NEXUS-L callback failed for {Job}: {Msg}", job.JobUid, e.Message);
            }
            await Task.Delay(TimeSpan.FromSeconds(5 * attempt));
        }
    }
}
