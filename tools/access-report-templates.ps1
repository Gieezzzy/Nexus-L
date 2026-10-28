# One-time: prints the BLANK form of each Access report (all values empty, borders / labels / logos kept) to a PDF, so NEXUS-L can
# fill the values in itself from dbnexus without Access.
# Method: in a PRIVATE COPY of the report front-end, each results table the reports read is renamed and replaced by a query of the
# same name that returns one row of empty fields, so every report prints as an empty form.
#   -Database  a PRIVATE COPY of the report front-end (never point this at the lab's own file: it renames tables in it)
#   -OutDir    folder for <report>.pdf
#   -Reports   report names;  -Tables  the results tables they read
param(
    [Parameter(Mandatory = $true)][string]$Database,
    [Parameter(Mandatory = $true)][string]$OutDir,
    [Parameter(Mandatory = $true)][string[]]$Reports,
    [Parameter(Mandatory = $true)][string[]]$Tables
)
New-Item -ItemType Directory -Force -Path $OutDir | Out-Null
$access = $null
try {
    $access = New-Object -ComObject 'Access.Application'
    $access.Visible = $false
    $access.AutomationSecurity = 1
    $access.OpenCurrentDatabase($Database, $false)
    $db = $access.CurrentDb()
    foreach ($t in $Tables) {
        $td = $db.TableDefs.Item($t)
        $cols = @(); foreach ($f in $td.Fields) { $cols += ("'' AS [" + $f.Name + ']') }
        $td.Name = $t + '_real'
        $db.TableDefs.Refresh()
        $db.CreateQueryDef($t, ('SELECT TOP 1 ' + ($cols -join ', ') + ' FROM [' + $t + '_real]')) | Out-Null
    }
    foreach ($n in $Reports) {
        try {
            $pdf = Join-Path $OutDir (($n -replace '[^A-Za-z0-9]+', '_') + '.pdf')
            if (Test-Path $pdf) { Remove-Item $pdf -Force }
            $access.DoCmd.OpenReport($n, 2, $null, $null, 1)                   # preview, hidden
            $access.DoCmd.OutputTo(3, $n, 'PDF Format (*.pdf)', $pdf, $false)
            $access.DoCmd.Close(3, $n, 2)
            Write-Output "OK $n"
        } catch { Write-Output ("ERR $n : " + $_.Exception.Message) }
    }
} finally {
    if ($access -ne $null) { try { $access.CloseCurrentDatabase() } catch {}; $access.Quit(); [Runtime.InteropServices.Marshal]::ReleaseComObject($access) | Out-Null }
}
