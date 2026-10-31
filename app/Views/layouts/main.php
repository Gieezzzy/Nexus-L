<?php helper('nx'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="same-origin">
    <?= csrf_meta() ?>
    <script>try{if(window.innerWidth>=1024&&localStorage.getItem('nx.side')==='1')document.documentElement.classList.add('side-collapsed')}catch(e){}</script>
    <meta name="base-url" content="<?= rtrim(base_url(), '/') . '/' ?>">
    <title><?= esc($title ?? 'NEXUS-L') ?> · NEXUS-L</title>
    <link rel="icon" type="image/png" href="<?= base_url('favicon.png') ?>">
    <link rel="apple-touch-icon" href="<?= base_url('favicon.png') ?>">
    <link rel="stylesheet" href="<?= nx_asset('css/dash.css') ?>">
    <?php if (! empty($withCss)): ?><link rel="stylesheet" href="<?= nx_asset('css/' . $withCss) ?>"><?php endif ?>
</head>
<body>
<?= view('partials/icons') ?>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <?= view('partials/sidebar') ?>

    <div class="shell">
        <?= view('partials/header') ?>
        <?= view('partials/browser_notification_prompt') ?>

        <main id="main-content" tabindex="-1">
            <h1 class="sr-only"><?= esc($title ?? 'NEXUS-L') ?></h1>
            <?= view('partials/auth_flash') ?>
            <?= $content ?>
        </main>

        <footer class="foot">
            <span>&copy; <?= date('Y') ?> NEXUS-L · System &amp; Process Audit</span>
            <span>v1.0.0</span>
        </footer>
    </div>

    <script src="<?= nx_asset('js/anime.min.js') ?>" defer></script>
    <script src="<?= nx_asset('js/ux.js') ?>" defer></script>
    <script src="<?= nx_asset('js/app.js') ?>" defer></script>
    <script src="<?= nx_asset('js/bot.js') ?>" defer></script>
    <?php foreach ((array) ($withJs ?? []) as $js): ?><script src="<?= nx_asset('js/' . $js) ?>" defer></script><?php endforeach ?>
</body>
</html>
