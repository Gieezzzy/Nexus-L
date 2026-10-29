<?php

namespace App\Controllers;

use App\Libraries\Auth;
use App\Libraries\ReportPdf;
use App\Libraries\SynologyChat;

/**
 * Report sign-off before a report goes to InterLink.
 *
 * 1. Upload Form: "Generate Report" makes the PDF (named <Application No>.pdf). Without an approval the user asks for
 *    one: the PDF is stored with the request and a QR code / link to approve/<token> is shown.
 * 2. Every active signatory (tbl_dtr_signatories) is told through Synology Chat and the web feed.
 * 3. A signatory opens the link, signs in, checks the report and approves (signature + date stamped on page 1) or
 *    rejects with a remark. The requester is told the same way, and the Upload Form updates by itself.
 * 4. Only the signed PDF can be sent to InterLink.
 *
 * The link is temporary and single-use: it works only while the request is pending and for LINK_MINUTES, every
 * request / renewal creates a different link (the old one stops working), and a decision kills it for good. The
 * stored PDF is reached through the permanent `file_key`, never through the link.
 */
class ApprovalController extends BaseController
{
    private const LINK_MINUTES = 60;

    private function db()
    {
        return \Config\Database::connect();
    }

    private function dir(): string
    {
        $d = WRITEPATH . 'approvals/';
        if (! is_dir($d)) {
            @mkdir($d, 0775, true);
        }

        return $d;
    }

    private function json(array $data, int $code = 200)
    {
        return $this->response->setStatusCode($code)->setJSON($data + ['csrf' => csrf_hash()]);
    }

    private function me(): array
    {
        return (array) service('auth')->user();
    }

    /** the signatory row of the signed-in user, or null */
    private function mySignatory(): ?array
    {
        $id = (int) ($this->me()['id'] ?? 0);

        return $id ? $this->db()->table('tbl_dtr_signatories')->where(['user_id' => $id, 'is_active' => 1])->get()->getRowArray() : null;
    }

    private function labref(string $labRef): ?array
    {
        return $labRef === '' ? null : $this->db()->table('tbl_dtr_unified_labrefs')->where('lab_ref_no', $labRef)->orderBy('id', 'DESC')->get()->getRowArray();
    }

    private function latest(string $labRef): ?array
    {
        return $this->db()->table('tbl_dtr_report_approvals')->where('lab_ref_no', $labRef)->where('status !=', 'superseded')->orderBy('id', 'DESC')->get()->getRowArray();
    }

    private function publicRow(?array $a): ?array
    {
        if (! $a) {
            return null;
        }

        $live = $a['status'] === 'pending' && ! empty($a['expires_at']) && strtotime((string) $a['expires_at']) > time();

        return [
            'status' => $a['status'], 'lab_ref_no' => $a['lab_ref_no'], 'application_no' => $a['application_no'],
            'requested_name' => $a['requested_name'], 'requested_at' => $a['requested_at'], 'decided_name' => $a['decided_name'],
            'decided_title' => $a['decided_title'], 'decided_at' => $a['decided_at'], 'remarks' => $a['remarks'],
            'expired' => $a['status'] === 'pending' && ! $live, 'expires_at' => $a['expires_at'], 'expires_in' => $live ? strtotime((string) $a['expires_at']) - time() : 0,
            'url' => $live ? site_url('approve/' . $a['token']) : null, // only a live link is ever handed out
            'file' => site_url('dtr/approval/file/' . ($a['file_key'] ?: $a['token'])),
        ];
    }

    /** The request behind a link that can still be used (pending, not expired), else null. */
    private function liveLink(string $token): ?array
    {
        if (! preg_match('/^[a-f0-9]{40}$/', $token)) {
            return null;
        }
        $a = $this->db()->table('tbl_dtr_report_approvals')->where(['token' => $token, 'status' => 'pending'])->where('expires_at >', date('Y-m-d H:i:s'))->get()->getRowArray();

        return $a ?: null;
    }

    /**
     * Keeps the report sheet (HTML) the approver reads, next to the PDF. Presentation only: no scripts, frames, forms,
     * external stylesheets, event handlers or javascript: / data: / file: links (same scrubbing as dtr/sheet-pdf).
     */
    private function saveSheet(string $key, string $html): void
    {
        $f = $this->dir() . $key . '.html';
        if ($html === '' || strlen($html) > 2000000) {
            @unlink($f);

            return;
        }
        $html = preg_replace('#<(script|iframe|object|embed|link|base|meta|form|style)\b[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace('#<(script|iframe|object|embed|link|base|meta|form)\b[^>]*/?>#is', '', $html);
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = preg_replace('/(href|src)\s*=\s*(["\'])\s*(javascript|vbscript|data:text|file):[^"\']*\2/i', '$1=$2$2', $html);
        file_put_contents($f, $html);
    }

    private function sheetFor(array $a): string
    {
        $f = $this->dir() . ($a['file_key'] ?: $a['token']) . '.html';

        return is_file($f) ? (string) file_get_contents($f) : '';
    }

    private function spentToken(): string
    {
        return 'x' . bin2hex(random_bytes(19)); // can never match a link (links are 40 hex digits)
    }

    private function synology(array $userIds, string $text): void
    {
        $rows = $this->db()->table('tbl_users')->select('SynologyID')->whereIn('UserID', $userIds ?: [0])->get()->getResultArray();
        $ids  = array_values(array_filter(array_map('intval', array_column($rows, 'SynologyID'))));
        if (! $ids) {
            return;
        }
        // one message per person: Synology rejects a list of several recipients with "record is not valid"
        foreach (array_unique($ids) as $id) {
            try {
                (new SynologyChat())->send($id, $text);
            } catch (\Throwable $e) {
                log_message('error', 'Approval Synology notice failed: ' . $e->getMessage());
            }
        }
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Report PDF
    // ---------------------------------------------------------------------------------------------------------------

    /** GET dtr/report-pdf?lab_ref=..&app=.. : the official Access report of a material Lab Ref, named <app>.pdf */
    public function reportPdf()
    {
        $lr = $this->labref(trim((string) $this->request->getGet('lab_ref')));
        if (! $lr) {
            return $this->response->setStatusCode(404)->setBody('Lab Reference not found.');
        }
        if ($lr['form_slug'] === 'bonding') {
            return $this->response->setStatusCode(422)->setBody('Bonding reports are made from the VF sheet in the browser.');
        }
        $pdf = ReportPdf::material((string) $lr['form_slug'], (int) $lr['source_id']);
        if (! $pdf) {
            return $this->response->setStatusCode(500)->setBody('The Access report could not be printed for ' . $lr['lab_ref_no'] . ' (Microsoft Access must be installed on the server).');
        }
        $app = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($this->request->getGet('app') ?: $lr['tr_no'] ?: $lr['lab_ref_no']));

        return $this->response->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="' . $app . '.pdf"')
            ->setBody((string) file_get_contents($pdf));
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Approval requests
    // ---------------------------------------------------------------------------------------------------------------

    /** GET dtr/approval/status?lab_ref= */
    public function status()
    {
        $a = $this->latest(trim((string) $this->request->getGet('lab_ref')));
        $signers = (int) $this->db()->table('tbl_dtr_signatories')->where('is_active', 1)->countAllResults();

        return $this->json(['ok' => true, 'approval' => $this->publicRow($a), 'signatories' => $signers]);
    }

    /** POST dtr/approval/request {lab_ref, application_no, pdf_b64}: stores the report PDF and asks the signatories */
    public function request()
    {
        $p   = $this->request->getJSON(true) ?? [];
        $lr  = $this->labref(trim((string) ($p['lab_ref'] ?? '')));
        $app = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($p['application_no'] ?? ''));
        $pdf = base64_decode((string) ($p['pdf_b64'] ?? ''), true);
        if (! $lr || $pdf === false || ! str_starts_with($pdf, '%PDF') || strlen($pdf) > 20 * 1024 * 1024) {
            return $this->json(['ok' => false, 'error' => 'Generate the report first, then ask for approval.'], 422);
        }
        $me  = $this->me();
        $now = date('Y-m-d H:i:s');
        $a   = $this->latest($lr['lab_ref_no']);
        if ($a && $a['status'] === 'approved' && empty($p['replace'])) {
            return $this->json(['ok' => true, 'approval' => $this->publicRow($a), 'message' => 'This report is already approved.']);
        }
        // the approver reads the report exactly as printed: page images of this PDF (always the same file that is signed and sent)
        $pages = ReportPdf::pagesHtml($pdf);
        if ($pages !== '') {
            $p['sheet_html'] = '<div class="apv-pages">' . $pages . '</div>';
        }
        // the name printed after "Approved by" on the report is the one who must sign it
        $tmp = tempnam(sys_get_temp_dir(), 'so');
        file_put_contents($tmp, $pdf);
        $printed = trim((string) (ReportPdf::signoff($tmp)['approved']['name'] ?? ''));
        @unlink($tmp);
        $signRows = $this->db()->table('tbl_dtr_signatories')->where('is_active', 1)->get()->getResultArray();
        if ($printed !== '') {
            $signRows = array_values(array_filter($signRows, static fn ($r) => ReportPdf::nameMatches($printed, (string) $r['full_name'])));
            if (! $signRows) {
                return $this->json(['ok' => false, 'error' => 'This report says "Approved by ' . $printed . '", but no registered signatory has that name. Ask an admin to add ' . $printed . ' in Report Designer → Signatories (with their NEXUS-L account), then request approval again.'], 422);
            }
        }
        if ($a && $a['status'] === 'approved') {
            // a new version of an approved report: the approved one is kept on file but no longer counts, so nothing is sent
            // to InterLink until the new version is approved
            $this->db()->table('tbl_dtr_report_approvals')->where('id', $a['id'])->update(['status' => 'superseded', 'token' => $this->spentToken(), 'updated_at' => $now]);
            $a = null;
        }
        $token   = bin2hex(random_bytes(20));
        $expires = date('Y-m-d H:i:s', time() + self::LINK_MINUTES * 60);
        if ($a && $a['status'] === 'pending') {
            // asked again (new file, or the link expired): the old link dies, a different one is made
            $key = $a['file_key'] ?: $a['token'];
            file_put_contents($this->dir() . $key . '.pdf', $pdf);
            $this->saveSheet($key, (string) ($p['sheet_html'] ?? ''));
            $this->db()->table('tbl_dtr_report_approvals')->where('id', $a['id'])->update([
                'token' => $token, 'file_key' => $key, 'expires_at' => $expires, 'application_no' => $app ?: $a['application_no'],
                'requested_by' => (int) ($me['id'] ?? 0), 'requested_name' => (string) ($me['name'] ?? $me['username'] ?? ''), 'requested_at' => $now, 'updated_at' => $now,
                'required_name' => $printed !== '' ? mb_substr($printed, 0, 120) : null,
            ]);
        } else {
            $key = bin2hex(random_bytes(20));
            file_put_contents($this->dir() . $key . '.pdf', $pdf);
            $this->saveSheet($key, (string) ($p['sheet_html'] ?? ''));
            $this->db()->table('tbl_dtr_report_approvals')->insert([
                'token' => $token, 'file_key' => $key, 'lab_ref_no' => $lr['lab_ref_no'], 'application_no' => $app ?: $lr['tr_no'], 'form_slug' => $lr['form_slug'],
                'source_id' => (int) $lr['source_id'], 'status' => 'pending', 'requested_by' => (int) ($me['id'] ?? 0),
                'requested_name' => (string) ($me['name'] ?? $me['username'] ?? ''), 'requested_at' => $now, 'expires_at' => $expires, 'updated_at' => $now,
                'required_name' => $printed !== '' ? mb_substr($printed, 0, 120) : null,
            ]);
        }
        $signers = array_column($signRows, 'user_id');
        $this->synology($signers, "NEXUS-L: report {$lr['lab_ref_no']} (" . ($app ?: $lr['tr_no']) . ", {$lr['overall_result']}) from "
            . ($me['name'] ?? 'the lab') . " is waiting for your approval before it goes to InterLink.\nThis link works once and expires in " . self::LINK_MINUTES . " minutes:\n" . site_url('approve/' . $token));

        return $this->json(['ok' => true, 'approval' => $this->publicRow($this->latest($lr['lab_ref_no']))]);
    }

    /**
     * GET dtr/approval/verify?lab_ref=: the sender extension asks this right before it opens InterLink and again right before it
     * presses Save. Only an APPROVED report may be saved in InterLink; `sha256` is the hash of the signed PDF, which the extension
     * compares with the file it is about to attach, so no other file can go in under an approval.
     */
    public function verify()
    {
        $a      = $this->latest(trim((string) $this->request->getGet('lab_ref')));
        $signed = $a && $a['status'] === 'approved' ? $this->dir() . ($a['file_key'] ?: $a['token']) . '_signed.pdf' : '';
        if ($signed === '' || ! is_file($signed)) {
            return $this->json(['ok' => true, 'approved' => false, 'status' => $a['status'] ?? 'none']);
        }

        return $this->json(['ok' => true, 'approved' => true, 'sha256' => hash_file('sha256', $signed), 'by' => $a['decided_name'], 'at' => $a['decided_at']]);
    }

    /** POST dtr/approval/pdf-pages {pdf_b64}: the report as page images, for the report view (no PDF viewer needed) */
    public function pdfPages()
    {
        $p   = $this->request->getJSON(true) ?? [];
        $pdf = base64_decode((string) ($p['pdf_b64'] ?? ''), true);
        if ($pdf === false || ! str_starts_with($pdf, '%PDF') || strlen($pdf) > 20 * 1024 * 1024) {
            return $this->json(['ok' => false, 'error' => 'No report to show.'], 422);
        }
        $html = ReportPdf::pagesHtml($pdf);

        return $html === '' ? $this->json(['ok' => false, 'error' => 'The report pages could not be drawn.'], 500) : $this->json(['ok' => true, 'html' => $html]);
    }

    /** GET dtr/approval/file/<file_key>: the signed PDF once approved, else the PDF waiting for approval (signed-in users) */
    public function file(string $key)
    {
        $a = preg_match('/^[a-f0-9]{40}$/', $key) ? $this->db()->table('tbl_dtr_report_approvals')->where('file_key', $key)->get()->getRowArray() : null;
        if (! $a) {
            return $this->response->setStatusCode(404)->setBody('Not found.');
        }

        return $this->sendPdf($a, $a['status'] === 'approved');
    }

    /** GET approve/<token>/pdf: the report shown on the approval page; works only while the link is live */
    public function preview(string $token)
    {
        $a = $this->liveLink($token);
        if (! $a) {
            return $this->response->setStatusCode(410)->setBody('This approval link is no longer valid.');
        }

        return $this->sendPdf($a, false);
    }

    private function sendPdf(array $a, bool $signed)
    {
        $key = $a['file_key'] ?: $a['token'];
        $f   = $this->dir() . $key . ($signed ? '_signed.pdf' : '.pdf');
        if (! is_file($f)) {
            return $this->response->setStatusCode(404)->setBody('The report file is missing.');
        }

        return $this->response->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($a['application_no'] ?: $a['lab_ref_no'])) . '.pdf"')
            ->setBody((string) file_get_contents($f));
    }

    /** GET approve/<token>: the page the QR code opens */
    public function page(string $token)
    {
        $a = $this->liveLink($token);
        if (! $a) {
            // used, expired or never existed: all look the same, and nothing about the report is shown
            return $this->response->setStatusCode(410)->setBody($this->render('approval/gone', ['title' => 'Approval link', 'withCss' => 'approval.css']));
        }

        return $this->render('approval/page', [
            'title'     => 'Report approval',
            'withCss'   => 'approval.css',
            'withJs'    => ['approval.js'],
            'approval'  => $a,
            'sheet'     => $this->sheetFor($a),
            'signatory' => $this->mySignatory(),
        ]);
    }

    /** POST approve/<token> {action: approve|reject, signature (data URL), remarks} */
    public function decide(string $token)
    {
        $a  = $this->liveLink($token);
        $sg = $this->mySignatory();
        $p  = $this->request->getJSON(true) ?? [];
        if (! $a) {
            return $this->json(['ok' => false, 'error' => 'This approval link was already used or has expired. Ask for a new one.'], 410);
        }
        if (! $sg) {
            return $this->json(['ok' => false, 'error' => 'Only a registered Manager or Supervisor can approve reports.'], 403);
        }
        if (! empty($a['required_name']) && ! ReportPdf::nameMatches((string) $a['required_name'], (string) $sg['full_name'])) {
            return $this->json(['ok' => false, 'error' => 'This report is signed off by ' . $a['required_name'] . '. Only ' . $a['required_name'] . ' can approve it: sign in with that account.'], 403);
        }
        $now    = date('Y-m-d H:i:s');
        $action = ($p['action'] ?? '') === 'reject' ? 'rejected' : 'approved';
        $remark = trim((string) ($p['remarks'] ?? ''));
        $data   = ['status' => $action, 'signatory_id' => $sg['id'], 'decided_name' => $sg['full_name'], 'decided_title' => $sg['title'],
            'decided_at' => $now, 'used_at' => $now, 'token' => $this->spentToken(), 'remarks' => $remark !== '' ? mb_substr($remark, 0, 1000) : null, 'seen_by_requester' => 0, 'updated_at' => $now];

        if ($action === 'rejected' && $remark === '') {
            return $this->json(['ok' => false, 'error' => 'Write what needs to be corrected.'], 422);
        }
        if ($action === 'approved') {
            $sig = (string) ($p['signature'] ?? '') ?: (string) $sg['signature_png'];
            if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $sig, $m) || strlen($m[1]) > 2000000) {
                return $this->json(['ok' => false, 'error' => 'Sign in the box first.'], 422);
            }
            $key = $a['file_key'] ?: $a['token'];
            $ok  = ReportPdf::stamp($this->dir() . $key . '.pdf', $this->dir() . $key . '_signed.pdf', [
                'png' => $m[1], 'name' => $sg['full_name'], 'title' => $sg['title'], 'date' => date('Y-m-d H:i', strtotime($now)),
                'kind' => $a['form_slug'] === 'bonding' ? 'web' : 'access',
            ]);
            if (! $ok) {
                return $this->json(['ok' => false, 'error' => 'The signature could not be put on the report.'], 500);
            }
            $data['signature_png'] = $sig;
        }
        $this->db()->table('tbl_dtr_report_approvals')->where('id', $a['id'])->update($data);
        // told right away, before the answer goes back: the requester (the report can now be sent / must be corrected) and the
        // other signatories (so nobody opens a link that no longer works)
        $when = date('H:i', strtotime($now));
        $this->synology([(int) $a['requested_by']], "NEXUS-L: report {$a['lab_ref_no']} ({$a['application_no']}) was " . strtoupper($action)
            . " by {$sg['full_name']} at {$when}" . ($remark !== '' ? ": {$remark}" : '') . ($action === 'approved' ? '. The signed report is ready: you can now send it to InterLink.' : '. Correct it and request approval again.'));
        $others = array_column(array_filter($this->db()->table('tbl_dtr_signatories')->where('is_active', 1)->get()->getResultArray(),
            static fn ($r) => empty($a['required_name']) || ReportPdf::nameMatches((string) $a['required_name'], (string) $r['full_name'])), 'user_id');
        $others = array_values(array_diff(array_map('intval', $others), [(int) $sg['user_id'], (int) $a['requested_by']]));
        $this->synology($others, "NEXUS-L: report {$a['lab_ref_no']} ({$a['application_no']}) was already " . $action . " by {$sg['full_name']} at {$when}. Its approval link no longer works; nothing more to do.");

        return $this->json(['ok' => true, 'approval' => $this->publicRow($this->latest($a['lab_ref_no']))]);
    }

    /**
     * GET dtr/approval/feed?after=<id>: polled by every page. For signatories: reports waiting for them; for requesters:
     * decisions on their requests. New items come back as events for a toast / browser notification.
     */
    public function feed()
    {
        $me    = $this->me();
        $uid   = (int) ($me['id'] ?? 0);
        $after = (string) $this->request->getGet('after');
        $out   = ['ok' => true, 'pending' => 0, 'events' => [], 'cursor' => date('Y-m-d H:i:s')];
        if ($sg = $this->mySignatory()) {
            // only what this signatory can still act on: link not expired, not decided, and (when the report names its approver) theirs
            $live = array_values(array_filter(
                $this->db()->table('tbl_dtr_report_approvals')->where('status', 'pending')->where('expires_at >', date('Y-m-d H:i:s'))->orderBy('id', 'DESC')->get(60)->getResultArray(),
                static fn ($a) => empty($a['required_name']) || ReportPdf::nameMatches((string) $a['required_name'], (string) $sg['full_name']),
            ));
            $out['pending'] = count($live);
            $out['waiting'] = array_map(static fn ($a) => ['text' => "{$a['lab_ref_no']} · {$a['application_no']} · {$a['requested_name']}", 'url' => site_url('approve/' . $a['token'])], array_slice($live, 0, 10));
            if ($after !== '') {
                foreach (array_reverse($live) as $a) {
                    if ((string) $a['updated_at'] > $after) {
                        $out['events'][] = ['kind' => 'pending', 'text' => "{$a['lab_ref_no']} ({$a['application_no']}) needs your approval", 'url' => site_url('approve/' . $a['token'])];
                    }
                }
            }
        }
        if ($after !== '' && $uid) {
            foreach ($this->db()->table('tbl_dtr_report_approvals')->where('requested_by', $uid)->whereIn('status', ['approved', 'rejected'])->where('updated_at >', $after)->orderBy('id')->get(20)->getResultArray() as $a) {
                $out['events'][] = ['kind' => $a['status'], 'text' => "{$a['lab_ref_no']} was {$a['status']} by {$a['decided_name']}" . ($a['remarks'] ? ": {$a['remarks']}" : ''),
                    'url' => site_url('interlink'), 'lab_ref' => $a['lab_ref_no']];
            }
        }

        return $this->json($out);
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Per-user file name format
    // ---------------------------------------------------------------------------------------------------------------

    /**
     * GET / POST dtr/approval/name-format {format}: the file name the signed report gets when it is sent to InterLink, per
     * user. Tokens: {app} {tr} {labref} {style} {material} {result} {date}. Default {app}.
     */
    public function nameFormat()
    {
        $uid = (int) ($this->me()['id'] ?? 0);
        $key = 'pdf_name_fmt_' . $uid;
        $t   = $this->db()->table('tbl_app_settings');
        if (strtolower($this->request->getMethod()) === 'post') {
            $fmt = trim((string) (($this->request->getJSON(true) ?? [])['format'] ?? ''));
            // letters, digits, spaces and . _ - ( ) [ ] plus {token} only: it becomes part of a file name
            if ($fmt !== '' && (! preg_match('/^[A-Za-z0-9 ._\-()\[\]{}]{1,80}$/', $fmt) || preg_match('/\{(?!(app|tr|labref|style|material|result|date)\})/', $fmt))) {
                return $this->json(['ok' => false, 'error' => 'Use letters, numbers, spaces, . _ - ( ) and the tokens {app} {tr} {labref} {style} {material} {result} {date}.'], 422);
            }
            $now = date('Y-m-d H:i:s');
            $has = $this->db()->table('tbl_app_settings')->where('skey', $key)->countAllResults() > 0;
            if ($fmt === '') {
                $this->db()->table('tbl_app_settings')->where('skey', $key)->delete();
            } elseif ($has) {
                $this->db()->table('tbl_app_settings')->where('skey', $key)->update(['svalue' => $fmt, 'updated_by' => (string) ($this->me()['username'] ?? ''), 'updated_at' => $now]);
            } else {
                $t->insert(['skey' => $key, 'svalue' => $fmt, 'updated_by' => (string) ($this->me()['username'] ?? ''), 'updated_at' => $now]);
            }
        }
        $row = $this->db()->table('tbl_app_settings')->where('skey', $key)->get()->getRowArray();

        return $this->json(['ok' => true, 'format' => $row['svalue'] ?? '{app}']);
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Admin: signatories
    // ---------------------------------------------------------------------------------------------------------------

    /** GET designer/signatories */
    public function signatories()
    {
        $rows  = $this->db()->table('tbl_dtr_signatories s')->select('s.*, u.Username, u.SynologyID')->join('tbl_users u', 'u.UserID = s.user_id', 'left')->orderBy('s.full_name')->get()->getResultArray();
        $users = $this->db()->table('tbl_users')->select('UserID, Username, FullName, Role')->where('IsActive', 1)->orderBy('FullName')->get()->getResultArray();

        return $this->json(['ok' => true, 'rows' => $rows, 'users' => $users]);
    }

    /** POST designer/signatories/save {id?, user_id, full_name, title, signature_png?, is_active} */
    public function saveSignatory()
    {
        $p   = $this->request->getJSON(true) ?? [];
        $uid = (int) ($p['user_id'] ?? 0);
        $sig = (string) ($p['signature_png'] ?? '');
        if (! $uid || ! $this->db()->table('tbl_users')->where('UserID', $uid)->countAllResults()) {
            return $this->json(['ok' => false, 'error' => 'Pick the NEXUS-L account this signatory signs in with.'], 422);
        }
        if ($sig !== '' && ! preg_match('#^data:image/png;base64,[A-Za-z0-9+/=]+$#', $sig)) {
            return $this->json(['ok' => false, 'error' => 'The signature must be drawn in the box.'], 422);
        }
        $data = [
            'user_id' => $uid, 'full_name' => mb_substr(trim((string) ($p['full_name'] ?? '')), 0, 120), 'title' => mb_substr(trim((string) ($p['title'] ?? '')), 0, 80) ?: 'QA Laboratory Supervisor',
            'is_active' => ! empty($p['is_active']) ? 1 : 0, 'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($data['full_name'] === '') {
            return $this->json(['ok' => false, 'error' => 'Type the name printed on reports.'], 422);
        }
        if ($sig !== '') {
            $data['signature_png'] = $sig;
        }
        $t     = $this->db()->table('tbl_dtr_signatories');
        $exist = $t->where('user_id', $uid)->get()->getRowArray();
        if ($exist) {
            $this->db()->table('tbl_dtr_signatories')->where('id', $exist['id'])->update($data);
        } else {
            $this->db()->table('tbl_dtr_signatories')->insert($data + ['created_by' => (string) ($this->me()['username'] ?? ''), 'created_at' => date('Y-m-d H:i:s')]);
        }

        return $this->signatories();
    }

    /** POST designer/signatories/delete {id} */
    public function deleteSignatory()
    {
        $id = (int) (($this->request->getJSON(true) ?? [])['id'] ?? 0);
        $this->db()->table('tbl_dtr_signatories')->where('id', $id)->delete();

        return $this->signatories();
    }
}
