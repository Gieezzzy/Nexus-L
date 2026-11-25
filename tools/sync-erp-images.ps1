#Requires -Version 5.1
<#
.SYNOPSIS
    Syncs ERP shoe images from \\192.168.32.43\ERP Image to the local web-server cache.

.DESCRIPTION
    Apache (LocalSystem) cannot access network shares directly.
    This script copies every *.JPG / *.PNG from the share into
    writable\cache\erp_images\ so PHP can serve them without credentials.

    Run once manually after changing anything, or schedule it daily via Task Scheduler.

.EXAMPLE
    # Run manually:
    powershell -ExecutionPolicy Bypass -File "D:\xampp\htdocs\NEXUS-L\tools\sync-erp-images.ps1"

    # Register as a daily Task Scheduler job (run as DTP-ADMIN):
    # - Program : powershell.exe
    # - Arguments: -ExecutionPolicy Bypass -NonInteractive -File "D:\xampp\htdocs\NEXUS-L\tools\sync-erp-images.ps1"
    # - Trigger  : Daily at logon / every 4 hours
#>

$source = "\\192.168.32.43\ERP Image"
$dest   = "D:\xampp\htdocs\NEXUS-L\writable\cache\erp_images"

if (-not (Test-Path $source)) {
    Write-Error "Cannot reach share: $source"
    exit 1
}

if (-not (Test-Path $dest)) {
    New-Item -ItemType Directory -Path $dest -Force | Out-Null
}

$copied  = 0
$skipped = 0
$errors  = 0

Get-ChildItem -Path $source -File -Include "*.JPG","*.jpg","*.JPEG","*.jpeg","*.PNG","*.png" | ForEach-Object {
    $target = Join-Path $dest $_.Name
    # Only copy if the destination is missing or older
    if (-not (Test-Path $target) -or ($_.LastWriteTime -gt (Get-Item $target).LastWriteTime)) {
        try {
            Copy-Item -Path $_.FullName -Destination $target -Force
            $copied++
        } catch {
            Write-Warning "Failed to copy $($_.Name): $_"
            $errors++
        }
    } else {
        $skipped++
    }
}

Write-Host "ERP image sync complete: $copied copied, $skipped up-to-date, $errors errors."
