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
    <link rel="stylesheet" href="<?= base_url('assets/css/auth.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/auth.css') ?>">
</head>
<body>
    <main class="wrap">
        <div class="brand">
            <div class="logo"><svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M9 3h6M10 3v6.5L4.6 18.4A1.8 1.8 0 006.2 21h11.6a1.8 1.8 0 001.6-2.6L14 9.5V3"/><path d="M7.5 15h9"/></svg></div>
            <h1>NEXUS-L</h1>
            <p>Networked Execution &amp; Unified System for Laboratories</p>
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
