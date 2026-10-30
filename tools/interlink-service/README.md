# NEXUS-L InterLink background service (C#)

Submits approved NEXUS-L reports to InterLink **without opening anything on the user's computer**. The user presses *Send* in NEXUS-L,
NEXUS-L prepares the payload and the signed PDF and hands them to this program; it does *Add* / *Edit* in InterLink with an
invisible browser (Playwright, headless Chromium), and NEXUS-L tells the user by a toast (wherever they are in NEXUS-L) and a Synology
message: `Labreference: ... / ReportNO: ... / submitted to InterLink`.

```
browser (NEXUS-L)  ->  NEXUS-L (PHP)  ->  this program (invisible browser)  ->  InterLink
        toast <------  callback  <--------------------- result
                       Synology message
```

* Only a report approved and signed by a Manager / Supervisor is sent. NEXUS-L checks it when it builds the job, and the program asks
  NEXUS-L again right before it presses Save.
* One job at a time (one InterLink session). Jobs wait in a queue.
* The page logic is the same code as the browser extension: `Scripts/interlink-core.js` is cut out of
  `tools/interlink-sender/background.js` by `node tools/build/build-service-scripts.js` (run it again after changing the extension's
  page functions, then rebuild).
* The browser extension keeps working as a fallback when this program is not set up (or does not answer).

## The program: `D:\desktop\MaterialTest\NexusInterlinkService`

`NexusInterlinkService.exe` is a small tray program: an icon next to the clock, the service running behind it.
Double-click the icon (or right-click > **Settings...**) to open the properties:

| Setting | |
|---|---|
| **IP / Port** | the address that receives NEXUS-L's payload (`0.0.0.0` = every address of this computer, or pick one of its IPs) |
| **Shared key** | a secret, 16+ characters (**New key** makes one); the same key goes into NEXUS-L |
| **NEXUS-L address** | where results and approval checks go, e.g. `http://192.168.32.43/NEXUS-L/` |
| Invisible browser | untick it to watch the browser work, for testing |
| Start with Windows | the icon and the service start when this user logs in |

**Save and restart** keeps them in `appsettings.Local.json` next to the program: they are used on every start, nothing has to be entered
again. The window also shows the two lines to put in NEXUS-L's `.env` (**Copy the lines**), and **Check** tells whether the service runs
and whether the InterLink sign-in is saved. The log is `data\service.log` (tray menu > Open the log).

### First time on a computer
1. Needs the .NET 9 (Desktop) runtime. Run `Install-Browser.cmd` once (downloads the invisible Chromium).
2. Start `NexusInterlinkService.exe`. Set the IP and port, check the NEXUS-L address, **Save and restart**.
3. Copy the two lines into NEXUS-L's `.env` (`interlinkService.url` and `interlinkService.key`).
4. **Sign in to InterLink...** (or `Sign-in-to-InterLink.cmd`) once, with the shared service account. InterLink uses Microsoft sign-in
   (MFA included), which an invisible browser cannot pass, so it is done once in a visible window; the session is saved to
   `data\interlink-session.json` (no password is stored) and refreshed after every job. If InterLink asks again, jobs fail with
   "sign in again" (the submitter is told on Synology) and this step is repeated.
5. Optional: tick *Start with Windows*. Without a logged-in user: `NexusInterlinkService.exe --service` from a scheduled task or `sc create`.

Windows firewall: allow inbound TCP on the chosen port from the NEXUS-L server.
Everything submitted goes into InterLink under the one shared account.

### Building it
`dotnet publish -c Release -r win-x64 --self-contained false -o D:\desktop\MaterialTest\NexusInterlinkService` in this folder
(run `node tools/build/build-service-scripts.js` first if the extension's page functions changed). The publish does not touch
`appsettings.Local.json` or `data\`.

## Endpoints (header `X-Service-Key`)

| | |
|---|---|
| `GET /health` | no key; `{ ok, waiting, signedIn, version }` |
| `POST /jobs` | NEXUS-L's job: `{ job_uid, application_no, lab_ref_no, report_no, mode: add or edit, verify_url, callback_url, job: { controls, tables, pdf } }` |
| `GET /jobs/{uid}` | state: queued, running, done, failed, signin-required |

NEXUS-L side: `InterlinkServiceController` (`interlink/service/status|submit|feed`, and the key-protected `callback` and `verify`),
table `tbl_interlink_service_jobs` (created on first use).
