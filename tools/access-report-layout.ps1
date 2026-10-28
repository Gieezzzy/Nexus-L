# One-time extraction of the lab's Access report designs (controls, positions, fonts, bound fields) to JSON, so NEXUS-L can
# print the same reports from dbnexus without Access.   -Database <front-end copy>  -OutDir <folder>  [-Report <name>]
param(
    [Parameter(Mandatory = $true)][string]$Database,
    [Parameter(Mandatory = $true)][string]$OutDir,
    [string]$Report = ''
)
$names = @('Hardware Test Report Form', 'Zipper Test Report Form', 'VH Test Report Form', 'TH Test Report Form', 'Lace Test Report Form',
    'WST Test Report Form', 'SKL/FML Test Report Form', 'FM Test Report Form', 'Fabric Test Report Form - Ink', 'Fabric Test Report Form - (w/ ASW)',
    'WL Label Test Report Form', 'Leather Test Report Form', 'Box Test Report', 'Insole Test Report Form', 'Outsole Test Report', 'Foxing Tape and Heel Logo')
if ($Report -ne '') { $names = @($Report) }
New-Item -ItemType Directory -Force -Path $OutDir | Out-Null
$typeName = @{ 100 = 'label'; 109 = 'text'; 110 = 'listbox'; 111 = 'combo'; 112 = 'subreport'; 101 = 'rect'; 20 = 'line'; 103 = 'image'; 104 = 'image'; 114 = 'pagebreak'; 124 = 'tab'; 122 = 'button'; 106 = 'check' }

function Get-Ctl($c) {
    $o = [ordered]@{ name = $c.Name; type = $typeName[[int]$c.ControlType]; left = [int]$c.Left; top = [int]$c.Top; width = [int]$c.Width; height = [int]$c.Height; visible = [bool]$c.Visible }
    foreach ($p in 'Caption', 'ControlSource', 'FontName', 'FontSize', 'FontWeight', 'FontItalic', 'FontUnderline', 'ForeColor', 'BackColor', 'BackStyle', 'BorderStyle', 'BorderColor', 'BorderWidth', 'TextAlign', 'SourceObject', 'CanGrow', 'Format', 'LinkMasterFields', 'LinkChildFields', 'Name') {
        try { $v = $c.$p; if ($null -ne $v -and "$v" -ne '') { $o[$p.Substring(0, 1).ToLower() + $p.Substring(1)] = $v } } catch {}
    }
    if ($c.ControlType -eq 20) { try { $o['lineSlant'] = [bool]$c.LineSlant } catch {} }
    return $o
}

function Get-Report($acc, $name, $isSub) {
    $acc.DoCmd.OpenReport($name, 5)                       # design view, hidden
    $rpt = $acc.Reports.Item($name)
    $pr = $rpt.Printer
    $out = [ordered]@{ name = $name; recordSource = $rpt.RecordSource; width = [int]$rpt.Width; margins = @([int]$pr.LeftMargin, [int]$pr.TopMargin, [int]$pr.RightMargin, [int]$pr.BottomMargin); paper = [int]$pr.PaperSize; orientation = [int]$pr.Orientation; sections = @() }
    foreach ($i in 0..4) {                                # detail, report header/footer, page header/footer
        try { $s = $rpt.Section($i) } catch { continue }
        $ctls = @()
        foreach ($c in $s.Controls) {
            $d = Get-Ctl $c
            if ($d.type -eq 'subreport' -and $d.sourceObject) {
                $so = ($d.sourceObject -replace '^Report\.', '')
                if (-not $isSub) { try { $d['report'] = Get-Report $acc $so $true } catch { $d['reportError'] = $_.Exception.Message } }
            }
            $ctls += , $d
        }
        $out.sections += , ([ordered]@{ index = $i; height = [int]$s.Height; visible = [bool]$s.Visible; backColor = $s.BackColor; controls = $ctls })
    }
    $acc.DoCmd.Close(3, $name, 2)
    return $out
}

$access = $null
try {
    $access = New-Object -ComObject 'Access.Application'
    $access.Visible = $false
    $access.AutomationSecurity = 1
    $access.OpenCurrentDatabase($Database, $false)
    foreach ($n in $names) {
        try {
            $j = Get-Report $access $n $false | ConvertTo-Json -Depth 12 -Compress
            [IO.File]::WriteAllText((Join-Path $OutDir (($n -replace '[^A-Za-z0-9]+', '_') + '.json')), $j, (New-Object Text.UTF8Encoding($false)))
            Write-Output "OK $n"
        } catch { Write-Output ("ERR $n : " + $_.Exception.Message) }
    }
} finally {
    if ($access -ne $null) { try { $access.CloseCurrentDatabase() } catch {}; $access.Quit(); [Runtime.InteropServices.Marshal]::ReleaseComObject($access) | Out-Null }
}
