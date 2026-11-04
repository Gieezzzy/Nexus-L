<?php
/** @var array $reg */
/** @var array $folders */
/** @var array $files */
/** @var array $crumbs */
/** @var string $q */
/** @var string $base */
$kind = ['pdf' => 'PDF', 'jpg' => 'Picture', 'jpeg' => 'Picture', 'png' => 'Picture', 'gif' => 'Picture', 'xlsx' => 'Excel', 'xls' => 'Excel', 'doc' => 'Word', 'docx' => 'Word', 'zip' => 'Zip'];
$hue  = ['pdf' => 4, 'jpg' => 164, 'jpeg' => 164, 'png' => 164, 'gif' => 164, 'xlsx' => 132, 'xls' => 132, 'doc' => 212, 'docx' => 212, 'zip' => 42];
$size = static function (int $b): string {
    return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, (int) round($b / 1024)) . ' KB';
};
?>
<section class="ld vm" id="vm">
    <div class="ld-bar glass">
        <div class="ld-bar-l">
            <div class="ld-title"><?php if (! empty($parent)): ?><a class="vd-back fl-up" href="<?= esc($parent, 'attr') ?>" aria-label="Back">‹ Back</a><?php endif ?><b><?= esc($reg['code']) ?></b> <span><?= esc($reg['title']) ?></span></div>
            <nav class="fl-crumbs" aria-label="Folder">
                <a href="<?= esc($base, 'attr') ?>">All folders</a>
                <?php foreach ($crumbs as $c): ?> <span aria-hidden="true">›</span> <a href="<?= esc($c['url'], 'attr') ?>"><?= esc($c['name']) ?></a><?php endforeach ?>
                <?php if ($q !== ''): ?> <span aria-hidden="true">›</span> <span>Search “<?= esc($q) ?>”</span><?php endif ?>
            </nav>
        </div>
        <div class="ld-bar-r">
            <form method="get" action="<?= esc($base, 'attr') ?>" role="search" class="fl-search">
                <input type="search" name="q" value="<?= esc($q, 'attr') ?>" class="vm-search" placeholder="Search file names in every folder" aria-label="Search files" autocomplete="off">
            </form>
        </div>
    </div>

    <?php if ($folders): ?>
    <div class="vm-grid fl-folders" aria-label="Folders">
        <?php foreach ($folders as $f): ?>
            <a class="vm-card fl-folder" style="--h:42" href="<?= esc($f['url'], 'attr') ?>">
                <svg viewBox="0 0 24 24" width="34" height="34" aria-hidden="true"><path d="M3 6a2 2 0 012-2h4l2 2h8a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2z" fill="hsl(42 55% 62%)" stroke="hsl(42 40% 38%)" stroke-width="1.2"/></svg>
                <span class="vm-name"><?= esc($f['name']) ?></span>
                <span class="vm-upd"><b><?= (int) $f['count'] ?></b> file<?= $f['count'] === 1 ? '' : 's' ?></span>
            </a>
        <?php endforeach ?>
    </div>
    <?php endif ?>

    <?php if ($files): ?>
    <p class="ld-meta fl-count"><?= count($files) ?> file<?= count($files) === 1 ? '' : 's' ?><?= $q !== '' && count($files) >= 300 ? ' (the first 300 matches)' : '' ?></p>
    <div class="vm-grid fl-files">
        <?php foreach ($files as $f):
            $k = $kind[$f['ext']] ?? strtoupper($f['ext'] ?: 'File');
            $isPic = in_array($f['ext'], ['pdf', 'jpg', 'jpeg', 'png', 'gif'], true);
            ?>
            <article class="vm-card fl-card" style="--h:<?= $hue[$f['ext']] ?? 215 ?>"<?php if ($f['thumb']): ?> data-prev="<?= esc($f['thumb'], 'attr') ?>"<?php endif ?>>
                <a class="vm-main fl-main" href="<?= esc($f['url'], 'attr') ?>"<?= $f['open'] ? '' : ' download' ?> aria-label="Open <?= esc($f['name'], 'attr') ?>">
                    <?php if ($f['thumb']): ?><span class="fl-thumb"><img src="<?= esc($f['thumb'], 'attr') ?>" alt="" loading="lazy"></span>
                    <?php else: ?><span class="fl-thumb fl-none"><b><?= esc($k) ?></b></span><?php endif ?>
                    <span class="vm-name fl-name"><?= esc($f['name']) ?></span>
                    <span class="vm-upd"><span class="bk-kind"><?= esc($k) ?></span> <?= esc($size($f['size'])) ?><?= $f['date'] ? ' · ' . esc($f['date']) : '' ?><?php if ($q !== '' && $f['folder'] !== ''): ?><br><span class="fl-path"><?= esc($f['folder']) ?></span><?php endif ?></span>
                </a>
            </article>
        <?php endforeach ?>
    </div>
    <?php elseif (! $folders): ?>
        <p class="vm-none"><?= $q !== '' ? 'No file matches.' : 'This folder is empty.' ?></p>
    <?php endif ?>
</section>
