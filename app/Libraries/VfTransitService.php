<?php

namespace App\Libraries;

/**
 * VF Test Monitoring - Transit Monitoring and Request Material Monitoring.
 *
 *  - An e-mail that says the samples were sent ("... the tracking number is SF1461164077481" + a table with MTL# / TR# / CRD) makes ONE shipment
 *    (tbl_vf_shipments) and links every row of its table to the request it belongs to (by Application No. = TR#, else Prod Code + MTL#). The
 *    request gets the tracking number (container_no) and the stage IN_TRANSIT.
 *  - The carrier (SF International) is checked every 3 hours counted from the time the e-mail arrived. SF's waybill page asks for a CAPTCHA, which
 *    a program must not solve, so the check is done by a person on the SF page ("Check on SF") and its result is recorded here ("Record check").
 *    NEXUS-L keeps the schedule, raises the alarm when a check is due or when the status changed, and e-mails the lab on request.
 *  - Every request has one overall status for the Material Monitoring tab: SENDING / IN_TRANSIT / TESTING / DONE / FOLLOW_UP.
 */
class VfTransitService
{
    public const DEFAULT_URL     = 'https://www.sf-international.com/us/en/support/querySupport/waybill';
    public const DEFAULT_PATTERN = 'SF\d{12,15}';

    private static bool $ready = false;

    // ---- what Settings > VF decides: the tracking website, the carrier, how a tracking number looks, how often it is checked

    /** every how many hours a shipment is checked (Settings: vf.transit_hours) */
    public static function hours(): int
    {
        return max(1, min(72, (int) SystemSettings::get('vf.transit_hours', '3')));
    }

    public static function carrier(): string
    {
        return trim((string) SystemSettings::get('vf.transit_carrier', 'SF')) ?: 'SF';
    }

    /** the page "Check on ..." opens; {tracking} stays in it for the page script to fill (the address is only opened as an http(s) link) */
    public static function checkUrlTemplate(): string
    {
        $u = trim((string) SystemSettings::get('vf.transit_url', self::DEFAULT_URL));

        return preg_match('#^https?://#i', $u) ? $u : self::DEFAULT_URL;
    }

    /** the PCRE that finds a tracking number in an e-mail; a pattern that does not compile falls back to the SF one */
    public static function trackingRegex(): string
    {
        $p = trim((string) SystemSettings::get('vf.transit_pattern', self::DEFAULT_PATTERN));
        $p = str_replace('/', '\/', $p);
        $re = '/\b(?:' . ($p !== '' ? $p : self::DEFAULT_PATTERN) . ')\b/';
        if (@preg_match($re, '') === false) {
            $re = '/\b(?:' . self::DEFAULT_PATTERN . ')\b/';
        }

        return $re;
    }

    private function db()
    {
        return \Config\Database::connect();
    }

    /** the requests table (and its columns) plus the shipment tables */
    public function ensureTables(): void
    {
        if (self::$ready) {
            return;
        }
        (new VfTestRequestService())->ensureTables();   // creates / migrates tbl_vf_test_requests (this class' columns are added there)
        $db = $this->db();
        $db->query('CREATE TABLE IF NOT EXISTS tbl_vf_shipments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tracking_no VARCHAR(40) NOT NULL,
            carrier VARCHAR(20) NOT NULL DEFAULT "SF",
            outlook_record_uid VARCHAR(100) NULL,
            internet_message_id VARCHAR(255) NOT NULL DEFAULT "",
            subject VARCHAR(500) NOT NULL DEFAULT "",
            sender_email VARCHAR(191) NOT NULL DEFAULT "",
            anchor_at DATETIME NOT NULL,
            next_check_at DATETIME NOT NULL,
            last_check_at DATETIME NULL,
            last_check_by VARCHAR(120) NOT NULL DEFAULT "",
            last_status VARCHAR(500) NOT NULL DEFAULT "",
            last_event_at DATETIME NULL,
            delivered TINYINT(1) NOT NULL DEFAULT 0,
            alarm_at DATETIME NULL,
            alarm_ack_at DATETIME NULL,
            mailed_at DATETIME NULL,
            mail_outbox_id BIGINT NULL,
            history_json MEDIUMTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_tracking (tracking_no),
            KEY ix_next (delivered, next_check_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $db->query('CREATE TABLE IF NOT EXISTS tbl_vf_shipment_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            shipment_id BIGINT UNSIGNED NOT NULL,
            request_id BIGINT UNSIGNED NULL,
            prod_code VARCHAR(80) NOT NULL DEFAULT "",
            style_name VARCHAR(190) NOT NULL DEFAULT "",
            mtl_no VARCHAR(100) NOT NULL DEFAULT "",
            color VARCHAR(255) NOT NULL DEFAULT "",
            state_text VARCHAR(60) NOT NULL DEFAULT "",
            tr_no VARCHAR(80) NOT NULL DEFAULT "",
            crd VARCHAR(30) NOT NULL DEFAULT "",
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_item (shipment_id, mtl_no, tr_no, prod_code),
            KEY ix_req (request_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        self::$ready = true;
    }

    // ------------------------------------------------------------------------------------------------------------ reading the e-mail

    /**
     * What a "samples were sent" e-mail says. Pure: no database.
     *
     * @return array{tracking:list<string>,rows:list<array<string,string>>}
     */
    public static function parseTransitEmail(string $subject, string $html, string $text): array
    {
        // A forwarded e-mail carries the whole chain of older messages under the newest one, each with its own tracking number and table.
        // Only the NEWEST message counts: the first tracking number in the document, the numbers written with it, and the first table after it.
        if (stripos($html, '<table') !== false) {
            $dom = new \DOMDocument();
            $prev = libxml_use_internal_errors(true);
            @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            $xp    = new \DOMXPath($dom);
            $nodes = [];
            foreach ($xp->query('//text()') as $n) {
                $nodes[] = $n;
            }
            $firstIdx = null;
            foreach ($nodes as $i => $n) {
                if (preg_match(self::trackingRegex(), (string) $n->nodeValue)) {
                    $firstIdx = $i;
                    break;
                }
            }
            if ($firstIdx !== null) {
                $table = $xp->query('following::table[1]', $nodes[$firstIdx])->item(0);
                $stop  = count($nodes);
                if ($table instanceof \DOMElement) {
                    $inside = $xp->query('.//text()', $table)->item(0);
                    $at     = $inside ? array_search($inside, $nodes, true) : false;
                    $stop   = $at === false ? count($nodes) : (int) $at;
                }
                $tracking = [];
                for ($i = $firstIdx; $i < $stop; $i++) {
                    if (preg_match_all(self::trackingRegex(), (string) $nodes[$i]->nodeValue, $m)) {
                        array_push($tracking, ...$m[0]);
                    }
                }

                return ['tracking' => array_values(array_unique($tracking)), 'rows' => $table instanceof \DOMElement ? self::rowsOfTable($table) : []];
            }
        }
        // no HTML table: the first tracking number of the plain text, no rows
        $plain = $subject . "\n" . $text . "\n" . trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</tr>'], "\n", html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
        preg_match(self::trackingRegex(), $plain, $m);

        return ['tracking' => $m ? [$m[0]] : [], 'rows' => []];
    }

    /** the rows of ONE request table (指令編號 / 型體代號 / MTL# / 顏色 / State / TR# / CRD) */
    public static function rowsOfTable(\DOMElement $tbl): array
    {
        $out  = [];
        $seen = [];
        foreach ([$tbl] as $tbl) {
            $trs = $tbl->getElementsByTagName('tr');
            if ($trs->length < 2) {
                continue;
            }
            $cells = static function (\DOMNode $tr): array {
                $v = [];
                foreach ($tr->childNodes as $c) {
                    if ($c->nodeType === XML_ELEMENT_NODE && in_array(strtolower($c->nodeName), ['td', 'th'], true)) {
                        $v[] = trim(preg_replace('/\s+/u', ' ', $c->textContent));
                    }
                }

                return $v;
            };
            $hdr = array_map(static fn ($h) => mb_strtolower($h), $cells($trs->item(0)));
            $col = ['prod' => -1, 'style' => -1, 'mtl' => -1, 'color' => -1, 'state' => -1, 'tr' => -1, 'crd' => -1];
            foreach ($hdr as $i => $h) {
                if (str_contains($h, 'mtl')) {
                    $col['mtl'] = $i;
                } elseif (str_contains($h, '指令') || str_contains($h, 'prod') || str_contains($h, 'order no')) {
                    $col['prod'] = $i;
                } elseif (str_contains($h, '型體') || str_contains($h, '型体') || str_contains($h, 'style')) {
                    $col['style'] = $i;
                } elseif (str_contains($h, '顏色') || str_contains($h, '颜色') || str_contains($h, 'color')) {
                    $col['color'] = $i;
                } elseif (str_contains($h, 'state')) {
                    $col['state'] = $i;
                } elseif (str_contains($h, 'tr#') || str_contains($h, 'tr no') || $h === 'tr') {
                    $col['tr'] = $i;
                } elseif (str_contains($h, 'crd')) {
                    $col['crd'] = $i;
                }
            }
            if ($col['mtl'] < 0 && $col['tr'] < 0) {
                continue;
            }
            for ($r = 1; $r < $trs->length; $r++) {
                $v   = $cells($trs->item($r));
                $get = static fn (int $i) => $i >= 0 ? trim((string) ($v[$i] ?? '')) : '';
                $row = [
                    'prod_code' => $get($col['prod']), 'style_name' => $get($col['style']), 'mtl_no' => strtoupper($get($col['mtl'])),
                    'color' => $get($col['color']), 'state_text' => $get($col['state']), 'tr_no' => strtoupper($get($col['tr'])), 'crd' => $get($col['crd']),
                ];
                if ($row['mtl_no'] === '' && $row['tr_no'] === '') {
                    continue;
                }
                $key = $row['mtl_no'] . '|' . $row['tr_no'] . '|' . $row['prod_code'];
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[]      = $row;
            }
        }

        return $out;
    }

    /** the request a row of the table belongs to: its Application No. (TR#) first, else Prod Code + MTL# */
    private function matchRequest(array $row): ?array
    {
        $db = $this->db();
        // live requests first, then the imported Excel history (the Application No. of an old request is kept in tr_no)
        $order = "CASE WHEN stage = 'HISTORY' THEN 1 ELSE 0 END, id DESC";
        if (preg_match('/^A\d{8,12}$/', $row['tr_no'])) {
            $r = $db->table('tbl_vf_test_requests')->groupStart()->where('generated_application_no', $row['tr_no'])->orWhere('tr_no', $row['tr_no'])->groupEnd()->orderBy($order, '', false)->get(1)->getRowArray();
            if ($r) {
                return $r;
            }
        }
        if ($row['mtl_no'] !== '' && $row['prod_code'] !== '') {      // the Prod Code is unique to one request: MTL# alone is not (one style is tested again and again)
            $r = $db->table('tbl_vf_test_requests')->where('mtl_no', $row['mtl_no'])->where('prod_code', $row['prod_code'])->orderBy($order, '', false)->get(1)->getRowArray();
            if ($r) {
                return $r;
            }
        }

        return null;
    }

    /**
     * A "samples were sent" e-mail: creates the shipment(s), links the table rows to their requests and gives the requests the tracking number.
     *
     * @param array<string,mixed> $em the e-mail as OutlookSyncReader returns it
     *
     * @return int how many table rows were linked (0 = this is not such an e-mail)
     */
    public function applyEmail(array $em): int
    {
        $p = self::parseTransitEmail((string) $em['subject'], (string) $em['body_html'], (string) $em['body_text']);
        if (! $p['tracking'] || ! $p['rows']) {
            return 0;
        }
        if (count($p['rows']) > 40 || count($p['tracking']) > 3) {
            // one shipment never has this many numbers / rows: the e-mail is a long chain that was not read correctly - nothing is linked
            $this->ensureTables();
            $this->db()->table('tbl_vf_test_events')->insert([
                'event_type' => 'TRANSIT_EMAIL_SKIPPED', 'outlook_record_uid' => (string) ($em['record_uid'] ?? ''), 'subject' => (string) $em['subject'],
                'details_json' => json_encode(['tracking' => count($p['tracking']), 'rows' => count($p['rows'])]), 'created_at' => date('Y-m-d H:i:s'),
            ]);

            return 0;
        }
        $this->ensureTables();
        $db   = $this->db();
        $now  = date('Y-m-d H:i:s');
        $recv = ! empty($em['received_at']) && ($ts = strtotime((string) $em['received_at'])) ? date('Y-m-d H:i:s', $ts) : $now;
        $linked = 0;

        // an e-mail older than a week is a shipment that has long arrived: it is kept (the requests get their tracking number) but closed, with no alarms
        $old = (time() - strtotime($recv)) > 7 * 86400;
        foreach ($p['tracking'] as $trk) {
            $ship = $db->table('tbl_vf_shipments')->where('tracking_no', $trk)->get(1)->getRowArray();
            if (! $ship) {
                $db->table('tbl_vf_shipments')->insert([
                    'tracking_no' => $trk, 'carrier' => 'SF', 'outlook_record_uid' => (string) ($em['record_uid'] ?? ''), 'internet_message_id' => (string) ($em['internet_message_id'] ?? ''),
                    'subject' => mb_substr((string) $em['subject'], 0, 480), 'sender_email' => mb_substr((string) ($em['sender_email'] ?? ''), 0, 190),
                    'anchor_at' => $recv, 'next_check_at' => date('Y-m-d H:i:s', strtotime($recv) + self::hours() * 3600),
                    'delivered' => $old ? 1 : 0, 'last_status' => $old ? 'Imported from an old e-mail (not tracked)' : '',
                    'history_json' => json_encode([['at' => $now, 'what' => $old ? 'Imported from an old e-mail: closed' : 'E-mail received', 'by' => (string) ($em['sender_email'] ?? '')]], JSON_UNESCAPED_UNICODE),
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $sid = (int) $db->insertID();
            } else {
                $sid = (int) $ship['id'];
            }
            foreach ($p['rows'] as $row) {
                $req = $this->matchRequest($row);
                try {
                    $db->table('tbl_vf_shipment_items')->insert([
                        'shipment_id' => $sid, 'request_id' => $req ? (int) $req['id'] : null, 'prod_code' => mb_substr($row['prod_code'], 0, 80), 'style_name' => mb_substr($row['style_name'], 0, 190),
                        'mtl_no' => mb_substr($row['mtl_no'], 0, 100), 'color' => mb_substr($row['color'], 0, 250), 'state_text' => mb_substr($row['state_text'], 0, 60),
                        'tr_no' => mb_substr($row['tr_no'], 0, 80), 'crd' => mb_substr($row['crd'], 0, 30), 'created_at' => $now,
                    ]);
                } catch (\Throwable $e) {
                    continue;                     // the same row of this shipment was already linked
                }
                $linked++;
                if ($req) {
                    $upd = ['container_no' => $trk, 'transit_status' => 'IN_TRANSIT', 'transit_updated_at' => $now, 'shipment_id' => $sid, 'updated_at' => $now];
                    if (! in_array($req['stage'], ['LAB_RECEIVED', 'COMPLETED', 'FOLLOW_UP'], true)) {
                        $upd['stage'] = 'IN_TRANSIT';        // an imported history row that is now on its way joins the live flow
                    }
                    if ($req['tr_no'] === '' && preg_match('/^A\d{8,12}$/', $row['tr_no'])) {
                        $upd['tr_no'] = $row['tr_no'];
                    }
                    $db->table('tbl_vf_test_requests')->where('id', $req['id'])->update($upd);
                }
            }
        }
        $db->table('tbl_vf_test_events')->insert([
            'event_type' => 'EMAIL_SAMPLES_SENT', 'outlook_record_uid' => (string) ($em['record_uid'] ?? ''), 'subject' => (string) $em['subject'],
            'details_json' => json_encode(['tracking' => $p['tracking'], 'rows' => count($p['rows']), 'linked' => $linked], JSON_UNESCAPED_UNICODE), 'created_at' => $now,
        ]);

        return max(1, $linked);
    }

    // ------------------------------------------------------------------------------------------------------------ schedule, checks, alarm

    /** the next check: the first slot of "arrival time + 3 h, + 6 h, ..." that is still to come */
    public static function nextSlot(string $anchor, ?int $now = null): string
    {
        $now ??= time();
        $a    = strtotime($anchor) ?: $now;
        $step = self::hours() * 3600;
        $k    = max(1, (int) floor(($now - $a) / $step) + 1);

        return date('Y-m-d H:i:s', $a + $k * $step);
    }

    /** the shipments with their items, newest first, and what needs attention */
    public function listShipments(): array
    {
        $this->ensureTables();
        $db = $this->db();
        MailOutbox::ensureTable();
        $now   = time();
        $ships = $db->query('SELECT s.*, o.status AS outbox_status, o.last_error AS outbox_error FROM tbl_vf_shipments s LEFT JOIN tbl_email_outbox o ON o.id = s.mail_outbox_id ORDER BY s.id DESC LIMIT 200')->getResultArray();
        if (! $ships) {
            return ['shipments' => [], 'kpi' => ['shipments' => 0, 'due' => 0, 'updates' => 0, 'delivered' => 0]];
        }
        $ids   = array_column($ships, 'id');
        $items = $db->query('SELECT i.*, r.generated_application_no, r.stage, r.test_package, r.style_no AS req_style, r.colorway_c, r.colorway, r.crd AS req_crd, r.email_state, r.lab_order_no, r.lab_report_no, r.etrf_pdf_path
            FROM tbl_vf_shipment_items i LEFT JOIN tbl_vf_test_requests r ON r.id = i.request_id WHERE i.shipment_id IN (' . implode(',', array_map('intval', $ids)) . ') ORDER BY i.id')->getResultArray();
        $by = [];
        foreach ($items as $it) {
            $by[(int) $it['shipment_id']][] = [
                'id' => (int) $it['id'], 'request_id' => $it['request_id'] ? (int) $it['request_id'] : null, 'prod_code' => $it['prod_code'], 'mtl_no' => $it['mtl_no'],
                'style' => $it['style_name'] ?: $it['req_style'], 'color' => $it['color'] ?: ($it['colorway_c'] ?: $it['colorway']), 'state' => $it['state_text'] ?: $it['email_state'],
                'application_no' => $it['generated_application_no'] ?: $it['tr_no'], 'crd' => $it['req_crd'] ?: $it['crd'], 'stage' => $it['stage'] ?? '', 'package' => $it['test_package'] ?? '',
                'linked' => $it['request_id'] !== null,
            ];
        }
        $kpi = ['shipments' => count($ships), 'due' => 0, 'updates' => 0, 'delivered' => 0];
        foreach ($ships as &$s) {
            // every request of the shipment is already past "in transit" (the lab received it / it is done / it needs follow-up): the samples ARRIVED
            $its   = $by[(int) $s['id']] ?? [];
            $atLab = $its !== [] && count(array_filter($its, static fn ($i) => in_array((string) ($i['stage'] ?? ''), ['LAB_RECEIVED', 'COMPLETED', 'FOLLOW_UP', 'FAILED'], true))) === count($its);
            $due   = ! (int) $s['delivered'] && ! $atLab && strtotime((string) $s['next_check_at']) <= $now;
            $s['at_lab'] = $atLab;
            // an update is "waiting" until somebody has seen it (Seen / the e-mail to the lab was queued)
            $alarm = $s['alarm_at'] !== null && ($s['alarm_ack_at'] === null || strtotime((string) $s['alarm_at']) > strtotime((string) $s['alarm_ack_at']));
            $s['due']        = $due;
            $s['alarm']      = $alarm;
            $s['seconds_to_check'] = max(0, strtotime((string) $s['next_check_at']) - $now);
            $s['items']      = $by[(int) $s['id']] ?? [];
            $s['history']    = array_slice(array_reverse(json_decode((string) ($s['history_json'] ?? '[]'), true) ?: []), 0, 12);
            unset($s['history_json']);
            $kpi['due']       += $due ? 1 : 0;
            $kpi['updates']   += $alarm ? 1 : 0;
            $kpi['delivered'] += (int) $s['delivered'];
        }
        unset($s);

        return ['shipments' => $ships, 'kpi' => $kpi, 'check_url' => self::checkUrlTemplate(), 'carrier' => self::carrier(), 'hours' => self::hours()];
    }

    /**
     * The person checked the SF page and enters what it shows. A different status (or "delivered") raises the alarm; the next check is scheduled
     * on the 3-hour grid that started when the e-mail arrived.
     *
     * @return array{ok:bool,changed?:bool,error?:string}
     */
    public function recordCheck(int $shipmentId, string $statusText, ?string $eventAt, bool $delivered, string $by): array
    {
        $this->ensureTables();
        $db = $this->db();
        $s  = $db->table('tbl_vf_shipments')->where('id', $shipmentId)->get(1)->getRowArray();
        if (! $s) {
            return ['ok' => false, 'error' => 'Shipment not found.'];
        }
        $statusText = trim(preg_replace('/\s+/u', ' ', $statusText));
        if ($statusText === '' && ! $delivered) {
            return ['ok' => false, 'error' => 'Write what the SF page shows (the latest status line), or tick Delivered.'];
        }
        if ($statusText === '') {
            $statusText = 'Delivered';
        }
        $now     = date('Y-m-d H:i:s');
        $event   = $eventAt && ($t = strtotime($eventAt)) ? date('Y-m-d H:i:s', $t) : $now;
        $changed = mb_strtolower($statusText) !== mb_strtolower((string) $s['last_status']) || ($delivered && ! (int) $s['delivered']);
        $hist    = json_decode((string) ($s['history_json'] ?? '[]'), true) ?: [];
        $hist[]  = ['at' => $now, 'what' => ($changed ? 'UPDATE: ' : 'Checked, no change: ') . $statusText, 'by' => $by, 'event_at' => $event, 'delivered' => $delivered];
        $upd = [
            'last_check_at' => $now, 'last_check_by' => mb_substr($by, 0, 118), 'next_check_at' => self::nextSlot((string) $s['anchor_at']),
            'history_json' => json_encode(array_slice($hist, -60), JSON_UNESCAPED_UNICODE), 'updated_at' => $now,
        ];
        if ($changed) {
            $upd += ['last_status' => mb_substr($statusText, 0, 495), 'last_event_at' => $event, 'alarm_at' => $now, 'alarm_ack_at' => null];
        }
        if ($delivered) {
            $upd['delivered'] = 1;
        }
        $db->table('tbl_vf_shipments')->where('id', $shipmentId)->update($upd);
        if ($changed) {
            $db->table('tbl_vf_test_requests')->where('shipment_id', $shipmentId)->update(['transit_status' => mb_substr($statusText, 0, 58), 'transit_updated_at' => $now, 'updated_at' => $now]);
        }

        return ['ok' => true, 'changed' => $changed];
    }

    public function acknowledge(int $shipmentId): void
    {
        $this->ensureTables();
        $this->db()->table('tbl_vf_shipments')->where('id', $shipmentId)->update(['alarm_ack_at' => date('Y-m-d H:i:s')]);
    }

    // ------------------------------------------------------------------------------------------------------------ e-mail to the lab

    /** To / CC of the e-mail that tells the lab the samples are on their way (Settings keys vf.transit_to / vf.transit_cc) */
    public static function defaultRecipients(): array
    {
        return [
            'to' => (string) SystemSettings::get('vf.transit_to', 'Linda.ZhouT@sgs.com; dtp_cfa2@dtshoes.com; nila_erazo@dtshoes.com'),
            'cc' => (string) SystemSettings::get('vf.transit_cc', 'yoyo_lab@dtshoes.com; djc_lab@dtshoes.com'),
        ];
    }

    /**
     * The e-mail "Please be informed that our team sent the sample shoes to your Laboratory today ... The tracking number is SF...", with the table of the
     * ticked rows. Used by the dialog preview and by the send.
     *
     * @param list<int>            $itemIds ids of tbl_vf_shipment_items
     * @param array<string,string> $opts    to, cc, subject
     */
    public function buildEmail(int $shipmentId, array $itemIds, array $opts = []): array
    {
        $this->ensureTables();
        $db = $this->db();
        $s  = $db->table('tbl_vf_shipments')->where('id', $shipmentId)->get(1)->getRowArray();
        if (! $s) {
            return ['ok' => false, 'error' => 'Shipment not found.'];
        }
        $itemIds = array_values(array_filter(array_map('intval', $itemIds)));
        $q = $db->table('tbl_vf_shipment_items i')->select('i.*, r.generated_application_no, r.style_no AS req_style, r.colorway_c, r.colorway, r.crd AS req_crd, r.email_state, r.test_package, r.lab_order_no, r.lab_report_no')
            ->join('tbl_vf_test_requests r', 'r.id = i.request_id', 'left')->where('i.shipment_id', $shipmentId);
        if ($itemIds) {
            $q->whereIn('i.id', $itemIds);
        }
        $items = $q->orderBy('i.id')->get()->getResultArray();
        if (! $items) {
            return ['ok' => false, 'error' => 'Tick at least one request of this shipment.'];
        }
        $esc  = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $sig  = static fn (string $k, string $d = '') => (string) SystemSettings::get($k, $d);
        $th   = 'style="border:1px solid #000;background:#92d050;padding:3px 8px;font-weight:normal;font-family:\'Courier New\',monospace;font-size:13px"';
        $td   = 'style="border:1px solid #000;padding:3px 8px;font-family:\'Courier New\',monospace;font-size:12.5px;vertical-align:top"';
        $rows = '';
        $mtls = [];
        $reports = [];
        $pkg  = '';
        foreach ($items as $i) {
            $mtls[]    = $i['mtl_no'];
            $pkg       = $pkg ?: (string) ($i['test_package'] ?? '');
            foreach ([$i['lab_order_no'] ?? '', $i['lab_report_no'] ?? ''] as $rp) {
                if (preg_match('/^SL\d{10,}FW$/i', (string) $rp)) {
                    $reports[] = strtoupper($rp);
                }
            }
            // what the shipment e-mail itself said comes first (that is what the lab was told); the request record fills what it left out
            $state = (string) ($i['state_text'] ?: (($i['email_state'] ?? '') ?: 'Need to test'));
            $rows .= '<tr><td ' . $td . '>' . $esc($i['prod_code']) . '</td><td ' . $td . '>' . $esc($i['style_name'] ?: ($i['req_style'] ?? '')) . '</td><td ' . $td . '>' . $esc($i['mtl_no']) . '</td>'
                . '<td ' . $td . '>' . $esc($i['color'] ?: (($i['colorway_c'] ?? '') ?: ($i['colorway'] ?? ''))) . '</td>'
                . '<td ' . $td . '><b style="color:#e00000;font-family:Arial,sans-serif">' . $esc($state) . '</b></td>'
                . '<td ' . $td . '>' . $esc($i['generated_application_no'] ?: $i['tr_no']) . '</td><td ' . $td . '>' . $esc($i['req_crd'] ?: $i['crd']) . '</td></tr>';
        }
        $short = strtoupper(trim(preg_replace('/\s*test$/i', '', VfTestRequestService::shortTypeForPackage($pkg) ?: 'CPSIA')));
        $subject = trim((string) ($opts['subject'] ?? '')) ?: ('da tian SUBIC --VF ' . $short . '--' . implode('/', array_values(array_unique($mtls)))
            . ($reports ? '-' . implode(' /', array_values(array_unique($reports))) : ''));
        $def = self::defaultRecipients();
        $to  = trim((string) ($opts['to'] ?? '')) ?: $def['to'];
        $cc  = array_key_exists('cc', $opts) ? trim((string) $opts['cc']) : $def['cc'];

        $logo = '';
        $logoPath = trim($sig('vf.mail_logo'));
        if ($logoPath !== '' && is_file($logoPath)) {
            $ext  = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
            $mime = $ext === 'png' ? 'image/png' : ($ext === 'gif' ? 'image/gif' : 'image/jpeg');
            $logo = '<p><img alt="" src="data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($logoPath)) . '" style="height:90px"></p>';
        }
        $latest = trim((string) $s['last_status']) !== '' ? '<p style="margin:0 0 10px">Latest tracking update (' . $esc($s['last_event_at'] ?: $s['last_check_at']) . '): ' . $esc($s['last_status']) . '</p>' : '';
        $body = '<div style="font-family:Calibri,Arial,sans-serif;font-size:13.5px;color:#000;line-height:1.5">'
            . '<p>' . $esc($sig('vf.transit_greeting', 'Dear Ms. Linda,')) . '</p><p>Good day!</p>'
            . '<p>Please be informed that our team sent the sample shoes to your Laboratory today ' . $esc(date('n/j')) . '. The tracking number is <b style="background:#ffff00">' . $esc($s['tracking_no']) . '</b>.</p>'
            . $latest
            . '<p>We would also appreciate if you could provide us with an update on the testing and expected date of release of the test results for the following styles.</p>'
            . '<table style="border-collapse:collapse"><tr><th ' . $th . '>指令編號</th><th ' . $th . '>型體代號</th><th ' . $th . '>MTL#</th><th ' . $th . '>顏色</th><th ' . $th . '>State</th><th ' . $th . '>TR#</th><th ' . $th . '>CRD</th></tr>' . $rows . '</table>'
            . '<p style="font-family:\'Monotype Corsiva\',\'Segoe Script\',cursive;font-size:17px;font-weight:bold;color:#1f3864"><i>Thank you and have a blessed day!</i></p>'
            . '<p style="font-family:Arial,sans-serif;font-size:11px;font-weight:bold;color:#1f3864;margin-bottom:2px">Best Regards,</p>'
            . '<p style="margin-top:2px"><b><u style="color:#ff0000;font-size:20px">' . $esc($sig('vf.sign_name', 'QC Laboratory Team')) . '</u></b><br>'
            . '<b style="font-family:Arial,sans-serif;font-size:13px;color:#2e75b6">' . $esc($sig('vf.sign_title')) . '</b><br>'
            . '<b style="font-family:Arial,sans-serif;font-size:13px;color:#000"><i>' . $esc($sig('vf.sign_company', 'DA TIAN SUBIC SHOES INC.')) . '</i></b><br>'
            . '<i style="font-family:Arial,sans-serif;font-size:12px;color:#2e75b6">Telephone No.: ' . $esc($sig('vf.sign_phone')) . '</i><br>'
            . '<i style="font-family:Arial,sans-serif;font-size:12px;color:#2e75b6">' . $esc($sig('vf.sign_address')) . '</i></p>' . $logo . '</div>';

        return ['ok' => true, 'shipment_id' => $shipmentId, 'to' => $to, 'cc' => $cc, 'subject' => $subject, 'body' => $body, 'count' => count($items), 'tracking_no' => $s['tracking_no']];
    }

    /** queues the e-mail (MailOutbox -> Hub / Outlook) and marks the shipment as mailed */
    public function sendEmail(int $shipmentId, array $itemIds, array $opts, int $userId): array
    {
        $b = $this->buildEmail($shipmentId, $itemIds, $opts);
        if (empty($b['ok'])) {
            return $b;
        }
        [$outboxId, $err] = MailOutbox::enqueue($b['to'], $b['subject'], $b['body'], ['cc' => $b['cc'], 'is_html' => true, 'source' => 'NEXUS-L VF-Transit', 'sender_name' => 'DTP QC Lab']);
        if ($outboxId <= 0) {
            return ['ok' => false, 'error' => $err ?: 'Failed to queue the e-mail.'];
        }
        $db   = $this->db();
        $now  = date('Y-m-d H:i:s');
        $s    = $db->table('tbl_vf_shipments')->where('id', $shipmentId)->get(1)->getRowArray();
        $hist = json_decode((string) ($s['history_json'] ?? '[]'), true) ?: [];
        $hist[] = ['at' => $now, 'what' => 'E-mail to the lab queued (' . $b['count'] . ' request(s)): ' . $b['to'], 'by' => ''];
        $db->table('tbl_vf_shipments')->where('id', $shipmentId)->update([
            'mailed_at' => $now, 'mail_outbox_id' => $outboxId, 'alarm_ack_at' => $now, 'history_json' => json_encode(array_slice($hist, -60), JSON_UNESCAPED_UNICODE), 'updated_at' => $now,
        ]);

        return ['ok' => true, 'outbox_id' => $outboxId, 'to' => $b['to'], 'count' => $b['count']];
    }

    // ------------------------------------------------------------------------------------------------------------ Request Material Monitoring

    /** SENDING / IN_TRANSIT / TESTING / DONE / FOLLOW_UP for one request row */
    public static function materialStatus(array $r): string
    {
        $stage = strtoupper((string) ($r['stage'] ?? ''));
        $res   = strtoupper((string) ($r['test_result'] ?? ''));
        if ($stage === 'FOLLOW_UP' || $stage === 'FAILED' || ($stage === 'COMPLETED' && in_array($res, ['FAIL', 'FAILED'], true))) {
            return 'FOLLOW_UP';
        }
        if ($stage === 'COMPLETED') {
            return 'DONE';
        }
        if ($stage === 'LAB_RECEIVED') {
            return 'TESTING';
        }
        if ($stage === 'IN_TRANSIT') {
            return 'IN_TRANSIT';
        }

        return 'SENDING';
    }

    /** every request with its overall data and status (not the Excel history) */
    public function materialRows(string $status = '', string $q = '', string $pkg = ''): array
    {
        $this->ensureTables();
        $db = $this->db();
        $qb = $db->table('tbl_vf_test_requests r')
            ->select('r.id, r.prod_code, r.mtl_no, r.style_no, r.colorway_c, r.colorway, r.test_package, r.test_type_short, r.crd, r.generated_application_no, r.tr_no, r.container_no, r.transit_status, r.transit_updated_at, r.lab_name, r.lab_order_no, r.lab_report_no, r.expected_due_date, r.expected_due_remark, r.lab_received_at, r.test_result, r.fail_reason, r.certificate_pdf_name, r.certificate_pdf_path, r.cert_email_subject, r.cert_email_at, r.stage, r.completed_at, r.updated_at, r.batch_id, r.interlink_status, r.etrf_emailed_at, r.etrf_pdf_path, r.fail_reason AS follow_reason, r.issue_date, r.validity_date, s.last_status AS ship_status, s.last_event_at AS ship_event_at, s.delivered AS ship_delivered, b.subject AS batch_subject, b.sender_name AS batch_sender_name, b.sender_email AS batch_sender, b.received_at AS batch_received_at')
            ->join('tbl_vf_shipments s', 's.id = r.shipment_id', 'left')
            ->join('tbl_vf_email_batches b', 'b.id = r.batch_id', 'left')
            ->where('r.stage !=', 'HISTORY');
        if ($pkg !== '') {
            $qb->where('r.test_package', $pkg);
        }
        if ($q !== '') {
            $qb->groupStart()->like('r.prod_code', $q)->orLike('r.mtl_no', $q)->orLike('r.style_no', $q)->orLike('r.generated_application_no', $q)->orLike('r.container_no', $q)->orLike('r.lab_report_no', $q)->orLike('r.lab_order_no', $q)->groupEnd();
        }
        $rows   = $qb->orderBy('r.id', 'DESC')->get(1500)->getResultArray();
        $counts = ['SENDING' => 0, 'IN_TRANSIT' => 0, 'TESTING' => 0, 'DONE' => 0, 'FOLLOW_UP' => 0];
        $out    = [];
        foreach ($rows as $r) {
            $st = self::materialStatus($r);
            $counts[$st]++;
            if ($status !== '' && $status !== $st) {
                continue;
            }
            $r['status']           = $st;
            $r['application_no']   = $r['generated_application_no'] ?: (preg_match('/^A\d{8,12}$/', (string) $r['tr_no']) ? $r['tr_no'] : '');
            $r['color']            = $r['colorway_c'] ?: $r['colorway'];
            $r['has_certificate']  = (string) $r['certificate_pdf_path'] !== '' && is_file((string) $r['certificate_pdf_path']);
            $r['has_email']        = (string) $r['cert_email_subject'] !== '';
            $r['has_etrf_pdf']     = (string) $r['etrf_pdf_path'] !== '' && is_file((string) $r['etrf_pdf_path']);
            unset($r['certificate_pdf_path'], $r['etrf_pdf_path'], $r['colorway_c'], $r['colorway']);
            $out[] = $r;
        }

        return ['rows' => $out, 'counts' => $counts, 'total' => count($rows)];
    }
}
