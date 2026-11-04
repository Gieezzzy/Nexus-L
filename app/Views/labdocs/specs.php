<?php
/** @var array $reg */
/** @var array $cards */
/** @var array $docs */
$kindLabel = ['pdf' => 'PDF', 'xlsx' => 'Excel', 'docx' => 'Word'];
$hues      = [212, 262, 164, 24, 190, 338, 132, 42, 292, 4];
$years     = array_values(array_unique(array_filter(array_column($cards, 'year'))));
rsort($years);
$hue = static fn (string $y): int => $hues[(int) $y % count($hues)];
?>
<section class="ld vm" id="vm" data-count="<?= count($cards) ?>">
    <div class="ld-bar glass">
        <div class="ld-bar-l">
            <div class="ld-title"><b><?= esc($reg['code']) ?></b> <span><?= esc($reg['title']) ?></span></div>
            <div class="ld-meta">Folder <code><?= esc($reg['folder_name']) ?></code> · <span id="vm-count"><?= count($cards) ?></span> specification sheets</div>
        </div>
        <div class="ld-bar-r">
            <input type="search" id="vm-search" class="vm-search" placeholder="Search a specification sheet or year" aria-label="Search specification sheets" autocomplete="off">
        </div>
    </div>

    <?php if ($docs): ?>
    <nav class="vm-docs" aria-label="Excel files of the folder">
        <?php foreach ($docs as $d): ?>
            <a class="vm-doc" href="<?= esc($d['url'], 'attr') ?>" target="_blank" rel="noopener" title="Opens in a new tab (<?= esc($kindLabel[$d['kind']] ?? strtoupper($d['kind'])) ?>)">
                <span class="vm-doc-k"><?= esc($kindLabel[$d['kind']] ?? strtoupper($d['kind'])) ?></span><?= esc($d['label']) ?>
            </a>
        <?php endforeach ?>
    </nav>
    <?php endif ?>

    <div class="vm-chips" id="vm-chips" role="group" aria-label="Year">
        <button type="button" class="vm-chip on" data-s="">All</button>
        <?php foreach ($years as $y): ?>
            <button type="button" class="vm-chip" data-s="<?= esc($y, 'attr') ?>" style="--h:<?= $hue($y) ?>"><?= esc($y) ?></button>
        <?php endforeach ?>
    </div>

    <div class="vm-grid" id="vm-grid">
        <?php foreach ($cards as $c): ?>
            <article class="vm-card"<?php if (!empty($c['thumb'])): ?> data-prev="<?= esc($c['thumb'], 'attr') ?>"<?php endif ?> data-s="<?= esc($c['year'], 'attr') ?>" style="--h:<?= $hue((string) $c['year']) ?>" data-t="<?= esc(mb_strtolower($c['name'] . ' ' . $c['year']), 'attr') ?>">
                <a class="vm-main" href="<?= esc($c['url'], 'attr') ?>" aria-label="Open <?= esc($c['name'], 'attr') ?>">
                    <span class="vm-code vm-spec-name"><?= esc($c['name']) ?></span>
                    <span class="vm-upd">Year <b><?= esc($c['year'] ?: '-') ?></b><?php if ($c['edited']): ?> · edited <?= esc($c['edited']) ?><?php endif ?></span>
                </a>
                <div class="vm-foot">
                    <a class="vm-btn" href="<?= esc($c['url'], 'attr') ?>">Open</a>
                    <span class="vm-pg"><?= (int) $c['pages'] ?> p</span>
                </div>
            </article>
        <?php endforeach ?>
    </div>
    <p class="vm-none" id="vm-none" hidden>No specification sheet matches.</p>
    <ul class="ld-menu vm-pop" id="vm-pop" hidden role="menu"></ul>
</section>
