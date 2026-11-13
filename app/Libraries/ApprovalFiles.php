<?php

namespace App\Libraries;

/**
 * Which PDF goes to InterLink for an APPROVED report.
 *
 * Normally it is the signed copy made at approval (<file_key>_signed.pdf). A report that carries the approved flag may still have its attached
 * file changed by the user (Browse...): the chosen file is copied into NEXUS-L (<file_key>_user_<time>.pdf, older copies are kept) and recorded
 * on the approval row, and THAT copy is what is sent. "Use the signed report again" removes the choice.
 */
class ApprovalFiles
{
    private static bool $ready = false;

    public static function dir(): string
    {
        $d = WRITEPATH . 'approvals/';
        if (! is_dir($d)) {
            @mkdir($d, 0775, true);
        }

        return $d;
    }

    /** adds the four columns that remember the user's own file (cheap after the first call) */
    public static function ensureColumns(): void
    {
        if (self::$ready) {
            return;
        }
        $db = \Config\Database::connect();
        if (! $db->tableExists('tbl_dtr_report_approvals')) {
            return;
        }
        foreach ([
            'user_file'      => 'VARCHAR(90) NULL',
            'user_file_name' => 'VARCHAR(160) NULL',
            'user_file_by'   => 'VARCHAR(120) NULL',
            'user_file_at'   => 'DATETIME NULL',
        ] as $col => $def) {
            if (! $db->fieldExists($col, 'tbl_dtr_report_approvals')) {
                $db->query("ALTER TABLE tbl_dtr_report_approvals ADD COLUMN {$col} {$def}");
            }
        }
        self::$ready = true;
    }

    /** path of the user's own file of an approval row, or '' when there is none (or it is gone) */
    public static function userPath(array $a): string
    {
        $f = (string) ($a['user_file'] ?? '');
        if ($f === '' || ! preg_match('/^[a-f0-9]{40}_user_\d{14}\.pdf$/', $f)) {
            return '';
        }
        $p = self::dir() . $f;

        return is_file($p) ? $p : '';
    }

    /**
     * The file to send for an approved row: ['path' => ..., 'name' => file name chosen by the user or '', 'replaced' => bool]
     * (path is '' when there is no usable file at all).
     */
    public static function chosen(array $a): array
    {
        $user = self::userPath($a);
        if ($user !== '') {
            return ['path' => $user, 'name' => (string) ($a['user_file_name'] ?? ''), 'replaced' => true];
        }
        $signed = self::dir() . ($a['file_key'] ?: $a['token']) . '_signed.pdf';

        return ['path' => is_file($signed) ? $signed : '', 'name' => '', 'replaced' => false];
    }
}
