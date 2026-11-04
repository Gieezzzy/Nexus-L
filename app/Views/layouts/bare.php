<?php helper('nx'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <?= csrf_meta() ?>
    <title><?= esc($title ?? 'NEXUS-L') ?></title>
    <link rel="stylesheet" href="<?= nx_asset('css/dash.css') ?>">
    <?php if (! empty($withCss)): ?><link rel="stylesheet" href="<?= nx_asset('css/' . $withCss) ?>"><?php endif ?>
</head>
<body class="ld-embed">
<main><?= $content ?></main>
<?php foreach ((array) ($withJs ?? []) as $js): ?><script src="<?= nx_asset('js/' . $js) ?>" defer></script><?php endforeach ?>
</body>
</html>
