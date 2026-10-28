# Registers the NEXUS-L InterLink background worker: runs "php spark interlink:work" every minute, whether or not
# anyone is logged in or any page is open. Run once from an ELEVATED PowerShell:
#   powershell -ExecutionPolicy Bypass -File D:\html\NEXUS-L\tools\interlink-worker.ps1
$php   = 'C:\xampp\php\php.exe'
$root  = 'D:\html\NEXUS-L'
$name  = 'NEXUS-L InterLink Worker'

$action  = New-ScheduledTaskAction -Execute $php -Argument 'spark interlink:work' -WorkingDirectory $root
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1)
$set     = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 5) -StartWhenAvailable
Register-ScheduledTask -TaskName $name -Action $action -Trigger $trigger -Settings $set -User 'SYSTEM' -RunLevel Highest -Force | Out-Null
Write-Host "Registered '$name' (every minute). Remove with: Unregister-ScheduledTask -TaskName '$name' -Confirm:`$false"
