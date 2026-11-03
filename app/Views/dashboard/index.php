<?php $u = service('auth')->user(); ?>
<div class="ld" id="ld-root" data-src="<?= esc(site_url('dashboard/data')) ?>" data-dtr="<?= esc(site_url('dtr')) ?>">
    <div class="ld-bg" aria-hidden="true"><i></i><i></i><i></i></div>

    <header class="ld-glass ld-head">
        <div class="ld-title">
            <h2>Laboratory overview</h2>
            <p id="ld-range">Hello <?= esc($u['name']) ?>, loading the latest figures…</p>
        </div>
        <div class="ld-ctrl">
            <div class="ld-seg" role="radiogroup" aria-label="Period">
                <?php foreach ([1 => 'Today', 7 => '7 days', 30 => '30 days', 90 => '90 days'] as $d => $l): ?>
                    <button type="button" role="radio" aria-checked="<?= $d === 30 ? 'true' : 'false' ?>" data-days="<?= $d ?>"><?= $l ?></button>
                <?php endforeach ?>
            </div>
            <button type="button" class="ld-btn" id="ld-refresh">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11a8 8 0 10-2.3 5.7M20 4v7h-7"/></svg>Refresh
            </button>
        </div>
        <nav class="ld-nav" aria-label="Dashboard sections">
            <a href="#ld-s-overview">Overview</a><a href="#ld-s-bonding">Bonding</a><a href="#ld-s-materials">Materials</a>
            <a href="#ld-s-wholeshoe">Whole shoe</a><a href="#ld-s-people">People and suppliers</a><a href="#ld-s-reports">Reports</a>
        </nav>
    </header>

    <!-- Overview -->
    <section id="ld-s-overview" class="ld-sec" aria-labelledby="ld-h-overview">
        <h2 class="ld-sh" id="ld-h-overview">Overview</h2>
        <div class="ld-kpis" id="ld-kpis"><?php for ($i = 0; $i < 5; $i++): ?><div class="ld-glass ld-kpi" aria-hidden="true"></div><?php endfor ?></div>

        <div class="ld-row">
            <div class="ld-glass ld-c12 ld-matrix">
                <?php foreach ([['ongoing', 'Ongoing', 'Collected or in testing, no final result yet'], ['failed', 'Failed', 'Final result Fail in the period'], ['for_report', 'For report', 'Finished, no approved report yet']] as [$k, $label, $hint]): ?>
                    <div class="ld-mx ld-mx-<?= $k ?>">
                        <h3><?= $label ?> <small><?= $hint ?></small></h3>
                        <div class="ld-mx-row">
                            <?php foreach (['bonding' => 'Bonding', 'material' => 'Material', 'wholeshoe' => 'Whole shoe'] as $g => $gl): ?>
                                <div><b data-tile="<?= $k ?>.<?= $g ?>">–</b><small><?= $gl ?></small></div>
                            <?php endforeach ?>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
        </div>

        <div class="ld-row">
            <article class="ld-glass ld-c8">
                <header class="ld-ch"><h3>Lab references finished each day</h3><p>Final results per day, all tests, bonding and failures; point at a day for its lab references</p></header>
                <div id="ld-ch-daily" class="ld-chart"></div>
            </article>
            <article class="ld-glass ld-c4">
                <header class="ld-ch"><h3>Bonding results</h3><p>Whole shoe bonding: passed, tolerable and failed in the period</p></header>
                <div id="ld-ch-mix" class="ld-chart"></div>
            </article>
        </div>
    </section>

    <!-- Bonding -->
    <section id="ld-s-bonding" class="ld-sec" aria-labelledby="ld-h-bonding">
        <h2 class="ld-sh" id="ld-h-bonding">Bonding</h2>
        <div class="ld-row">
            <article class="ld-glass ld-c4 ld-gauge-card">
                <header class="ld-ch"><h3>Overall equipment effectiveness
                    </h3>
                    <p id="ld-oee-note">Six bonding testers</p></header>
                <svg class="ld-gauge" viewBox="0 0 240 140" role="img" aria-labelledby="ld-oee-text">
                    <defs><linearGradient id="ld-gg" x1="0" x2="1"><stop offset="0" stop-color="#ef4444"/><stop offset=".55" stop-color="#f59e0b"/><stop offset="1" stop-color="#22c55e"/></linearGradient></defs>
                    <path d="M25 120 A95 95 0 0 1 215 120" class="ld-g-track"/>
                    <path d="M25 120 A95 95 0 0 1 215 120" class="ld-g-val" id="ld-g-val" pathLength="100" stroke-dasharray="0 100"/>
                    <line id="ld-g-target" x1="120" y1="120" x2="120" y2="14" class="ld-g-target"/>
                    <line id="ld-g-needle" x1="120" y1="120" x2="120" y2="42" class="ld-g-needle"/>
                    <circle cx="120" cy="120" r="8" class="ld-g-hub"/>
                </svg>
                <p class="ld-oee-text" id="ld-oee-text"><span>OEE <b id="ld-oee">–</b></span><span>Target <b id="ld-target">–</b></span></p>
            </article>
            <article class="ld-glass ld-c8">
                <header class="ld-ch"><h3>Bonding testers</h3><p>OEE and its three parts for each machine</p></header>
                <div class="ld-mgrid" id="ld-machines"></div>
            </article>
        </div>
        <div class="ld-row">
            <article class="ld-glass ld-c6">
                <header class="ld-ch"><h3>Bonding tests per day</h3><p>Stacked by tester, last 14 days</p></header>
                <div id="ld-ch-trend" class="ld-chart"></div>
            </article>
            <article class="ld-glass ld-c6">
                <header class="ld-ch"><h3>Average bond strength</h3><p>kgf/cm per day; dashed line is the 2.5 kgf/cm minimum</p></header>
                <div id="ld-ch-strength" class="ld-chart"></div>
            </article>
        </div>
        <div class="ld-row">
            <article class="ld-glass ld-c4">
                <header class="ld-ch"><h3>Results by bonded part</h3><p>Every reading in the period</p></header>
                <div id="ld-ch-parts" class="ld-chart"></div>
            </article>
            <article class="ld-glass ld-c8">
                <header class="ld-ch"><h3>Pull, eyelet and rivet tests</h3><p>Tensile pulls on the bonding testers, peak force in kgf</p></header>
                <div id="ld-pull" class="ld-tw"></div>
            </article>
        </div>
        <div class="ld-row">
            <article class="ld-glass ld-c12">
                <header class="ld-ch"><h3>Bonding tracker</h3><p>Latest samples collected from the lines; click a row to open it in the bonding view</p></header>
                <div class="ld-tw">
                    <table class="ld-table">
                        <thead><tr><th>Lab Reference</th><th>Prod code</th><th>Style</th><th>Line</th><th>Prod date</th><th>Test date</th><th>Technician</th><th>Result</th></tr></thead>
                        <tbody id="ld-ongoing"><tr><td colspan="8">Loading…</td></tr></tbody>
                    </table>
                </div>
            </article>
        </div>
    </section>

    <!-- Materials -->
    <section id="ld-s-materials" class="ld-sec" aria-labelledby="ld-h-materials">
        <h2 class="ld-sh" id="ld-h-materials">Materials</h2>
        <div class="ld-row">
            <article class="ld-glass ld-c6">
                <header class="ld-ch"><h3>Results by test category</h3><p>Pass, tolerable, fail and ongoing lab references</p></header>
                <div id="ld-ch-cats" class="ld-chart"></div>
            </article>
            <article class="ld-glass ld-c6">
                <header class="ld-ch"><h3>Where failures happen</h3><p id="ld-items-note">Test items inside the reports, most failures first</p></header>
                <div id="ld-items" class="ld-tw"></div>
            </article>
        </div>
        <div class="ld-row">
            <article class="ld-glass ld-c7">
                <header class="ld-ch"><h3>Most performed tests</h3><p>Times each test was run in the period, with failures</p></header>
                <div id="ld-ch-tests" class="ld-chart"></div>
            </article>
            <article class="ld-glass ld-c5">
                <header class="ld-ch"><h3>Flex and abrasion testers</h3><p>Cycles completed against the target</p></header>
                <div id="ld-running" class="ld-plist"></div>
            </article>
        </div>
    </section>

    <!-- Whole shoe -->
    <section id="ld-s-wholeshoe" class="ld-sec" aria-labelledby="ld-h-ws">
        <h2 class="ld-sh" id="ld-h-ws">Whole shoe</h2>
        <div class="ld-row">
            <article class="ld-glass ld-c12">
                <header class="ld-ch"><h3>Ongoing tests</h3><p>Each test type collected in the period and how many are finished</p></header>
                <div id="ld-tracking" class="ld-tgrid"></div>
            </article>
        </div>
    </section>

    <!-- People -->
    <section id="ld-s-people" class="ld-sec" aria-labelledby="ld-h-people">
        <h2 class="ld-sh" id="ld-h-people">People and suppliers</h2>
        <div class="ld-row">
            <article class="ld-glass ld-c6">
                <header class="ld-ch"><h3>Technician workload</h3><p>Lab references finished in the period</p></header>
                <div id="ld-ch-people" class="ld-chart"></div>
            </article>
            <article class="ld-glass ld-c6">
                <header class="ld-ch"><h3>Suppliers</h3><p>Most failures first</p></header>
                <div id="ld-supplier" class="ld-tw"></div>
            </article>
        </div>
    </section>

    <!-- Reports -->
    <section id="ld-s-reports" class="ld-sec" aria-labelledby="ld-h-reports">
        <h2 class="ld-sh" id="ld-h-reports">Reports</h2>
        <div class="ld-row">
            <article class="ld-glass ld-c6">
                <header class="ld-ch"><h3>InterLink applications</h3><p>All stored applications by job status</p></header>
                <div id="ld-ch-il" class="ld-chart"></div>
            </article>
            <article class="ld-glass ld-c6">
                <header class="ld-ch"><h3>Report approvals</h3><p>Signatory approvals of finished reports</p></header>
                <div id="ld-approvals" class="ld-big2"></div>
            </article>
        </div>
    </section>
</div>
