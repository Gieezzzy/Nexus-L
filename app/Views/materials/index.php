<?php
/**
 * Material Testing: the materials received in the Lab Tracker (Material Received). Each Lab Reference is a parent; the tests its material type has
 * (from the lab's Access material test system) are its children, with their status and result.
 *
 * @var list<array{slug:string,name:string}> $categories
 * @var bool $canEdit
 */
?>
<section class="mt-page" id="mt-root" data-base="<?= esc(site_url('materials'), 'attr') ?>" data-can-edit="<?= $canEdit ? '1' : '0' ?>" data-user="<?= esc($userName ?? '', 'attr') ?>">
    <header class="mt-top">
        <div>
            <h1 class="mt-title">Material Testing</h1>
            <p class="mt-sub">Materials received in the Lab Tracker, each Lab Reference with the tests of its material type.</p>
        </div>
    </header>

    <div class="mt-tabs" role="tablist" aria-label="Material Testing">
        <button type="button" class="mt-tab active" role="tab" aria-selected="true">Material Testing</button>
    </div>

    <div class="mt-layout">
        <aside class="mt-cats card glass" aria-label="Material Categories">
            <h2 class="mt-cats-h">Material Categories</h2>
            <ul id="mt-cat-list" class="mt-cat-list">
                <li><button type="button" class="mt-cat active" data-cat=""><span>All materials</span><small id="mt-count-all">0</small></button></li>
                <?php foreach ($categories as $c): ?>
                    <li><button type="button" class="mt-cat" data-cat="<?= esc($c['slug'], 'attr') ?>"><span><?= esc($c['name']) ?></span><small data-count="<?= esc($c['slug'], 'attr') ?>">0</small></button></li>
                <?php endforeach ?>
            </ul>
        </aside>

        <div class="mt-main">
            <div class="mt-kpis" id="mt-kpis" aria-live="polite"></div>
            <div class="mt-toolbar card glass">
                <input type="search" id="mt-q" class="mt-in" placeholder="Search Lab Ref, material, code, supplier, style, MTL..." aria-label="Search the materials">
                <label class="mt-field">Received from <input type="date" id="mt-from" class="mt-in"></label>
                <label class="mt-field">to <input type="date" id="mt-to" class="mt-in"></label>
                <span class="mt-gap"></span>
                <button type="button" class="dtr-btn dtr-btn-outline" id="mt-expand">Expand all</button>
                <button type="button" class="dtr-btn dtr-btn-outline" id="mt-collapse">Collapse all</button>
                <button type="button" class="dtr-btn dtr-btn-outline" id="mt-refresh">Refresh</button>
            </div>
            <div class="mt-stabs" id="mt-stabs" role="tablist" aria-label="Status"></div>
            <div id="mt-list" class="mt-list" aria-live="polite"><p class="muted">Loading...</p></div>
            <div class="mt-pager" id="mt-pager"></div>
        </div>
    </div>
    <div class="mt-modal" id="mt-sheet" hidden role="dialog" aria-modal="true" aria-labelledby="mt-sh-title">
        <div class="mt-modal-box">
            <header class="mt-modal-head">
                <h2 id="mt-sh-title">Test sheet</h2>
                <div class="mt-modal-act">
                    <button type="button" class="dtr-btn dtr-btn-primary" id="mt-sh-saveall" hidden>Save all tests</button>
                    <button type="button" class="dtr-btn dtr-btn-outline" id="mt-sh-close">Close</button>
                </div>
            </header>
            <div class="mt-modal-body" id="mt-sh-body"><p class="muted">Loading...</p></div>
        </div>
    </div>
</section>
