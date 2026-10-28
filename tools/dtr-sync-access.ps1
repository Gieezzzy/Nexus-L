# Registers "NEXUS-L Access Sync": runs "php spark dtr:sync-access --log" every 15 minutes so dbnexus keeps up with the
# QC Lab Access databases (standards, tolerances, test results, master lists). Run once, as the Windows user that can
# open \\192.168.32.130\qc lab files (SYSTEM cannot reach that share):
#   powershell -ExecutionPolicy Bypass -File D:\html\NEXUS-L\tools\dtr-sync-access.ps1
# Runs while that user is signed in to this PC. Log: D:\html\NEXUS-L\writable\logs\dtr-sync-access.log
$php  = 'C:\xampp\php\php.exe'
$root = 'D:\html\NEXUS-L'
$name = 'NEXUS-L Access Sync'
$user = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name

# conhost --headless: no console window pops up on the user's screen while it runs
$action    = New-ScheduledTaskAction -Execute 'conhost.exe' -Argument "--headless `"$php`" spark dtr:sync-access --log" -WorkingDirectory $root
$trigger   = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 15)
$set       = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 14) -StartWhenAvailable
$principal = New-ScheduledTaskPrincipal -UserId $user -LogonType Interactive -RunLevel Limited
Register-ScheduledTask -TaskName $name -Action $action -Trigger $trigger -Settings $set -Principal $principal -Force | Out-Null
Write-Host "Registered '$name' (every 15 minutes, as $user). Remove with: Unregister-ScheduledTask -TaskName '$name' -Confirm:`$false"
