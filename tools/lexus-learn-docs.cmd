@echo off
rem Lexus: re-reads the "#" folders of the Lab Documents share (new and changed files only) and indexes the passages into the AI brain.
rem Run it by hand, or as a nightly Windows scheduled task, for example:
rem   schtasks /Create /TN "NEXUS-L Lexus Learn Documents" /SC DAILY /ST 02:00 /TR "D:\html\NEXUS-L\tools\lexus-learn-docs.cmd"
cd /d "%~dp0..\ai-service"
"C:\NexusAI\venv\Scripts\python.exe" labdocs_extract.py >> "E:\NexusAI\labdocs_extract.log" 2>&1
curl -s -X POST -H "X-AI-Key: uqirpkfl7m34yz65s1wghed0t2xnvb9o8jca" http://127.0.0.1:8765/admin/labdocs/index >> "E:\NexusAI\labdocs_extract.log"
echo.>> "E:\NexusAI\labdocs_extract.log"
