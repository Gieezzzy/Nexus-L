<?php
/** @var array $reg */
/** @var array $cards */
/** @var array $docs */
/** @var array $sections */
$kindLabel = ['pdf' => 'PDF', 'xlsx' => 'Excel', 'docx' => 'Word'];
?>
<section class="ld vm" id="vm" data-count="<?= count($cards) ?>">
    <div class="ld-bar glass">
        <div class="ld-bar-l">
            <div class="ld-title"><b><?= esc($reg['code']) ?></b> <span><?= esc($reg['title']) ?></span></div>
            <div class="ld-meta">Folder <code><?= esc($reg['folder_name']) ?></code> · <span id="vm-count"><?= count($cards) ?></span> test methods</div>
        </div>
        <div class="ld-bar-r">
            <input type="search" id="vm-search" class="vm-search" placeholder="Search a test method or VF number" aria-label="Search test methods" autocomplete="off">
        </div>
    </div>

    <?php if ($docs): ?>
    <nav class="vm-docs" aria-label="Documents of the folder">
        <?php foreach ($docs as $d): ?>
            <a class="vm-doc" href="<?= esc($d['url'], 'attr') ?>" target="_blank" rel="noopener" title="Opens in a new tab (<?= esc($kindLabel[$d['kind']] ?? strtoupper($d['kind'])) ?>)">
                <span class="vm-doc-k"><?= esc($kindLabel[$d['kind']] ?? strtoupper($d['kind'])) ?></span><?= esc($d['label']) ?>
            </a>
        <?php endforeach ?>
    </nav>
    <?php endif ?>

    <div class="vm-chips" id="vm-chips" role="group" aria-label="Series">
        <button type="button" class="vm-chip on" data-s="">All</button>
        <?php
        $present = array_values(array_unique(array_column($cards, 'series')));
        sort($present);
        foreach ($present as $s): ?>
            <button type="button" class="vm-chip" data-s="<?= esc($s, 'attr') ?>" title="<?= esc($sections[$s] ?? '', 'attr') ?>"><?= esc($s) ?><?= isset($sections[$s]) ? ' · ' . esc(mb_strimwidth($sections[$s], 0, 22, '…')) : '' ?></button>
        <?php endforeach ?>
    </div>

    <div class="vm-grid" id="vm-grid">
        <?php foreach ($cards as $c): ?>
            <article class="vm-card"<?php if (!empty($c['thumb'])): ?> data-prev="<?= esc($c['thumb'], 'attr') ?>"<?php endif ?> data-code="<?= esc($c['code'], 'attr') ?>" data-s="<?= esc($c['series'], 'attr') ?>" data-t="<?= esc(mb_strtolower($c['code'] . ' ' . $c['title'] . ' ' . $c['rev']), 'attr') ?>">
                <a class="vm-main" href="<?= esc($c['url'], 'attr') ?>" aria-label="Open <?= esc($c['code'] . ' ' . $c['title'], 'attr') ?>">
                    <span class="vm-code"><?= esc($c['code']) ?></span>
                    <span class="vm-name"><?= esc($c['title']) ?></span>
                    <span class="vm-upd">Updated <b><?= esc($c['rev'] ?: '-') ?></b><?php if ($c['edited']): ?> · edited <?= esc($c['edited']) ?><?php endif ?></span>
                </a>
                <div class="vm-foot">
                    <a class="vm-btn" href="<?= esc($c['url'], 'attr') ?>">Open</a>
                    <?php if (count($c['ill']) === 1): ?>
                        <a class="vm-btn vm-ill" href="<?= esc($c['ill'][0]['url'], 'attr') ?>" target="_blank" rel="noopener">Illustrated</a>
                    <?php elseif (count($c['ill']) > 1): ?>
                        <button type="button" class="vm-btn vm-ill" data-ill="<?= esc(json_encode($c['ill'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>" aria-haspopup="true">Illustrated ▾</button>
                    <?php endif ?>
                    <span class="vm-pg"><?= (int) $c['pages'] ?> p</span>
                </div>
            </article>
        <?php endforeach ?>
    </div>
    <p class="vm-none" id="vm-none" hidden>No test method matches.</p>
    <ul class="ld-menu vm-pop" id="vm-pop" hidden role="menu"></ul>
</section>
