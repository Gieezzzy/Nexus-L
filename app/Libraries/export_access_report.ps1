# Prints one record's official Access test report (the same report the lab prints from Access) to a PDF.
#   -Database  Access front-end holding the reports (a private copy, see DtrReportPdf::frontEnd)
#   -Report    report name, e.g. "Hardware Test Report Form"
#   -Where     filter, e.g. "ID = 555"
param(
    [Parameter(Mandatory = $true)][string]$Database,
    [Parameter(Mandatory = $true)][string]$Report,
    [Parameter(Mandatory = $true)][string]$Where,
    [Parameter(Mandatory = $true)][string]$OutPdf
)
$access = $null
try {
    $access = New-Object -ComObject 'Access.Application'
    $access.Visible = $false
    $access.AutomationSecurity = 1   # report code (formatting) runs as it does in Access
    $access.OpenCurrentDatabase($Database, $false)
    $access.DoCmd.OpenReport($Report, 2, $null, $Where, 1)   # preview, hidden
    $access.DoCmd.OutputTo(3, $Report, 'PDF Format (*.pdf)', $OutPdf, $false)
    $access.DoCmd.Close(3, $Report, 2)
    Write-Output "OK $OutPdf"
} catch {
    Write-Output ("ERR " + $_.Exception.Message)
    exit 1
} finally {
    if ($access -ne $null) {
        try { $access.CloseCurrentDatabase() } catch {}
        $access.Quit()
        [System.Runtime.InteropServices.Marshal]::ReleaseComObject($access) | Out-Null
    }
}
