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
<div class="ac" id="ac-root" data-tab="<?= esc($tab, 'attr') ?>" data-min="<?= (int) $minPwd ?>" data-initials="<?= esc($initials, 'attr') ?>">
    <div class="card glass ac-head">
        <div class="ac-avatar-wrap" id="ac-head-avatar-trigger" title="<?= empty($isGuestSession) ? 'Click to change profile picture' : 'Profile picture' ?>" role="<?= empty($isGuestSession) ? 'button' : 'img' ?>" tabindex="<?= empty($isGuestSession) ? '0' : '-1' ?>">
            <div class="ac-avatar" id="ac-head-avatar" style="<?= ! empty($avatarUrl) ? "background-image:url('" . esc($avatarUrl, 'attr') . "');background-size:cover;background-position:center;color:transparent" : '' ?>"><?= ! empty($avatarUrl) ? '' : esc($initials) ?></div>
            <?php if (empty($isGuestSession)): ?>
            <span class="ac-avatar-cam" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
            </span>
            <?php endif ?>
        </div>
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
        <?php if (empty($isGuestSession)): ?><button type="button" data-tab="security">Password &amp; security</button>
        <button type="button" data-tab="preferences">My settings</button><?php endif ?>
        <button type="button" data-tab="access">My access</button>
        <?php if ($canSign): ?><button type="button" data-tab="signature">Signature</button><?php endif ?>
    </div>

    <!-- Profile -->
    <section class="card glass ac-pane" data-pane="profile">
        <?php if (empty($isGuestSession)): ?>
        <div class="ac-pic-box">
            <div class="ac-avatar lg" id="ac-avatar-preview" style="<?= ! empty($avatarUrl) ? "background-image:url('" . esc($avatarUrl, 'attr') . "');background-size:cover;background-position:center;color:transparent" : '' ?>"><?= ! empty($avatarUrl) ? '' : esc($initials) ?></div>
            <div class="ac-pic-meta">
                <h3>Profile picture</h3>
                <p class="muted sm">Visible to everyone in Inbox messages, group chats, and across NEXUS-L. Select a photo to crop, drag to reposition, zoom, rotate, or flip before saving.</p>
                <div class="ac-actions" style="margin-top:8px">
                    <input type="file" id="ac-avatar-file" accept="image/png,image/jpeg,image/webp" hidden>
                    <button type="button" class="nx-btn nx-primary" id="ac-avatar-upload-btn">Upload &amp; crop picture</button>
                    <button type="button" class="nx-btn" id="ac-avatar-remove-btn" <?= empty($avatarUrl) ? 'hidden' : '' ?>>Remove picture</button>
                    <span class="ac-msg" id="ac-avatar-msg"></span>
                </div>
            </div>
        </div>
        <hr class="ac-sep">
        <?php endif ?>
        <h3>Personal details</h3>
        <p class="muted sm"><?= ! empty($isGuestSession) ? 'Signed in via one-click Guest access (read-only session). Ask an Admin for a personal account or custom permissions.' : 'These details are used in reports, approvals, chat and the lists of people. The username and the role are set by an Admin.' ?></p>
        <div class="ac-grid">
            <label>Full name *<input type="text" id="p-FullName" value="<?= esc($u['FullName'], 'attr') ?>" maxlength="120"<?= ! empty($isGuestSession) ? ' disabled' : '' ?>></label>
            <label>Username<input type="text" value="<?= esc($u['Username'], 'attr') ?>" disabled></label>
            <label>E-mail<input type="email" id="p-Email" value="<?= esc((string) $u['Email'], 'attr') ?>" maxlength="150" placeholder="name@dtshoes.com"<?= ! empty($isGuestSession) ? ' disabled' : '' ?>></label>
            <label>Phone<input type="text" id="p-Phone" value="<?= esc((string) $u['Phone'], 'attr') ?>" maxlength="40" placeholder="(047) 252-2899"<?= ! empty($isGuestSession) ? ' disabled' : '' ?>></label>
            <label>Department<input type="text" id="p-Department" value="<?= esc((string) $u['Department'], 'attr') ?>" maxlength="100" placeholder="QC Laboratory"<?= ! empty($isGuestSession) ? ' disabled' : '' ?>></label>
            <label>Job title<input type="text" id="p-JobTitle" value="<?= esc((string) $u['JobTitle'], 'attr') ?>" maxlength="100" placeholder="Lab Technician"<?= ! empty($isGuestSession) ? ' disabled' : '' ?>></label>
            <label>Employee no.<input type="text" id="p-EmployeeNo" value="<?= esc((string) $u['EmployeeNo'], 'attr') ?>" maxlength="40"<?= ! empty($isGuestSession) ? ' disabled' : '' ?>></label>
            <label>Role(s)<input type="text" value="<?= esc($roles ? implode(', ', $roles) : 'not assigned yet', 'attr') ?>" disabled></label>
        </div>
        <?php if (empty($isGuestSession)): ?><div class="ac-actions"><button type="button" class="nx-btn nx-primary" id="p-save">Save details</button><span class="ac-msg" id="p-msg"></span></div><?php endif ?>

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

    <!-- Interactive Crop / Rearrange / Rotate / Zoom Modal -->
    <div class="ac-crop-backdrop" id="ac-crop-modal" hidden role="dialog" aria-modal="true" aria-labelledby="ac-crop-title">
        <div class="ac-crop-dialog card glass">
            <div class="ac-crop-head">
                <div>
                    <h3 id="ac-crop-title">Crop &amp; Adjust Profile Picture</h3>
                    <p class="muted sm" style="margin:2px 0 0">Drag image to reposition &middot; Scroll or slide to zoom &middot; Rotate or flip as needed</p>
                </div>
                <button type="button" class="ac-crop-close" id="ac-crop-close" aria-label="Close">&times;</button>
            </div>
            <div class="ac-crop-body">
                <div class="ac-crop-stage-wrap">
                    <canvas id="ac-crop-canvas" width="320" height="320"></canvas>
                </div>
                <div class="ac-crop-controls">
                    <div class="ac-crop-preview-row">
                        <canvas id="ac-crop-preview" width="76" height="76"></canvas>
                        <div>
                            <div style="font-weight:700;font-size:13px">Live Preview</div>
                            <div class="muted sm">How your profile picture will appear to all users</div>
                        </div>
                    </div>
                    <label class="ac-crop-slider">
                        <span>Zoom <b id="ac-crop-zoom-val">100%</b></span>
                        <input type="range" id="ac-crop-zoom" min="50" max="400" step="1" value="100">
                    </label>
                    <label class="ac-crop-slider">
                        <span>Straighten / Angle <b id="ac-crop-angle-val">0&deg;</b></span>
                        <input type="range" id="ac-crop-angle" min="-180" max="180" step="1" value="0">
                    </label>
                    <div class="ac-crop-tools">
                        <button type="button" class="nx-btn" id="ac-crop-rot-l" title="Rotate 90° Left">&#8630; Rotate Left</button>
                        <button type="button" class="nx-btn" id="ac-crop-rot-r" title="Rotate 90° Right">&#8631; Rotate Right</button>
                        <button type="button" class="nx-btn" id="ac-crop-flip-h" title="Flip Horizontal">&#8596; Flip H</button>
                        <button type="button" class="nx-btn" id="ac-crop-flip-v" title="Flip Vertical">&#8597; Flip V</button>
                        <button type="button" class="nx-btn" id="ac-crop-reset" title="Reset adjustments">Reset</button>
                    </div>
                </div>
            </div>
            <div class="ac-crop-foot">
                <button type="button" class="nx-btn" id="ac-crop-pick-another">Choose another photo</button>
                <div style="display:flex;gap:8px;align-items:center">
                    <button type="button" class="nx-btn" id="ac-crop-cancel">Cancel</button>
                    <button type="button" class="nx-btn nx-primary" id="ac-crop-save">Save profile picture</button>
                </div>
            </div>
        </div>
    </div>
</div>
