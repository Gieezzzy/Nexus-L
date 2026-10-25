<?php
/**
 * Sidebar navigation. Add items to $menu; 'roles' restricts visibility.
 */
$user = service('auth')->user();
$menu = [
    ['label' => 'Dashboard', 'url' => url_to('dashboard'), 'match' => ['', 'dashboard'], 'icon' => 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10'],
    ['label' => 'Inbox', 'url' => url_to('inbox'), 'match' => ['inbox'], 'icon' => 'M21 12a8 8 0 01-11.6 7.1L3 21l1.9-5.4A8 8 0 1121 12z', 'badge' => true],
    // ['label' => 'Samples', 'url' => site_url('samples'), 'match' => ['samples'], 'icon' => '...', 'roles' => ['admin']],
];
$segment = service('uri')->getSegment(1);
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
