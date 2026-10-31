@echo off
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0ai-service\run-ai.ps1" %*
