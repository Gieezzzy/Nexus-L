# Starts the three local processes of the assistant stack, each in its own window. Everything listens on 127.0.0.1 only.
#   1. PostgreSQL (audit store)  port 5440   data: C:\NexusAI\pgdata
#   2. Phoenix (traces, evals)   port 6006   data: C:\NexusAI\phoenix        http://127.0.0.1:6006
#   3. The assistant service     port 8765   http://127.0.0.1:8765/health
# Run it from a PowerShell window you keep open (or add the three commands to a Windows startup task yourself).
param([switch]$WithRasa)      # .\run-ai.ps1 -WithRasa   also starts the Rasa NLU server (port 5005)
$ErrorActionPreference = 'Stop'
$py  = 'C:\NexusAI\venv\Scripts\python.exe'
$pg  = 'C:\Program Files\PostgreSQL\16\bin\postgres.exe'
$app = Split-Path -Parent $MyInvocation.MyCommand.Path

if (-not (Get-NetTCPConnection -State Listen -LocalPort 5440 -ErrorAction SilentlyContinue)) {
    Start-Process $pg -ArgumentList '-D', 'C:\NexusAI\pgdata', '-p', '5440' -WindowStyle Minimized
    Start-Sleep 6
}
if (-not (Get-NetTCPConnection -State Listen -LocalPort 6006 -ErrorAction SilentlyContinue)) {
    $env:PHOENIX_WORKING_DIR = 'C:\NexusAI\phoenix'; $env:PHOENIX_HOST = '127.0.0.1'; $env:PHOENIX_PORT = '6006'
    Start-Process $py -ArgumentList '-m', 'phoenix.server.main', 'serve' -WindowStyle Minimized
}
if ($WithRasa -and -not (Get-NetTCPConnection -State Listen -LocalPort 5005 -ErrorAction SilentlyContinue)) {
    # Rasa needs about 1.6 GB of committed memory (TensorFlow). This PC has no page file and only ~2 GB of commit to spare, so Rasa is
    # opt-in: without it the service uses its built-in rule router automatically (AI_USE_RASA=true falls back when Rasa is not running).
    # Rasa 3.6 (NLU only) runs in its own Python 3.10 environment on E:; telemetry off, listens on 127.0.0.1 only
    $env:RASA_TELEMETRY_ENABLED = 'false'; $env:TMP = 'E:\NexusAI\tmp'; $env:TEMP = 'E:\NexusAI\tmp'
    $model = (Get-ChildItem 'E:\NexusAI\rasa-models' -Filter *.tar.gz | Sort-Object LastWriteTime -Descending | Select-Object -First 1).FullName
    Start-Process 'E:\NexusAI\rasa-venv\Scripts\rasa.exe' -ArgumentList 'run', '--enable-api', '-i', '127.0.0.1', '-p', '5005', '-m', $model -WorkingDirectory (Join-Path $app 'rasa') -WindowStyle Minimized
}
if (-not (Get-NetTCPConnection -State Listen -LocalPort 8765 -ErrorAction SilentlyContinue)) {
    $env:HF_HUB_OFFLINE = '1'                        # the embedding model is already on disk: nothing is downloaded or sent
    Start-Process $py -ArgumentList '-m', 'uvicorn', 'app.main:app', '--host', '127.0.0.1', '--port', '8765' -WorkingDirectory $app -WindowStyle Minimized
}
Write-Host 'Started. Check http://127.0.0.1:8765/health (the first start indexes the answers and takes about a minute).'
