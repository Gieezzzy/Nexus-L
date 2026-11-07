<?php
/**
 * Account Profile. Data: $u (the user's record without the password), $roles, $legacy, $isAdmin, $levels, $modules, $prefs, $canSign, $signature, $minPwd, $tab.
 */
$initials = '';
foreach (preg_split('/\s+/', trim((string) $u['FullName'])) as $w) {
    $initials .= mb_strtoupper(mb_substr($w, 0, 1));
}
$initials = mb_substr($initials, 0, 2);
$levelName = [0 => 'No access', 1 => 'View', 2 => 'View and edit'];
$when = static fn ($d) => $d ? date('d M Y, H:i', strtotime((string) $d)) : '-';
?>
<div class="ac" id="ac-root" data-tab="<?= esc($tab, 'attr') ?>" data-min="<?= (int) $minPwd ?>">
    <div class="card glass ac-head">
        <div class="ac-avatar"><?= esc($initials) ?></div>
        <div class="ac-who">
            <h2><?= esc($u['FullName']) ?></h2>
            <div class="muted">@<?= esc($u['Username']) ?><?= $u['JobTitle'] ? ' &middot; ' . esc($u['JobTitle']) : '' ?><?= $u['Department'] ? ' &middot; ' . esc($u['Department']) : '' ?></div>
            <div class="ac-chips">
                <?php if ($legacy && ! $isAdmin): ?><span class="ac-chip warn" title="No role has been assigned yet: you keep the access everyone had before roles. Ask an Admin to assign yours.">No role assigned yet</span><?php endif ?>
                <?php foreach ($roles as $r): ?><span class="ac-chip <?= $r === 'Admin' ? 'admin' : '' ?>"><?= esc($r) ?></span><?php endforeach ?>
                <span class="ac-chip <?= (int) $u['IsActive'] === 1 ? 'ok' : 'off' ?>"><?= (int) $u['IsActive'] === 1 ? 'Active' : 'Disabled' ?></span>
            </div>
        </div>
        <?php if ($isAdmin): ?><a class="nx-btn" href="<?= site_url('settings') ?>">System settings &amp; users</a><?php endif ?>
    </div>

    <div class="ac-tabs" role="tablist">
        <button type="button" data-tab="profile">Profile</button>
        <button type="button" data-tab="security">Password &amp; security</button>
        <button type="button" data-tab="preferences">My settings</button>
        <button type="button" data-tab="access">My access</button>
        <?php if ($canSign): ?><button type="button" data-tab="signature">Signature</button><?php endif ?>
    </div>

    <!-- Profile -->
    <section class="card glass ac-pane" data-pane="profile">
        <h3>Personal details</h3>
        <p class="muted sm">These details are used in reports, approvals, chat and the lists of people. The username and the role are set by an Admin.</p>
        <div class="ac-grid">
            <label>Full name *<input type="text" id="p-FullName" value="<?= esc($u['FullName'], 'attr') ?>" maxlength="120"></label>
            <label>Username<input type="text" value="<?= esc($u['Username'], 'attr') ?>" disabled></label>
            <label>E-mail<input type="email" id="p-Email" value="<?= esc((string) $u['Email'], 'attr') ?>" maxlength="150" placeholder="name@dtshoes.com"></label>
            <label>Phone<input type="text" id="p-Phone" value="<?= esc((string) $u['Phone'], 'attr') ?>" maxlength="40" placeholder="(047) 252-2899"></label>
            <label>Department<input type="text" id="p-Department" value="<?= esc((string) $u['Department'], 'attr') ?>" maxlength="100" placeholder="QC Laboratory"></label>
            <label>Job title<input type="text" id="p-JobTitle" value="<?= esc((string) $u['JobTitle'], 'attr') ?>" maxlength="100" placeholder="Lab Technician"></label>
            <label>Employee no.<input type="text" id="p-EmployeeNo" value="<?= esc((string) $u['EmployeeNo'], 'attr') ?>" maxlength="40"></label>
            <label>Role(s)<input type="text" value="<?= esc($roles ? implode(', ', $roles) : 'not assigned yet', 'attr') ?>" disabled></label>
        </div>
        <div class="ac-actions"><button type="button" class="nx-btn nx-primary" id="p-save">Save details</button><span class="ac-msg" id="p-msg"></span></div>

        <h4>Account information</h4>
        <dl class="ac-dl">
            <dt>Account created</dt><dd><?= esc($when($u['CreatedAt'] ?? null)) ?></dd>
            <dt>Last sign-in</dt><dd><?= esc($when($u['LastLoginAt'] ?? null)) ?><?= ! empty($u['LastLoginIP']) ? ' from ' . esc($u['LastLoginIP']) : '' ?></dd>
            <dt>Password changed</dt><dd><?= esc($when($u['PasswordChangedAt'] ?? null)) ?></dd>
            <dt>Synology Chat ID</dt><dd><?= ! empty($u['SynologyID']) ? esc((string) $u['SynologyID']) . ' (approval messages reach you)' : 'not linked: an Admin links it so approval and report messages reach you' ?></dd>
        </dl>
    </section>

    <!-- Password & security -->
    <section class="card glass ac-pane" data-pane="security">
        <h3>Change password</h3>
        <p class="muted sm">At least <?= (int) $minPwd ?> characters with upper and lower case letters, a number and a symbol. Changing it signs your other sessions out.</p>
        <div class="ac-grid one">
            <label>Current password<input type="password" id="s-current" autocomplete="current-password"></label>
            <label>New password<input type="password" id="s-new" autocomplete="new-password"></label>
            <label>Repeat new password<input type="password" id="s-confirm" autocomplete="new-password"></label>
        </div>
        <div class="ac-strength"><i id="s-bar"></i></div><div class="muted sm" id="s-hint">Type a new password to see its strength.</div>
        <div class="ac-actions"><button type="button" class="nx-btn nx-primary" id="s-save">Change password</button><span class="ac-msg" id="s-msg"></span></div>
        <p class="muted sm">Forgot the current password? Sign out and use "Forgot password" on the sign-in page.</p>
    </section>

    <!-- My settings -->
    <section class="card glass ac-pane" data-pane="preferences">
        <h3>My settings</h3>
        <p class="muted sm">Only for you; they do not change anything for other people.</p>
        <div class="ac-grid">
            <label>Open this page after sign-in
                <select id="f-landing">
                    <?php foreach (['dashboard' => 'Dashboard', 'inbox' => 'Inbox', 'dtr' => 'QC Lab Reports (DTR)', 'interlink' => 'InterLink report submission', 'analytics' => 'Analytics'] as $k => $l): ?>
                        <option value="<?= $k ?>" <?= $prefs['landing'] === $k ? 'selected' : '' ?>><?= esc($l) ?></option>
                    <?php endforeach ?>
                </select></label>
            <label>Menu on a computer
                <select id="f-sidebar"><option value="expanded" <?= $prefs['sidebar'] === 'expanded' ? 'selected' : '' ?>>Expanded (names and icons)</option><option value="collapsed" <?= $prefs['sidebar'] === 'collapsed' ? 'selected' : '' ?>>Collapsed (icons only)</option></select></label>
            <label>Rows per page in lists
                <select id="f-page"><?php foreach ([10, 15, 25, 50, 100] as $n): ?><option value="<?= $n ?>" <?= (int) $prefs['page_size'] === $n ? 'selected' : '' ?>><?= $n ?></option><?php endforeach ?></select></label>
        </div>
        <div class="ac-checks" role="group" aria-label="Notifications">
            <label class="ac-sw"><input type="checkbox" id="f-chat" <?= $prefs['notify_chat'] ? 'checked' : '' ?>><span></span>Tell me about new chat messages</label>
            <label class="ac-sw"><input type="checkbox" id="f-appr" <?= $prefs['notify_approval'] ? 'checked' : '' ?>><span></span>Tell me when a report waits for my approval or was approved</label>
            <label class="ac-sw"><input type="checkbox" id="f-toast" <?= $prefs['toasts'] ? 'checked' : '' ?>><span></span>Show small pop-up notices (toasts)</label>
        </div>
        <div class="ac-actions"><button type="button" class="nx-btn nx-primary" id="f-save">Save my settings</button><span class="ac-msg" id="f-msg"></span></div>
    </section>

    <!-- My access -->
    <section class="card glass ac-pane" data-pane="access">
        <h3>What my role(s) can open</h3>
        <p class="muted sm"><?php if ($isAdmin): ?>You are an Admin: everything is open to you, including Settings, user management, the Brain console and the Report Designer.<?php elseif ($legacy): ?>No role has been assigned to you yet, so you keep the access every user had before roles. Once an Admin gives you a role the list below follows that role.<?php else: ?>Your role<?= count($roles) > 1 ? 's' : '' ?>: <b><?= esc(implode(', ', $roles)) ?></b>. With several roles you get the highest access any of them gives. An Admin can change the roles.<?php endif ?></p>
        <div class="ac-table"><table>
            <thead><tr><th>Part of the system</th><th>My access</th></tr></thead>
            <tbody>
            <?php foreach ($modules as $k => $label): ?>
                <tr><td><?= esc($label) ?></td><td><span class="ac-lv l<?= (int) $levels[$k] ?>"><?= esc($levelName[(int) $levels[$k]]) ?></span></td></tr>
            <?php endforeach ?>
            </tbody>
        </table></div>
    </section>

    <!-- Signature -->
    <?php if ($canSign): ?>
    <section class="card glass ac-pane" data-pane="signature">
        <h3>My signature</h3>
        <p class="muted sm">Used on the reports you approve. Draw it below with the mouse or your finger and save; it is stored as a picture and never shown to anyone who cannot open the approved report.</p>
        <?php if ($signature): ?><div class="ac-sigcur"><div class="muted sm">Saved signature</div><img src="<?= esc($signature, 'attr') ?>" alt="Saved signature"></div><?php endif ?>
        <canvas id="g-canvas" width="560" height="180"></canvas>
        <div class="ac-actions"><button type="button" class="nx-btn nx-primary" id="g-save">Save signature</button><button type="button" class="nx-btn" id="g-clear">Clear drawing</button><?php if ($signature): ?><button type="button" class="nx-btn" id="g-remove">Remove saved signature</button><?php endif ?><span class="ac-msg" id="g-msg"></span></div>
    </section>
    <?php endif ?>
</div>
