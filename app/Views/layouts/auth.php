<?php helper('nx'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="same-origin">
    <?= csrf_meta() ?>
    <title><?= esc($title ?? 'NEXUS-L') ?> · NEXUS-L</title>
    <link rel="icon" type="image/png" href="<?= base_url('favicon.png') ?>">
    <link rel="apple-touch-icon" href="<?= base_url('favicon.png') ?>">
    <link rel="stylesheet" href="<?= nx_asset('css/auth.css') ?>">
</head>
<body>
    <main class="wrap">
        <div class="brand">
            <div class="brand-logo" role="img" aria-label="NEXUS-L - Networked Execution &amp; Unified System for Laboratories" style="background-image:url('<?= base_url('assets/images/nexus-l-logo.webp') ?>')"></div>
        </div>

        <section class="card">
            <?= view('partials/auth_flash') ?>
            <?= $content ?>
        </section>

        <p class="copy">&copy; <?= date('Y') ?> NEXUS-L · System &amp; Process Audit</p>
    </main>
    <script src="<?= base_url('assets/js/app.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/app.js') ?>" defer></script>
</body>
</html>
