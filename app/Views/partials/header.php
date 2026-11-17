<?php
/**
 * Top header bar. Expects: $title
 */
$user      = service('auth')->user();
$initials  = strtoupper(implode('', array_map(static fn ($p) => mb_substr($p, 0, 1), array_slice(explode(' ', trim($user['name'] ?? 'U')), 0, 2))));
$isAdmin   = \App\Libraries\Roles::isAdmin((string) ($user['role'] ?? ''));
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

        <?php $isDark = (($user['theme'] ?? '') === 'dark'); ?>
        <button type="button" id="nx-theme-toggle" class="nx-theme-btn" data-theme="<?= $isDark ? 'dark' : 'light' ?>" aria-pressed="<?= $isDark ? 'true' : 'false' ?>" title="<?= $isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode' ?>" aria-label="Toggle dark mode">
            <svg class="nx-theme-ico-moon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
            <svg class="nx-theme-ico-sun" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
            <span class="nx-theme-label"><?= $isDark ? 'Dark' : 'Light' ?></span>
        </button>

        <a class="who" href="<?= site_url('account') ?>" title="Account Profile" style="text-decoration:none;color:inherit">
            <div class="who-t">
                <b><?= esc($user['name'] ?? '') ?></b>
                <span><?= esc($user['role'] ?? '') ?> · @<?= esc($user['username'] ?? '') ?></span>
            </div>
            <?php $topAv = $user['avatar'] ?? null; ?>
            <div class="avatar" id="top-user-avatar" data-initials="<?= esc($initials, 'attr') ?>" style="<?= $topAv ? "background-image:url('" . esc($topAv, 'attr') . "');background-size:cover;background-position:center;color:transparent" : '' ?>"><?= $topAv ? '' : esc($initials) ?></div>
        </a>
    </div>
</header>
