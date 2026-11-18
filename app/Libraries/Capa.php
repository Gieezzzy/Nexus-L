<?php

namespace App\Libraries;

/**
 * Failed Sample CAPA Closure Tracker. Every Lab Ref that failed the bonding test (trial and LACKING rows left out) since the start date becomes one CAPA case:
 * root cause + corrective action + the retest Lab Ref. A case stays OPEN and cannot be closed until
 *  - the root cause and the corrective action are written, and
 *  - a retest Lab Ref is linked that has a saved PASS report after the failure (the same Lab Ref tested again, or another Lab Ref, e.g. DTP...-R01).
 * Start date: Settings key capa.start_date (default 2026-11-01) - older failures are not cases, so day one does not show hundreds of old items.
 */
class Capa
{
    public const ROLES = ['Admin', 'Supervisor', 'Manager', 'Bonding PIC'];

    public static function canEdit(): bool
    {
        return (bool) array_intersect(self::ROLES, Roles::parse((string) (service('auth')->user()['role'] ?? '')));
    }

    public static function startDate(): string
    {
        $v = trim((string) SystemSettings::get('capa.start_date', '2026-11-01'));

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '2026-11-01';
    }

    public static function ensureTable(): void
    {
        \Config\Database::connect()->query('CREATE TABLE IF NOT EXISTS tbl_dtr_capa (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            lab_ref_no VARCHAR(191) NOT NULL,
            failed_report_id INT UNSIGNED NOT NULL DEFAULT 0,
            failed_date DATE NULL,
            line VARCHAR(10) NOT NULL DEFAULT "",
            style VARCHAR(191) NOT NULL DEFAULT "",
            cement VARCHAR(191) NOT NULL DEFAULT "",
            mode_of_failure VARCHAR(255) NOT NULL DEFAULT "",
            root_cause TEXT NULL,
            corrective_action TEXT NULL,
            retest_lab_ref VARCHAR(191) NOT NULL DEFAULT "",
            status VARCHAR(10) NOT NULL DEFAULT "Open",
            closed_by VARCHAR(120) NOT NULL DEFAULT "",
            closed_at DATETIME NULL,
            updated_by VARCHAR(120) NOT NULL DEFAULT "",
            updated_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_capa_ref (lab_ref_no),
            KEY k_capa_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    /** one case per failed Lab Ref since the start date (new ones are added; existing ones keep what was written) */
    public static function sync(): int
    {
        self::ensureTable();
        $db   = \Config\Database::connect();
        $rows = $db->query("SELECT LabRefNo ref, MIN(BondingTestID) first_id, MIN(LEFT(TestedDate, 10)) fd, MAX(ModelStyle) style, MAX(AdhesiveSupplier) cement,
                GROUP_CONCAT(DISTINCT NULLIF(TRIM(ModeOfFailure), '') SEPARATOR '; ') modes, GROUP_CONCAT(DISTINCT NULLIF(TRIM(PossibleCause), '') SEPARATOR '; ') cause
            FROM tbl_dtr_bonding_info
            WHERE Result LIKE 'F%' AND TestedDate >= ? AND LabRefNo <> '' AND LabRefNo NOT LIKE '%TRIAL%' AND LabRefNo NOT LIKE '%SHUTDOWN%' AND COALESCE(TestType, '') NOT LIKE '%LACKING%'
            GROUP BY LabRefNo", [self::startDate()])->getResultArray();
        $have = array_flip(array_column($db->query('SELECT lab_ref_no FROM tbl_dtr_capa')->getResultArray(), 'lab_ref_no'));
        $n    = 0;
        foreach ($rows as $r) {
            if (isset($have[$r['ref']])) {
                continue;
            }
            $db->table('tbl_dtr_capa')->insert([
                'lab_ref_no' => $r['ref'], 'failed_report_id' => (int) $r['first_id'], 'failed_date' => $r['fd'] ?: null,
                'line' => \App\Controllers\QualityInsightsController::lineOf((string) $r['ref']), 'style' => mb_substr((string) $r['style'], 0, 191), 'cement' => mb_substr((string) $r['cement'], 0, 191),
                'mode_of_failure' => mb_substr((string) $r['modes'], 0, 255), 'root_cause' => (string) ($r['cause'] ?? ''), 'created_at' => date('Y-m-d H:i:s'),
            ]);
            $n++;
        }

        return $n;
    }

    /** @return array{cases:list<array<string,mixed>>,open:int,closed:int,overdue:int,since:string} */
    public static function listing(string $status = 'Open'): array
    {
        self::sync();
        $db = \Config\Database::connect();
        $c  = $db->query("SELECT SUM(status = 'Open') o, SUM(status = 'Closed') c, SUM(status = 'Open' AND failed_date < DATE_SUB(CURDATE(), INTERVAL 3 DAY)) late FROM tbl_dtr_capa")->getRowArray();
        $q  = $db->table('tbl_dtr_capa')->orderBy('status', 'ASC')->orderBy('failed_date', 'ASC')->orderBy('id', 'ASC')->limit(300);
        if (in_array($status, ['Open', 'Closed'], true)) {
            $q->where('status', $status);
        }
        $cases = [];
        foreach ($q->get()->getResultArray() as $r) {
            $cases[] = [
                'id' => (int) $r['id'], 'ref' => $r['lab_ref_no'], 'failed_date' => (string) $r['failed_date'], 'age' => $r['failed_date'] ? (int) floor((time() - strtotime($r['failed_date'])) / 86400) : 0, 'line' => $r['line'],
                'style' => $r['style'], 'cement' => $r['cement'], 'mode' => $r['mode_of_failure'], 'root_cause' => (string) $r['root_cause'], 'action' => (string) $r['corrective_action'],
                'retest' => $r['retest_lab_ref'], 'status' => $r['status'], 'closed_by' => $r['closed_by'], 'closed_at' => (string) $r['closed_at'], 'candidates' => $r['status'] === 'Open' ? self::candidates($r) : [],
            ];
        }

        return ['cases' => $cases, 'open' => (int) ($c['o'] ?? 0), 'closed' => (int) ($c['c'] ?? 0), 'overdue' => (int) ($c['late'] ?? 0), 'since' => self::startDate()];
    }

    /** Lab Refs that could be the retest: the same Lab Ref passed again after the last failure, and Lab Refs built on it (-R01, -2 ...) that passed */
    private static function candidates(array $case): array
    {
        $db   = \Config\Database::connect();
        $ref  = (string) $case['lab_ref_no'];
        $base = preg_replace('/-(?:R\d+|\d+)$/i', '', $ref);
        $lastFail = (int) ($db->query("SELECT MAX(BondingTestID) m FROM tbl_dtr_bonding_info WHERE LabRefNo = ? AND Result LIKE 'F%'", [$ref])->getRow('m') ?? 0);
        $out = [];
        foreach ($db->query("SELECT LabRefNo ref, MAX(BondingTestID) id, MAX(LEFT(TestedDate, 10)) td FROM tbl_dtr_bonding_info
                WHERE Result LIKE 'P%' AND ((LabRefNo = ? AND BondingTestID > ?) OR (LabRefNo <> ? AND LabRefNo LIKE ?))
                GROUP BY LabRefNo ORDER BY id DESC LIMIT 6", [$ref, $lastFail, $ref, $base . '-%'])->getResultArray() as $r) {
            $out[] = ['ref' => $r['ref'], 'tested' => $r['td']];
        }

        return $out;
    }

    /**
     * @param array{root_cause?:string,corrective_action?:string,retest_lab_ref?:string} $in
     * @return array{ok:bool,message:string,status?:string}
     */
    public static function save(int $id, array $in, bool $close, string $by): array
    {
        self::ensureTable();
        $db  = \Config\Database::connect();
        $row = $db->table('tbl_dtr_capa')->where('id', $id)->get()->getRowArray();
        if (! $row) {
            return ['ok' => false, 'message' => 'That case no longer exists.'];
        }
        if ($row['status'] === 'Closed') {
            return ['ok' => false, 'message' => 'A closed case cannot be changed.'];
        }
        $cause  = trim((string) ($in['root_cause'] ?? ''));
        $action = trim((string) ($in['corrective_action'] ?? ''));
        $retest = trim((string) ($in['retest_lab_ref'] ?? ''));
        $upd    = ['root_cause' => mb_substr($cause, 0, 4000), 'corrective_action' => mb_substr($action, 0, 4000), 'retest_lab_ref' => mb_substr($retest, 0, 191), 'updated_by' => mb_substr($by, 0, 120), 'updated_at' => date('Y-m-d H:i:s')];
        if ($close) {
            $miss = [];
            if ($cause === '') {
                $miss[] = 'the root cause';
            }
            if ($action === '') {
                $miss[] = 'the corrective action';
            }
            if ($retest === '') {
                $miss[] = 'the retest Lab Ref';
            }
            if ($miss) {
                return ['ok' => false, 'message' => 'To close this case write ' . implode(', ', $miss) . '.'];
            }
            $problem = self::retestProblem($row, $retest);
            if ($problem !== '') {
                return ['ok' => false, 'message' => $problem];
            }
            $upd += ['status' => 'Closed', 'closed_by' => mb_substr($by, 0, 120), 'closed_at' => date('Y-m-d H:i:s')];
        }
        $db->table('tbl_dtr_capa')->where('id', $id)->update($upd);

        return ['ok' => true, 'message' => $close ? 'Case closed: ' . $row['lab_ref_no'] . ' (retest ' . $retest . ' passed).' : 'Saved.', 'status' => $close ? 'Closed' : 'Open'];
    }

    /** '' when the retest Lab Ref has a saved PASS report after the failure, else why not */
    private static function retestProblem(array $case, string $retest): string
    {
        $db  = \Config\Database::connect();
        $ref = (string) $case['lab_ref_no'];
        if (stripos($retest, 'TRIAL') !== false) {
            return 'A trial Lab Ref cannot be the retest.';
        }
        $rows = $db->query("SELECT BondingTestID id, Result, LEFT(TestedDate, 10) td FROM tbl_dtr_bonding_info WHERE LabRefNo = ? AND Result IS NOT NULL AND Result <> '' ORDER BY BondingTestID", [$retest])->getResultArray();
        if (! $rows) {
            return $retest . ' has no saved result yet: a failed sample is closed only after a passing retest.';
        }
        $lastFail = (int) ($db->query("SELECT MAX(BondingTestID) m FROM tbl_dtr_bonding_info WHERE LabRefNo = ? AND Result LIKE 'F%'", [$ref])->getRow('m') ?? 0);
        foreach (array_reverse($rows) as $r) {          // the latest report decides
            if (strtoupper($retest) === strtoupper($ref) && (int) $r['id'] <= $lastFail) {
                return 'The retest of the same Lab Ref must be saved after its last failed report.';
            }
            if (stripos((string) $r['Result'], 'P') === 0) {
                return '';
            }

            return $retest . ' did not pass (latest result: ' . strtoupper((string) $r['Result']) . '): a failed sample is closed only after a passing retest.';
        }

        return 'No passing retest found.';
    }
}
