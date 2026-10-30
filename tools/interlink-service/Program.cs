using System.Security.Cryptography;
using System.Text;
using System.Text.Json.Nodes;
using NexusInterlinkService;

var builder = WebApplication.CreateBuilder(args);
builder.Host.UseWindowsService();
builder.Configuration.AddJsonFile("appsettings.Local.json", optional: true, reloadOnChange: false);   // the real key and URLs of this computer (not in git)
builder.WebHost.ConfigureKestrel(k => k.Limits.MaxRequestBodySize = 120 * 1024 * 1024);   // a signed PDF travels inside the job

if (args.Contains("--signin"))
    return await SignIn.RunAsync(builder.Configuration);

builder.Services.AddSingleton<Notifier>();
builder.Services.AddSingleton<InterlinkRunner>();
builder.Services.AddSingleton<JobQueue>();
builder.Services.AddHostedService(sp => sp.GetRequiredService<JobQueue>());

var app = builder.Build();
var apiKey = app.Configuration["Service:ApiKey"] ?? "";

bool KeyOk(HttpRequest r)
{
    var got = r.Headers["X-Service-Key"].ToString();
    return apiKey.Length >= 16 && !apiKey.StartsWith("CHANGE-ME")
        && CryptographicOperations.FixedTimeEquals(Encoding.UTF8.GetBytes(got), Encoding.UTF8.GetBytes(apiKey));
}

// health: no key, nothing private
app.MapGet("/health", (JobQueue q) => Results.Ok(new
{
    ok = true,
    waiting = q.Waiting,
    signedIn = File.Exists(InterlinkRunner.SessionFile(app.Configuration)),
    version = "1.0.0",
}));

// NEXUS-L hands over one report to submit
app.MapPost("/jobs", async (HttpRequest http, JobQueue q) =>
{
    if (!KeyOk(http)) return Results.Json(new { ok = false, error = "Wrong or missing service key." }, statusCode: 401);
    JsonNode? body;
    try { body = await JsonNode.ParseAsync(http.Body); }
    catch { return Results.BadRequest(new { ok = false, error = "The job is not valid JSON." }); }
    if (body is not JsonObject o || o["job"] is not JsonObject job) return Results.BadRequest(new { ok = false, error = "The job has no payload." });
    var req = new JobRequest
    {
        JobUid = (string?)o["job_uid"] ?? "",
        ApplicationNo = (string?)o["application_no"] ?? "",
        LabRefNo = (string?)o["lab_ref_no"] ?? "",
        ReportNo = (string?)o["report_no"] ?? "",
        Mode = (string?)o["mode"] == "add" ? "add" : "edit",
        UserName = (string?)o["user_name"] ?? "",
        VerifyUrl = (string?)o["verify_url"] ?? "",
        CallbackUrl = (string?)o["callback_url"] ?? "",
        Job = job,
    };
    if (req.JobUid.Length < 8 || req.ApplicationNo.Length < 3) return Results.BadRequest(new { ok = false, error = "job_uid and application_no are required." });
    var st = q.Enqueue(req);
    return Results.Ok(new { ok = true, job_uid = st.JobUid, state = st.State, waiting = q.Waiting });
});

app.MapGet("/jobs/{uid}", (string uid, HttpRequest http, JobQueue q) =>
{
    if (!KeyOk(http)) return Results.Json(new { ok = false, error = "Wrong or missing service key." }, statusCode: 401);
    var st = q.Get(uid);
    return st == null ? Results.NotFound(new { ok = false, error = "Unknown job." }) : Results.Ok(new { ok = true, job = st });
});

app.Run();
return 0;
