<?php

namespace App\Libraries;

/**
 * Defensive watch over this server: reads the tail of the Apache access log and the NEXUS-L sign-in log and finds attack patterns
 * (SQL injection, script injection, path traversal, probes for secret files and admin tools, scanner tools, bursts of errors from one address,
 * password guessing). It only READS and REPORTS: every finding comes with a recommended response, and nothing is blocked or changed
 * without the owner doing it (blocking an address is a change of the web server settings).
 *
 * Limits, said plainly: this sees web requests and sign-ins. Port scans and traffic that never reaches the web server need the Windows
 * firewall log or a network monitor, which this does not read.
 */
class SecurityWatch
{
    private const LOG = 'C:/xampp/apache/logs/access.log';

    /** paths that are normal machine traffic and must not count as probing */
    private const QUIET = '#^/(NEXUS-L/)?(mail-relay|notifier|interlink/service|brain|bot/|inbox/(unread|file)|dtr/(approval/feed|bonding-image|erp-image|material-report-image|photo-image)|interlink/dsr-file|calendar|assets|account/avatar)#i';

    public static function ensureTable(): void
    {
        $db = \Config\Database::connect();
        $db->query('CREATE TABLE IF NOT EXISTS tbl_security_events (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, seen_at DATETIME NOT NULL, ip VARCHAR(45) NOT NULL, kind VARCHAR(40) NOT NULL, severity VARCHAR(10) NOT NULL,
            hits INT NOT NULL DEFAULT 1, detail VARCHAR(400) NOT NULL, sig CHAR(32) NOT NULL, status VARCHAR(12) NOT NULL DEFAULT "open", UNIQUE KEY uq_sig (sig), KEY idx_seen (seen_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    public static function isInternal(string $ip): bool
    {
        return $ip === '::1' || (bool) preg_match('/^(127\.|10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/', $ip);
    }

    /** the last lines of the access log (about $bytes bytes) */
    private static function tail(int $bytes = 5000000): array
    {
        if (! is_file(self::LOG)) {
            return [];
        }
        $f = fopen(self::LOG, 'rb');
        $s = filesize(self::LOG);
        fseek($f, max(0, $s - $bytes));
        $txt = (string) stream_get_contents($f);
        fclose($f);
        $lines = explode("\n", $txt);
        array_shift($lines);   // the first line may be cut

        return $lines;
    }

    /** what each kind of finding means and what to do about it */
    private const KINDS = [
        'sql_injection'   => ['high', 'SQL injection attempt', 'Check that the page uses prepared statements (NEXUS-L does); if the address is outside the lab, block it in the web server and keep the log line.'],
        'script_injection' => ['high', 'Script injection (XSS) attempt', 'Nothing to fix if the page escapes output (NEXUS-L does); block the address if it repeats.'],
        'path_traversal'  => ['high', 'Path traversal attempt (reading files outside the site)', 'Confirm the file was not served (status 403/404); block the address.'],
        'secret_probe'    => ['medium', 'Probe for secret or admin files (.env, .git, backups, wp-login, phpMyAdmin...)', 'Make sure those files are not reachable (the web root now denies the common ones); block the address if it keeps going.'],
        'scanner_tool'    => ['high', 'Security scanner tool (sqlmap, nikto, nmap, ...)', 'A scan was run against the server. If it was not your own test, block the address and check the logs around it.'],
        'error_burst'     => ['medium', 'Many failed requests from one address (probing or a broken client)', 'Look at the paths; block the address if they are not a known device.'],
        'password_guessing' => ['high', 'Repeated failed sign-ins (password guessing)', 'Check whether it is a user who forgot the password; otherwise lock the account for a while and block the address.'],
    ];

    /**
     * @return list<array{ip:string,kind:string,severity:string,hits:int,detail:string,when:string}>
     */
    public static function scan(int $minutes = 60): array
    {
        $since  = time() - $minutes * 60;
        $found  = [];
        $errs   = [];
        $add    = static function (string $ip, string $kind, int $ts, string $detail) use (&$found): void {
            $k = $ip . '|' . $kind;
            if (! isset($found[$k])) {
                $found[$k] = ['ip' => $ip, 'kind' => $kind, 'severity' => self::KINDS[$kind][0], 'hits' => 0, 'detail' => mb_substr($detail, 0, 200), 'when' => date('Y-m-d H:i:s', $ts)];
            }
            $found[$k]['hits']++;
            $found[$k]['when'] = date('Y-m-d H:i:s', $ts);
        };
        foreach (self::tail() as $line) {
            if (! preg_match('/^(\S+) \S+ \S+ \[([^\]]+)\] "(\S+) (\S+)[^"]*" (\d{3}) \S+ "[^"]*" "([^"]*)"/', $line, $m)) {
                continue;
            }
            [, $ip, $when, , $uri, $status, $ua] = $m;
            if ($ip === '127.0.0.1' || $ip === '::1') {
                continue;
            }
            $ts = strtotime(str_replace(':', ' ', substr($when, 0, 11)) . substr($when, 11));
            if (! $ts || $ts < $since) {
                continue;
            }
            $path = rawurldecode(rawurldecode($uri));
            $low  = strtolower($path);
            $bad  = false;
            if (preg_match('/\b(union(\s|\+|%20)+select|select(\s|\+).{1,40}(\s|\+)from|or(\s|\+)1=1|sleep\(\d|benchmark\(|information_schema|load_file\(|\'\s*or\s*\')/i', $low)) {
                $add($ip, 'sql_injection', $ts, $uri);
                $bad = true;
            }
            if (preg_match('/(<script|javascript:|onerror\s*=|onload\s*=|<img[^>]+src)/i', $low)) {
                $add($ip, 'script_injection', $ts, $uri);
                $bad = true;
            }
            if (preg_match('#(\.\./|\.\.\\\\|/etc/passwd|boot\.ini|c:\\\\windows|/proc/self)#i', $low)) {
                $add($ip, 'path_traversal', $ts, $uri);
                $bad = true;
            }
            if (preg_match('#(/\.env|/\.git/|wp-login|wp-admin|xmlrpc\.php|phpmyadmin|/cgi-bin/|eval-stdin|vendor/phpunit|actuator|\.ssh/|id_rsa|/config\.php|backup\.(zip|sql|tar)|rms-alerts|/\.aws|/server-status|/\.DS_Store)#i', $low) && (int) $status >= 400) {
                $add($ip, 'secret_probe', $ts, $uri . ' -> ' . $status);
                $bad = true;
            }
            if (preg_match('/(sqlmap|nikto|nmap|masscan|acunetix|nessus|zgrab|dirbuster|gobuster|hydra|wpscan|openvas|burp)/i', $ua)) {
                $add($ip, 'scanner_tool', $ts, 'User agent: ' . $ua);
                $bad = true;
            }
            if (! $bad && in_array($status, ['400', '401', '403', '404'], true) && ! preg_match(self::QUIET, $uri) && ! preg_match('#favicon\.ico|\.map$|/apple-touch#i', $uri)) {
                $errs[$ip][] = [$ts, $status . ' ' . $uri];
            }
        }
        foreach ($errs as $ip => $list) {
            if (count($list) >= 25) {
                $add($ip, 'error_burst', end($list)[0], count($list) . ' failed requests, e.g. ' . $list[0][1]);
                $found[$ip . '|error_burst']['hits'] = count($list);
            }
        }
        // password guessing: the sign-in log
        try {
            $rows = \Config\Database::connect()->query("SELECT IPAddress ip, COUNT(*) n, COUNT(DISTINCT Username) u, MAX(CreatedAt) last_at, GROUP_CONCAT(DISTINCT Username SEPARATOR ', ') names
                FROM tbl_auth_logs WHERE Event = 'login' AND Success = 0 AND CreatedAt >= ? GROUP BY IPAddress HAVING n >= 5", [date('Y-m-d H:i:s', $since)])->getResultArray();
            foreach ($rows as $r) {
                $found[$r['ip'] . '|password_guessing'] = ['ip' => (string) $r['ip'], 'kind' => 'password_guessing', 'severity' => 'high', 'hits' => (int) $r['n'],
                    'detail' => $r['n'] . ' failed sign-ins for ' . $r['u'] . ' name(s): ' . mb_substr((string) $r['names'], 0, 120), 'when' => (string) $r['last_at']];
            }
        } catch (\Throwable $e) {
            // no sign-in log
        }
        $out = array_values($found);
        usort($out, static fn ($a, $b) => [$a['severity'] === 'high' ? 0 : 1, $b['when']] <=> [$b['severity'] === 'high' ? 0 : 1, $a['when']]);

        return $out;
    }

    /** stores new findings (one per address, kind and hour) and returns how many were new */
    public static function record(array $findings): int
    {
        self::ensureTable();
        $db = \Config\Database::connect();
        $n  = 0;
        foreach ($findings as $f) {
            $sig = md5($f['ip'] . '|' . $f['kind'] . '|' . substr($f['when'], 0, 13));
            if ($db->query('SELECT 1 FROM tbl_security_events WHERE sig = ?', [$sig])->getRowArray()) {
                continue;
            }
            $db->table('tbl_security_events')->insert(['seen_at' => $f['when'], 'ip' => $f['ip'], 'kind' => $f['kind'], 'severity' => $f['severity'], 'hits' => $f['hits'], 'detail' => $f['detail'], 'sig' => $sig]);
            $n++;
        }

        return $n;
    }

    /** the owner's report in plain words */
    public static function report(int $minutes = 60, string $name = 'Gie'): array
    {
        $f        = self::scan($minutes);
        $external = array_values(array_filter($f, static fn ($x) => ! self::isInternal($x['ip'])));
        $high     = array_values(array_filter($f, static fn ($x) => $x['severity'] === 'high'));
        $period   = $minutes >= 120 ? round($minutes / 60) . ' hours' : $minutes . ' minutes';
        if (! $f) {
            $p = $name !== '' ? "{$name}, in the last " : 'In the last ';
            return ['text' => "{$p}{$period} I found no attack patterns in the web server log or the sign-in log. I watch web requests and sign-ins; I do not see port scans or traffic that never reaches the web server.",
                'steps' => [], 'findings' => [], 'level' => 'clear'];
        }
        $text  = ($name !== '' ? "{$name}, in the last " : 'In the last ') . "{$period} I found " . count($f) . ' suspicious pattern(s): ' . count($high) . ' serious' . ($external ? ', ' . count($external) . ' from outside the lab network' : ', all from inside the lab network') . '.';
        $steps = [];
        foreach (array_slice($f, 0, 8) as $x) {
            [, $title, $do] = self::KINDS[$x['kind']];
            $steps[] = strtoupper($x['severity']) . ': ' . $title . ' from ' . $x['ip'] . (self::isInternal($x['ip']) ? ' (inside the network)' : ' (OUTSIDE)') . ', ' . $x['hits'] . ' time(s), last ' . $x['when'] . '. ' . $x['detail'] . ' - Suggested: ' . $do;
        }
        $text .= ' I have not blocked anything: blocking an address changes the web server settings, so tell me which address and I will prepare the exact rule for you to approve.';

        return ['text' => $text, 'steps' => $steps, 'findings' => $f, 'level' => $high ? 'high' : 'medium'];
    }
}
