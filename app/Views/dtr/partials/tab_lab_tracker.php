<?php /* DTR partial: Lab Tracker + IQC container / material receiving log. Included by dtr/index.php, shares its variables. */ ?>
<!-- =======================================================================
     TAB 1: LAB TRACKER & IQC CONTAINER / MATERIAL LOG
     ======================================================================= -->
<section id="dtr-panel-tracker" class="dtr-panel <?= $activeTab === 'tracker' ? '' : 'hidden' ?>">
    <!-- Category Pills -->
    <div class="card glass dtr-section-card">
        <div class="dtr-table-wrap dtr-pc-wrap">
            <table class="dtr-table dtr-pc-table" id="dtr-tracker-table">
                <thead>
                    <tr>
                        <th>MTL #</th>
                        <th>Colorway</th>
                        <th>Style</th>
                        <th>Production Date</th>
                        <th>Tested Date</th>
                        <th>Status</th>
                        <th>Overall Result</th>
                    </tr>
                </thead>
                <tbody id="dtr-tracker-tbody"><tr><td colspan="7" class="dtr-empty">Loading lab reports&hellip;</td></tr></tbody>
            </table>
        </div>
    </div>

</section>

<!-- =======================================================================
     TAB: LAB TRACKER (IQC CONTAINER / MATERIAL RECEIVING LOG)
     ======================================================================= -->
<section id="dtr-panel-labtracker" class="dtr-panel <?= $activeTab === 'labtracker' ? '' : 'hidden' ?>">
    <!-- IQC Container & Material Receiving Log (tbl_dtr_containers & tbl_dtr_materials_master) -->
    <?php
    $subTab = in_array($subTab ?? '', ['containers', 'materials', 'bonding', 'shoes', 'erp', 'cfa', 'schedule', 'failed'], true) ? $subTab : 'bonding';
    $archiveTitles = [
        'materials' => 'IQC Materials Master Queue',
        'erp'       => 'ERP Test Orders (' . number_format((int) ($erpTotal ?? 0)) . ')',
        'cfa'       => 'CFA & CAP (' . number_format((int) ($cfaTotal ?? 0)) . ')',
        'schedule'  => 'Prod. Test Schedule (' . number_format((int) ($schedTotal ?? 0)) . ')',
        'failed'    => 'Failed Samples (' . number_format((int) ($failedTotal ?? 0)) . ')',
    ];
    $isArchiveSub = isset($archiveTitles[$subTab]);
    ?>
    <?php if ($isArchiveSub): ?>
    <div class="dtr-subtabs" role="tablist">
        <span class="dtr-subtab active" style="cursor:default"><svg class="ic" aria-hidden="true"><use href="#i-clipboard"/></svg> <?= esc($archiveTitles[$subTab]) ?></span>
        <div class="dtr-subtabs-right dtr-right-tools">
            <a href="<?= site_url('dtr?tab=labtracker') ?>" class="dtr-btn dtr-btn-outline">
                <svg class="ic" aria-hidden="true"><use href="#i-bolt"/></svg> Back to Lab Tracker
            </a>
            <a href="<?= site_url('interlink') ?>" class="dtr-btn dtr-btn-outline">
                <svg class="ic" aria-hidden="true"><use href="#i-link"/></svg> InterLink Sync
            </a>
        </div>
    </div>
    <?php else: ?>
    <div class="dtr-subtabs" role="tablist">
        <button type="button" role="tab" class="dtr-subtab <?= $subTab === 'bonding' ? 'active' : '' ?>" data-subtab="bonding"><svg class="ic" aria-hidden="true"><use href="#i-bolt"/></svg> Bonding Shoe Collection</button>
        <button type="button" role="tab" class="dtr-subtab <?= $subTab === 'shoes' ? 'active' : '' ?>" data-subtab="shoes"><svg class="ic" aria-hidden="true"><use href="#i-shoe"/></svg> Whole Shoe Collection</button>
        <button type="button" role="tab" class="dtr-subtab <?= $subTab === 'containers' ? 'active' : '' ?>" data-subtab="containers"><svg class="ic" aria-hidden="true"><use href="#i-box"/></svg> Lab Material Test Collection</button>
        <div class="dtr-subtabs-right dtr-right-tools">
            <?php $isDefToday = ! empty($defaultToday); $defTodayLbl = 'Today · ' . date('M j'); ?>
            <div class="dtr-datefilter" id="dtr-datefilter"<?= $isDefToday ? ' data-default-today="1"' : '' ?>>
                <?php /* icon only: the chosen range is the tooltip (and the blue dot shows a range is on); "Reset to all dates" is inside the popup */ ?>
                <button type="button" id="dtr-date-btn" class="dtr-btn dtr-btn-outline dtr-date-btn dtr-icon-only<?= $isDefToday ? ' on' : '' ?>" title="Date filter: <?= $isDefToday ? esc($defTodayLbl) : 'All dates' ?>" aria-label="Filter by date" aria-haspopup="true" aria-expanded="false">
                    <svg class="ic" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    <span id="dtr-date-label"><?= $isDefToday ? esc($defTodayLbl) : 'All dates' ?></span>
                    <span id="dtr-date-clear" class="dtr-date-clear"<?= $isDefToday ? '' : ' hidden' ?> title="Reset to all dates" aria-label="Reset date filter">&times;</span>
                </button>
                <div id="dtr-date-pop" class="dtr-date-pop dtr-dp2" hidden>
                    <div class="dp-right">
                        <div class="dp-modes" role="tablist" aria-label="Calendar filter granularity">
                            <button type="button" class="dp-mode-btn on" role="tab" aria-selected="true" data-dp-mode="daily">Daily</button>
                            <button type="button" class="dp-mode-btn" role="tab" aria-selected="false" data-dp-mode="weekly">Weekly</button>
                            <button type="button" class="dp-mode-btn" role="tab" aria-selected="false" data-dp-mode="monthly">Monthly</button>
                            <button type="button" class="dp-mode-btn" role="tab" aria-selected="false" data-dp-mode="year">Year</button>
                        </div>
                        <div class="dp-right-head">
                            <h4 id="dtr-dp-head-title">Daily / Custom Date Range</h4>
                            <small class="dp-hint" id="dtr-dp-hint">Click a day, or click start &rarr; end date</small>
                        </div>
                        <div class="dp-inputs">
                            <div class="dp-in-box"><small>FROM</small><input type="text" id="dtr-dp-from" placeholder="YYYY-MM-DD" readonly aria-label="From"></div>
                            <span class="dp-in-sep">&rarr;</span>
                            <div class="dp-in-box"><small>TO</small><input type="text" id="dtr-dp-to" placeholder="YYYY-MM-DD" readonly aria-label="To"></div>
                        </div>
                        <div class="dp-cal">
                            <div class="dp-cal-head"><button type="button" class="dp-nav" id="dtr-dp-prev" aria-label="Previous month">&#9664;</button><b id="dtr-dp-title"></b><button type="button" class="dp-nav" id="dtr-dp-next" aria-label="Next month">&#9654;</button></div>
                            <div class="dp-grid" id="dtr-dp-grid"></div>
                        </div>
                        <div class="dp-actions">
                            <button type="button" class="dp-clear-cal" id="dtr-dp-clear">Clear</button>
                            <button type="button" class="dp-apply" id="dtr-dp-apply">Apply Filter</button>
                        </div>
                    </div>
                </div>
            </div>
            <button type="button" id="dtr-export-btn" class="dtr-btn dtr-btn-outline dtr-icon-only" title="Download the table as an Excel file (all rows of the chosen date range and search)" aria-label="Download the table as Excel">
                <svg class="ic" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/></svg>
            </button>
            <a href="<?= site_url('interlink') ?>" class="dtr-btn dtr-btn-outline">
                <svg class="ic" aria-hidden="true"><use href="#i-link"/></svg> InterLink Sync
            </a>
        </div>
    </div>
    <?php endif ?>
    <script>window.dtrEditLocked = <?= json_encode(array_values($editLocked ?? [])) ?>;</script>
    <script>window.dtrCanAddPull = <?= ! empty($canAddPullTests) ? 'true' : 'false' ?>;</script>
    <div>
        <!-- Lab Material Test Collection: the Material Bench Log (tbl_dtr_material_bench_log) -->
        <div class="card glass dtr-section-card dtr-subpanel <?= $subTab === 'containers' ? '' : 'hidden' ?>" data-subpanel="containers">
            <div class="dtr-card-head" style="gap:8px;flex-wrap:wrap;justify-content:flex-start">
                <input type="search" class="dtr-list-search dtr-as-search" data-as-type="bench" style="margin:0 auto 0 0 !important;order:-1" placeholder="Search Lab Ref, material test, category, technician, machine..." aria-label="Search the Lab Material Test Collection">
                <div class="dtr-subtabs-right muted sm" style="display:flex;align-items:center;gap:8px;margin-left:auto">
                    <span id="as-count-bench">0 records</span>
                    <button type="button" class="dtr-btn dtr-btn-outline dtr-as-prev" data-as-type="bench">&lsaquo; Prev</button>
                    <span id="as-page-bench" class="mono">1</span>
                    <button type="button" class="dtr-btn dtr-btn-outline dtr-as-next" data-as-type="bench">Next &rsaquo;</button>
                    <button type="button" class="dtr-btn dtr-btn-primary" data-add-record="containers">+ Material Receive</button>
                </div>
            </div>
            <div class="dtr-table-wrap dtr-fill-wrap" style="max-height:440px">
                <table class="dtr-table dtr-pc-table" id="dtr-bench-table">
                    <thead>
                        <tr>
                            <th>Lab Ref No</th>
                            <th>Category</th>
                            <th>Material Test</th>
                            <th>Code</th>
                            <th>Test Date</th>
                            <th>Started / Finished</th>
                            <th>Machine</th>
                            <th>Technician</th>
                            <th>Result</th>
                            <th>Other Details &amp; Bench Breakdown</th>
                        </tr>
                    </thead>
                    <tbody id="as-tbody-bench">
                        <tr><td colspan="10" class="muted" style="text-align:center;padding:24px">Loading the Lab Material Test Collection...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card glass dtr-section-card dtr-subpanel <?= $subTab === 'materials' ? '' : 'hidden' ?>" data-subpanel="materials">
            <?php $matTot = (int) ($materialsTotal ?? count($materialsMaster ?? [])); $matPg = (int) ($materialsPage ?? 1); $matPages = max(1, (int) ceil($matTot / 15)); ?>
            <div class="dtr-card-head" style="gap:8px;flex-wrap:wrap;justify-content:flex-start">
                <input type="search" class="dtr-list-search" style="margin:0 auto 0 0 !important;order:-1" placeholder="Search Lab Reference, material, code, supplier..." aria-label="Search the list">
                <div class="dtr-subtabs-right muted sm" style="display:flex;align-items:center;gap:8px;margin-left:auto">
                    <span id="lt-count-materials" data-total="<?= $matTot ?>"><?= number_format($matTot) ?> record(s)</span>
                    <button type="button" class="dtr-btn dtr-btn-outline dtr-lt-prev" data-lt-type="materials">&lsaquo; Prev</button>
                    <span id="lt-page-materials" class="mono" data-page="<?= $matPg ?>" data-pages="<?= $matPages ?>"><?= $matPg ?> / <?= $matPages ?></span>
                    <button type="button" class="dtr-btn dtr-btn-outline dtr-lt-next" data-lt-type="materials">Next &rsaquo;</button>
                </div>
            </div>
            <div class="dtr-table-wrap dtr-fill-wrap" style="max-height:420px">
                <table class="dtr-table">
                    <thead>
                        <tr>
                            <th>LabReference</th>
                            <th>Material Type</th>
                            <th>Material Code</th>
                            <th>Material Name</th>
                            <th>Supplier</th>
                            <th>Status</th>
                            <th>Final</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($materialsMaster as $mm): ?>
                            <tr>
                                <td class="mono"><?php if (! empty($mm['LabReference'])): ?><a href="#" class="dtr-labref-link" data-open-material="<?= esc($mm['LabReference'], 'attr') ?>" title="Open in Material Tests"><b><?= esc($mm['LabReference']) ?></b></a><?php endif ?></td>
                                <td><?= esc($mm['MaterialType'] ?? '') ?></td>
                                <td class="mono"><?= esc($mm['MaterialCode'] ?? '') ?></td>
                                <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= esc($mm['MaterialName'] ?? '') ?></td>
                                <td><?= esc($mm['Supplier'] ?? '') ?></td>
                                <td><?= esc($mm['TestingStatus'] ?? '') ?></td>
                                <td><?php $fs = (string) ($mm['FinalStatus'] ?? 'Done'); $fc = $resChip($fs); ?><?= str_contains($fc, 'res-chip') ? $fc : '<span class="badge b-ok">' . esc($fs) . '</span>' ?></td>
                                <td style="text-align:center"><button type="button" class="dtr-del-btn" data-del="material" data-id="<?= (int) ($mm['MaterialID'] ?? 0) ?>" data-ref="<?= esc((string) ($mm['LabReference'] ?? '') . ' ' . ($mm['MaterialCode'] ?? ''), 'attr') ?>" title="Delete this entry" aria-label="Delete this entry"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg></button></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card glass dtr-section-card dtr-subpanel <?= $subTab === 'bonding' ? '' : 'hidden' ?>" data-subpanel="bonding">
            <?php
            $bndTot = (int) ($bondingFilteredTotal ?? $bondingTotal ?? count($bondingShoes ?? []));
            $bndPg = (int) ($bondingPage ?? 1);
            $bndPages = max(1, (int) ceil($bndTot / 15));
            $bc = $bondingCounts ?? [];
            $bcItems = [
                ['Daily',          (int) ($bc['daily'] ?? 0),    'is-daily',  'daily'],
                ['Tech Trial',     (int) ($bc['trial'] ?? 0),    'is-trial',  'trial'],
                ['Sample',         (int) ($bc['sample'] ?? 0),   'is-sample', 'sample'],
                ['Retest',         (int) ($bc['retest'] ?? 0),   'is-retest', 'retest'],
                ['First Article',  (int) ($bc['first'] ?? 0),    'is-first',  'first'],
                ['Advance',        (int) ($bc['advance'] ?? 0),  'is-adv',    'advance'],
                ['Lacking',        (int) ($bc['lacking'] ?? 0),  'is-lack',   'lacking'],
                ['Shutdown lines', (int) ($bc['shutdown'] ?? 0), 'is-shut',   'shutdown'],
            ];
            $bcActive = (string) ($lType ?? '');
            ?>
            <div class="dtr-card-head dtr-bshoe-head" style="gap:8px;flex-wrap:wrap;justify-content:flex-start">
                <input type="search" class="dtr-list-search" style="margin:0 auto 0 0 !important;order:-1" placeholder="Search Lab Ref, Prod. Code, Style, Colorway, MTL..." aria-label="Search the list">
                <div class="dtr-subtabs-right muted sm" style="display:flex;align-items:center;gap:8px;margin-left:auto;flex-wrap:wrap">
                    <div id="lt-counts-bonding" class="dtr-lt-counts" aria-label="Bonding collection summary counts">
                        <?php foreach ($bcItems as [$bcLabel, $bcVal, $bcCls, $bcKey]): ?>
                            <button type="button" class="dtr-lt-cnt <?= $bcCls ?><?= $bcVal === 0 ? ' is-zero' : '' ?><?= $bcActive === $bcKey ? ' is-on' : '' ?>" data-lt-filter="<?= $bcKey ?>" title="Show only <?= esc($bcLabel, 'attr') ?>" aria-pressed="<?= $bcActive === $bcKey ? 'true' : 'false' ?>"><?= esc($bcLabel) ?> <b><?= number_format($bcVal) ?></b></button>
                        <?php endforeach ?>
                        <?php $brc = $bondingResCounts ?? ['pass' => 0, 'tol' => 0, 'fail' => 0]; ?>
                        <span id="lt-res-counts" hidden data-pass="<?= (int) $brc['pass'] ?>" data-tol="<?= (int) $brc['tol'] ?>" data-fail="<?= (int) $brc['fail'] ?>" data-pending="<?= (int) ($brc['pending'] ?? 0) ?>"></span>
                    </div>
                    <button type="button" class="dtr-btn dtr-btn-outline dtr-lt-prev" data-lt-type="bonding">&lsaquo; Prev</button>
                    <span id="lt-page-bonding" class="mono" data-page="<?= $bndPg ?>" data-pages="<?= $bndPages ?>"><?= $bndPg ?> / <?= $bndPages ?></span>
                    <button type="button" class="dtr-btn dtr-btn-outline dtr-lt-next" data-lt-type="bonding">Next &rsaquo;</button>
                    <button type="button" class="dtr-btn dtr-btn-primary" data-add-record="bonding">+ Bonding Shoe Collected</button>
                </div>
            </div>
            <div class="dtr-table-wrap dtr-fill-wrap dtr-bshoe-wrap" style="max-height:420px">
                <table class="dtr-table dtr-bshoe-table">
                    <thead>
                        <tr>
                            <th>Bonding LabRef No</th>
                            <th>Pull Test LabRefNo</th>
                            <th>Prod. Code</th>
                            <th>Style</th>
                            <th>Colorway</th>
                            <th>MTL #</th>
                            <th>Prod. Date</th>
                            <th>Tested Date</th>
                            <th>Result</th>
                            <th>Status</th>
                            <th>Technician</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bondingShoes as $b): ?>
                            <?php $bRes = strtoupper(trim((string) ($b['Result'] ?? ''))); $bPulls = (array) ($b['PullRefs'] ?? []); $bTint = $bPulls ? (string) ($b['RowResult'] ?? $bRes) : $bRes; ?>
                            <tr class="<?= $rowTint($bTint) ?>">
                                <?php $pOnly = ! empty($b['PullOnly']); $isShut = ! empty($b['IsShutdown']); if ($isShut) { $pOnly = false; } ?>
                                <td class="mono"><?php if ($isShut): ?><span class="dtr-ref-item"><b><?= esc($b['LabRefNo'] ?? '') ?></b></span><?php elseif ($pOnly): ?><span class="muted">-</span><?php else: ?><span class="dtr-ref-item"><?= $labThumb('bonding', (string) ($b['LabRefNo'] ?? '')) ?><a href="#" class="dtr-labref-link" data-open-bonding="<?= (int) ($b['BondingTestID'] ?? 0) ?>" title="Open in Shoe Bonding (U60)"><b><?= esc($b['LabRefNo'] ?? '') ?></b></a></span><?php endif ?></td>
                                <td class="mono"><?php if ($pOnly): ?><span class="dtr-ref-item"><?= $labThumb('bonding', (string) ($b['LabRefNo'] ?? '')) ?><a href="#" class="dtr-labref-link" data-open-bonding="<?= (int) ($b['BondingTestID'] ?? 0) ?>" title="Pull test: open in Shoe Bonding (U60)"><b><?= esc($b['LabRefNo'] ?? '') ?></b></a></span><?php endif ?><?php if (! $bPulls && ! $pOnly): ?><span class="muted">-</span><?php endif ?><?php foreach ($bPulls as $bp): ?><span class="dtr-ref-item"><?= $labThumb('bonding', (string) $bp['ref']) ?><a href="#" class="dtr-labref-link" data-open-bonding="<?= (int) $bp['id'] ?>" title="Pull test: open in Shoe Bonding (U60)"><b><?= esc($bp['ref']) ?></b></a></span><?php endforeach ?></td>
                                <?php $bMtl = trim((string) ($b['MTL'] ?? '')); if ($bMtl === '' && preg_match('/MTL ([^;]+)/', (string) ($b['LabNotesComments'] ?? ''), $mm0)) { $bMtl = trim($mm0[1]); } ?>
                                <td class="mono"><span class="dtr-ref-item"><?php if (preg_match('/^[A-Za-z0-9_-]{3,40}$/', $bMtl)): ?><img class="ik-img dtr-prod-thumb" src="<?= esc(site_url('dtr/erp-image') . '?mtl=' . rawurlencode($bMtl), 'attr') ?>" alt="" title="Click to enlarge" loading="lazy" onerror="this.remove()" style="height:34px;width:auto;max-width:54px;object-fit:contain;cursor:zoom-in;border:1px solid #cbd5e1;border-radius:4px;background:#fff;flex:none"><?php endif ?><b><?= esc($b['POLotNO'] ?? '') ?></b></span></td>
                                <td><?= esc($b['ModelStyle'] ?? '') ?></td>
                                <td><?= esc($b['Color'] ?? '') ?></td>
                                <td class="mono"><?= trim((string) ($b['MTL'] ?? '')) !== '' ? esc(trim((string) $b['MTL'])) : (preg_match('/MTL ([^;]+)/', (string) ($b['LabNotesComments'] ?? ''), $mm) ? esc(trim($mm[1])) : '') ?></td>
                                <td><?= esc(substr((string) ($b['ProductionDate'] ?? ''), 0, 10)) ?></td>
                                <td><?= esc(substr((string) ($b['TestedDate'] ?? ''), 0, 10)) ?><?php foreach ($bPulls as $bp): ?><br><small class="muted">Pull: <?= esc((string) ($bp['tested'] ?? '') ?: '-') ?></small><?php endforeach ?></td>
                                <td><?= $resChip($bRes) ?><?php foreach ($bPulls as $bp): ?><br><small class="muted">Pull: <?= $resChip((string) $bp['result']) ?></small><?php endforeach ?></td>
                                <td><?= $bRes !== '' ? '<span class="badge b-ok">Tested</span>' : '<span class="badge b-warn">Pending</span>' ?><?php if ($bRes === '' && ! empty($b['PendCarry'])): ?><br><small class="muted" title="Scheduled on the last working day, not tested yet">carried over</small><?php endif ?><?php foreach ($bPulls as $bp): ?><br><small class="muted">Pull: <?= trim((string) $bp['result']) !== '' ? 'Tested' : 'Pending' ?></small><?php endforeach ?></td>
                                <td><?= esc(trim((string) ($b['Technician'] ?? ''))) ?><?php foreach ($bPulls as $bp): ?><br><small class="muted">Pull: <?= esc((string) ($bp['tech'] ?? '') ?: '-') ?></small><?php endforeach ?></td>
                                <td style="text-align:center"><?php if ($isShut): ?><?php else: ?><button type="button" class="dtr-del-btn" data-del="<?= ! empty($b['IsLacking']) ? 'lacking' : 'bonding' ?>" data-id="<?= (int) (! empty($b['IsLacking']) ? $b['TrackerID'] : ($b['BondingTestID'] ?? 0)) ?>" data-ref="<?= esc((string) ($b['LabRefNo'] ?? ''), 'attr') ?>" title="Delete this entry" aria-label="Delete this entry"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg></button><?php endif ?></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card glass dtr-section-card dtr-subpanel <?= $subTab === 'shoes' ? '' : 'hidden' ?>" data-subpanel="shoes">
            <?php $shoTot = (int) ($shoesFilteredTotal ?? $shoeTotal ?? count($wholeShoes ?? [])); $shoPg = (int) ($shoesPage ?? 1); $shoPages = max(1, (int) ceil($shoTot / 15)); ?>
            <div class="dtr-card-head" style="gap:8px;flex-wrap:wrap;justify-content:flex-start">
                <input type="search" class="dtr-list-search" style="margin:0 auto 0 0 !important;order:-1" placeholder="Search Lab Ref, Prod. Code, Style, Colorway, MTL..." aria-label="Search the list">
                <div class="dtr-subtabs-right muted sm" style="display:flex;align-items:center;gap:8px;margin-left:auto">
                    <span id="lt-count-shoes" data-total="<?= $shoTot ?>"><?= number_format($shoTot) ?> record(s)</span>
                    <button type="button" class="dtr-btn dtr-btn-outline dtr-lt-prev" data-lt-type="shoes">&lsaquo; Prev</button>
                    <span id="lt-page-shoes" class="mono" data-page="<?= $shoPg ?>" data-pages="<?= $shoPages ?>"><?= $shoPg ?> / <?= $shoPages ?></span>
                    <button type="button" class="dtr-btn dtr-btn-outline dtr-lt-next" data-lt-type="shoes">Next &rsaquo;</button>
                    <button type="button" class="dtr-btn dtr-btn-primary" data-add-record="shoes">+ Whole Shoe Collected</button>
                </div>
            </div>
            <div class="dtr-table-wrap dtr-fill-wrap" style="max-height:420px">
                <table class="dtr-table dtr-wlist-table dtr-ws-mothers">
                    <thead>
                        <tr>
                            <th>Whole Shoe LabRefNo</th>
                            <th>Line</th>
                            <th>Prod. Code</th>
                            <th>Colorway</th>
                            <th>Style No.</th>
                            <th>MTL #</th>
                            <th>Prod. Date</th>
                            <th>Status</th>
                            <th>Remarks</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $delIco = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>'; ?>
                        <?php foreach ($wholeShoes as $s): ?>
                            <?php
                            $mRef   = (string) ($s['MotherRef'] ?? $s['LabRefNo'] ?? '');
                            $mRes   = (string) ($s['MotherResult'] ?? '');
                            $kids   = $s['Children'] ?? [$s];
                            $kDone  = (int) ($s['ChildDone'] ?? 0);
                            $kTotal = (int) ($s['ChildTotal'] ?? count($kids));
                            $mLine  = trim((string) ($s['LineNo'] ?? '')) !== '' ? trim((string) $s['LineNo']) : (trim((string) ($s['Building'] ?? '')) !== '' ? (string) $s['Building'] : '');
                            ?>
                            <tr class="ws-mother <?= $rowTint($mRes) ?>" data-ws-mother="<?= esc($mRef, 'attr') ?>" title="Click to show / hide its test types">
                                <td class="mono"><span class="ws-caret" aria-hidden="true">&#9656;</span> <?php if (! empty($s['WholeshoeLabRefNo'])): ?><button type="button" class="dtr-dl-btn" data-dl-labref="<?= esc($s['WholeshoeLabRefNo'], 'attr') ?>" data-dl-route="dtr/wholeshoe-report-pdf" data-dl-suffix="Whole-Footwear-Test-Results" title="Download Whole Footwear Test Results" aria-label="Download report"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M12 11v6"/><path d="m9 14 3 3 3-3"/></svg></button> <?php endif ?><a href="#" class="dtr-labref-link" data-open-wholeshoe="<?= esc($mRef, 'attr') ?>" title="Open the whole shoe report (all its tests)"><b><?= esc($mRef) ?></b></a> <small class="muted">(<?= $kTotal ?> test<?= $kTotal === 1 ? '' : 's' ?>)</small></td>
                                <td><?= esc($mLine) ?></td>
                                <td class="mono"><b><?= esc($s['ProdCode'] ?? '') ?></b></td>
                                <td><?= esc($s['Colorway'] ?? '') ?></td>
                                <td><?= esc($s['StyleNo'] ?? '') ?></td>
                                <td class="mono"><?= esc($s['MTL'] ?? '') ?></td>
                                <td><?= esc(substr((string) ($s['IntakeProductionDate'] ?? ''), 0, 10)) ?></td>
                                <td>
                                    <?= $kDone === 0 ? '<span class="badge b-warn">Pending</span>' : ($kDone >= $kTotal ? '<span class="badge b-ok">Tested</span>' : '<span class="badge b-warn">Testing</span>') ?> <small class="muted"><?= $kDone ?>/<?= $kTotal ?></small>
                                    <?php /* per test type: completed (has a result) or not yet tested */ ?>
                                    <?php foreach ($kids as $k0): ?>
                                        <?php $k0Done = trim((string) ($k0['FinalResult'] ?? '')) !== ''; $k0Name = trim((string) ($k0['TestTypes'] ?? '')) ?: 'Test'; ?>
                                        <br><small class="dtr-ws-st <?= $k0Done ? 'is-done' : 'is-todo' ?>"><?= esc($k0Name) ?>: <?= $k0Done ? 'Completed' : 'Not yet tested' ?></small>
                                    <?php endforeach ?>
                                </td>
                                <?php /* Remarks = the overall result of the shoe, from the FinalResult of its tests (PASS when all of them passed; FAILED as soon as one failed; nothing until every test has a result) */ ?>
                                <td><?= $mRes !== '' ? $resChip($mRes) : '<span class="muted">' . ($kDone === 0 ? 'Not yet tested' : 'Testing in progress') . '</span>' ?></td>
                                <td></td>
                            </tr>
                            <tr class="ws-kids" hidden>
                                <td colspan="10">
                                    <table class="dtr-table ws-child-table">
                                        <thead><tr><th>Lab Ref No</th><th>Test Type</th><th>Tested Date</th><th>Result</th><th>Technician</th><th></th></tr></thead>
                                        <tbody>
                                            <?php foreach ($kids as $k): ?>
                                                <?php $kRes = strtoupper(trim((string) ($k['FinalResult'] ?? ''))); ?>
                                                <tr class="<?= $rowTint($kRes) ?>">
                                                    <td class="mono"><?php if (! empty($k['LabRefNo'])): ?><?= $labThumb('shoes', (string) $k['LabRefNo']) ?><a href="#" class="dtr-labref-link" data-open-wholeshoe="<?= esc($k['LabRefNo'], 'attr') ?>" title="Open in Whole Shoe"><b><?= esc($k['LabRefNo']) ?></b></a><?php endif ?></td>
                                                    <td><?= esc($k['TestTypes'] ?? '') ?></td>
                                                    <td><?= esc(substr((string) (($k['TimeCompleted'] ?? '') ?: ($k['TimeStarted'] ?? '')), 0, 10)) ?></td>
                                                    <td><?= $resChip($kRes) ?></td>
                                                    <td><?= esc(trim((string) ($k['Technician'] ?? ''))) ?></td>
                                                    <td style="text-align:center"><button type="button" class="dtr-del-btn" data-del="wholeshoe" data-id="<?= (int) ($k['TrackerID'] ?? 0) ?>" data-ref="<?= esc((string) ($k['LabRefNo'] ?? ''), 'attr') ?>" title="Delete this entry" aria-label="Delete this entry"><?= $delIco ?></button></td>
                                                </tr>
                                            <?php endforeach ?>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>


        <!-- AppSheet ERP Test Orders Sub-panel -->
        <div class="card glass dtr-section-card dtr-subpanel <?= $subTab === 'erp' ? '' : 'hidden' ?>" data-subpanel="erp">
            <div class="dtr-card-head" style="gap:8px;flex-wrap:wrap;justify-content:flex-start">
                <input type="search" class="dtr-list-search dtr-as-search" data-as-type="erp" style="margin:0 auto 0 0 !important;order:-1" placeholder="Search Lab Ref, VSP/PO, MTL, Style, Colorway, TR No..." aria-label="Search ERP test orders">
                <div class="dtr-subtabs-right muted sm" style="display:flex;align-items:center;gap:8px;margin-left:auto">
                    <span id="as-count-erp">0 record(s)</span>
                    <button type="button" class="dtr-btn dtr-btn-outline dtr-as-prev" data-as-type="erp">&lsaquo; Prev</button>
                    <span id="as-page-erp" class="mono">1 / 1</span>
                    <button type="button" class="dtr-btn dtr-btn-outline dtr-as-next" data-as-type="erp">Next &rsaquo;</button>
                </div>
            </div>
            <div class="dtr-table-wrap dtr-fill-wrap" style="max-height:440px">
                <table class="dtr-table">
                    <thead>
                        <tr>
                            <th>Lab Ref No</th>
                            <th>VSP / Prod. Code</th>
                            <th>MTL #</th>
                            <th>Style / Colorway</th>
                            <th>Line / Side</th>
                            <th>Test Date</th>
                            <th>Send / Receipt Date</th>
                            <th>Result</th>
                            <th>TR Numbers (Bonding / Pull / Flex / Aging / Hydro / Wash / UV)</th>
                            <th>Creator</th>
                        </tr>
                    </thead>
                    <tbody id="as-tbody-erp">
                        <tr><td colspan="10" class="muted" style="text-align:center;padding:24px">Select this tab or search to load ERP Test Orders...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- AppSheet CFA Reports & Bonding CAP Sub-panel -->
        <div class="card glass dtr-section-card dtr-subpanel <?= $subTab === 'cfa' ? '' : 'hidden' ?>" data-subpanel="cfa">
            <div class="dtr-card-head" style="gap:8px;flex-wrap:wrap;justify-content:flex-start">
                <input type="search" class="dtr-list-search dtr-as-search" data-as-type="cfa" style="margin:0 auto 0 0 !important;order:-1" placeholder="Search Lab Ref, Prod. Code, MTL, Style, Test Name, TR No..." aria-label="Search CFA reports">
                <div class="dtr-subtabs-right muted sm" style="display:flex;align-items:center;gap:8px;margin-left:auto">
                    <span id="as-count-cfa">0 record(s)</span>
                    <button type="button" class="dtr-btn dtr-btn-outline dtr-as-prev" data-as-type="cfa">&lsaquo; Prev</button>
                    <span id="as-page-cfa" class="mono">1 / 1</span>
                    <button type="button" class="dtr-btn dtr-btn-outline dtr-as-next" data-as-type="cfa">Next &rsaquo;</button>
                </div>
            </div>
            <div id="as-cap-wrap" style="margin-bottom:10px;display:none"></div>
            <div class="dtr-table-wrap dtr-fill-wrap" style="max-height:400px">
                <table class="dtr-table">
                    <thead>
                        <tr>
                            <th>Lab Ref No</th>
                            <th>Prod. Code</th>
                            <th>Prod. Date</th>
                            <th>Style / Colorway</th>
                            <th>MTL #</th>
                            <th>Test Name</th>
                            <th>TR Refs</th>
                            <th>CFA Ack / Requests</th>
                            <th>Doc Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody id="as-tbody-cfa">
                        <tr><td colspan="10" class="muted" style="text-align:center;padding:24px">Select this tab or search to load CFA Test Reports...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- AppSheet Production Test Schedule Sub-panel -->
        <div class="card glass dtr-section-card dtr-subpanel <?= $subTab === 'schedule' ? '' : 'hidden' ?>" data-subpanel="schedule">
            <div class="dtr-card-head" style="gap:8px;flex-wrap:wrap;justify-content:flex-start">
                <input type="search" class="dtr-list-search dtr-as-search" data-as-type="schedule" style="margin:0 auto 0 0 !important;order:-1" placeholder="Search Lab Ref, Prod. Code, MTL, Style, Test Name, Machine..." aria-label="Search production test schedule">
                <div class="dtr-subtabs-right muted sm" style="display:flex;align-items:center;gap:8px;margin-left:auto">
                    <span id="as-count-schedule">0 record(s)</span>
                    <button type="button" class="dtr-btn dtr-btn-outline dtr-as-prev" data-as-type="schedule">&lsaquo; Prev</button>
                    <span id="as-page-schedule" class="mono">1 / 1</span>
                    <button type="button" class="dtr-btn dtr-btn-outline dtr-as-next" data-as-type="schedule">Next &rsaquo;</button>
                </div>
            </div>
            <div class="dtr-table-wrap dtr-fill-wrap" style="max-height:440px">
                <table class="dtr-table">
                    <thead>
                        <tr>
                            <th>Lab Ref No</th>
                            <th>Prod. Date</th>
                            <th>Bldg / Line</th>
                            <th>Prod. Code / MTL</th>
                            <th>Style / Colorway</th>
                            <th>Test Name / Type</th>
                            <th>Machine</th>
                            <th>Status</th>
                            <th>Result</th>
                            <th>Other Details (Failure / Cause / Retest)</th>
                        </tr>
                    </thead>
                    <tbody id="as-tbody-schedule">
                        <tr><td colspan="10" class="muted" style="text-align:center;padding:24px">Select this tab or search to load Production Test Schedule...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- AppSheet Failed Samples Registry Sub-panel -->
        <div class="card glass dtr-section-card dtr-subpanel <?= $subTab === 'failed' ? '' : 'hidden' ?>" data-subpanel="failed">
            <div class="dtr-card-head" style="gap:8px;flex-wrap:wrap;justify-content:flex-start">
                <input type="search" class="dtr-list-search dtr-as-search" data-as-type="failed" style="margin:0 auto 0 0 !important;order:-1" placeholder="Search Sample ID, MTL, Style, Colorway, Test Failed..." aria-label="Search failed samples registry">
                <div class="dtr-subtabs-right muted sm" style="display:flex;align-items:center;gap:8px;margin-left:auto">
                    <span id="as-count-failed">0 record(s)</span>
                    <button type="button" class="dtr-btn dtr-btn-outline dtr-as-prev" data-as-type="failed">&lsaquo; Prev</button>
                    <span id="as-page-failed" class="mono">1 / 1</span>
                    <button type="button" class="dtr-btn dtr-btn-outline dtr-as-next" data-as-type="failed">Next &rsaquo;</button>
                </div>
            </div>
            <div class="dtr-table-wrap dtr-fill-wrap" style="max-height:440px">
                <table class="dtr-table">
                    <thead>
                        <tr>
                            <th>Sample ID</th>
                            <th>MTL #</th>
                            <th>Style No.</th>
                            <th>Colorway</th>
                            <th>Test Failed</th>
                            <th>Created Date</th>
                        </tr>
                    </thead>
                    <tbody id="as-tbody-failed">
                        <tr><td colspan="6" class="muted" style="text-align:center;padding:24px">Select this tab or search to load Failed Samples Registry...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
