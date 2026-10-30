using System.Security.Cryptography;
using System.Text;
using System.Text.Json.Nodes;

namespace NexusInterlinkService;

/// <summary>Starts, stops and restarts the web service inside this program (so a changed address or key takes effect straight after Save).</summary>
public sealed class ServiceHost
{
    private WebApplication? _app;
    public string? LastError { get; private set; }
    public bool Running => _app != null;

    public WebApplication Build(string[] args)
    {
        var builder = WebApplication.CreateBuilder(new WebApplicationOptions { Args = args, ContentRootPath = AppContext.BaseDirectory });
        builder.Host.UseWindowsService();
        builder.Configuration.AddJsonFile(AppSettings.FilePath, optional: true, reloadOnChange: false);   // this computer's settings (the Settings window writes it)
        builder.WebHost.ConfigureKestrel(k => k.Limits.MaxRequestBodySize = 120 * 1024 * 1024);          // a signed PDF travels inside the job
        builder.Logging.ClearProviders();
        builder.Logging.AddProvider(new FileLoggerProvider(Path.Combine(InterlinkRunner.DataDir(builder.Configuration), "service.log")));
        builder.Services.AddSingleton<Notifier>();
        builder.Services.AddSingleton<InterlinkRunner>();
        builder.Services.AddSingleton<JobQueue>();
        builder.Services.AddHostedService(sp => sp.GetRequiredService<JobQueue>());
        var app = builder.Build();
        MapApi(app);
        return app;
    }

    /// <summary>runs until stopped (Windows service / --service)</summary>
    public async Task RunBlockingAsync(string[] args)
    {
        var app = Build(args);
        await app.RunAsync();
    }

    public async Task<bool> StartAsync(string[] args)
    {
        await StopAsync();
        try
        {
            var app = Build(args);
            await app.StartAsync();
            _app = app;
            LastError = null;
            return true;
        }
        catch (Exception e)
        {
            LastError = e.InnerException?.Message ?? e.Message;
            return false;
        }
    }

    public async Task StopAsync()
    {
        var app = _app;
        _app = null;
        if (app == null) return;
        try { await app.StopAsync(TimeSpan.FromSeconds(5)); await app.DisposeAsync(); } catch { /* already down */ }
    }

    private static void MapApi(WebApplication app)
    {
        var apiKey = app.Configuration["Service:ApiKey"] ?? "";

        bool KeyOk(HttpRequest r)
        {
            var got = r.Headers["X-Service-Key"].ToString();
            return apiKey.Length >= 16 && CryptographicOperations.FixedTimeEquals(Encoding.UTF8.GetBytes(got), Encoding.UTF8.GetBytes(apiKey));
        }

        // health: no key, nothing private
        app.MapGet("/health", (JobQueue q) => Results.Ok(new
        {
            ok = true,
            waiting = q.Waiting,
            signedIn = File.Exists(InterlinkRunner.SessionFile(app.Configuration)),
            version = typeof(ServiceHost).Assembly.GetName().Version?.ToString(3),
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
    }
}

/// <summary>a plain log file (data/service.log): the program has no console window</summary>
public sealed class FileLoggerProvider : ILoggerProvider
{
    private readonly string _path;
    private static readonly object Gate = new();
    public FileLoggerProvider(string path) { _path = path; }
    public ILogger CreateLogger(string categoryName) => new FileLogger(_path, categoryName);
    public void Dispose() { }

    private sealed class FileLogger : ILogger
    {
        private readonly string _path;
        private readonly string _cat;
        public FileLogger(string path, string cat) { _path = path; _cat = cat; }
        public IDisposable? BeginScope<TState>(TState state) where TState : notnull => null;
        public bool IsEnabled(LogLevel logLevel) => logLevel >= LogLevel.Information;
        public void Log<TState>(LogLevel logLevel, EventId eventId, TState state, Exception? exception, Func<TState, Exception?, string> formatter)
        {
            if (!IsEnabled(logLevel)) return;
            var line = $"{DateTime.Now:yyyy-MM-dd HH:mm:ss} {logLevel,-11} {_cat}: {formatter(state, exception)}{(exception != null ? " | " + exception.Message : "")}{Environment.NewLine}";
            lock (Gate)
            {
                try
                {
                    var f = new FileInfo(_path);
                    if (f.Exists && f.Length > 2_000_000) File.Move(_path, _path + ".old", true);
                    File.AppendAllText(_path, line);
                }
                catch { /* logging must never stop the service */ }
            }
        }
    }
}
