<?php
/**
 * Shared <head>. Expects: $title (string)
 */
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="same-origin">
<?= csrf_meta() ?>
<title><?= esc($title ?? 'NEXUS-L') ?> · NEXUS-L</title>
<link rel="icon" href="<?= base_url('favicon.ico') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/app.css') ?>">
