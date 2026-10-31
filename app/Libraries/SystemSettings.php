<?php

namespace App\Libraries;

/**
 * Settings that an Admin changes from the web (Settings page) instead of in the code or the .env file. They live in the table
 * tbl_system_settings. A setting that was never saved falls back to its default here (or, for the InterLink service, to the old .env
 * value), so nothing changes until someone saves a value.
 *
 * Not kept here: the database connection itself (it cannot be stored in the database it describes): the Settings page edits it in .env.
 */
class SystemSettings
{
    /** @var array<string,string>|null every saved value, read once per request */
    private static ?array $cache = null;

    /**
     * key => [category, label, type text|password|number|bool|url|textarea, default, help]. A `password` is never shown again after saving.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function definitions(): array
    {
        $d = static fn (string $cat, string $label, string $type, $default, string $help = '') => compact('cat', 'label', 'type', 'default', 'help');

        return [
            // --- the InterLink background service (the C# program) -----------------------------------------------------------------
            'interlink.service.enabled' => $d('service', 'Send reports through the background service', 'bool', '1', 'Off = the browser extension does the sending instead.'),
            'interlink.service.url'     => $d('service', 'Service address (IP and port that receives the payload)', 'url', '', 'For example http://192.168.32.43:5077 - the IP and port set in the service program.'),
            'interlink.service.key'     => $d('service', 'Shared key', 'password', '', 'Must be the same key as in the service program\'s settings.'),

            // --- the values a new InterLink report starts with ---------------------------------------------------------------------
            'interlink.service_provider' => $d('defaults', 'Service Provider', 'text', 'DATIAN SUBIC SHOES INC.'),
            'interlink.lab_location'     => $d('defaults', 'Lab Location', 'text', 'PHILIPPINES'),
            'interlink.service_test_type' => $d('defaults', 'Service Test Type', 'text', 'In-House Lab Testing'),
            'interlink.test_stage'       => $d('defaults', 'Test Stage', 'text', 'Production'),
            'interlink.service_required' => $d('defaults', 'Service Required', 'text', 'Regular'),
            'interlink.email_recipients' => $d('defaults', 'Email Notification Recipient(s)', 'text', 'dtp_qcasst@dtshoes.com'),
            'interlink.company_name'     => $d('defaults', 'Company Name', 'text', 'KEY TRADE CO., LTD'),
            'interlink.interlink_code'   => $d('defaults', 'Interlink Code', 'text', 'E126863'),
            'interlink.vendor_name'      => $d('defaults', 'Vendor Name', 'text', 'KEY TRADE CO., LTD'),
            'interlink.vendor_id'        => $d('defaults', 'Vendor ID', 'text', '0000507450'),
            'interlink.factory_name'     => $d('defaults', 'Factory Name', 'text', 'DATIAN SUBIC SHOES INC.'),
            'interlink.factory_id'       => $d('defaults', 'Factory ID', 'text', '0000507452'),
            'interlink.buyer_brand'      => $d('defaults', 'Brand Name', 'text', 'Vans'),
            'interlink.season'           => $d('defaults', 'Season', 'text', 'S27'),
            'interlink.sourcing_office'  => $d('defaults', 'Sourcing Office', 'text', 'ASO'),
            'interlink.product_export_to' => $d('defaults', 'Product Export To', 'text', 'APAC - Non China'),
            'interlink.country_of_origin' => $d('defaults', 'Country of Origin', 'text', 'Philippines'),
            'interlink.product_category' => $d('defaults', 'Product Category', 'text', 'Footwear'),
            'interlink.test_packages'    => $d('defaults', 'Test Package(s)', 'text', 'VANS Pull Strength'),
            'interlink.factory_address'  => $d('defaults', 'Factory Address', 'text', 'Lot 1P7-A,Phase 2,Subic Bay GatewayPark, Subic Bay Freeport Zone'),
            'interlink.factory_country'  => $d('defaults', 'Factory Country', 'text', 'Philippines'),
            'interlink.factory_city'     => $d('defaults', 'Factory City', 'text', 'Subic Bay'),
            'interlink.factory_state_province' => $d('defaults', 'Factory State / Province', 'text', '03'),

            // --- report approval ---------------------------------------------------------------------------------------------------
            'approval.link_minutes'      => $d('approval', 'Approval link works for (minutes)', 'number', '60', 'The QR / link sent to the Manager / Supervisor expires after this time.'),

            // --- Synology Chat -----------------------------------------------------------------------------------------------------
            'synology.base_url'          => $d('synology', 'Synology Chat address', 'url', '', 'Empty = the address in the .env file.'),
            'synology.bot_token'         => $d('synology', 'Bot token', 'password', '', 'Empty = the token in the .env file.'),
            'synology.bot_prefix'        => $d('synology', 'Message prefix', 'text', '', 'Put before every message the bot sends.'),

            // --- AI Assistant / Help Bot -------------------------------------------------------------------------------------------
            'ai.mode'                    => $d('ai', 'AI Engine Operating Mode', 'select:off,shadow,live', 'shadow', 'off = legacy Brain only; shadow = LangGraph answers in background for evaluation; live = LangGraph answers users directly.'),
            'ai.url'                     => $d('ai', 'Local AI Service URL', 'url', 'http://127.0.0.1:8765', 'Address of the Python LangGraph / Rasa microservice.'),
            'ai.key'                     => $d('ai', 'AI Service Shared Key (X-AI-Key)', 'password', '', 'Shared secret between PHP and the Python AI service.'),
            'ai.confidence_threshold'    => $d('ai', 'Minimum Confidence Threshold (0.0 - 1.0)', 'number', '0.85', 'Answers scoring below this threshold are safely abstained or escalated to human admins.'),
            'ai.fallback_message'        => $d('ai', 'Safe Abstain Message', 'textarea', 'Checking system status... I cannot verify this action against current system documentation with full confidence. Escalating directly to an administrator.', 'Exact text displayed to users when confidence drops below threshold.'),
            'ai.seeded_problems_v1'      => $d('ai', 'Problems Auto-Seeded Flag', 'text', '0', 'Internal flag indicating whether the 200 QC Lab problem solutions have been seeded.'),
            'ai.git_synced_v201'         => $d('ai', 'Git Auto-Sync Flag', 'text', '0', 'Internal flag indicating whether Phase 201 has been pushed to git.'),
        ];
    }

    public static function categories(): array
    {
        return [
            'ai'       => 'AI Assistant & Bot Engine',
            'service'  => 'InterLink service',
            'defaults' => 'InterLink report defaults',
            'approval' => 'Report approval',
            'synology' => 'Synology Chat',
        ];
    }

    public static function ensureTable(): void
    {
        $db = \Config\Database::connect();
        $db->query('CREATE TABLE IF NOT EXISTS tbl_system_settings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            skey VARCHAR(100) NOT NULL,
            svalue TEXT NULL,
            category VARCHAR(40) NOT NULL DEFAULT "",
            updated_by VARCHAR(80) NOT NULL DEFAULT "",
            updated_at DATETIME NULL,
            UNIQUE KEY uq_skey (skey)
        ) DEFAULT CHARSET=utf8mb4');

        // One-time auto-seed of 200 QC problem questions
        try {
            $flag = $db->table('tbl_system_settings')->where('skey', 'ai.seeded_problems_v1')->get()->getRowArray();
            if (! $flag || ($flag['svalue'] ?? '0') !== '1') {
                \App\Libraries\ProblemKnowledgeSeeder::run();
                if ($flag) {
                    $db->table('tbl_system_settings')->where('skey', 'ai.seeded_problems_v1')->update(['svalue' => '1', 'updated_at' => date('Y-m-d H:i:s')]);
                } else {
                    $db->table('tbl_system_settings')->insert(['skey' => 'ai.seeded_problems_v1', 'svalue' => '1', 'category' => 'ai', 'updated_by' => 'system', 'updated_at' => date('Y-m-d H:i:s')]);
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'ProblemKnowledgeSeeder auto-seed check: ' . $e->getMessage());
        }

        // Auto git-push for Phase 201 changes
        try {
            $gitFlag = $db->table('tbl_system_settings')->where('skey', 'ai.git_synced_v201')->get()->getRowArray();
            if (! $gitFlag || ($gitFlag['svalue'] ?? '0') !== '1') {
                @exec('cmd /c "E:\\html\\NEXUS-L\\tools\\auto-push.cmd" 2>&1');
                if ($gitFlag) {
                    $db->table('tbl_system_settings')->where('skey', 'ai.git_synced_v201')->update(['svalue' => '1', 'updated_at' => date('Y-m-d H:i:s')]);
                } else {
                    $db->table('tbl_system_settings')->insert(['skey' => 'ai.git_synced_v201', 'svalue' => '1', 'category' => 'ai', 'updated_by' => 'system', 'updated_at' => date('Y-m-d H:i:s')]);
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'Auto-push check: ' . $e->getMessage());
        }
    }

    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        self::$cache = [];
        try {
            $db = \Config\Database::connect();
            if ($db->tableExists('tbl_system_settings')) {
                foreach ($db->table('tbl_system_settings')->get()->getResultArray() as $r) {
                    self::$cache[(string) $r['skey']] = (string) $r['svalue'];
                }
            }
        } catch (\Throwable $e) {
            // the database is not reachable: every setting is its default (the page itself will say so)
        }

        return self::$cache;
    }

    /** the saved value; else the old .env value (service / Synology); else the default of the definition; else $fallback */
    public static function get(string $key, $fallback = null)
    {
        $all = self::load();
        if (array_key_exists($key, $all) && $all[$key] !== '') {
            return $all[$key];
        }
        $env = self::fromEnv($key);
        if ($env !== null && $env !== '') {
            return $env;
        }
        $def = self::definitions()[$key]['default'] ?? null;
        if ($def !== null && $def !== '') {
            return $def;
        }

        return $fallback ?? $def;
    }

    public static function bool(string $key, bool $fallback = false): bool
    {
        $v = self::get($key, $fallback ? '1' : '0');

        return in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true);
    }

    /** where the value would come from when nothing is saved: the .env of the first installation */
    private static function fromEnv(string $key): ?string
    {
        return match ($key) {
            'interlink.service.url'  => (string) env('interlinkService.url', ''),
            'interlink.service.key'  => (string) env('interlinkService.key', ''),
            'synology.base_url'      => (string) env('synology.baseUrl', ''),
            'synology.bot_token'     => (string) env('synology.botToken', ''),
            default                  => null,
        };
    }

    public static function set(string $key, string $value, string $by = ''): void
    {
        $def = self::definitions()[$key] ?? null;
        if (! $def) {
            return;
        }
        self::ensureTable();
        $db  = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');
        $row = $db->table('tbl_system_settings')->where('skey', $key)->get()->getRowArray();
        $data = ['svalue' => $value, 'category' => $def['cat'], 'updated_by' => $by, 'updated_at' => $now];
        if ($row) {
            $db->table('tbl_system_settings')->where('id', $row['id'])->update($data);
        } else {
            $db->table('tbl_system_settings')->insert($data + ['skey' => $key]);
        }
        self::$cache = null;
    }

    /** the values a new InterLink report starts with, by field name (service_provider => ...): for the InterLink page and its script */
    public static function interlinkDefaults(): array
    {
        $out = [];
        foreach (self::definitions() as $key => $d) {
            if ($d['cat'] === 'defaults') {
                $out[substr($key, strlen('interlink.'))] = (string) self::get($key, '');
            }
        }

        return $out;
    }

    /** what the page shows: every definition with its saved state (a password is never sent back) */
    public static function forPage(): array
    {
        $all  = self::load();
        $rows = [];
        foreach (self::definitions() as $key => $d) {
            $saved = array_key_exists($key, $all) && $all[$key] !== '';
            $env   = self::fromEnv($key);
            $row   = $d + ['key' => $key, 'saved' => $saved, 'from_env' => ! $saved && $env !== null && $env !== ''];
            if ($d['type'] === 'password') {
                $row['value'] = '';
                $row['has_value'] = $saved || $row['from_env'];
            } else {
                $row['value'] = (string) self::get($key, '');
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
