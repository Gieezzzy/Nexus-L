# NEXUS-L InterLink background service (C#)

Submits approved NEXUS-L reports to InterLink **without opening anything on the user's computer**. The user presses *Send* in NEXUS-L,
NEXUS-L prepares the payload and the signed PDF and hands them to this service; the service does *Add* / *Edit* in InterLink with an
invisible browser (Playwright, headless Chromium), and NEXUS-L tells the user by a toast (wherever they are in NEXUS-L) and a Synology
message: `Labreference: … / ReportNO: … / submitted to InterLink`.

```
browser (NEXUS-L)  ->  NEXUS-L (PHP)  ->  this service (C#, invisible browser)  ->  InterLink
        toast <------  callback  <--------------------- result
                       Synology message
```

* Only a report approved and signed by a Manager / Supervisor is sent. NEXUS-L checks it when it builds the job, and the service asks
  NEXUS-L again right before it presses Save.
* One job at a time (one InterLink session). Jobs wait in a queue.
* The page logic is the same code as the browser extension: `Scripts/interlink-core.js` is cut out of
  `tools/interlink-sender/background.js` by `node tools/build/build-service-scripts.js` (run it again after changing the extension's
  page functions, then rebuild the service).
* The browser extension keeps working as a fallback when the service is not set up (or does not answer).

## Sign-in to InterLink (shared service account)

InterLink uses Microsoft sign-in (possibly with MFA), which an invisible browser cannot pass. So the service account signs in **once**,
in a visible window, and the service keeps that session:

```
NexusInterlinkService --signin
```

The session is saved to `data/interlink-session.json` (no password is stored) and refreshed after every job. If InterLink ever asks for a
sign-in again, the job fails with *signin-required* (NEXUS-L tells the submitter on Synology) and `--signin` is run again.
Everything submitted goes in under this one account.

## Install (on the server that hosts NEXUS-L)

1. .NET 9 SDK (or runtime). `dotnet build -c Release` in this folder.
2. Browser for Playwright, once: `powershell -ExecutionPolicy Bypass -File bin\Release\net9.0\playwright.ps1 install chromium`
3. Settings: copy the key into **both** places (a long random secret, 32+ characters):
   * this folder, `appsettings.Local.json` (not in git): `{ "Service": { "ApiKey": "<key>" }, "Nexus": { "BaseUrl": "http://<server>/NEXUS-L/" } }`
   * NEXUS-L `.env`: `interlinkService.url = 'http://127.0.0.1:5077'` and `interlinkService.key = '<key>'`
4. `NexusInterlinkService --signin` (once, on a desktop session of that server).
5. Run it: `dotnet bin\Release\net9.0\NexusInterlinkService.dll`, or as a Windows service:
   `sc create NexusInterlink binPath= "D:\html\NEXUS-L\tools\interlink-service\bin\Release\net9.0\NexusInterlinkService.exe" start= auto`
   (the service uses the Windows account's profile for the browser, so the account that ran `--signin` should run the service).

## Endpoints (header `X-Service-Key`)

| | |
|---|---|
| `GET /health` | no key; `{ ok, waiting, signedIn }` |
| `POST /jobs` | NEXUS-L's job: `{ job_uid, application_no, lab_ref_no, report_no, mode: add|edit, verify_url, callback_url, job: { controls, tables, pdf } }` |
| `GET /jobs/{uid}` | state: queued, running, done, failed, signin-required |

NEXUS-L side: `InterlinkServiceController` (`interlink/service/status|submit|feed`, and the key-protected `callback` and `verify`),
table `tbl_interlink_service_jobs` (created on first use).
