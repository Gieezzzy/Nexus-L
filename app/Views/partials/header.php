<?php
/**
 * Top header bar. Expects: $title
 */
$user     = service('auth')->user();
$initials = strtoupper(implode('', array_map(static fn ($p) => mb_substr($p, 0, 1), array_slice(explode(' ', trim($user['name'] ?? 'U')), 0, 2))));
?>
<header class="top glass">
    <div style="display:flex;align-items:center;gap:8px">
        <button id="sidebar-toggle" type="button" class="menu-btn" aria-label="Toggle menu">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <div><small class="crumb">Pages / <?= esc($title ?? '') ?></small><h1><?= esc($title ?? '') ?></h1></div>
    </div>

    <div class="who">
        <div class="who-t">
            <b><?= esc($user['name'] ?? '') ?></b>
            <span><?= esc($user['role'] ?? '') ?> · @<?= esc($user['username'] ?? '') ?></span>
        </div>
        <div class="avatar"><?= esc($initials) ?></div>
    </div>
</header>
