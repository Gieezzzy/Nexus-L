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
    /** the service address the next job goes to, as chosen under Settings > Service ("Send the payload to") */
    public static function serviceUrl(): string
    {
        $t = (string) self::get('interlink.service.target', 'custom');
        $u = $t === '43' ? (string) self::get('interlink.service.url_43', '') : ($t === '45' ? (string) self::get('interlink.service.url_45', '') : (string) self::get('interlink.service.url', ''));

        return rtrim(trim($u), '/');
    }

    public static function definitions(): array
    {
        $d = static fn (string $cat, string $label, string $type, $default, string $help = '') => compact('cat', 'label', 'type', 'default', 'help');

        return [
            // --- the InterLink background service (the C# program) -----------------------------------------------------------------
            'interlink.service.enabled' => $d('service', 'Send reports through the background service', 'bool', '1', 'Off = the browser extension does the sending instead.'),
            'approval.email'            => $d('approval', 'E-mail the signatories a link and QR code', 'bool', '1', 'Sent through the Server HTML Handler (its mail account). A signatory with no e-mail address on the account gets the Synology message only.'),
            'roles.matrix'              => $d('roles', 'Role access matrix (JSON)', 'textarea', '', 'Managed under Settings > Users & roles.'),
            'interlink.service.target'  => $d('service', 'Send the payload to', 'select:custom,43,45', 'custom', '43 = the address below for the .43 computer, 45 = the one for the .45 computer, custom = the free address further down. The next job goes to the one chosen here; jobs already waiting stay where they are.'),
            'interlink.service.url_43'  => $d('service', 'Service address on .43', 'url', 'http://192.168.32.43:5077', 'IP and port set in the service program running on the .43 computer.'),
            'interlink.service.url_45'  => $d('service', 'Service address on .45', 'url', 'http://192.168.32.45:5077', 'IP and port set in the service program running on the .45 computer.'),
            'interlink.service.url'     => $d('service', 'Service address (custom)', 'url', '', 'Used only when "Send the payload to" is custom, for example http://192.168.32.50:5077.'),
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
            'interlink.applicant_address' => $d('defaults', 'eTRF applicant address', 'text', 'Lot 1P7-A Subic Bay Gateway Park Phase II,, Subic Bay Freeport Zone, Philippines', 'Used by the VF eTRF forms (Applicant and Service Requested sections).'),
            'interlink.applicant_email'   => $d('defaults', 'eTRF applicant e-mail', 'text', 'dtp_lab@dtshoes.com'),
            'interlink.applicant_phone'   => $d('defaults', 'eTRF applicant phone', 'text', '(047) 252-2899'),
            'interlink.cost_centre'       => $d('defaults', 'eTRF cost centre', 'text', 'VF-P0676'),
            'order_summary.path'          => $d('defaults', 'Order Summary PDF folder', 'text', '\\\\192.168.32.130\\qc lab files\\#ORDER SUMMARY', 'Folder with the MTL running-style PDFs. New or changed PDFs are read on the next lookup.'),
            'order_summary.share_user'    => $d('defaults', 'Order Summary share user', 'text', '', 'Only needed when the web server cannot open the folder by itself (a service session does not share your Windows login). Example: 192.168.32.130\\user'),
            'order_summary.share_pass'    => $d('defaults', 'Order Summary share password', 'password', ''),

            // --- report approval ---------------------------------------------------------------------------------------------------
            'approval.link_minutes'      => $d('approval', 'Approval link works for (minutes)', 'number', '60', 'The QR / link sent to the Manager / Supervisor expires after this time.'),

            // --- ERP agent (ERP Finish Monitoring) -----------------------------------------------------------------------------------
            'erp.agent_enabled'          => $d('erp', 'Send reports to the ERP agent', 'bool', '1', 'Switch off to keep reports in the queue without sending them to the ERP PC.'),
            'erp.agent_target'           => $d('erp', 'Send ERP requests to the PC', 'select:43,45', '43', 'Which ERP PC receives the reports: .43 or .45. Change it here at any time; reports already handed to a PC stay with it.'),
            'erp.agent43_host'           => $d('erp', 'PC .43 - IP address', 'text', '192.168.32.43', 'Where NexusErpAgent.exe runs on the .43 PC (the ERP client must be open or startable there).'),
            'erp.agent43_port'           => $d('erp', 'PC .43 - port', 'number', '5057', 'The port set in that agent (its Settings tab).'),
            'erp.agent43_token'          => $d('erp', 'PC .43 - token', 'password', '', 'The token shown in that agent. NEXUS-L sends it with every request and the agent sends it back with each result.'),
            'erp.agent45_host'           => $d('erp', 'PC .45 - IP address', 'text', '192.168.32.45', 'Where NexusErpAgent.exe runs on the .45 PC.'),
            'erp.agent45_port'           => $d('erp', 'PC .45 - port', 'number', '5057', 'The port set in that agent.'),
            'erp.agent45_token'          => $d('erp', 'PC .45 - token', 'password', '', 'The token shown in that agent.'),

            'erp.extract_host'           => $d('erp', 'Extract agent - IP address', 'text', '192.168.32.43', 'The PC where ERP_Extract_Agent runs (the ERP client open there). Control it at Settings > ERP Extract (address /settings/erp-extract).'),
            'erp.extract_port'           => $d('erp', 'Extract agent - port', 'number', '5058', 'listen_port in the agent_config.json of that PC.'),
            'erp.extract_token'          => $d('erp', 'Extract agent - token', 'password', '', 'token in the agent_config.json of that PC (run: ERP_Extract_Agent.exe --token).'),

            // --- Synology Chat -----------------------------------------------------------------------------------------------------
            'synology.base_url'          => $d('synology', 'Synology Chat address', 'url', '', 'Empty = the address in the .env file.'),
            'synology.bot_token'         => $d('synology', 'Bot token', 'password', '', 'Empty = the token in the .env file.'),
            'synology.bot_prefix'        => $d('synology', 'Message prefix', 'text', '', 'Put before every message the bot sends.'),

            // --- AI Assistant / Help Bot -------------------------------------------------------------------------------------------
            'ai.enabled'                 => $d('ai', 'AI Brain Engine Enabled', 'bool', '1', 'Master switch: when turned off, the AI Brain assistant is disabled across the entire system.'),
            'ai.name'                    => $d('ai', 'AI Assistant Name', 'text', 'LEXUS AI', 'The display name of the AI assistant across the chat header and responses.'),
            'ai.mode'                    => $d('ai', 'AI Engine Operating Mode', 'select:off,shadow,live', 'shadow', 'off = legacy Brain only; shadow = LangGraph answers in background for evaluation; live = LangGraph answers users directly.'),
            'ai.url'                     => $d('ai', 'Local AI Service URL', 'url', 'http://127.0.0.1:8765', 'Address of the Python LangGraph / Rasa microservice.'),
            'ai.key'                     => $d('ai', 'AI Service Shared Key (X-AI-Key)', 'password', '', 'Shared secret between PHP and the Python AI service.'),
            'ai.confidence_threshold'    => $d('ai', 'Minimum Confidence Threshold (0.0 - 1.0)', 'number', '0.85', 'Answers scoring below this threshold are safely abstained or escalated to human admins.'),
            'ai.fallback_message'        => $d('ai', 'Safe Abstain Message', 'textarea', 'Checking system status... I cannot verify this action against current system documentation with full confidence. Escalating directly to an administrator.', 'Exact text displayed to users when confidence drops below threshold.'),
            'ai.seeded_problems_v1'      => $d('ai', 'Problems Auto-Seeded Flag', 'text', '0', 'Internal flag indicating whether the 200 QC Lab problem solutions have been seeded.'),
            'ai.trainer_token'           => $d('ai', 'Brain Trainer app token', 'password', '', 'The token typed into the Brain Trainer Windows app (X-Trainer-Token). Empty = the app is refused.'),
            'notify.token'               => $d('ai', 'Notifier app token', 'password', '', 'The token typed into the NEXUS-L Notifier Windows app (X-Notifier-Token). Empty = the app is refused.'),
            'insights.aliases'           => $d('ai', 'Supplier spelling merges (Quality Insights)', 'textarea', '', 'One per line: WRONG SPELLING=RIGHT NAME. For example NNAPAO=NANPAO. Suppliers written both ways are counted as one in the scorecard.'),
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
            'erp'      => 'ERP agent',
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
