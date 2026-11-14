<?php

namespace App\Libraries;

/**
 * "CPSIA, RSL & KC TEST SUMMARY" workbook, made from the lab's own Excel file (templates/vf_summary/summary.xlsx) so it looks exactly like it:
 * same sheets, fonts, fills, borders, column widths, merged cells, date separators, year titles, frozen panes, hyperlinks and validation.
 *   - every row of the lab's file stays as it is;
 *   - a blank cell of such a row is filled when NEXUS-L knows the value now (a result, report no, dates that came later);
 *   - a request that is not in the file yet is added at the end of its sheet, in the same style as the rows above it:
 *     CPSIA under a date row (the day the request e-mail came), KC / RSL under their year title, GB under its year list.
 * The lab's file has no formulas: it is values, styles and merged cells only, so that is what is written.
 * To start from a newer copy of the lab's file, replace templates/vf_summary/summary.xlsx.
 */
class VfSummaryExport
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    /** per sheet: file, the row whose cell styles a new data row copies, the rows used for a new group, and which DB value goes in which column */
    private const SHEETS = [
        'cpsia' => ['file' => 'sheet1', 'last' => 'P', 'data' => 1194, 'sep' => 1235, 'prod' => 'B', 'mtl' => 'E',
            'cols' => ['B' => 'prod', 'C' => 'po', 'D' => 'style', 'E' => 'mtl', 'F' => 'status', 'G' => 'season', 'H' => 'color', 'I' => 'tr', 'J' => 'report_cp', 'K' => 'order',
                'L' => 'send:iso', 'M' => 'result', 'N' => 'issue:iso', 'O' => 'validity:iso', 'P' => 'crd:serial']],
        'kc' => ['file' => 'sheet2', 'last' => 'S', 'data' => 35, 'blank' => 31, 'sec' => 32, 'prod' => 'D', 'mtl' => 'B', 'pass' => 519, 'fail' => 538, 'resultCol' => 'K', 'title' => 'KC TEST ',
            'cols' => ['A' => 'style', 'B' => 'mtl', 'C' => 'color', 'D' => 'prod', 'E' => 'po', 'F' => 'tr', 'G' => 'status', 'H' => 'season', 'I' => 'notif:us', 'J' => 'send:us',
                'K' => 'result', 'L' => 'issue:us', 'M' => 'validity:us', 'N' => 'report', 'O' => 'crd:us', 'P' => 'fail', 'R' => 'rereport', 'S' => 'reresult']],
        'rsl' => ['file' => 'sheet3', 'last' => 'S', 'data' => 22, 'blank' => 17, 'sec' => 18, 'prod' => 'D', 'mtl' => 'B', 'pass' => 7, 'fail' => 540, 'resultCol' => 'K', 'title' => 'RSL TARGETED ',
            'cols' => ['A' => 'style', 'B' => 'mtl', 'C' => 'color', 'D' => 'prod', 'E' => 'po', 'F' => 'tr', 'G' => 'status', 'H' => 'season', 'I' => 'notif:us', 'J' => 'send:us',
                'K' => 'result', 'L' => 'issue:us', 'M' => 'validity:us', 'N' => 'crd:us', 'O' => 'report', 'P' => 'fail', 'R' => 'rereport', 'S' => 'reresult']],
        'gb' => ['file' => 'sheet4', 'last' => 'K', 'data' => 8, 'sec' => 6, 'head' => 7, 'prod' => null, 'mtl' => 'D', 'title' => 'DTP-GB Test Result List ',
            'cols' => ['A' => 'received:serial', 'B' => 'factory', 'C' => 'style', 'D' => 'mtl', 'E' => 'color_c', 'F' => 'tr', 'G' => 'crd:serial', 'H' => 'report', 'I' => 'issue:serial', 'J' => 'validity:serial', 'K' => 'result']],
    ];

    private array $sst = [];

    /** @return array{data:string,filename:string,counts:array<string,int>} */
    public static function build(): array
    {
        return (new self())->make();
    }

    private function make(): array
    {
        $tpl = APPPATH . 'Libraries/templates/vf_summary/summary.xlsx';
        $tmp = tempnam(sys_get_temp_dir(), 'vfs');
        copy($tpl, $tmp);
        $z = new \ZipArchive();
        $z->open($tmp);
        $this->loadSst((string) $z->getFromName('xl/sharedStrings.xml'));

        $groups = $this->requests();
        $counts = [];
        foreach (self::SHEETS as $key => $cfg) {
            $path = 'xl/worksheets/' . $cfg['file'] . '.xml';
            [$xml, $filled, $added, $lastRow] = $this->sheet((string) $z->getFromName($path), $key, $cfg, $groups[$key]);
            $z->addFromString($path, $xml);
            $counts[$key] = ['requests' => count($groups[$key]), 'filled_cells' => $filled, 'added_rows' => $added];
            if ($key === 'cpsia') {   // the hidden filter range of the first sheet grows with it
                $wb = (string) $z->getFromName('xl/workbook.xml');
                $wb = preg_replace("/('DTP總表Total table'!\\\$A\\\$2:\\\$P\\\$)\\d+/u", '${1}' . $lastRow, $wb);
                $z->addFromString('xl/workbook.xml', $wb);
            }
        }
        $z->deleteName('xl/calcChain.xml');
        $z->close();
        $data = (string) file_get_contents($tmp);
        @unlink($tmp);

        return ['data' => $data, 'filename' => 'CPSIA,RSL & KC TEST SUMMARY ' . date('Y') . '.xlsx', 'counts' => $counts];
    }

    // ------------------------------------------------------------------ data
    /** the requests per sheet, oldest first, with the values the columns need */
    private function requests(): array
    {
        $db   = \Config\Database::connect();
        $rows = $db->query('SELECT r.*, b.received_at AS batch_received_at, b.source_channel FROM tbl_vf_test_requests r LEFT JOIN tbl_vf_email_batches b ON b.id = r.batch_id ORDER BY r.id ASC')->getResultArray();
        $out  = ['cpsia' => [], 'kc' => [], 'rsl' => [], 'gb' => []];
        foreach ($rows as $r) {
            $pk  = $r['test_package'] . ' ' . $r['test_type_short'];
            $key = preg_match('/(^|[^A-Za-z])KC([^A-Za-z]|$)/', $pk) ? 'kc'
                : (preg_match('/^\s*RSL/i', $r['test_package'] . ' ' . $r['test_type_short']) ? 'rsl'
                : (preg_match('/^\s*GB([^A-Za-z]|$)/i', $r['test_package']) || preg_match('/^\s*GB([^A-Za-z]|$)/i', $r['test_type_short']) ? 'gb' : 'cpsia'));
            $tr  = trim(str_replace("\u{3000}", ' ', (string) $r['tr_no']));
            $tr  = str_starts_with($tr, 'VF:') ? '' : $tr;   // an internal placeholder, not a TR No
            $rep = trim((string) $r['lab_report_no']);
            $ord = trim((string) $r['lab_order_no']);
            $out[$key][] = [
                'prod' => trim((string) $r['prod_code']), 'po' => trim(str_replace("\u{3000}", ' ', (string) $r['po_no'])), 'style' => $r['style_no'], 'mtl' => trim((string) $r['mtl_no']),
                'status' => $r['email_state'] === 'Need to test' ? 'NEW' : $r['email_state'], 'season' => $r['season'], 'color' => $r['colorway'],
                'color_c' => $r['colorway_c'] !== '' ? $r['colorway_c'] : $r['colorway'], 'factory' => $r['factory'],
                'tr' => $tr !== '' ? $tr : trim((string) $r['generated_application_no']),
                'report' => $rep, 'report_cp' => ($rep !== '' && $rep !== $ord) ? $rep : ($ord !== '' ? 'N/A' : ''), 'order' => $ord,
                'send' => $r['sample_send_date'], 'notif' => $r['notification_date'], 'result' => $this->result($r['test_result']),
                'issue' => $r['issue_date'], 'validity' => $r['validity_date'], 'crd' => $r['crd'], 'received' => substr((string) $r['lab_received_at'], 0, 10),
                'fail' => (string) $r['fail_reason'], 'rereport' => $r['retest_report_no'], 'reresult' => $this->result($r['retest_result']),
                'group_date' => substr((string) ($r['batch_received_at'] ?: $r['created_at']), 0, 10),
                'year' => $this->year([$r['sample_send_date'], $r['notification_date'], $r['issue_date'], $r['batch_received_at'], $r['created_at']]),
                'is_import' => $r['source_channel'] === 'excel_import', 'stage' => $r['stage'],
            ];
        }

        return $out;
    }

    private function result($v): string
    {
        $u = strtoupper(trim((string) $v));

        return str_starts_with($u, 'FAIL') ? 'FAILED' : trim((string) $v);
    }

    private function year(array $vals): string
    {
        foreach ($vals as $v) {
            if ($v && preg_match('/^(\d{4})-/', (string) $v, $m) && (int) $m[1] > 2000) {
                return $m[1];
            }
        }

        return date('Y');
    }

    // ------------------------------------------------------------------ one sheet
    private function sheet(string $xml, string $key, array $cfg, array $reqs): array
    {
        $doc = new \DOMDocument();
        $doc->preserveWhiteSpace = true;
        $doc->loadXML($xml, LIBXML_PARSEHUGE);
        $xp = new \DOMXPath($doc);
        $xp->registerNamespace('m', self::NS);
        $sheetData = $xp->query('//m:sheetData')->item(0);

        $rows = [];
        foreach ($xp->query('m:row', $sheetData) as $row) {
            $rows[(int) $row->getAttribute('r')] = $row;
        }
        // the rows a new row / group is copied from (taken before anything changes)
        $proto = [];
        foreach (['data', 'sep', 'blank', 'sec', 'head'] as $p) {
            if (isset($cfg[$p], $rows[$cfg[$p]])) {
                $proto[$p] = $rows[$cfg[$p]]->cloneNode(true);
            }
        }

        // 1) the lab's rows: index by MTL#, fill blank cells NEXUS-L knows now
        $byMtl = [];
        $lastContent = 0;
        foreach ($rows as $rn => $row) {
            $vals = $this->rowValues($row);
            if (array_filter($vals, static fn ($v) => trim($v) !== '')) {
                $lastContent = $rn;
            }
            $m = strtoupper(preg_replace('/\s+/', '', $vals[$cfg['mtl']] ?? ''));
            if ($rn > 1 && preg_match('/^V[A-Z0-9]{8,12}$/', $m)) {
                $byMtl[$m][] = [$rn, strtoupper(preg_replace('/\s+/', '', $cfg['prod'] ? ($vals[$cfg['prod']] ?? '') : ''))];
            }
        }
        $filled = 0;
        $new    = [];
        foreach ($reqs as $q) {
            $rn = $this->match($byMtl, $q, $cfg);
            if ($rn === null) {
                if (! $q['is_import']) {
                    $new[strtoupper($q['prod'] . '|' . $q['mtl'])] = $q;   // the same request twice (sent again): the newest one
                }
                continue;
            }
            if ($q['is_import'] && $q['stage'] === 'HISTORY') {
                continue;   // still exactly what the lab's file says
            }
            foreach ($cfg['cols'] as $col => $field) {
                // only facts that arrive later are filled in; the lab's own wording (status, style, colour, PO...) is never touched
                $name = explode(':', $field)[0];
                if (! in_array($name, ['tr', 'report', 'report_cp', 'order', 'send', 'notif', 'result', 'issue', 'validity', 'crd', 'received', 'fail', 'rereport', 'reresult'], true)
                    || ($name === 'report_cp' && $q['report_cp'] === 'N/A')) {
                    continue;
                }
                $cell = $this->cell($doc, $rows[$rn], $col, $proto['data'] ?? null);
                if (trim($this->cellText($cell)) !== '') {
                    continue;
                }
                if ($this->put($doc, $cell, $q, $field, $cfg)) {
                    $filled++;
                }
            }
        }

        // 2) new requests at the end of the sheet, in the same look as the rows above
        $trailing = [];
        foreach ($rows as $rn => $row) {
            if ($rn > $lastContent) {
                $trailing[] = $row;
                $sheetData->removeChild($row);
            }
        }
        $new    = array_values($new);
        $r      = $lastContent + 1;
        $merges = [];
        $added  = 0;
        if ($new) {
            if ($key === 'cpsia') {
                $byDate = [];
                foreach ($new as $q) {
                    $byDate[$q['group_date']][] = $q;
                }
                ksort($byDate);
                foreach ($byDate as $date => $list) {
                    $this->addRow($doc, $sheetData, $proto['sep'], $r, ['A' => $date]);
                    $merges[] = 'A' . $r . ':' . $cfg['last'] . $r;
                    $r++;
                    foreach ($list as $q) {
                        $this->addData($doc, $sheetData, $proto['data'], $r++, $q, $cfg);
                        $added++;
                    }
                }
            } else {
                $lastYear = $this->lastTitleYear($xp, $sheetData, $cfg);
                $byYear   = [];
                foreach ($new as $q) {
                    $byYear[max($q['year'], (string) $lastYear)][] = $q;
                }
                ksort($byYear);
                foreach ($byYear as $year => $list) {
                    if ((string) $year !== (string) $lastYear) {
                        if ($key === 'gb') {
                            $r += 2;
                        } else {
                            $this->addRow($doc, $sheetData, $proto['blank'], $r++, []);
                        }
                        $this->addRow($doc, $sheetData, $proto['sec'], $r, ['A' => $cfg['title'] . $year]);
                        $merges[] = 'A' . $r . ':' . $cfg['last'] . $r;
                        $r++;
                        if (isset($proto['head'])) {
                            $this->addRow($doc, $sheetData, $proto['head'], $r++, null);
                        }
                        $lastYear = $year;
                    }
                    foreach ($list as $q) {
                        $this->addData($doc, $sheetData, $proto['data'], $r++, $q, $cfg);
                        $added++;
                    }
                }
            }
        }
        $lastRow = $r - 1;
        foreach ($trailing as $row) {   // the empty formatted rows the lab keeps under the list
            $row->setAttribute('r', (string) $r);
            foreach ($xp->query('m:c', $row) as $c) {
                $c->setAttribute('r', preg_replace('/\d+$/', (string) $r, $c->getAttribute('r')));
            }
            $sheetData->appendChild($row);
            $r++;
        }

        if ($merges) {
            $mc = $xp->query('//m:mergeCells')->item(0);
            if (! $mc) {
                $mc = $doc->createElementNS(self::NS, 'mergeCells');
                $sheetData->parentNode->insertBefore($mc, $sheetData->nextSibling);
            }
            foreach ($merges as $m) {
                $e = $doc->createElementNS(self::NS, 'mergeCell');
                $e->setAttribute('ref', $m);
                $mc->appendChild($e);
            }
            $mc->setAttribute('count', (string) $xp->query('m:mergeCell', $mc)->length);
        }
        $dim = $xp->query('//m:dimension')->item(0);
        if ($dim) {
            $dim->setAttribute('ref', preg_replace('/\d+$/', (string) max($r - 1, 1), $dim->getAttribute('ref')));
        }

        return [$doc->saveXML(), $filled, $added, $lastRow];
    }

    /** the lab's row for this request: same MTL#, and the Prod Code in the row (when the row has one) */
    private function match(array $byMtl, array $q, array $cfg): ?int
    {
        $m = strtoupper(preg_replace('/\s+/', '', $q['mtl']));
        if (! isset($byMtl[$m])) {
            return null;
        }
        $p     = strtoupper(preg_replace('/\s+/', '', $q['prod']));
        $base  = preg_replace('/-[A-Z]{1,3}$/', '', $p);
        $loose = null;
        foreach ($byMtl[$m] as [$rn, $cellProd]) {
            if ($p !== '' && $cellProd !== '' && (str_contains($cellProd, $p) || ($base !== '' && str_contains($cellProd, $base)))) {
                return $rn;
            }
            if ($cellProd === '' || $p === '' || ! $cfg['prod']) {
                $loose ??= $rn;
            }
        }

        return $loose;
    }

    private function lastTitleYear(\DOMXPath $xp, \DOMElement $sheetData, array $cfg): string
    {
        $year = '';
        foreach ($xp->query('m:row', $sheetData) as $row) {
            $a = $this->rowValues($row)['A'] ?? '';
            if (str_starts_with(strtoupper(trim($a)), strtoupper(trim($cfg['title']))) && preg_match('/(20\d{2})/', $a, $m)) {
                $year = $m[1];
            }
        }

        return $year !== '' ? $year : date('Y');
    }

    // ------------------------------------------------------------------ rows / cells
    /** a copy of a template row at row $r: its cell styles and height; $set = column => text (null keeps the template's values, e.g. a header) */
    private function addRow(\DOMDocument $doc, \DOMElement $sheetData, \DOMElement $proto, int $r, ?array $set): \DOMElement
    {
        $row = $proto->cloneNode(true);
        $row->setAttribute('r', (string) $r);
        foreach (iterator_to_array($row->childNodes) as $c) {
            if (! $c instanceof \DOMElement) {
                continue;
            }
            $col = preg_replace('/\d+$/', '', $c->getAttribute('r'));
            $c->setAttribute('r', $col . $r);
            if ($set !== null) {
                $this->clear($c);
                if (isset($set[$col])) {
                    $this->setText($doc, $c, (string) $set[$col]);
                }
            }
        }
        $sheetData->appendChild($row);

        return $row;
    }

    private function addData(\DOMDocument $doc, \DOMElement $sheetData, \DOMElement $proto, int $r, array $q, array $cfg): void
    {
        $row = $this->addRow($doc, $sheetData, $proto, $r, []);
        foreach ($cfg['cols'] as $col => $field) {
            $this->put($doc, $this->cell($doc, $row, $col, $proto), $q, $field, $cfg);
        }
    }

    /** writes one DB value into a cell in the sheet's own way (text dates where the lab types text, real dates where it uses dates) */
    private function put(\DOMDocument $doc, \DOMElement $c, array $q, string $field, array $cfg): bool
    {
        [$name, $fmt] = array_pad(explode(':', $field, 2), 2, '');
        $v = trim((string) ($q[$name] ?? ''));
        if ($v === '' || str_starts_with($v, '0000')) {
            return false;
        }
        $this->clear($c);
        if ($fmt !== '' && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $d)) {
            if ($fmt === 'serial') {
                $days = (int) floor((gmmktime(0, 0, 0, (int) $d[2], (int) $d[3], (int) $d[1]) - gmmktime(0, 0, 0, 12, 30, 1899)) / 86400);
                $vEl  = $doc->createElementNS(self::NS, 'v', (string) $days);
                $c->appendChild($vEl);

                return true;
            }
            $v = $fmt === 'us' ? $d[2] . '-' . $d[3] . '-' . $d[1] : $d[1] . '-' . $d[2] . '-' . $d[3];
        }
        if (in_array($name, ['result', 'reresult'], true) && isset($cfg['pass']) && preg_replace('/\d+$/', '', $c->getAttribute('r')) === $cfg['resultCol']) {
            $c->setAttribute('s', (string) (str_starts_with($v, 'FAIL') ? $cfg['fail'] : $cfg['pass']));
        }
        $this->setText($doc, $c, $v);

        return true;
    }

    private function cell(\DOMDocument $doc, \DOMElement $row, string $col, ?\DOMElement $proto): \DOMElement
    {
        $want = $this->colNum($col);
        $before = null;
        foreach ($row->childNodes as $c) {
            if (! $c instanceof \DOMElement) {
                continue;
            }
            $cc = preg_replace('/\d+$/', '', $c->getAttribute('r'));
            if ($cc === $col) {
                return $c;
            }
            if ($this->colNum($cc) > $want) {
                $before = $c;
                break;
            }
        }
        $c = $doc->createElementNS(self::NS, 'c');
        $c->setAttribute('r', $col . $row->getAttribute('r'));
        if ($proto) {   // the style of that column in the template row
            foreach ($proto->childNodes as $p) {
                if ($p instanceof \DOMElement && preg_replace('/\d+$/', '', $p->getAttribute('r')) === $col && $p->hasAttribute('s')) {
                    $c->setAttribute('s', $p->getAttribute('s'));
                }
            }
        }
        $before ? $row->insertBefore($c, $before) : $row->appendChild($c);

        return $c;
    }

    private function clear(\DOMElement $c): void
    {
        $c->removeAttribute('t');
        while ($c->firstChild) {
            $c->removeChild($c->firstChild);
        }
    }

    private function setText(\DOMDocument $doc, \DOMElement $c, string $v): void
    {
        $v = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $v) ?? '';
        $c->setAttribute('t', 'inlineStr');
        $is = $doc->createElementNS(self::NS, 'is');
        $t  = $doc->createElementNS(self::NS, 't');
        $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        $t->appendChild($doc->createTextNode($v));
        $is->appendChild($t);
        $c->appendChild($is);
    }

    private function colNum(string $col): int
    {
        $n = 0;
        foreach (str_split($col) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n;
    }

    /** @return array<string,string> column => shown text */
    private function rowValues(\DOMElement $row): array
    {
        $out = [];
        foreach ($row->childNodes as $c) {
            if ($c instanceof \DOMElement) {
                $out[preg_replace('/\d+$/', '', $c->getAttribute('r'))] = $this->cellText($c);
            }
        }

        return $out;
    }

    private function cellText(\DOMElement $c): string
    {
        $t = $c->getAttribute('t');
        $s = '';
        foreach ($c->childNodes as $n) {
            if ($n instanceof \DOMElement) {
                if ($n->localName === 'v') {
                    $s = $t === 's' ? ($this->sst[(int) $n->textContent] ?? '') : $n->textContent;
                } elseif ($n->localName === 'is') {
                    $s = $n->textContent;
                }
            }
        }

        return $s;
    }

    private function loadSst(string $xml): void
    {
        $this->sst = [];
        if ($xml === '') {
            return;
        }
        $d = new \DOMDocument();
        $d->loadXML($xml, LIBXML_PARSEHUGE);
        foreach ($d->getElementsByTagNameNS(self::NS, 'si') as $si) {
            $txt = '';
            foreach ($si->getElementsByTagNameNS(self::NS, 't') as $t) {
                // phonetic (rPh) runs are not part of the shown text
                if ($t->parentNode->localName !== 'rPh') {
                    $txt .= $t->textContent;
                }
            }
            $this->sst[] = $txt;
        }
    }
}
