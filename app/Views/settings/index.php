<?php
/**
 * Settings (Admin only). Data: $rows (every setting with its saved state), $categories, $db (the connection in use), $envPath, $envWritable
 */
$byCat = [];
foreach ($rows as $r) {
    $byCat[$r['cat']][] = $r;
}
$field = static function (array $r): string {
    $key = esc($r['key'], 'attr');
    $id  = 'st-' . str_replace('.', '-', $r['key']);
    $help = $r['help'] !== '' ? '<small class="muted">' . esc($r['help']) . '</small>' : '';
    if ($r['type'] === 'bool') {
        $on = in_array(strtolower((string) $r['value']), ['1', 'true', 'yes', 'on'], true);

        return '<label class="st-row st-check"><input type="checkbox" id="' . $id . '" data-key="' . $key . '" data-type="bool" ' . ($on ? 'checked' : '') . '> <span>' . esc($r['label']) . '</span>' . $help . '</label>';
    }
    if (str_starts_with($r['type'], 'select:')) {
        $opts = explode(',', substr($r['type'], 7));
        $cur  = (string) ($r['value'] ?? '');
        $optionsHtml = '';
        foreach ($opts as $o) {
            $optionsHtml .= '<option value="' . esc($o, 'attr') . '" ' . ($cur === $o ? 'selected' : '') . '>' . esc(strtoupper($o)) . '</option>';
        }
        return '<label class="st-row"><span class="st-label">' . esc($r['label']) . '</span><select id="' . $id . '" data-key="' . $key . '" data-type="select">' . $optionsHtml . '</select>' . $help . '</label>';
    }
    if ($r['type'] === 'textarea') {
        return '<label class="st-row"><span class="st-label">' . esc($r['label']) . '</span><textarea id="' . $id . '" data-key="' . $key . '" data-type="textarea" rows="2" style="width:100%;border-radius:6px;border:1px solid rgba(148,163,184,0.3);padding:6px 8px;background:var(--bg-glass);color:inherit">' . esc($r['value'] ?? '') . '</textarea>' . $help . '</label>';
    }
    $type = $r['type'] === 'password' ? 'password' : ($r['type'] === 'number' ? 'number' : 'text');
    $ph   = $r['type'] === 'password' ? ($r['has_value'] ? ($r['from_env'] ? 'set in the .env file - type to replace' : 'saved - type to replace') : 'not set') : '';
    $val  = $r['type'] === 'password' ? '' : $r['value'];

    return '<label class="st-row"><span class="st-label">' . esc($r['label']) . '</span><input type="' . $type . '" id="' . $id . '" data-key="' . $key . '" data-type="' . esc($r['type'], 'attr') . '" value="' . esc($val, 'attr') . '" placeholder="' . esc($ph, 'attr') . '" autocomplete="off">' . $help . '</label>';
};
?>
<div class="st" id="st-root" data-base="<?= esc(site_url(), 'attr') ?>">
    <header class="st-top">
        <div>
            <h2>Settings</h2>
            <p class="muted">System settings for Admins. What you save here takes effect at once and is kept in the database (table <code>tbl_system_settings</code>); the database connection itself is written to <code>.env</code>, where the code reads it.</p>
        </div>
        <label class="st-find"><span class="sr-only">Find a setting</span><input type="search" id="st-find" placeholder="Find a setting (for example key, threshold, e-mail)" autocomplete="off"></label>
    </header>
    <nav class="st-nav" id="st-nav" aria-label="Settings sections">
        <a href="#su-root">Users &amp; roles</a>
        <a href="#st-ai">AI assistant</a>
        <a href="#st-service">InterLink service</a>
        <a href="#st-db">Database</a>
        <a href="#st-transfer">Transfer</a>
        <a href="#st-defaults">Report defaults</a>
        <a href="#st-approval">Approval</a>
        <a href="#st-erp">ERP agent</a>
        <a href="#st-synology">Synology Chat</a>
    </nav>
    <div class="st-empty" id="st-empty" hidden>No setting matches that search.</div>

    <!-- Users & roles -->
    <div class="card glass" id="su-root">
        <div class="su-head">
            <div>
                <h3>Users &amp; roles</h3>
                <p class="muted sm">Seven roles decide who can open what. A person can have several and gets the highest access any of them gives. A user with no role yet keeps the old open access until one is given. Changes apply within a minute, no new sign-in.</p>
            </div>
            <button type="button" class="nx-btn nx-primary" id="su-add">+ New user</button>
        </div>

        <div class="su-stats" id="su-stats" aria-live="polite"></div>

        <div class="su-tools">
            <input type="search" id="su-q" placeholder="Search name, username, department or role" aria-label="Search users">
            <select id="su-frole" aria-label="Filter by role"><option value="">All roles</option></select>
            <select id="su-fstat" aria-label="Filter by status"><option value="">Any status</option><option value="active">Active</option><option value="disabled">Disabled</option><option value="none">No role yet</option></select>
        </div>
        <div class="su-note" id="su-note" role="status"></div>

        <div class="su-list" id="su-list"><div class="muted sm">Loading users...</div></div>

        <h4 class="su-h4">Access of each role</h4>
        <p class="muted sm">Select a cell to change it: <span class="su-pill l0">None</span> hides the menu entry and refuses the page, <span class="su-pill l1">View</span> can open and read, <span class="su-pill l2">Edit</span> can also add and change. The Admin column and the Admin-only pages cannot be changed.</p>
        <div class="su-mxwrap"><table class="su-mx" id="su-matrix"></table></div>
        <div class="st-actions"><button type="button" class="nx-btn nx-primary" id="su-msave">Save the access</button> <button type="button" class="nx-btn" id="su-mreset">Back to defaults</button></div>

        <dialog id="su-dlg" class="su-dlg">
            <form method="dialog" id="su-form" autocomplete="off">
                <div class="su-dhead"><h3 id="su-dtitle">Edit user</h3><button type="button" class="su-x" id="su-dclose" aria-label="Close">&times;</button></div>
                <div class="su-fgrid">
                    <label>Username<input id="d-username" maxlength="50"></label>
                    <label>Full name *<input id="d-name" maxlength="120"></label>
                    <label>E-mail<input id="d-email" type="email" maxlength="150"></label>
                    <label>Department<input id="d-dept" maxlength="100"></label>
                    <label>Job title<input id="d-job" maxlength="100"></label>
                    <label>Synology Chat ID<input id="d-syn" inputmode="numeric" maxlength="12"></label>
                </div>
                <fieldset class="su-roleset"><legend>Roles</legend><div id="d-roles"></div></fieldset>
                <label class="su-switch" id="d-activewrap"><input type="checkbox" id="d-active"><span></span> Account is active</label>
                <div class="su-result" id="d-result" hidden></div>
                <div class="su-dfoot">
                    <button type="button" class="nx-btn" id="d-reset">Reset password</button>
                    <span class="su-sp"></span>
                    <button type="button" class="nx-btn" id="d-cancel">Cancel</button>
                    <button type="button" class="nx-btn nx-primary" id="d-save">Save</button>
                </div>
            </form>
        </dialog>
    </div>

    <!-- AI Assistant & Bot Engine -->
    <div class="card glass" id="st-ai">
        <h3><?= esc($categories['ai'] ?? 'AI Assistant & Bot Engine') ?> <small class="muted">(LangGraph, Rasa NLU, Mem0 &amp; Phoenix stack)</small></h3>
        <p class="muted sm">Configure operating mode (Shadow vs. Live), local Python service address, shared API key, confidence thresholds, and safe fallback messaging.</p>
        <?php foreach ($byCat['ai'] ?? [] as $r) { if ($r['key'] === 'ai.trainer_token') { continue; } echo $field($r); } ?>
        <div class="st-row" id="st-trainer">
            <span class="st-label">Brain Trainer app token</span>
            <div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                    <input type="text" id="st-trainer-token" readonly placeholder="no token yet" style="flex:1;min-width:260px;font-family:Consolas,monospace" autocomplete="off">
                    <button type="button" class="nx-btn" id="st-trainer-show">Show</button>
                    <button type="button" class="nx-btn nx-primary" id="st-trainer-gen">Generate new token</button>
                    <button type="button" class="nx-btn" id="st-trainer-copy">Copy</button>
                </div>
                <small class="muted">Type this token into the NEXUS-L Brain Trainer Windows app (with this site's address). Generating a new one signs the app out until the new token is entered. Saved at once; no need to press Save.</small>
                <span class="st-msg" id="st-trainer-msg"></span>
            </div>
        </div>
        <div class="st-actions">
            <button type="button" class="nx-btn nx-primary" data-save="st-ai">Save</button>
            <button type="button" class="nx-btn" id="st-ai-test">Test AI Service</button>
            <button type="button" class="nx-btn" id="st-ai-seed">Seed 200 Problem Answers</button>
            <button type="button" class="nx-btn" id="st-git-push">Push to Git</button>
            <span class="st-msg" id="st-ai-msg"></span>
        </div>
    </div>

    <!-- InterLink service -->
    <div class="card glass" id="st-service">
        <h3>InterLink service <small class="muted">(the C# program that submits reports to InterLink in the background)</small></h3>
        <p class="muted sm">The address is the IP and port that <b>receives the payload</b>; the key must be the same as in the service program's Settings window.</p>
        <?php foreach ($byCat['service'] ?? [] as $r) echo $field($r); ?>
        <div class="st-actions">
            <button type="button" class="nx-btn nx-primary" data-save="st-service">Save</button>
            <button type="button" class="nx-btn" id="st-service-test">Test the service</button>
            <span class="st-msg" id="st-service-msg"></span>
        </div>
    </div>

    <!-- Database -->
    <div class="card glass" id="st-db">
        <h3>Database in use</h3>
        <p class="st-now" id="st-db-now">
            <b><?= esc($db['hostname']) ?>:<?= (int) $db['port'] ?></b> &middot; database <b><?= esc($db['database']) ?></b> &middot; user <b><?= esc($db['username']) ?></b>
            <?php if (! empty($db['connected'])): ?>
                &middot; <span class="st-ok">connected</span> &middot; MySQL <?= esc($db['version'] ?? '') ?> &middot; <?= (int) ($db['tables'] ?? 0) ?> tables &middot; <?= esc((string) ($db['sizeMb'] ?? 0)) ?> MB
            <?php else: ?>
                &middot; <span class="st-bad">not connected<?= ! empty($db['error']) ? ': ' . esc($db['error']) : '' ?></span>
            <?php endif ?>
        </p>
        <p class="muted sm">To use another database: fill in its connection, <b>Test</b> it, then <b>Use this database</b>. The code reads the connection from <code><?= esc($envPath) ?></code>
            <?= $envWritable ? '' : '<span class="st-bad"> (this file is not writable: saving the connection will not work until it is)</span>' ?>. A copy of the old file is kept in <code>writable/env-backups</code>.
            If the new database is empty, copy everything into it first with the transfer below.</p>
        <div class="st-grid" data-conn="st-db">
            <label class="st-row"><span class="st-label">Server (IP or name)</span><input type="text" data-f="hostname" value="<?= esc($db['hostname'], 'attr') ?>"></label>
            <label class="st-row"><span class="st-label">Port</span><input type="number" data-f="port" value="<?= (int) $db['port'] ?>"></label>
            <label class="st-row"><span class="st-label">Database</span><input type="text" data-f="database" value="<?= esc($db['database'], 'attr') ?>"></label>
            <label class="st-row"><span class="st-label">User</span><input type="text" data-f="username" value="<?= esc($db['username'], 'attr') ?>"></label>
            <label class="st-row"><span class="st-label">Password</span><input type="password" data-f="password" placeholder="type it to test or switch" autocomplete="new-password"></label>
            <label class="st-row"><span class="st-label">Type SWITCH to confirm</span><input type="text" data-f="confirm" placeholder="SWITCH" autocomplete="off"></label>
        </div>
        <div class="st-actions">
            <button type="button" class="nx-btn" id="st-db-test">Test this connection</button>
            <button type="button" class="nx-btn nx-danger" id="st-db-switch">Use this database</button>
            <span class="st-msg" id="st-db-msg"></span>
        </div>
    </div>

    <!-- Transfer -->
    <div class="card glass" id="st-transfer">
        <h3>Transfer to another database</h3>
        <p class="muted sm">Copies <b>every table and all its rows</b> of the database in use into another database, so the whole web can move to another server without touching the code.
            Nothing in the current database is changed. A table that already holds rows in the target is skipped, unless you choose to overwrite. It runs in the background; you can leave this page.</p>
        <div class="st-grid" data-conn="st-transfer">
            <label class="st-row"><span class="st-label">Target server (IP or name)</span><input type="text" data-f="hostname" placeholder="192.168.32.50"></label>
            <label class="st-row"><span class="st-label">Port</span><input type="number" data-f="port" value="3306"></label>
            <label class="st-row"><span class="st-label">Target database</span><input type="text" data-f="database" placeholder="dbnexus"></label>
            <label class="st-row"><span class="st-label">User</span><input type="text" data-f="username"></label>
            <label class="st-row"><span class="st-label">Password</span><input type="password" data-f="password" autocomplete="new-password"></label>
        </div>
        <label class="st-row st-check"><input type="checkbox" id="st-tr-create"> <span>Create the database if it does not exist</span></label>
        <label class="st-row st-check"><input type="checkbox" id="st-tr-overwrite"> <span>Overwrite tables that already have rows in the target (they are emptied first)</span></label>
        <div class="st-actions">
            <button type="button" class="nx-btn nx-primary" id="st-tr-start">Start the transfer</button>
            <button type="button" class="nx-btn" id="st-tr-use" hidden>Use the new database now</button>
            <span class="st-msg" id="st-tr-msg"></span>
        </div>
        <div class="st-progress" id="st-tr-progress" hidden>
            <div class="st-bar"><div class="st-fill" id="st-tr-fill"></div></div>
            <div class="muted sm" id="st-tr-text"></div>
            <pre class="st-log" id="st-tr-log"></pre>
        </div>
    </div>

    <!-- Report defaults -->
    <div class="card glass" id="st-defaults">
        <h3><?= esc($categories['defaults']) ?></h3>
        <p class="muted sm">What a new InterLink report is filled with when a value was not entered. These were fixed in the code before; change them here when the lab, brand or season changes.</p>
        <div class="st-grid st-grid-2">
            <?php foreach ($byCat['defaults'] ?? [] as $r) echo $field($r); ?>
        </div>
        <div class="st-actions"><button type="button" class="nx-btn nx-primary" data-save="st-defaults">Save</button><span class="st-msg"></span></div>
    </div>

    <!-- Approval -->
    <div class="card glass" id="st-approval">
        <h3><?= esc($categories['approval']) ?></h3>
        <?php foreach ($byCat['approval'] ?? [] as $r) echo $field($r); ?>
        <div class="st-actions"><button type="button" class="nx-btn nx-primary" data-save="st-approval">Save</button><span class="st-msg"></span></div>
    </div>

    <!-- ERP agent -->
    <div class="card glass" id="st-erp">
        <h3><?= esc($categories['erp']) ?></h3>
        <p class="muted sm">ERP Finish Monitoring sends each report to a small program on the ERP PC (NexusErpAgent.exe). It enters the reports in the ERP one at a time, oldest first, in the background.</p>
        <?php foreach ($byCat['erp'] ?? [] as $r) echo $field($r); ?>
        <div class="st-actions"><button type="button" class="nx-btn nx-primary" data-save="st-erp">Save</button><button type="button" class="nx-btn" id="st-erp-test">Check the ERP PC</button><span class="st-msg" id="st-erp-msg"></span></div>
    </div>

    <!-- Synology -->
    <div class="card glass" id="st-synology">
        <h3><?= esc($categories['synology']) ?></h3>
        <p class="muted sm">The bot that sends the approval requests and the "submitted to InterLink" messages. Empty values use the .env file.</p>
        <?php foreach ($byCat['synology'] ?? [] as $r) echo $field($r); ?>
        <div class="st-actions"><button type="button" class="nx-btn nx-primary" data-save="st-synology">Save</button><span class="st-msg"></span></div>
    </div>
</div>
