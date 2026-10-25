<?php
$success = session()->getFlashdata('success');
$error   = session()->getFlashdata('error');
$errors  = session()->getFlashdata('errors');
?>
<?php if ($success): ?><div role="alert" data-autodismiss class="alert alert-ok"><?= esc($success) ?></div><?php endif ?>
<?php if ($error): ?><div role="alert" class="alert alert-err"><?= esc($error) ?></div><?php endif ?>
<?php if (is_array($errors) && $errors !== []): ?>
    <div role="alert" class="alert alert-err"><ul><?php foreach ($errors as $e): ?><li><?= esc($e) ?></li><?php endforeach ?></ul></div>
<?php endif ?>
