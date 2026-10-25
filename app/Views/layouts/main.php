<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="same-origin">
    <?= csrf_meta() ?>
    <title><?= esc($title ?? 'NEXUS-L') ?> · NEXUS-L</title>
    <link rel="icon" href="<?= base_url('favicon.ico') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/dash.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/dash.css') ?>">
    <?php if (! empty($withCss)): ?><link rel="stylesheet" href="<?= base_url('assets/css/' . $withCss) ?>?v=<?= @filemtime(FCPATH . 'assets/css/' . $withCss) ?>"><?php endif ?>
</head>
<body>
    <?= view('partials/sidebar') ?>

    <div class="shell">
        <?= view('partials/header') ?>

        <main>
            <?= view('partials/auth_flash') ?>
            <?= $content ?>
        </main>

        <footer class="foot">
            <span>&copy; <?= date('Y') ?> NEXUS-L · System &amp; Process Audit</span>
            <span>v1.0.0</span>
        </footer>
    </div>

    <script src="<?= base_url('assets/js/app.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/app.js') ?>" defer></script>
    <?php foreach ((array) ($withJs ?? []) as $js): ?><script src="<?= base_url('assets/js/' . $js) ?>?v=<?= @filemtime(FCPATH . 'assets/js/' . $js) ?>" defer></script><?php endforeach ?>
</body>
</html>
