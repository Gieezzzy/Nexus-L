param (
    [int]$BondingTestID = 1099,
    [string]$Side = "Left",
    [string]$OutPdf = "C:\Users\AUDIT STAFF\.gemini\antigravity\brain\390ee33a-2dc4-4f7f-84a7-be943524987b\scratch\sample.pdf"
)

$dbPath = "D:\#Digital Test Report\BondingReportV2.accdb"
if (-not (Test-Path $dbPath)) {
    Write-Error "Database file not found: $dbPath"
    exit 1
}

$rptName = "Report - " + $Side.ToUpper()

$access = $null
try {
    $access = New-Object -ComObject "Access.Application"
    $access.Visible = $false
    $access.OpenCurrentDatabase($dbPath, $false)
    $whereCond = "BondingTestID = " + $BondingTestID
    $access.DoCmd.OpenReport($rptName, 2, $null, $whereCond, 1) # 2 = acViewPreview, 1 = acHidden
    $access.DoCmd.OutputTo(3, $rptName, "PDF Format (*.pdf)", $OutPdf, $false)
    $access.DoCmd.Close(3, $rptName, 2)
    Write-Output "Successfully exported $rptName for ID $BondingTestID to $OutPdf"
} catch {
    Write-Error $_.Exception.Message
    exit 1
} finally {
    if ($access -ne $null) {
        $access.Quit()
        [System.Runtime.InteropServices.Marshal]::ReleaseComObject($access) | Out-Null
    }
}
