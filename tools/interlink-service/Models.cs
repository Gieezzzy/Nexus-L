using System.Text.Json.Nodes;

namespace NexusInterlinkService;

/// <summary>One report to submit. The payload is the one NEXUS-L prepares (the same fields the browser extension used to get).</summary>
public sealed class JobRequest
{
    public string JobUid { get; set; } = "";
    public string ApplicationNo { get; set; } = "";
    public string LabRefNo { get; set; } = "";
    public string ReportNo { get; set; } = "";
    /// <summary>"add" = a new report, "edit" = the current report (InterLink's Add / Edit menu)</summary>
    public string Mode { get; set; } = "edit";
    public string UserName { get; set; } = "";
    /// <summary>NEXUS-L's own approval check, asked again right before Save: no approval, no Save</summary>
    public string VerifyUrl { get; set; } = "";
    public string CallbackUrl { get; set; } = "";
    /// <summary>the whole job as the page scripts expect it: controls, tables, pdf {name, b64}, mode ...</summary>
    public JsonObject Job { get; set; } = new();

    /// <summary>arrival number: the queue is first in, first out by this number (kept on disk, so a restart keeps the order)</summary>
    public long Seq { get; set; }
    public DateTime ReceivedAt { get; set; } = DateTime.UtcNow;
    /// <summary>how many times a start was made (a start that never ended, because the program stopped, is tried once more)</summary>
    public int Attempts { get; set; }
}

public sealed class JobStatus
{
    public string JobUid { get; set; } = "";
    public string ApplicationNo { get; set; } = "";
    public string LabRefNo { get; set; } = "";
    public string ReportNo { get; set; } = "";
    public string UserName { get; set; } = "";
    /// <summary>queued | running | done | failed | signin-required</summary>
    public string State { get; set; } = "queued";
    public string Message { get; set; } = "Waiting in the queue.";
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime? StartedAt { get; set; }
    public DateTime? FinishedAt { get; set; }
    /// <summary>how many reports are ahead of this one (the one being done now included); 0 = it is the next one / being done</summary>
    public int Position { get; set; }
}

/// <summary>one line of the job list (data/history.jsonl): what was submitted, for whom, and how it ended</summary>
public sealed class HistoryLine
{
    public string JobUid { get; set; } = "";
    public string ApplicationNo { get; set; } = "";
    public string LabRefNo { get; set; } = "";
    public string ReportNo { get; set; } = "";
    public string User { get; set; } = "";
    public string Mode { get; set; } = "";
    public string State { get; set; } = "";
    public string Message { get; set; } = "";
    public DateTime ReceivedAt { get; set; }
    public DateTime? StartedAt { get; set; }
    public DateTime? FinishedAt { get; set; }
}

/// <summary>one thing the service did or is doing (shown in the Activity window)</summary>
public sealed class ActivityEntry
{
    public DateTime Time { get; set; } = DateTime.Now;
    public string JobUid { get; set; } = "";
    public string ApplicationNo { get; set; } = "";
    public string User { get; set; } = "";
    public string Text { get; set; } = "";
}
