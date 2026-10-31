# Starts the three local processes of the assistant stack, each in its own window. Everything listens on 127.0.0.1 only.
#   1. PostgreSQL (audit store)  port 5440   data: C:\NexusAI\pgdata
#   2. Phoenix (traces, evals)   port 6006   data: C:\NexusAI\phoenix        http://127.0.0.1:6006
#   3. The assistant service     port 8765   http://127.0.0.1:8765/health
# Run it from a PowerShell window you keep open (or add the three commands to a Windows startup task yourself).
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
if (-not (Get-NetTCPConnection -State Listen -LocalPort 8765 -ErrorAction SilentlyContinue)) {
    $env:HF_HUB_OFFLINE = '1'                        # the embedding model is already on disk: nothing is downloaded or sent
    Start-Process $py -ArgumentList '-m', 'uvicorn', 'app.main:app', '--host', '127.0.0.1', '--port', '8765' -WorkingDirectory $app -WindowStyle Minimized
}
Write-Host 'Started. Check http://127.0.0.1:8765/health (the first start indexes the answers and takes about a minute).'
