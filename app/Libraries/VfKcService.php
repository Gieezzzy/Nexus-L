<?php

declare(strict_types=1);

namespace App\Libraries;

use ZipArchive;

/**
 * KC Test (Korea, tested by KOTITI) support of VF Test Monitoring:
 *  - the Invoice and Packing List that go with the eTRF e-mail (built from the two sample workbooks in templates/kc, editable per batch, the
 *    Amount / TOTAL cells stay formulas so Excel recalculates them),
 *  - "Proceed" (the receiver answered: the samples are placed in Transit Monitoring),
 *  - the update e-mail with the tracking number and the proof files, and "Lab received".
 * CPSIA is not touched.
 */
class VfKcService
{
    private const T_INVOICE = ['file' => 'invoice.xlsx', 'first' => 18, 'last' => 37];
    private const T_PACKING = ['file' => 'packing.xlsx', 'first' => 32, 'last' => 51];

    private function db()
    {
        return \Config\Database::connect();
    }

    public static function isKcPackage(string $pkg): bool
    {
        return (bool) preg_match('/(^|[^A-Za-z])KC([^A-Za-z]|$)/', $pkg);
    }

    public function ensureTables(): void
    {
        (new VfTestRequestService())->ensureTables();
        $this->db()->query('CREATE TABLE IF NOT EXISTS tbl_vf_kc_docs (
            batch_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            data_json MEDIUMTEXT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    /** @return list<array<string,mixed>> the requests of the batch that already have an Application No. */
    private function requests(int $batchId, array $ids = []): array
    {
        $qb = $this->db()->table('tbl_vf_test_requests')->where('batch_id', $batchId)->where('generated_application_no !=', '');
        if ($ids) {
            $qb->whereIn('id', $ids);
        }

        return $qb->orderBy('id', 'ASC')->get()->getResultArray();
    }

    /** the saved sheet data of a batch, or a first proposal made from its requests */
    public function docs(int $batchId): array
    {
        $this->ensureTables();
        $row = $this->db()->table('tbl_vf_kc_docs')->where('batch_id', $batchId)->get(1)->getRowArray();
        $d   = $row ? (json_decode((string) $row['data_json'], true) ?: []) : [];
        $reqs = $this->requests($batchId) ?: $this->db()->table('tbl_vf_test_requests')->where('batch_id', $batchId)->orderBy('id', 'ASC')->get()->getResultArray();
        $mtls = array_values(array_unique(array_filter(array_map(static fn ($r) => (string) $r['mtl_no'], $reqs))));
        if (empty($d['lines'])) {
            $d['lines'] = [];
            foreach ($mtls as $m) {
                $d['lines'][] = ['hs' => '6404199000', 'item' => 'KC Test Sample Shoe', 'mtl' => $m, 'qty' => 1, 'unit' => 'PAIR', 'price' => 5];
            }
        }
        if (empty($d['invoice_no'])) {
            $n = (int) $this->db()->table('tbl_vf_kc_docs')->where('updated_at >=', date('Y-m-d') . ' 00:00:00')->countAllResults();
            $d['invoice_no'] = date('Ymd') . str_pad((string) ($n + 1), 3, '0', STR_PAD_LEFT);
        }
        $d += ['date' => date('Y-m-d'), 'cartons' => 1, 'nw' => '1.10kg', 'gw' => '1.16kg', 'measure' => '28*20*19CM'];
        $d['mtls']  = $mtls;
        $d['style'] = (string) ($reqs[0]['style_no'] ?? '');
        $d['saved'] = (bool) $row;

        return $d;
    }

    public function save(int $batchId, array $in): array
    {
        $this->ensureTables();
        $lines = [];
        foreach ((array) ($in['lines'] ?? []) as $l) {
            $item = trim((string) ($l['item'] ?? ''));
            if ($item === '' && trim((string) ($l['mtl'] ?? '')) === '') {
                continue;
            }
            $lines[] = ['hs' => trim((string) ($l['hs'] ?? '')), 'item' => $item, 'mtl' => trim((string) ($l['mtl'] ?? '')), 'qty' => (float) ($l['qty'] ?? 0),
                'unit' => trim((string) ($l['unit'] ?? 'PCS')) ?: 'PCS', 'price' => (float) ($l['price'] ?? 0)];
        }
        if (count($lines) > 20) {
            return ['ok' => false, 'error' => 'The sheets have room for 20 lines.'];
        }
        $d = ['invoice_no' => trim((string) ($in['invoice_no'] ?? '')), 'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($in['date'] ?? '')) ? (string) $in['date'] : date('Y-m-d'),
            'cartons' => max(1, (int) ($in['cartons'] ?? 1)), 'nw' => trim((string) ($in['nw'] ?? '')), 'gw' => trim((string) ($in['gw'] ?? '')), 'measure' => trim((string) ($in['measure'] ?? '')), 'lines' => $lines];
        $this->db()->query('INSERT INTO tbl_vf_kc_docs (batch_id, data_json, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE data_json = VALUES(data_json), updated_at = NOW()', [$batchId, json_encode($d, JSON_UNESCAPED_UNICODE)]);

        return ['ok' => true];
    }

    /** the 19 material lines of the sample workbook, to start from */
    public static function sampleLines(string $mtl): array
    {
        $j = json_decode((string) @file_get_contents(__DIR__ . '/templates/kc/sample_lines.json'), true) ?: [];

        return array_map(static fn ($l) => ['hs' => $l['hs'], 'item' => $l['item'], 'mtl' => $mtl, 'qty' => (float) $l['qty'], 'unit' => $l['unit'], 'price' => (float) $l['price']], $j);
    }

    // ------------------------------------------------------------------------------------------------ the workbooks

    /** @return list<array{filename:string,kind:string,data:string}> */
    public function files(int $batchId): array
    {
        $d = $this->docs($batchId);
        if (! $d['lines']) {
            return [];
        }
        $mtl  = $d['mtls'][0] ?? ($d['lines'][0]['mtl'] ?? '');
        $tail = trim($mtl . (count($d['mtls']) > 1 ? ' (+' . (count($d['mtls']) - 1) . ')' : '') . ($d['style'] !== '' && count($d['mtls']) <= 1 ? ' ' . $d['style'] : ''));
        $tail = preg_replace('/[\\\\\/:*?"<>|]+/', '-', $tail);
        $out  = [];
        foreach (['invoice' => 'DTP INVOICE LIST', 'packing' => 'DTP PACKING LIST'] as $kind => $label) {
            $bin = $this->build($kind, $d);
            if ($bin !== '') {
                $out[] = ['filename' => $label . ' ' . $tail . '.xlsx', 'kind' => $kind, 'data' => $bin];
            }
        }

        return $out;
    }

    private static function serial(string $ymd): int
    {
        return (int) floor((strtotime($ymd . ' 00:00:00 UTC') - strtotime('1899-12-30 00:00:00 UTC')) / 86400);
    }

    /** "THIRTY TWO AND NINETY FIVE CENTS ONLY" */
    public static function dollarsInWords(float $v): string
    {
        $ones = ['', 'ONE', 'TWO', 'THREE', 'FOUR', 'FIVE', 'SIX', 'SEVEN', 'EIGHT', 'NINE', 'TEN', 'ELEVEN', 'TWELVE', 'THIRTEEN', 'FOURTEEN', 'FIFTEEN', 'SIXTEEN', 'SEVENTEEN', 'EIGHTEEN', 'NINETEEN'];
        $tens = ['', '', 'TWENTY', 'THIRTY', 'FORTY', 'FIFTY', 'SIXTY', 'SEVENTY', 'EIGHTY', 'NINETY'];
        $w = static function (int $n) use (&$w, $ones, $tens): string {
            if ($n < 20) return $ones[$n];
            if ($n < 100) return trim($tens[intdiv($n, 10)] . ' ' . $ones[$n % 10]);
            if ($n < 1000) return trim($ones[intdiv($n, 100)] . ' HUNDRED ' . $w($n % 100));

            return trim($w(intdiv($n, 1000)) . ' THOUSAND ' . $w($n % 1000));
        };
        $cents = (int) round($v * 100);
        $d = intdiv($cents, 100);
        $c = $cents % 100;
        $s = $d > 0 ? $w($d) : 'ZERO';

        return $s . ($c > 0 ? ' AND ' . $w($c) . ' CENTS' : '') . ' ONLY';
    }

    private function build(string $kind, array $d): string
    {
        $t = $kind === 'invoice' ? self::T_INVOICE : self::T_PACKING;
        $tpl = __DIR__ . '/templates/kc/' . $t['file'];
        if (! is_file($tpl)) {
            return '';
        }
        $tmp = tempnam(sys_get_temp_dir(), 'kc');
        copy($tpl, $tmp);
        $z = new ZipArchive();
        if ($z->open($tmp) !== true) {
            @unlink($tmp);

            return '';
        }
        $sheet = (string) $z->getFromName('xl/worksheets/sheet1.xml');
        $dom   = new \DOMDocument();
        $dom->loadXML($sheet);
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $total = 0.0;
        foreach ($d['lines'] as $l) {
            $total += (float) $l['qty'] * (float) $l['price'];
        }
        $set = fn (string $ref, $v, bool $f = false) => self::setCell($dom, $xp, $ref, $v, $f);
        $serial = self::serial((string) $d['date']);
        $n = 0;
        if ($kind === 'invoice') {
            $set('B5', (string) $d['invoice_no']);
            $set('E5', $serial);
            $set('A16', 'SAY TOTAL U.S DOLLAR : ' . self::dollarsInWords($total));
            $set('A40', 'TOTAL NO OF CARTONS : ' . $d['cartons'] . ' CTNS');
            $set('A44', 'N.W. : ' . $d['nw']);
            $set('A45', 'G.W. : ' . $d['gw']);
            $set('A46', 'Measurement : ' . $d['measure']);
            foreach (range($t['first'], $t['last']) as $r) {
                $l = $d['lines'][$n] ?? null;
                $n++;
                foreach (['A', 'B', 'D', 'F', 'G', 'H', 'I', 'J'] as $c) {
                    $set($c . $r, null);
                }
                if ($l) {
                    $set('A' . $r, (string) $n);
                    $set('B' . $r, ctype_digit($l['hs']) ? (float) $l['hs'] : $l['hs']);
                    $set('D' . $r, ' ' . $l['item']);
                    $set('F' . $r, $l['mtl']);
                    $set('G' . $r, (float) $l['qty']);
                    $set('H' . $r, $l['unit']);
                    $set('I' . $r, (float) $l['price']);
                    $set('J' . $r, "I{$r}*G{$r}", true);
                }
            }
        } else {
            $set('F5', (string) $d['invoice_no']);
            $set('F7', $serial);
            $set('B28', 'SAY TOTAL ' . strtoupper(self::countWord((int) $d['cartons'])) . ' ( ' . $d['cartons'] . ' ) PKG ONLY');
            $set('B55', 'Carton No.: ' . $d['cartons']);
            $set('B59', 'N.W. : ' . $d['nw']);
            $set('B60', 'G.W. : ' . $d['gw']);
            $set('B61', 'Measurement : ' . $d['measure']);
            foreach (range($t['first'], $t['last']) as $r) {
                $l = $d['lines'][$n] ?? null;
                $n++;
                foreach (['B', 'C', 'E', 'G', 'H', 'I', 'J', 'K'] as $c) {
                    $set($c . $r, null);
                }
                if ($l) {
                    $set('B' . $r, (string) $n);
                    $set('C' . $r, ctype_digit($l['hs']) ? (float) $l['hs'] : $l['hs']);
                    $set('E' . $r, ' ' . $l['item']);
                    $set('G' . $r, $l['mtl']);
                    $set('H' . $r, (float) $l['qty']);
                    $set('I' . $r, $l['unit']);
                    $set('J' . $r, (float) $l['price']);
                    $set('K' . $r, "J{$r}*H{$r}", true);
                }
            }
            // the sample has a stray SUM over the wrong rows in the QTY / PRICE total: totals cover every line
            $set('G52', 'SUM(G' . $t['first'] . ':G' . $t['last'] . ')', true);
        }
        $z->addFromString('xl/worksheets/sheet1.xml', $dom->saveXML());
        // Excel recalculates every formula when it opens the file; the old calculation chain would no longer match
        $wb = (string) $z->getFromName('xl/workbook.xml');
        if (! str_contains($wb, 'fullCalcOnLoad')) {
            $wb = preg_match('/<calcPr[^>]*\/>/', $wb) ? preg_replace('/<calcPr/', '<calcPr fullCalcOnLoad="1"', $wb, 1) : str_replace('</workbook>', '<calcPr fullCalcOnLoad="1"/></workbook>', $wb);
        }
        $mtl = (string) ($d['mtls'][0] ?? '');
        if ($mtl !== '') {
            $wb = preg_replace_callback('/(<sheet [^>]*name=")([^"]*)(")/', static fn ($m) => $m[1] . htmlspecialchars(mb_substr(trim(preg_replace('/\s+[A-Z0-9]{11}\s*$/', '', $m[2])) . ' ' . $mtl, 0, 31), ENT_QUOTES) . $m[3], $wb, 1);
        }
        $z->addFromString('xl/workbook.xml', $wb);
        foreach (['xl/calcChain.xml'] as $gone) {
            $z->deleteName($gone);
        }
        $ct = (string) $z->getFromName('[Content_Types].xml');
        $z->addFromString('[Content_Types].xml', (string) preg_replace('/<Override[^>]*calcChain[^>]*\/>/', '', $ct));
        $rels = (string) $z->getFromName('xl/_rels/workbook.xml.rels');
        $z->addFromString('xl/_rels/workbook.xml.rels', (string) preg_replace('/<Relationship [^>]*calcChain[^>]*\/>/', '', $rels));
        $z->close();
        $bin = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $bin;
    }

    private static function countWord(int $n): string
    {
        return ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten'][$n] ?? (string) $n;
    }

    /** writes one cell (string / number / formula / empty) keeping its style; the cell is created when the template row lacks it */
    private static function setCell(\DOMDocument $dom, \DOMXPath $xp, string $ref, $v, bool $formula = false): void
    {
        $ns   = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $cell = $xp->query('//m:c[@r="' . $ref . '"]')->item(0);
        if (! $cell) {
            if ($v === null) {
                return;
            }
            preg_match('/^([A-Z]+)(\d+)$/', $ref, $m);
            $row = $xp->query('//m:row[@r="' . $m[2] . '"]')->item(0);
            if (! $row) {
                return;
            }
            $cell = $dom->createElementNS($ns, 'c');
            $cell->setAttribute('r', $ref);
            $col  = static fn (string $s): int => array_reduce(str_split($s), static fn ($c, $ch) => $c * 26 + ord($ch) - 64, 0);
            $before = null;
            foreach ($xp->query('m:c', $row) as $c) {
                preg_match('/^([A-Z]+)/', $c->getAttribute('r'), $mm);
                if ($col($mm[1]) > $col($m[1])) {
                    $before = $c;
                    break;
                }
            }
            $row->insertBefore($cell, $before);
        }
        while ($cell->firstChild) {
            $cell->removeChild($cell->firstChild);
        }
        $cell->removeAttribute('t');
        if ($v === null || $v === '') {
            return;
        }
        if ($formula) {
            $f = $dom->createElementNS($ns, 'f');
            $f->appendChild($dom->createTextNode((string) $v));
            $cell->appendChild($f);
        } elseif (is_int($v) || is_float($v)) {
            $e = $dom->createElementNS($ns, 'v');
            $e->appendChild($dom->createTextNode((string) $v));
            $cell->appendChild($e);
        } else {
            $cell->setAttribute('t', 'inlineStr');
            $is = $dom->createElementNS($ns, 'is');
            $t  = $dom->createElementNS($ns, 't');
            $t->setAttribute('xml:space', 'preserve');
            $t->appendChild($dom->createTextNode((string) $v));
            $is->appendChild($t);
            $cell->appendChild($is);
        }
    }

    // ------------------------------------------------------------------------------------------------ e-mail bodies

    public static function signature(): string
    {
        $sig = static fn (string $k, string $d = '') => htmlspecialchars((string) SystemSettings::get($k, $d), ENT_QUOTES);
        $logo = '';
        $lp   = trim((string) SystemSettings::get('vf.mail_logo', ''));
        if ($lp !== '' && is_file($lp)) {
            $ext  = strtolower(pathinfo($lp, PATHINFO_EXTENSION));
            $logo = '<p><img alt="" src="data:' . ($ext === 'png' ? 'image/png' : ($ext === 'gif' ? 'image/gif' : 'image/jpeg')) . ';base64,' . base64_encode((string) file_get_contents($lp)) . '" style="height:90px"></p>';
        }

        return '<p style="font-family:\'Monotype Corsiva\',\'Segoe Script\',cursive;font-size:17px;font-weight:bold;color:#1f3864"><i>Thank you and have a blessed day!</i></p>'
            . '<p style="font-family:Arial,sans-serif;font-size:11px;font-weight:bold;color:#1f3864;margin-bottom:2px">Best Regards,</p>'
            . '<p style="margin-top:2px"><b><u style="color:#ff0000;font-size:20px">' . $sig('vf.sign_name', 'QC Laboratory Team') . '</u></b><br>'
            . '<b style="font-family:Arial,sans-serif;font-size:13px;color:#2e75b6">' . $sig('vf.sign_title') . '</b><br>'
            . '<b style="font-family:Arial,sans-serif;font-size:13px;color:#000"><i>' . $sig('vf.sign_company', 'DA TIAN SUBIC SHOES INC.') . '</i></b><br>'
            . '<i style="font-family:Arial,sans-serif;font-size:12px;color:#2e75b6">Telephone No.: ' . $sig('vf.sign_phone') . '</i><br>'
            . '<i style="font-family:Arial,sans-serif;font-size:12px;color:#2e75b6">' . $sig('vf.sign_address') . '</i></p>' . $logo;
    }

    /** the first e-mail: the eTRF number(s) with the packing list and the invoice (a reply to VF's request) */
    public static function requestBody(int $n, bool $withSheets): string
    {
        $ph  = VfTestRequestService::countPhrase($n);
        $it  = $n === 1 ? 'item' : 'items';
        $esc = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES);

        return '<div style="font-family:\'Times New Roman\',serif;font-size:13.5px;color:#000;line-height:1.5">'
            . '<p>' . $esc(SystemSettings::get('vf.mail_greeting', 'Hello DJC Team,')) . '</p>'
            . '<p>This email is to provide the ETRF number for the ' . $esc($ph) . ' new KC Test ' . $it . ' that ' . ($n === 1 ? 'requires' : 'require') . ' testing.'
            . ($withSheets ? ' Attached as well are the packing list and invoice for the ' . $esc($ph) . ' new KC Test ' . $it . '.' : '') . '</p>'
            . '<p>Please refer to the attached files for your reference. Should you have any questions or need further clarification, please feel free to contact me.</p>'
            . self::signature() . '</div>';
    }

    /** the update e-mail of the shipment: "the KC TEST sample shoes ... were shipped out today ... Tracking Number Reference" */
    public static function transitBody(array $mtls, string $tracking, string $when): string
    {
        $esc = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES);
        $hl  = 'style="background:#ffff00"';

        return '<div style="font-family:Calibri,Arial,sans-serif;font-size:13.5px;color:#000;line-height:1.5">'
            . '<p>' . $esc(SystemSettings::get('vf.kc_greeting', 'Hi Ms. Jan,')) . '</p><p>Good day!</p>'
            . '<p>Please be informed that the KC TEST sample shoes <b ' . $hl . '>' . $esc(implode(',', $mtls)) . '</b> were shipped out today <b>' . $esc($when) . '</b> for testing at ' . $esc(SystemSettings::get('vf.kc_lab_name', 'KOTITI')) . ' Laboratory.</p>'
            . '<p>Tracking Number Reference: <b ' . $hl . '>' . $esc($tracking) . '</b></p>'
            . '<p>Attached are the Invoice, Packing List, and ETRF application number for your reference.</p>'
            . '<p>If you have any questions or concerns, please feel free to reach out to me.</p>'
            . self::signature() . '</div>';
    }

    // ------------------------------------------------------------------------------------------------ Proceed / update e-mail / lab received

    /** the receiver answered: the KC samples of this batch go to Transit Monitoring (tracking number may be given now or when the update e-mail is sent) */
    public function proceed(int $batchId, string $tracking, array $requestIds = []): array
    {
        $this->ensureTables();
        (new VfTransitService())->ensureTables();
        $db   = $this->db();
        $reqs = $this->requests($batchId, $requestIds);
        $reqs = array_values(array_filter($reqs, static fn ($r) => ! in_array($r['stage'], ['IN_TRANSIT', 'LAB_RECEIVED', 'COMPLETED', 'FOLLOW_UP', 'HISTORY'], true)));
        if (! $reqs) {
            return ['ok' => false, 'error' => 'No submitted request of this batch is waiting to go to transit.'];
        }
        $now = date('Y-m-d H:i:s');
        $tracking = trim($tracking);
        $pending  = $tracking === '';
        if ($pending) {
            $tracking = 'KC-PENDING-' . $batchId . '-' . date('ymdHi');
        }
        if ($db->table('tbl_vf_shipments')->where('tracking_no', $tracking)->countAllResults() > 0) {
            return ['ok' => false, 'error' => 'A shipment with this tracking number already exists.'];
        }
        $b = $db->table('tbl_vf_email_batches')->where('id', $batchId)->get(1)->getRowArray() ?: [];
        $db->table('tbl_vf_shipments')->insert([
            'tracking_no' => $tracking, 'carrier' => 'KC', 'internet_message_id' => '', 'subject' => mb_substr('KC TEST - batch #' . $batchId . ' ' . ($b['subject'] ?? ''), 0, 480),
            'sender_email' => '', 'anchor_at' => $now, 'next_check_at' => date('Y-m-d H:i:s', time() + 3 * 3600), 'delivered' => 0,
            'last_status' => $pending ? 'Waiting for the tracking number' : '',
            'history_json' => json_encode([['at' => $now, 'what' => 'Proceed: placed in Transit Monitoring', 'by' => '']], JSON_UNESCAPED_UNICODE), 'created_at' => $now, 'updated_at' => $now,
        ]);
        $sid = (int) $db->insertID();
        foreach ($reqs as $r) {
            try {
                $db->table('tbl_vf_shipment_items')->insert([
                    'shipment_id' => $sid, 'request_id' => (int) $r['id'], 'prod_code' => mb_substr((string) $r['prod_code'], 0, 80), 'style_name' => mb_substr((string) $r['style_no'], 0, 190),
                    'mtl_no' => (string) $r['mtl_no'], 'color' => mb_substr((string) ($r['colorway_c'] ?: $r['colorway']), 0, 250), 'state_text' => 'Need to test', 'tr_no' => (string) $r['generated_application_no'], 'crd' => (string) $r['crd'], 'created_at' => $now,
                ]);
            } catch (\Throwable $e) {
                continue;
            }
            $db->table('tbl_vf_test_requests')->where('id', $r['id'])->update(['stage' => 'IN_TRANSIT', 'container_no' => $pending ? '' : $tracking, 'transit_status' => 'IN_TRANSIT', 'transit_updated_at' => $now, 'shipment_id' => $sid, 'lab_name' => 'KOTITI', 'updated_at' => $now]);
        }
        $db->table('tbl_vf_test_events')->insert(['batch_id' => $batchId, 'event_type' => 'KC_PROCEED', 'subject' => 'KC batch #' . $batchId, 'details_json' => json_encode(['shipment_id' => $sid, 'tracking' => $tracking, 'count' => count($reqs)]), 'created_at' => $now]);

        return ['ok' => true, 'shipment_id' => $sid, 'count' => count($reqs)];
    }

    public static function defaultTransitRecipients(): array
    {
        return [
            'to' => (string) SystemSettings::get('vf.kc_transit_to', 'Jan_Zhang@vfc.com'),
            'cc' => (string) SystemSettings::get('vf.kc_transit_cc', 'yoyo_lab@dtshoes.com; nila_erazo@dtshoes.com; dtp_cfa2@dtshoes.com'),
        ];
    }

    /** is this shipment a KC one (carrier KC, or its requests are KC Test Package) */
    public function isKcShipment(int $shipmentId): bool
    {
        $db = $this->db();
        $s  = $db->table('tbl_vf_shipments')->select('carrier')->where('id', $shipmentId)->get(1)->getRowArray();
        if ($s && $s['carrier'] === 'KC') {
            return true;
        }
        $r = $db->query('SELECT r.test_package FROM tbl_vf_shipment_items i JOIN tbl_vf_test_requests r ON r.id = i.request_id WHERE i.shipment_id = ? LIMIT 1', [$shipmentId])->getRowArray();

        return $r && self::isKcPackage((string) $r['test_package']);
    }

    /**
     * The update e-mail of a KC shipment (preview and send): the tracking number can be filled in here, the invoice / packing list / eTRF PDFs are attached
     * automatically and $extra holds the files the person added (proof of transit).
     *
     * @param list<array{name:string,data:string}> $extra base64 files
     */
    public function transitMail(int $shipmentId, array $itemIds, array $opts, array $extra = []): array
    {
        (new VfTransitService())->ensureTables();
        $db = $this->db();
        $s  = $db->table('tbl_vf_shipments')->where('id', $shipmentId)->get(1)->getRowArray();
        if (! $s) {
            return ['ok' => false, 'error' => 'Shipment not found.'];
        }
        $q = $db->table('tbl_vf_shipment_items i')->select('i.*, r.batch_id, r.etrf_pdf_path, r.generated_application_no, r.test_type_short, r.test_package')->join('tbl_vf_test_requests r', 'r.id = i.request_id', 'left')->where('i.shipment_id', $shipmentId);
        if ($itemIds) {
            $q->whereIn('i.id', array_map('intval', $itemIds));
        }
        $items = $q->orderBy('i.id')->get()->getResultArray();
        if (! $items) {
            return ['ok' => false, 'error' => 'No request in this shipment.'];
        }
        $tracking = trim((string) ($opts['tracking_no'] ?? '')) ?: (string) $s['tracking_no'];
        $mtls     = array_values(array_unique(array_filter(array_map(static fn ($i) => (string) $i['mtl_no'], $items))));
        $def      = self::defaultTransitRecipients();
        $to       = trim((string) ($opts['to'] ?? '')) ?: $def['to'];
        $cc       = array_key_exists('cc', $opts) ? trim((string) $opts['cc']) : $def['cc'];
        $subject  = trim((string) ($opts['subject'] ?? '')) ?: 'KC TEST UPDATE';
        $body     = self::transitBody($mtls, $tracking, date('d/m/Y'));

        $att = [];
        $files = [];
        $add = static function (string $name, string $bin) use (&$att, &$files): void {
            $att[]   = ['filename' => $name, 'data_base64' => base64_encode($bin)];
            $files[] = ['filename' => $name, 'bytes' => strlen($bin), 'ok' => true];
        };
        foreach ($items as $i) {
            $p = (string) ($i['etrf_pdf_path'] ?? '');
            if ($p !== '' && is_file($p)) {
                $add(VfTestRequestService::formatPdfFilename((string) $i['generated_application_no'], (string) $i['mtl_no'], 'KC TEST'), (string) file_get_contents($p));
            }
        }
        $bids = array_values(array_unique(array_filter(array_map(static fn ($i) => (int) ($i['batch_id'] ?? 0), $items))));
        foreach ($bids as $bid) {
            foreach ($this->files($bid) as $f) {
                $add($f['filename'], $f['data']);
            }
        }
        foreach ($extra as $x) {
            $bin = base64_decode((string) ($x['data'] ?? ''), true);
            if ($bin !== false && $bin !== '') {
                $add(preg_replace('/[\\\\\/:*?"<>|]+/', '-', (string) ($x['name'] ?? 'file')), $bin);
            }
        }

        return ['ok' => true, 'shipment_id' => $shipmentId, 'to' => $to, 'cc' => $cc, 'subject' => $subject, 'body' => $body, 'count' => count($items), 'tracking_no' => $tracking,
            'pending' => str_starts_with((string) $s['tracking_no'], 'KC-PENDING-'), 'kc' => true, 'attachments' => $att, 'files' => $files];
    }

    public function sendTransitMail(int $shipmentId, array $itemIds, array $opts, array $extra): array
    {
        $b = $this->transitMail($shipmentId, $itemIds, $opts, $extra);
        if (empty($b['ok'])) {
            return $b;
        }
        $db  = $this->db();
        $now = date('Y-m-d H:i:s');
        $s   = $db->table('tbl_vf_shipments')->where('id', $shipmentId)->get(1)->getRowArray();
        if ($b['tracking_no'] !== $s['tracking_no']) {
            if (! preg_match('/^[A-Za-z0-9\-]{6,40}$/', $b['tracking_no'])) {
                return ['ok' => false, 'error' => 'The tracking number must be 6-40 letters, digits or dashes.'];
            }
            if ($db->table('tbl_vf_shipments')->where('tracking_no', $b['tracking_no'])->where('id !=', $shipmentId)->countAllResults() > 0) {
                return ['ok' => false, 'error' => 'Another shipment already has this tracking number.'];
            }
            $db->table('tbl_vf_shipments')->where('id', $shipmentId)->update(['tracking_no' => $b['tracking_no'], 'last_status' => '', 'updated_at' => $now]);
            $db->table('tbl_vf_test_requests')->where('shipment_id', $shipmentId)->update(['container_no' => $b['tracking_no'], 'updated_at' => $now]);
        }
        [$outboxId, $err] = MailOutbox::enqueue($b['to'], $b['subject'], $b['body'], ['cc' => $b['cc'], 'attachments' => $b['attachments'], 'is_html' => true, 'source' => 'NEXUS-L VF-KC', 'sender_name' => 'DTP QC Lab']);
        if ($outboxId <= 0) {
            return ['ok' => false, 'error' => $err ?: 'Failed to queue the e-mail.'];
        }
        $hist   = json_decode((string) ($s['history_json'] ?? '[]'), true) ?: [];
        $hist[] = ['at' => $now, 'what' => 'KC update e-mail queued (' . count($b['attachments']) . ' file(s)): ' . $b['to'], 'by' => ''];
        $db->table('tbl_vf_shipments')->where('id', $shipmentId)->update(['mailed_at' => $now, 'mail_outbox_id' => $outboxId, 'alarm_ack_at' => $now, 'history_json' => json_encode(array_slice($hist, -60), JSON_UNESCAPED_UNICODE), 'updated_at' => $now]);

        return ['ok' => true, 'outbox_id' => $outboxId, 'to' => $b['to'], 'count' => $b['count'], 'files' => count($b['attachments'])];
    }

    /** trigger: KOTITI received the specimens (the shipment's requests go to "testing"; the lab's report e-mail completes them later) */
    public function labReceived(int $shipmentId, string $by): array
    {
        $db  = $this->db();
        $s   = $db->table('tbl_vf_shipments')->where('id', $shipmentId)->get(1)->getRowArray();
        if (! $s) {
            return ['ok' => false, 'error' => 'Shipment not found.'];
        }
        $now = date('Y-m-d H:i:s');
        $n   = 0;
        foreach ($db->table('tbl_vf_test_requests')->where('shipment_id', $shipmentId)->get()->getResultArray() as $r) {
            if (in_array($r['stage'], ['COMPLETED', 'FOLLOW_UP', 'LAB_RECEIVED'], true)) {
                continue;
            }
            $db->table('tbl_vf_test_requests')->where('id', $r['id'])->update(['stage' => 'LAB_RECEIVED', 'lab_name' => 'KOTITI', 'lab_received_at' => $now, 'updated_at' => $now]);
            $n++;
        }
        $hist   = json_decode((string) ($s['history_json'] ?? '[]'), true) ?: [];
        $hist[] = ['at' => $now, 'what' => 'Lab (KOTITI) received the specimens - marked by hand', 'by' => $by];
        $db->table('tbl_vf_shipments')->where('id', $shipmentId)->update(['delivered' => 1, 'history_json' => json_encode(array_slice($hist, -60), JSON_UNESCAPED_UNICODE), 'updated_at' => $now]);

        return ['ok' => true, 'updated' => $n];
    }
}
