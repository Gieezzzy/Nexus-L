<?php
/**
 * Sidebar navigation. Add items to $menu; 'roles' restricts visibility.
 */
$user = service('auth')->user();
$myRole = (string) ($user['role'] ?? '');
$can = static fn (string $mod, int $lvl = 1): bool => \App\Libraries\Roles::can($myRole, $mod, $lvl);
$menu = [
    ['label' => 'Dashboard', 'url' => url_to('dashboard'), 'match' => ['', 'dashboard'], 'icon' => 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10'],
    ['label' => 'Inbox', 'url' => url_to('inbox'), 'match' => ['inbox'], 'icon' => 'M21 12a8 8 0 01-11.6 7.1L3 21l1.9-5.4A8 8 0 1121 12z', 'badge' => true],
    ['label' => 'Calendar Monitoring', 'url' => site_url('calendar-monitoring'), 'match' => ['calendar-monitoring', 'calendar'], 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
    ['label' => 'Quality Insights', 'url' => site_url('quality-insights'), 'match' => ['quality-insights'], 'icon' => 'M3 3v18h18M7 14l3-3 3 3 5-6M18 8h-3v3'],
    ['label' => 'InterLink', 'url' => url_to('interlink'), 'match' => ['interlink'], 'icon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15', 'children' => [
        ['label' => 'VF eTRF', 'url' => site_url('interlink/etrf'), 'etrf' => true],
        ['label' => 'Report Submission', 'url' => url_to('interlink'), 'etrf' => false],
    ]],
    ['label' => 'Analytics', 'url' => url_to('analytics'), 'match' => ['analytics'], 'icon' => 'M3 3v18h18M7 15l4-4 3 3 5-6', 'children' => [
        'bonding'   => 'Shoe Bonding',
        'wholeshoe' => 'Whole Shoe',
        'materials' => 'Materials',
    ]],
    ['label' => 'Help', 'url' => url_to('help'), 'match' => ['help'], 'icon' => 'M12 22a10 10 0 100-20 10 10 0 000 20zM9.1 9a3 3 0 015.8 1c0 2-3 3-3 3M12 17h.01'],
    ['label' => 'Brain', 'url' => site_url('brain'), 'match' => ['brain'], 'roles' => ['admin'], 'icon' => 'M12 2a7 7 0 00-4 12.7V17a2 2 0 002 2h4a2 2 0 002-2v-2.3A7 7 0 0012 2zM9 22h6', 'badge' => true, 'badgeId' => 'nav-brain'],
    ['label' => 'Settings', 'url' => site_url('settings'), 'match' => ['settings'], 'icon' => 'M12 15a3 3 0 100-6 3 3 0 000 6zM19.4 15a1.7 1.7 0 00.3 1.8l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.8-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 11-4 0v-.1a1.7 1.7 0 00-1.1-1.5 1.7 1.7 0 00-1.8.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.8 1.7 1.7 0 00-1.5-1H3a2 2 0 110-4h.1a1.7 1.7 0 001.5-1.1 1.7 1.7 0 00-.3-1.8l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.8.3H9a1.7 1.7 0 001-1.5V3a2 2 0 114 0v.1a1.7 1.7 0 001 1.5 1.7 1.7 0 001.8-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.8V9a1.7 1.7 0 001.5 1H21a2 2 0 110 4h-.1a1.7 1.7 0 00-1.5 1z'],
    ['label' => 'Account Profile', 'url' => site_url('account'), 'match' => ['account'], 'icon' => 'M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z'],
    ['label' => 'Report Designer', 'url' => site_url('designer'), 'match' => ['designer'], 'roles' => ['admin'], 'icon' => 'M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4L16.5 3.5z'],
];
// the flow: what you work with first (home, messages, the lab's picture, submissions), then the groups of tests / reports / documents, then the tools at the bottom
$byLabel = [];
foreach ($menu as $m) {
    $byLabel[$m['label']] = $m;
}
$order = static fn (array $labels): array => array_values(array_filter(array_map(static fn ($l) => $byLabel[$l] ?? null, $labels)));
$menuTop    = $order(['Dashboard', 'Inbox', 'Calendar Monitoring', 'Quality Insights', 'Analytics', 'InterLink']);
$menuBottom = $order(['Help', 'Report Designer', 'Brain', 'Settings', 'Account Profile']);
$segment = service('uri')->getSegment(1);
$activeTabParam = (string) (service('request')->getGet('tab') ?? 'tracker');
?>
<aside id="sidebar" class="side glass">
    <div class="side-head">
        <div class="logo logo-img" role="img" aria-label="NEXUS-L" style="background-image:url('<?= base_url('assets/images/nexus-l-logo.webp') ?>')"></div>
        <div><b>NEXUS-L</b><small>Laboratory System</small></div>
    </div>

    <nav class="nav">
        <?php $drawMenu = function (array $items) use ($myRole, $can, $segment): void { ?>
        <?php foreach ($items as $item): ?>
            <?php if (isset($item['roles']) && ! \App\Libraries\Roles::isAdmin($myRole)) continue; ?>
            <?php $modOf = ['Dashboard' => 'dashboard', 'Inbox' => 'inbox', 'QC Lab Reports (DTR)' => 'dtr', 'Analytics' => 'analytics', 'Help' => 'help', 'Calendar Monitoring' => 'calendar', 'Quality Insights' => 'insights'][$item['label']] ?? ''; if ($item['label'] === 'InterLink' ? (! $can('interlink') && ! $can('etrf')) : ($modOf !== '' && ! $can($modOf))) continue; ?>
            <?php if (! empty($item['children'])): ?>
                <?php $grpOpen = in_array((string) $segment, $item['match'], true); $grpTab = (string) (service('request')->getGet('tab') ?? 'bonding'); $seg2 = (string) (service('uri')->getSegments()[1] ?? ''); ?>
                <div class="nav-group" data-nav-group="<?= esc(strtolower($item['label']), 'attr') ?>">
                    <button type="button" class="nav-link nav-group-toggle" data-current="<?= $grpOpen ? '1' : '0' ?>" aria-expanded="<?= $grpOpen ? 'true' : 'false' ?>" style="width:100%;background:none;border:0;text-align:left;font:inherit;cursor:pointer">
                        <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="<?= esc($item['icon'], 'attr') ?>"/></svg>
                        <?= esc($item['label']) ?>
                        <svg class="nav-chev" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-left:auto;width:14px;height:14px;transition:transform .15s;<?= $grpOpen ? 'transform:rotate(90deg)' : '' ?>"><path d="M9 6l6 6-6 6"/></svg>
                    </button>
                    <div class="nav-group-items" <?= $grpOpen ? '' : 'hidden' ?> style="margin:2px 0 4px 18px;padding-left:8px;border-left:1px solid rgba(148,163,184,0.25)">
                        <?php foreach ($item['children'] as $ck => $cl): ?>
                            <?php if (is_array($cl)): // a child with its own page ?>
                                <a href="<?= esc($cl['url'], 'attr') ?>" class="nav-link <?= ($grpOpen && (($seg2 === 'etrf') === (bool) $cl['etrf'])) ? 'active' : '' ?>" style="padding:6px 12px;font-size:12.5px"><?= esc($cl['label']) ?></a>
                            <?php else: ?>
                            <a href="<?= esc($item['url'] . '?tab=' . $ck, 'attr') ?>" class="nav-link <?= ($grpOpen && $grpTab === $ck) ? 'active' : '' ?>" style="padding:6px 12px;font-size:12.5px"><?= esc($cl) ?></a>
                            <?php endif ?>
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
        <?php }; $drawMenu($menuTop); ?>

        <!-- Section for Monitoring & Physical Tests -->
        <?php
        $testsOpen = in_array((string) $segment, ['materials', 'material-testing', 'wholeshoe', 'whole-shoe-test', 'test-summary', 'tests-summary', 'test-results'], true);
        ?>
        <details class="side-group side-group-collapsible" data-grp="tests"<?= $testsOpen ? ' open data-here="1"' : '' ?> style="margin-top:8px;padding-top:8px;border-top:1px solid rgba(148,163,184,0.18)">
            <summary class="side-group-h" style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:4px 12px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);cursor:pointer;list-style:none">
                <span>Tests</span>
                <svg class="side-group-c" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px;transition:transform .15s ease-out" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
            </summary>
            <?php if ($can('materials')): ?><a href="<?= site_url('materials') ?>" class="nav-link <?= in_array((string) $segment, ['materials', 'material-testing'], true) ? 'active' : '' ?>" style="padding:7px 12px;font-size:12.5px">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" style="width:16px;height:16px"><path d="M12 2l10 5-10 5L2 7l10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                Material Testing
            </a><?php endif ?>
            <?php if ($can('wholeshoe')): ?><a href="<?= site_url('wholeshoe') ?>" class="nav-link <?= in_array((string) $segment, ['wholeshoe', 'whole-shoe-test'], true) ? 'active' : '' ?>" style="padding:7px 12px;font-size:12.5px">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" style="width:16px;height:16px"><path d="M3 18h18v-2H3v2zm19-4c0-1.1-.9-2-2-2h-2.5l-3-4.5c-.4-.6-1-1-1.7-1H8c-.8 0-1.5.5-1.8 1.2L4 12H3c-1.1 0-2 .9-2 2v2h21v-2z"/></svg>
                Whole Shoe Test
            </a><?php endif ?>
            <?php if ($can('testsummary')): ?><a href="<?= site_url('test-summary') ?>" class="nav-link <?= in_array((string) $segment, ['test-summary', 'tests-summary', 'test-results'], true) ? 'active' : '' ?>" style="padding:7px 12px;font-size:12.5px">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" style="width:16px;height:16px"><path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Test Results Summary
            </a><?php endif ?>
        </details>

        <!-- Lab Monitoring: Lab Tracker, Warehouse 3D Tracker & ERP Finish -->
        <?php $monOpen = in_array((string) $segment, ['lab-monitoring', 'warehouse-tracker', 'warehouse', 'calendar-monitoring', 'calendar'], true) || ($segment === 'dtr' && $activeTabParam === 'labtracker'); ?>
        <details class="side-group side-group-collapsible" data-grp="labmon"<?= $monOpen ? ' open data-here="1"' : '' ?> style="margin-top:8px;padding-top:8px;border-top:1px solid rgba(148,163,184,0.18)">
            <summary class="side-group-h" style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:4px 12px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);cursor:pointer;list-style:none">
                <span>Lab Monitoring</span>
                <svg class="side-group-c" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px;transition:transform .15s ease-out" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
            </summary>
            <?php if ($can('dtr')): ?>
            <a href="<?= site_url('dtr?tab=labtracker') ?>" class="nav-link <?= ($segment === 'dtr' && $activeTabParam === 'labtracker') ? 'active' : '' ?>" style="padding:7px 12px;font-size:12.5px">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" style="width:16px;height:16px"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2M9 12h6M9 16h6"/></svg>
                Lab Tracker
            </a>
            <?php endif ?>
            <a href="<?= site_url('warehouse-tracker') ?>" class="nav-link <?= in_array((string) $segment, ['warehouse-tracker', 'warehouse'], true) ? 'active' : '' ?>" style="padding:7px 12px;font-size:12.5px">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" style="width:16px;height:16px"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                Warehouse 3D Tracker
            </a>

            <?php if ($can('erpfinish')): ?>
            <a href="<?= site_url('lab-monitoring/erp-finish') ?>" class="nav-link <?= $segment === 'lab-monitoring' ? 'active' : '' ?>" style="padding:7px 12px;font-size:12.5px">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" style="width:16px;height:16px"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                ERP Finish Monitoring
            </a>
            <?php endif ?>
        </details>


        <!-- Sub-navigation for QC Lab Digital Test Reports (#Digital Test Report) -->
        <?php
        $labLinks = [
            ['tab' => 'bonding',     'label' => 'Shoe Bonding (U60)',     'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'],
            ['tab' => 'wholeshoe',   'label' => 'Whole Shoe',             'icon' => 'M3 18h18v-2H3v2zm19-4c0-1.1-.9-2-2-2h-2.5l-3-4.5c-.4-.6-1-1-1.7-1H8c-.8 0-1.5.5-1.8 1.2L4 12H3c-1.1 0-2 .9-2 2v2h21v-2z'],
            ['tab' => 'material',    'label' => 'Material Tests',         'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10'],
            ['tab' => 'thirdparty',  'label' => 'Third Party Test',       'url' => site_url('thirdparty'), 'icon' => 'M9 12l2 2 4-4M12 3l8 3v6c0 4.5-3.2 8.2-8 9-4.8-.8-8-4.5-8-9V6l8-3z'],
            ['tab' => 'tracker',     'label' => 'Digital Test Report',   'icon' => 'M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z'],
            ['tab' => 'calculators', 'label' => 'Calculators & Standards', 'icon' => 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z'],
        ];
        $labMod = ['bonding' => 'bonding', 'wholeshoe' => 'wholeshoe', 'material' => 'materials', 'thirdparty' => 'thirdparty'];
        $labLinks = array_values(array_filter($labLinks, static fn ($l) => \App\Libraries\Roles::can($myRole, $labMod[$l['tab']] ?? 'dtr')));
        $labReportsOpen = ($segment === 'dtr' && $activeTabParam !== 'labtracker') || $segment === 'thirdparty';
        ?>
        <details class="side-group side-group-collapsible" data-grp="labreports"<?= $labReportsOpen ? ' open data-here="1"' : '' ?> style="margin-top:8px;padding-top:8px;border-top:1px solid rgba(148,163,184,0.18)">
            <summary class="side-group-h" style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:4px 12px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);cursor:pointer;list-style:none">
                <span>Lab Reports</span>
                <svg class="side-group-c" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px;transition:transform .15s ease-out" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
            </summary>
            <?php foreach ($labLinks as $ll): ?>
            <a href="<?= $ll['url'] ?? site_url('dtr?tab=' . $ll['tab']) ?>" class="nav-link <?= (isset($ll['url']) ? $segment === $ll['tab'] : ($segment === 'dtr' && $activeTabParam === $ll['tab'])) ? 'active' : '' ?>" style="padding:7px 12px;font-size:12.5px">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" style="width:16px;height:16px"><path d="<?= esc($ll['icon'], 'attr') ?>"/></svg>
                <?= esc($ll['label']) ?>
            </a>
            <?php endforeach ?>
        </details>
        <!-- Laboratory Documents (the numbered "#" folders) and Other Documents (every other folder of the share): one link per register in dbnexus, each group collapsible -->
        <?php
        $labGroups = ['lab' => ['title' => 'Laboratory Documents', 'regs' => []], 'other' => ['title' => 'Other Documents', 'regs' => []]];
        try {
            $labDb = \Config\Database::connect();
            if ($labDb->tableExists('tbl_labdoc_registers')) {
                $hasGrp = $labDb->fieldExists('grp', 'tbl_labdoc_registers');
                foreach ($labDb->table('tbl_labdoc_registers')->select('code, menu_label' . ($hasGrp ? ', grp' : ''))->where('is_active', 1)->orderBy('sort_order')->orderBy('menu_label')->get()->getResultArray() as $lr) {
                    $labGroups[($lr['grp'] ?? 'lab') === 'other' ? 'other' : 'lab']['regs'][] = $lr;
                }
            }
        } catch (\Throwable $e) {
        }
        $labCode    = $segment === 'labdocs' ? rawurldecode((string) service('uri')->getSegment(2)) : '';
        $labLimit   = (int) ($_COOKIE['nx_side_limit_lab'] ?? 5);
        if ($labLimit < 5) { $labLimit = 5; }
        $otherLimit = (int) ($_COOKIE['nx_side_limit_other'] ?? 5);
        if ($otherLimit < 5) { $otherLimit = 5; }
        ?>
        <?php
        $regTag = static function (array $r): string {
            $code = (string) ($r['code'] ?? '');
            if (preg_match('/Lab-(\d{2,3})(?:-([A-Za-z]))?/i', $code, $m)) {
                return $m[1] . (isset($m[2]) ? strtoupper($m[2]) : '');
            }
            $label = trim((string) ($r['menu_label'] ?? $code));
            $words = preg_split('/[^A-Za-z0-9]+/', $label, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (count($words) === 1) {
                return strtoupper(substr($words[0], 0, 3));
            }
            $t = '';
            foreach (array_slice($words, 0, 3) as $w) {
                $t .= strtoupper($w[0]);
            }

            return $t;
        };
        ?>
        <?php if (! empty($labGroups['lab']['regs']) || ! empty($labGroups['other']['regs'])): ?>
        <div class="side-doc-search-wrap" style="margin:10px 10px 4px 10px">
            <div class="side-doc-search-box" style="position:relative;display:flex;align-items:center;width:100%">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="position:absolute;left:8px;width:13px;height:13px;color:var(--muted,#64748b);pointer-events:none;opacity:.75" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"></circle>
                    <path d="M21 21l-4.35-4.35"></path>
                </svg>
                <input type="text" id="side-doc-search" class="side-doc-search-input" placeholder="Search documents..." autocomplete="off" spellcheck="false" aria-label="Search lab and other documents" style="width:100%;padding:5px 24px 5px 26px;font-size:11.5px;font-family:inherit;border:1px solid rgba(148,163,184,0.3);border-radius:6px;background:rgba(255,255,255,0.65);color:var(--ink,#0f172a);outline:none;box-sizing:border-box">
                <button type="button" id="side-doc-search-clear" class="side-doc-search-clear" title="Clear search" aria-label="Clear search" style="display:none;position:absolute;right:5px;top:50%;transform:translateY(-50%);align-items:center;justify-content:center;width:18px;height:18px;border:none;background:transparent;color:var(--muted,#64748b);cursor:pointer;border-radius:50%;padding:0">
                    <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" style="width:11px;height:11px">
                        <path d="M18 6L6 18M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
        <?php endif ?>
        <?php foreach ($labGroups as $lgKey => $lg): if (! $lg['regs'] || ! $can('labdocs')) { continue; }
            $lgHere = $segment === 'labdocs' && ($labCode === '' ? $lgKey === 'lab' : in_array($labCode, array_column($lg['regs'], 'code'), true));
            $groupLimit = ($lgKey === 'other') ? $otherLimit : $labLimit;
        ?>
        <details class="side-group" data-grp="<?= esc($lgKey, 'attr') ?>"<?= $lgHere ? ' open data-here="1"' : '' ?> style="margin-top:8px;padding-top:8px;border-top:1px solid rgba(148,163,184,0.18)">
            <summary class="side-group-h" style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:4px 12px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);cursor:pointer;list-style:none">
                <span><?= esc($lg['title']) ?> <span class="side-group-count" style="font-weight:600;opacity:.7">(<?= count($lg['regs']) ?>)</span></span>
                <svg class="side-group-c" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px;transition:transform .15s ease-out" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
            </summary>
            <?php foreach ($lg['regs'] as $idx => $lr):
                $isActive = ($segment === 'labdocs' && ($labCode === $lr['code'] || ($labCode === '' && $lgKey === 'lab' && $lr === $lg['regs'][0])));
                $searchStr = strtolower(($lr['menu_label'] ?: $lr['code']) . ' ' . $lr['code'] . ' ' . $regTag($lr));
                $hiddenStyle = ($idx >= $groupLimit) ? 'display:none;' : '';
            ?>
            <a href="<?= site_url('labdocs/' . rawurlencode($lr['code'])) ?>" title="<?= esc($lr['menu_label'] ?: $lr['code'], 'attr') ?>" class="nav-link side-doc-item <?= $isActive ? 'active' : '' ?>" data-search-text="<?= esc($searchStr, 'attr') ?>" style="padding:7px 12px;font-size:12.5px;<?= $hiddenStyle ?>">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" style="width:16px;height:16px;flex:none"><path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zM14 3v5h5M9 13h6M9 17h6M9 9h2"/></svg>
                <span class="side-doc-label" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1"><?= esc($lr['menu_label'] ?: $lr['code']) ?></span>
                <span class="nav-num"><?= esc($regTag($lr)) ?></span>
            </a>
            <?php endforeach ?>
            <div class="side-doc-no-match" style="display:none;padding:6px 14px;font-size:11.5px;font-style:italic;color:var(--muted,#64748b)">No matching documents</div>
            <?php if (count($lg['regs']) > 5): ?>
            <div class="side-view-more-wrap" style="padding:4px 10px 6px">
                <button type="button" class="side-view-more-btn" data-grp-more="<?= esc($lgKey, 'attr') ?>" style="display:flex;align-items:center;justify-content:center;gap:6px;width:100%;padding:5px 8px;margin:2px 0 4px;border:1px dashed rgba(148,163,184,0.35);border-radius:6px;background:rgba(248,250,252,0.5);color:var(--muted,#64748b);font-size:11.5px;font-weight:600;cursor:pointer">
                    <span class="side-view-more-text">View more (+5)</span>
                    <svg class="side-view-more-chev" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" style="width:12px;height:12px;transition:transform .15s ease"><path d="M19 9l-7 7-7-7"/></svg>
                </button>
            </div>
            <?php endif ?>
        </details>
        <?php endforeach ?>
        <style>
            .side-group > summary::-webkit-details-marker { display: none; }
            .side-group[open] .side-group-c { transform: rotate(180deg); }
            .side-group > summary:focus-visible { outline: 2px solid var(--brand, #35618f); outline-offset: 2px; border-radius: 6px; }
            @media (prefers-reduced-motion: reduce) { .side-group-c, .side-view-more-chev { transition: none !important; } }
            .side-doc-search-input:focus { border-color: var(--brand, #2563eb) !important; background: #fff !important; box-shadow: 0 0 0 2px rgba(37,99,235,0.12); }
            .side-view-more-btn:hover { background: rgba(239,246,255,0.7) !important; border-color: var(--brand, #2563eb) !important; color: var(--brand, #2563eb) !important; }
            html.side-collapsed .side-doc-search-wrap,
            html.side-collapsed .side-view-more-wrap,
            html.side-collapsed .side-doc-no-match { display: none !important; }
        </style>
        <script>
        /* each group opens and closes on its click and remembers it; the group of the page being viewed is always open */
        (function () {
          document.querySelectorAll('#sidebar .side-group').forEach(function (d) {
            var k = 'nx.side.grp.' + d.dataset.grp, v = null;
            try { v = localStorage.getItem(k); } catch (e) {}
            if (!d.dataset.here) d.open = v === '1';
            d.addEventListener('toggle', function () {
              var sInput = document.getElementById('side-doc-search');
              if (!sInput || !sInput.value.trim()) {
                try { localStorage.setItem(k, d.open ? '1' : '0'); } catch (e) {}
              }
            });
          });
        })();

        /* Lab Documents & Other Documents: 5-visible limit per group, View More (+5), and real-time Search */
        (function () {
          var searchInput = document.getElementById('side-doc-search');
          var clearBtn = document.getElementById('side-doc-search-clear');
          var groups = document.querySelectorAll('#sidebar .side-group');
          if (!groups.length) return;

          var STEP = 5;
          var visibleLimits = {};
          var wasOpenBeforeSearch = {};
          var isSearching = false;

          function saveLimit(key, val) {
            try {
              localStorage.setItem('nx.side.limit.' + key, String(val));
              document.cookie = 'nx_side_limit_' + encodeURIComponent(key) + '=' + encodeURIComponent(val) + ';path=/;max-age=31536000;SameSite=Lax';
            } catch (e) {}
          }

          // Restore saved limit per group from localStorage / cookie (persists on document clicks & page reloads)
          groups.forEach(function (grp) {
            var key = grp.dataset.grp || 'default';
            var saved = null;
            try { saved = parseInt(localStorage.getItem('nx.side.limit.' + key), 10); } catch (e) {}
            if (!saved) {
              var m = document.cookie.match(new RegExp('(?:^|; )nx_side_limit_' + key + '=([0-9]+)'));
              if (m) saved = parseInt(m[1], 10);
            }
            var limit = (saved && saved >= STEP) ? saved : STEP;

            // If this group contains the active document link, ensure it remains visible
            var activeItem = grp.querySelector('.side-doc-item.active, a.side-doc-item[class*="active"]');
            if (activeItem) {
              var allItems = Array.from(grp.querySelectorAll('.side-doc-item'));
              var activeIdx = allItems.indexOf(activeItem);
              if (activeIdx !== -1 && activeIdx >= limit) {
                limit = Math.ceil((activeIdx + 1) / STEP) * STEP;
                saveLimit(key, limit);
              }
            }

            visibleLimits[key] = limit;
          });

          function updateGroup(grp, query) {
            var key = grp.dataset.grp || 'default';
            var items = grp.querySelectorAll('.side-doc-item');
            var moreWrap = grp.querySelector('.side-view-more-wrap');
            var moreText = grp.querySelector('.side-view-more-text');
            var moreChev = grp.querySelector('.side-view-more-chev');
            var moreBtn = grp.querySelector('.side-view-more-btn');
            var noMatch = grp.querySelector('.side-doc-no-match');
            var countSpan = grp.querySelector('.side-group-count');
            var total = items.length;

            if (query) {
              var matchCount = 0;
              items.forEach(function (it) {
                var text = (it.dataset.searchText || it.textContent || '').toLowerCase();
                var match = text.indexOf(query) !== -1;
                it.style.display = match ? '' : 'none';
                if (match) matchCount++;
              });

              if (moreWrap) moreWrap.style.display = 'none';
              if (noMatch) noMatch.style.display = matchCount === 0 ? '' : 'none';
              if (countSpan) countSpan.textContent = '(' + matchCount + (matchCount === 1 ? ' match' : ' matches') + ')';

              if (matchCount > 0) {
                grp.open = true;
              }
            } else {
              if (noMatch) noMatch.style.display = 'none';
              if (countSpan) countSpan.textContent = '(' + total + ')';

              var limit = visibleLimits[key] || STEP;
              items.forEach(function (it, idx) {
                it.style.display = (idx < limit) ? '' : 'none';
              });

              if (moreWrap) {
                if (total <= STEP) {
                  moreWrap.style.display = 'none';
                } else {
                  moreWrap.style.display = '';
                  var remaining = total - limit;
                  if (remaining > 0) {
                    var nextAdd = Math.min(STEP, remaining);
                    if (moreText) moreText.textContent = 'View more (+' + nextAdd + ' of ' + remaining + ' more)';
                    if (moreBtn) moreBtn.dataset.action = 'more';
                    if (moreChev) moreChev.style.transform = 'rotate(0deg)';
                  } else {
                    if (moreText) moreText.textContent = 'Show less';
                    if (moreBtn) moreBtn.dataset.action = 'less';
                    if (moreChev) moreChev.style.transform = 'rotate(180deg)';
                  }
                }
              }
            }
          }

          function renderAll() {
            var query = searchInput ? searchInput.value.trim().toLowerCase() : '';
            if (clearBtn) clearBtn.style.display = query ? 'inline-flex' : 'none';

            if (query && !isSearching) {
              groups.forEach(function (grp) {
                var key = grp.dataset.grp || 'default';
                wasOpenBeforeSearch[key] = grp.open;
              });
              isSearching = true;
            } else if (!query && isSearching) {
              groups.forEach(function (grp) {
                var key = grp.dataset.grp || 'default';
                if (!grp.dataset.here && typeof wasOpenBeforeSearch[key] !== 'undefined') {
                  grp.open = wasOpenBeforeSearch[key];
                }
              });
              isSearching = false;
            }

            groups.forEach(function (grp) {
              updateGroup(grp, query);
            });
          }

          document.querySelectorAll('#sidebar .side-view-more-btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
              e.preventDefault();
              var grpKey = btn.dataset.grpMore;
              var grp = document.querySelector('#sidebar .side-group[data-grp="' + grpKey + '"]');
              if (!grp) return;

              if (btn.dataset.action === 'less') {
                visibleLimits[grpKey] = STEP;
                saveLimit(grpKey, STEP);
              } else {
                visibleLimits[grpKey] = (visibleLimits[grpKey] || STEP) + STEP;
                saveLimit(grpKey, visibleLimits[grpKey]);
              }
              updateGroup(grp, searchInput ? searchInput.value.trim().toLowerCase() : '');
            });
          });

          if (searchInput) {
            searchInput.addEventListener('input', renderAll);
            searchInput.addEventListener('keydown', function (e) {
              if (e.key === 'Escape') {
                searchInput.value = '';
                renderAll();
                searchInput.blur();
              }
            });
          }

          if (clearBtn) {
            clearBtn.addEventListener('click', function () {
              if (searchInput) {
                searchInput.value = '';
                renderAll();
                searchInput.focus();
              }
            });
          }

          renderAll();
        })();
        </script>
        <!-- tools: Help, Report Designer, Brain, Settings, Account -->
        <div class="side-tools" style="margin-top:10px;padding-top:8px;border-top:1px solid rgba(148,163,184,0.18)">
            <?php $drawMenu($menuBottom); ?>
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
/* the menu keeps its scroll position from page to page; with no saved position the active link is brought into view */
(function () {
  var n = document.querySelector('#sidebar .nav'); if (!n) return;
  var k = 'nx.side.scroll', v = null;
  try { v = sessionStorage.getItem(k); } catch (e) {}
  if (v !== null) n.scrollTop = parseInt(v, 10) || 0;
  else { var a = n.querySelector('.active, [aria-current]'); if (a) n.scrollTop = Math.max(0, a.offsetTop - n.clientHeight / 2); }
  function save() { try { sessionStorage.setItem(k, String(n.scrollTop)); } catch (e) {} }
  n.addEventListener('scroll', save, { passive: true });
  window.addEventListener('pagehide', save);
})();
</script>
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
  // collapsed sidebar: the sub-menu is a flyout next to the icon
  var root = document.documentElement, t;
  var show = function () {
    if (!root.classList.contains('side-collapsed')) return;
    clearTimeout(t);
    document.querySelectorAll('.nav-group.fly').forEach(function (o) { if (o !== g) o.classList.remove('fly'); });
    g.classList.add('fly');
    var r = btn.getBoundingClientRect(), h = box.offsetHeight || 80;
    box.style.top = Math.max(8, Math.min(r.top, window.innerHeight - h - 8)) + 'px';
  };
  var hide = function () { t = setTimeout(function () { g.classList.remove('fly'); }, 160); };
  g.addEventListener('mouseenter', show); g.addEventListener('mouseleave', hide);
  g.addEventListener('focusin', show); g.addEventListener('focusout', hide);
});
</script>
