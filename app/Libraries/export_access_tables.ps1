# Exports Access tables (local or linked) to UTF-8 NDJSON files for `php spark dtr:sync-access`.
# .NET OleDb is used because PHP's ODBC driver turns Chinese text into "???".
# Line 1 of each file: {"__cols":[{"name":..,"type":..}]}; then one JSON object per row.
param(
    [Parameter(Mandatory = $true)][string]$Database,
    [Parameter(Mandatory = $true)][string]$Tables,
    [Parameter(Mandatory = $true)][string]$OutDir
)
$ErrorActionPreference = 'Stop'
New-Item -ItemType Directory -Force $OutDir | Out-Null
$utf8 = New-Object System.Text.UTF8Encoding($false)
Add-Type -AssemblyName System.Web.Extensions
$json = New-Object System.Web.Script.Serialization.JavaScriptSerializer
$json.MaxJsonLength = [int]::MaxValue
$cn = New-Object System.Data.OleDb.OleDbConnection("Provider=Microsoft.ACE.OLEDB.12.0;Data Source=$Database;Mode=Read;Persist Security Info=False")
$cn.Open()
foreach ($t in ($Tables -split ',')) {
    $t = $t.Trim()
    if ($t -eq '') { continue }
    $file = Join-Path $OutDir (($t -replace '[^A-Za-z0-9_-]', '_') + '.ndjson')
    try {
        $cmd = New-Object System.Data.OleDb.OleDbCommand("SELECT * FROM [$t]", $cn)
        $rd = $cmd.ExecuteReader()
        $w = New-Object System.IO.StreamWriter($file, $false, $utf8)
        $cols = @()
        for ($i = 0; $i -lt $rd.FieldCount; $i++) { $cols += [ordered]@{ name = $rd.GetName($i); type = $rd.GetFieldType($i).Name } }
        $w.WriteLine($json.Serialize(@{ __cols = $cols }))
        $n = 0
        while ($rd.Read()) {
            $row = New-Object 'System.Collections.Generic.Dictionary[string,object]'
            for ($i = 0; $i -lt $rd.FieldCount; $i++) {
                $v = $rd.GetValue($i)
                if ($v -is [System.DBNull]) { $v = $null }
                elseif ($v -is [datetime]) { $v = $v.ToString('yyyy-MM-dd HH:mm:ss') }
                elseif ($v -is [bool]) { $v = [int]$v }
                elseif ($v -is [byte[]]) { $v = $null }
                elseif ($v -is [double] -or $v -is [single] -or $v -is [decimal]) { $v = [double]$v }
                $row[$cols[$i].name] = $v
            }
            $w.WriteLine($json.Serialize($row))
            $n++
        }
        $rd.Close(); $w.Close()
        Write-Output "OK`t$t`t$n"
    } catch {
        if ($w) { $w.Close() }
        Remove-Item -ErrorAction SilentlyContinue $file
        Write-Output "ERR`t$t`t$($_.Exception.Message)"
    }
}
$cn.Close()
