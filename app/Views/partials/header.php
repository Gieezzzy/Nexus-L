<?php
/**
 * Top header bar. Expects: $title
 */
$user      = service('auth')->user();
$initials  = strtoupper(implode('', array_map(static fn ($p) => mb_substr($p, 0, 1), array_slice(explode(' ', trim($user['name'] ?? 'U')), 0, 2))));
$isAdmin   = strcasecmp((string) ($user['role'] ?? ''), 'admin') === 0;
$aiEnabled = \App\Libraries\SystemSettings::get('ai.enabled', '1') !== '0';
?>
<header class="top glass">
    <div style="display:flex;align-items:center;gap:8px">
        <button id="sidebar-toggle" type="button" class="menu-btn" aria-label="Toggle menu">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <div><small class="crumb">Pages / <?= esc($title ?? '') ?></small><h1><?= esc($title ?? '') ?></h1></div>
    </div>

    <div style="display:flex;align-items:center;gap:12px">
        <?php if ($isAdmin): ?>
            <button type="button" id="btn-admin-ai-toggle" class="nx-ai-toggle <?= $aiEnabled ? 'is-active' : 'is-disabled' ?>" data-enabled="<?= $aiEnabled ? '1' : '0' ?>" title="Admin Switch: Click to turn AI Brain ON or OFF system-wide" aria-label="Toggle AI Brain">
                <span class="nx-ai-toggle-pulse"></span>
                <svg class="nx-ai-brain-ico" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2a7 7 0 00-4 12.7V17a2 2 0 002 2h4a2 2 0 002-2v-2.3A7 7 0 0012 2zM9 22h6"/></svg>
                <span class="nx-ai-toggle-text">AI Brain: <b class="nx-ai-toggle-status"><?= $aiEnabled ? 'ON' : 'OFF' ?></b></span>
            </button>
        <?php endif; ?>

        <div class="who">
            <div class="who-t">
                <b><?= esc($user['name'] ?? '') ?></b>
                <span><?= esc($user['role'] ?? '') ?> · @<?= esc($user['username'] ?? '') ?></span>
            </div>
            <div class="avatar"><?= esc($initials) ?></div>
        </div>
    </div>
</header>
