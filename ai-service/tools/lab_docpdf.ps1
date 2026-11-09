# Word documents of a Laboratory Documents folder -> PDF (so NEXUS-L can show them exactly as they look in Word).
#   powershell -File lab_docpdf.ps1 -Root "\\server\share\#Folder" -Out "E:\NexusAI\vfm\conv110"
# Writes <Out>\<n>.pdf and <Out>\index.json = { "relative/path.docx": "<n>.pdf" }. The documents are opened read-only and never saved.
param([Parameter(Mandatory = $true)][string]$Root, [Parameter(Mandatory = $true)][string]$Out)
$ErrorActionPreference = 'Continue'
New-Item -ItemType Directory -Force -Path $Out | Out-Null
$rootFull = (Resolve-Path -LiteralPath $Root).ProviderPath.TrimEnd('\')
$files = Get-ChildItem -LiteralPath $rootFull -Recurse -File | Where-Object { $_.Extension -match '^\.(docx|doc)$' -and -not $_.Name.StartsWith('~$') }
if (-not $files) { Write-Output '0 documents'; '{}' | Out-File -Encoding utf8 (Join-Path $Out 'index.json'); exit 0 }
$word = New-Object -ComObject Word.Application
$word.Visible = $false
$word.DisplayAlerts = 0
$idx = [ordered]@{}
$n = 0; $bad = 0
foreach ($f in $files) {
    $n++
    $rel = $f.FullName.Substring($rootFull.Length).TrimStart('\') -replace '\\', '/'
    $dst = Join-Path $Out ("$n.pdf")
    try {
        $doc = $word.Documents.Open($f.FullName, $false, $true, $false, '', '', $true)   # read only, no conversion dialog
        $doc.SaveAs2($dst, 17)                                                          # 17 = PDF
        $doc.Close($false)
        if (Test-Path -LiteralPath $dst) { $idx[$rel] = "$n.pdf" }
    } catch {
        $bad++
        Write-Output ("no PDF: " + $rel + " - " + $_.Exception.Message.Split("`n")[0])
        try { $doc.Close($false) } catch {}
    }
}
$word.Quit()
[System.Runtime.InteropServices.Marshal]::ReleaseComObject($word) | Out-Null
($idx | ConvertTo-Json) | Out-File -Encoding utf8 (Join-Path $Out 'index.json')
Write-Output ("{0} converted, {1} failed" -f $idx.Count, $bad)
