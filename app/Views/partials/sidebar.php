<?php
/**
 * Sidebar navigation. Add items to $menu; 'roles' restricts visibility.
 */
$user = service('auth')->user();
$menu = [
    ['label' => 'Dashboard', 'url' => url_to('dashboard'), 'match' => ['', 'dashboard'], 'icon' => 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10'],
    ['label' => 'Inbox', 'url' => url_to('inbox'), 'match' => ['inbox'], 'icon' => 'M21 12a8 8 0 01-11.6 7.1L3 21l1.9-5.4A8 8 0 1121 12z', 'badge' => true],
    ['label' => 'InterLink Sync', 'url' => url_to('interlink'), 'match' => ['interlink'], 'icon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15'],
    ['label' => 'QC Lab Reports (DTR)', 'url' => url_to('dtr'), 'match' => ['dtr'], 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
];
$segment = service('uri')->getSegment(1);
$activeTabParam = (string) (service('request')->getGet('tab') ?? 'tracker');
?>
<aside id="sidebar" class="side glass">
    <div class="side-head">
        <div class="logo"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 3h6M10 3v6.5L4.6 18.4A1.8 1.8 0 006.2 21h11.6a1.8 1.8 0 001.6-2.6L14 9.5V3"/></svg></div>
        <div><b>NEXUS-L</b><small>Laboratory System</small></div>
    </div>

    <nav class="nav">
        <?php foreach ($menu as $item): ?>
            <?php if (isset($item['roles']) && ! in_array($user['role'] ?? '', $item['roles'], true)) continue; ?>
            <a href="<?= esc($item['url'], 'attr') ?>" class="nav-link <?= in_array((string) $segment, $item['match'], true) ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="<?= esc($item['icon'], 'attr') ?>"/></svg>
                <?= esc($item['label']) ?>
                <?php if (! empty($item['badge'])): ?><span class="nav-badge hidden" id="nav-unread"></span><?php endif ?>
            </a>
        <?php endforeach ?>

        <!-- Sub-navigation for QC Lab Digital Test Reports (#Digital Test Report) -->
        <div style="margin-top:8px;padding-top:8px;border-top:1px solid rgba(148,163,184,0.18)">
            <div style="padding:4px 12px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted)">Digital Test Report Suite</div>
            <a href="<?= site_url('dtr?tab=tracker') ?>" class="nav-link <?= ($segment === 'dtr' && $activeTabParam === 'tracker') ? 'active' : '' ?>" style="padding:7px 12px;font-size:12.5px">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" style="width:16px;height:16px"><path d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                Lab Tracker &amp; IQC Log
            </a>
            <a href="<?= site_url('dtr?tab=material') ?>" class="nav-link <?= ($segment === 'dtr' && $activeTabParam === 'material') ? 'active' : '' ?>" style="padding:7px 12px;font-size:12.5px">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" style="width:16px;height:16px"><path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                Material Test Forms (10)
            </a>
            <a href="<?= site_url('dtr?tab=bonding') ?>" class="nav-link <?= ($segment === 'dtr' && $activeTabParam === 'bonding') ? 'active' : '' ?>" style="padding:7px 12px;font-size:12.5px">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" style="width:16px;height:16px"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                Shoe Bonding Form (U60)
            </a>
            <a href="<?= site_url('dtr?tab=calculators') ?>" class="nav-link <?= ($segment === 'dtr' && $activeTabParam === 'calculators') ? 'active' : '' ?>" style="padding:7px 12px;font-size:12.5px">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" style="width:16px;height:16px"><path d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                Calculators &amp; Standards
            </a>
        </div>
    </nav>

    <div class="side-foot">
        <?= form_open(url_to('logout')) ?>
            <button type="submit" class="nav-link danger">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M15 17l5-5-5-5M20 12H9M12 21H5a2 2 0 01-2-2V5a2 2 0 012-2h7"/></svg>
                Sign out
            </button>
        <?= form_close() ?>
    </div>
</aside>
<div id="sidebar-backdrop" class="backdrop hidden"></div>
