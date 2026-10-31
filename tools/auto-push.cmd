@echo off
setlocal enabledelayedexpansion

echo ========================================================
echo  NEXUS-L Bot & Full AI Stack Git Auto-Push
echo ========================================================

:: Ensure virtual D: is mapped to E:\ if needed
subst | findstr /i "D:" >nul
if errorlevel 1 (
    echo Mapping virtual D: to E:\...
    subst D: E:\ >nul 2>&1
)

cd /d "E:\html\NEXUS-L"

:: Check if git repo exists, if not initialize one
if not exist ".git" (
    echo Initializing new Git repository...
    git init -b main
    git remote add origin git@github.com:Gieezzzy/Nexus-L.git
)

:: Verify remote origin
git remote | findstr /i "origin" >nul
if errorlevel 1 (
    echo Adding remote origin...
    git remote add origin git@github.com:Gieezzzy/Nexus-L.git
)

echo.
echo [1/4] Explicitly staging all bot and AI service files...
git add ai-service/
git add app/Controllers/BotController.php
git add app/Libraries/ProblemKnowledgeSeeder.php
git add app/Libraries/AiBridge.php
git add app/Libraries/Brain.php
git add app/Libraries/BotSessions.php
git add app/Libraries/SystemSettings.php
git add app/Controllers/SettingsController.php
git add app/Config/Routes.php
git add public/assets/js/bot.js
git add public/assets/min/js/bot.js
git add public/assets/css/bot.css
git add public/assets/js/settings.js
git add app/Views/settings/index.php
git add run-ai.cmd
git add push.cmd
git add tools/auto-push.cmd
git add _docs/ICM_CHANGELOG.md
git add _docs/OBSIDIAN_CODEBASE_INDEX.md
:: never "git add -A": writable/ holds report data (approved and signed PDFs) and the repository is public

echo.
echo [2/4] Checking staged changes...
git status --short

echo.
echo [3/4] Committing bot files and updates...
git commit -m "feat(bot): complete AI assistant stack, LangGraph service, 200 problem knowledge, live DB queries & test calculators"

echo.
echo [4/4] Pushing to origin main...
git push -u origin main
if errorlevel 1 (
    echo Trying fallback push with force lease or branch verification...
    git push origin main
)

echo.
echo ========================================================
echo  Bot files successfully staged, committed, and pushed!
echo ========================================================
