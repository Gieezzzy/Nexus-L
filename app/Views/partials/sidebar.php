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
    ['label' => 'Analytics', 'url' => url_to('analytics'), 'match' => ['analytics'], 'icon' => 'M3 3v18h18M7 15l4-4 3 3 5-6', 'children' => [
        'bonding'   => 'Shoe Bonding',
        'wholeshoe' => 'Whole Shoe',
        'materials' => 'Materials',
    ]],
    ['label' => 'Help', 'url' => url_to('help'), 'match' => ['help'], 'icon' => 'M12 22a10 10 0 100-20 10 10 0 000 20zM9.1 9a3 3 0 015.8 1c0 2-3 3-3 3M12 17h.01'],
    ['label' => 'Brain', 'url' => site_url('brain'), 'match' => ['brain'], 'roles' => ['admin'], 'icon' => 'M12 2a7 7 0 00-4 12.7V17a2 2 0 002 2h4a2 2 0 002-2v-2.3A7 7 0 0012 2zM9 22h6', 'badge' => true, 'badgeId' => 'nav-brain'],
    ['label' => 'Settings', 'url' => site_url('settings'), 'match' => ['settings'], 'roles' => ['admin'], 'icon' => 'M12 15a3 3 0 100-6 3 3 0 000 6zM19.4 15a1.7 1.7 0 00.3 1.8l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.8-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 11-4 0v-.1a1.7 1.7 0 00-1.1-1.5 1.7 1.7 0 00-1.8.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.8 1.7 1.7 0 00-1.5-1H3a2 2 0 110-4h.1a1.7 1.7 0 001.5-1.1 1.7 1.7 0 00-.3-1.8l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.8.3H9a1.7 1.7 0 001-1.5V3a2 2 0 114 0v.1a1.7 1.7 0 001 1.5 1.7 1.7 0 001.8-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.8V9a1.7 1.7 0 001.5 1H21a2 2 0 110 4h-.1a1.7 1.7 0 00-1.5 1z'],
    ['label' => 'Report Designer', 'url' => site_url('designer'), 'match' => ['designer'], 'roles' => ['admin'], 'icon' => 'M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4L16.5 3.5z'],
];
$segment = service('uri')->getSegment(1);
$activeTabParam = (string) (service('request')->getGet('tab') ?? 'tracker');
?>
<aside id="sidebar" class="side glass">
    <div class="side-head">
        <div class="logo logo-img" role="img" aria-label="NEXUS-L" style="background-image:url('<?= base_url('assets/images/nexus-l-logo.webp') ?>')"></div>
        <div><b>NEXUS-L</b><small>Laboratory System</small></div>
    </div>

    <nav class="nav">
        <?php foreach ($menu as $item): ?>
            <?php if (isset($item['roles']) && ! in_array(strtolower((string) ($user['role'] ?? '')), array_map('strtolower', $item['roles']), true)) continue; ?>
            <?php if (! empty($item['children'])): ?>
                <?php $grpOpen = in_array((string) $segment, $item['match'], true); $grpTab = (string) (service('request')->getGet('tab') ?? 'bonding'); ?>
                <div class="nav-group" data-nav-group="<?= esc(strtolower($item['label']), 'attr') ?>">
                    <button type="button" class="nav-link nav-group-toggle" data-current="<?= $grpOpen ? '1' : '0' ?>" aria-expanded="<?= $grpOpen ? 'true' : 'false' ?>" style="width:100%;background:none;border:0;text-align:left;font:inherit;cursor:pointer">
                        <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="<?= esc($item['icon'], 'attr') ?>"/></svg>
                        <?= esc($item['label']) ?>
                        <svg class="nav-chev" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-left:auto;width:14px;height:14px;transition:transform .15s;<?= $grpOpen ? 'transform:rotate(90deg)' : '' ?>"><path d="M9 6l6 6-6 6"/></svg>
                    </button>
                    <div class="nav-group-items" <?= $grpOpen ? '' : 'hidden' ?> style="margin:2px 0 4px 18px;padding-left:8px;border-left:1px solid rgba(148,163,184,0.25)">
                        <?php foreach ($item['children'] as $ck => $cl): ?>
                            <a href="<?= esc($item['url'] . '?tab=' . $ck, 'attr') ?>" class="nav-link <?= ($grpOpen && $grpTab === $ck) ? 'active' : '' ?>" style="padding:6px 12px;font-size:12.5px"><?= esc($cl) ?></a>
                        <?php endforeach ?>
                    </div>
                </div>
                <?php continue; ?>
            <?php endif ?>
            <a href="<?= esc($item['url'], 'attr') ?>" class="nav-link <?= in_array((string) $segment, $item['match'], true) ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="<?= esc($item['icon'], 'attr') ?>"/></svg>
                <?= esc($item['label']) ?>
                <?php if (! empty($item['badge'])): ?><span class="nav-badge hidden" id="<?= esc($item['badgeId'] ?? 'nav-unread') ?>"></span><?php endif ?>
            </a>
        <?php endforeach ?>

        <!-- Section for Monitoring & Physical Tests -->
        <div style="margin-top:8px;padding-top:8px;border-top:1px solid rgba(148,163,184,0.18)">
            <div style="padding:4px 12px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted)">Tests</div>
            <a href="<?= site_url('materials') ?>" class="nav-link <?= in_array((string) $segment, ['materials', 'material-testing'], true) ? 'active' : '' ?>" style="padding:7px 12px;font-size:12.5px">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" style="width:16px;height:16px"><path d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
                Material Testing
            </a>
            <a href="<?= site_url('wholeshoe') ?>" class="nav-link <?= in_array((string) $segment, ['wholeshoe', 'whole-shoe-test'], true) ? 'active' : '' ?>" style="padding:7px 12px;font-size:12.5px">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" style="width:16px;height:16px"><path d="M3 18h18v-2H3v2zm19-4c0-1.1-.9-2-2-2h-2.5l-3-4.5c-.4-.6-1-1-1.7-1H8c-.8 0-1.5.5-1.8 1.2L4 12H3c-1.1 0-2 .9-2 2v2h21v-2z"/></svg>
                Whole Shoe Test
            </a>
            <a href="<?= site_url('test-summary') ?>" class="nav-link <?= in_array((string) $segment, ['test-summary', 'tests-summary', 'test-results'], true) ? 'active' : '' ?>" style="padding:7px 12px;font-size:12.5px">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" style="width:16px;height:16px"><path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Test Results Summary
            </a>
        </div>

        <!-- Sub-navigation for QC Lab Digital Test Reports (#Digital Test Report) -->
        <div style="margin-top:8px;padding-top:8px;border-top:1px solid rgba(148,163,184,0.18)">
            <div style="padding:4px 12px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted)">Lab Reports</div>
            <?php
            $labLinks = [
                ['tab' => 'labtracker',  'label' => 'Lab Tracker',            'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2M9 12h6M9 16h6'],
                ['tab' => 'bonding',     'label' => 'Shoe Bonding (U60)',     'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'],
                ['tab' => 'wholeshoe',   'label' => 'Whole Shoe',             'icon' => 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z'],
                ['tab' => 'material',    'label' => 'Material Tests',         'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10'],
                ['tab' => 'tracker',     'label' => 'Digital Test Report',    'icon' => 'M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z'],
                ['tab' => 'calculators', 'label' => 'Calculators & Standards', 'icon' => 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z'],
            ];
            foreach ($labLinks as $ll): ?>
            <a href="<?= site_url('dtr?tab=' . $ll['tab']) ?>" class="nav-link <?= ($segment === 'dtr' && $activeTabParam === $ll['tab']) ? 'active' : '' ?>" style="padding:7px 12px;font-size:12.5px">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" style="width:16px;height:16px"><path d="<?= esc($ll['icon'], 'attr') ?>"/></svg>
                <?= esc($ll['label']) ?>
            </a>
            <?php endforeach ?>
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
<script>
// expandable sidebar groups (Analytics): the open / closed state is remembered per browser
document.querySelectorAll('.nav-group').forEach(function (g) {
  var btn = g.querySelector('.nav-group-toggle'), box = g.querySelector('.nav-group-items'), chev = g.querySelector('.nav-chev');
  var key = 'nav-group-' + g.dataset.navGroup;
  var set = function (open) {
    box.hidden = !open; btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    chev.style.transform = open ? 'rotate(90deg)' : '';
    try { localStorage.setItem(key, open ? '1' : '0'); } catch (e) {}
  };
  try { var saved = localStorage.getItem(key); if (saved !== null && btn.dataset.current !== '1') set(saved === '1'); } catch (e) {}
  btn.addEventListener('click', function () { set(box.hidden); });
});
</script>
