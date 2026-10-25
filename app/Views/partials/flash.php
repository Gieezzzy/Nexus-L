<?php
/**
 * Flash messages + validation errors. Works on both glass (auth) and app layouts.
 */
$success = session()->getFlashdata('success');
$error   = session()->getFlashdata('error');
$errors  = session()->getFlashdata('errors');
?>
<?php if ($success): ?>
    <div role="alert" data-autodismiss class="mb-4 flex items-start gap-2 rounded-xl border border-emerald-300/30 bg-emerald-500/15 px-4 py-3 text-sm text-emerald-100">
        <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
        <span><?= esc($success) ?></span>
    </div>
<?php endif ?>
<?php if ($error): ?>
    <div role="alert" class="mb-4 flex items-start gap-2 rounded-xl border border-rose-300/30 bg-rose-500/15 px-4 py-3 text-sm text-rose-100">
        <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/></svg>
        <span><?= esc($error) ?></span>
    </div>
<?php endif ?>
<?php if (is_array($errors) && $errors !== []): ?>
    <div role="alert" class="mb-4 rounded-xl border border-rose-300/30 bg-rose-500/15 px-4 py-3 text-sm text-rose-100">
        <ul class="list-disc space-y-0.5 pl-5">
            <?php foreach ($errors as $e): ?><li><?= esc($e) ?></li><?php endforeach ?>
        </ul>
    </div>
<?php endif ?>
