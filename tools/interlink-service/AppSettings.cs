using System.Net.NetworkInformation;
using System.Net.Sockets;
using System.Security.Cryptography;
using System.Text.Json;
using System.Text.Json.Nodes;
using Microsoft.Win32;

namespace NexusInterlinkService;

/// <summary>
/// The settings of this computer, kept in appsettings.Local.json next to the program (the Settings window edits it): the address the
/// service listens on (the IP that receives NEXUS-L's payload), the port, the shared key, NEXUS-L's address and whether the browser is
/// invisible. Saved once, used on every start.
/// </summary>
public sealed class AppSettings
{
    public string ListenIp { get; set; } = "0.0.0.0";
    public int Port { get; set; } = 5077;
    public string ApiKey { get; set; } = "";
    public string NexusBaseUrl { get; set; } = "http://192.168.32.43/NEXUS-L/";
    public bool Headless { get; set; } = true;

    public static string FilePath => Path.Combine(AppContext.BaseDirectory, "appsettings.Local.json");

    public string Url => $"http://{ListenIp}:{Port}";

    /// <summary>the address NEXUS-L should call: the listening IP, or this computer's own address when it listens on all of them</summary>
    public string AddressForNexus()
    {
        var ip = ListenIp is "0.0.0.0" or "" ? LocalIPv4().FirstOrDefault() ?? "127.0.0.1" : ListenIp;
        return $"http://{ip}:{Port}";
    }

    public static AppSettings Load()
    {
        var s = new AppSettings();
        try
        {
            if (File.Exists(FilePath) && JsonNode.Parse(File.ReadAllText(FilePath)) is JsonObject o)
            {
                var urls = (string?)o["Urls"];
                if (!string.IsNullOrWhiteSpace(urls) && Uri.TryCreate(urls.Split(';')[0].Replace("+", "0.0.0.0").Replace("*", "0.0.0.0"), UriKind.Absolute, out var u))
                {
                    s.ListenIp = u.Host;
                    s.Port = u.Port;
                }
                s.ApiKey = (string?)o["Service"]?["ApiKey"] ?? s.ApiKey;
                s.Headless = (bool?)o["Service"]?["Headless"] ?? s.Headless;
                s.NexusBaseUrl = (string?)o["Nexus"]?["BaseUrl"] ?? s.NexusBaseUrl;
            }
        }
        catch { /* a damaged file: the defaults are shown and a Save repairs it */ }
        return s;
    }

    public void Save()
    {
        JsonObject o;
        try { o = File.Exists(FilePath) ? (JsonNode.Parse(File.ReadAllText(FilePath)) as JsonObject ?? new JsonObject()) : new JsonObject(); }
        catch { o = new JsonObject(); }
        o["Urls"] = Url;
        var svc = o["Service"] as JsonObject ?? new JsonObject();
        svc["ApiKey"] = ApiKey;
        svc["Headless"] = Headless;
        o["Service"] = svc;
        var nexus = o["Nexus"] as JsonObject ?? new JsonObject();
        nexus["BaseUrl"] = NexusBaseUrl;
        o["Nexus"] = nexus;
        File.WriteAllText(FilePath, o.ToJsonString(new JsonSerializerOptions { WriteIndented = true }));
    }

    public static string NewKey() => Convert.ToHexString(RandomNumberGenerator.GetBytes(24)).ToLowerInvariant();

    public static List<string> LocalIPv4() =>
        NetworkInterface.GetAllNetworkInterfaces()
            .Where(n => n.OperationalStatus == OperationalStatus.Up && n.NetworkInterfaceType != NetworkInterfaceType.Loopback)
            .SelectMany(n => n.GetIPProperties().UnicastAddresses)
            .Where(a => a.Address.AddressFamily == AddressFamily.InterNetwork)
            .Select(a => a.Address.ToString())
            .Distinct()
            .OrderBy(ip => ip.StartsWith("192.168.") ? 0 : ip.StartsWith("10.") ? 1 : ip.StartsWith("172.") ? 2 : 3)   // the office network first, VPN addresses (100.x) last
            .ToList();

    private const string RunKey = @"Software\Microsoft\Windows\CurrentVersion\Run";

    public static bool StartsWithWindows
    {
        get
        {
            using var k = Registry.CurrentUser.OpenSubKey(RunKey);
            return k?.GetValue("NexusInterlinkService") != null;
        }
        set
        {
            using var k = Registry.CurrentUser.OpenSubKey(RunKey, true);
            if (k == null) return;
            if (value) k.SetValue("NexusInterlinkService", "\"" + Environment.ProcessPath + "\"");
            else k.DeleteValue("NexusInterlinkService", false);
        }
    }
}
