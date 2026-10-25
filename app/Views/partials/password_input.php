<?php
/**
 * Reusable glass password field.
 * Vars: $id, $name, $label, $autocomplete, $placeholder?, $strength? (bool), $match? (id of field to match), $minlength?
 */
?>
<div>
    <label for="<?= esc($id, 'attr') ?>" class="glass-label"><?= esc($label) ?></label>
    <div class="relative">
        <input id="<?= esc($id, 'attr') ?>" name="<?= esc($name, 'attr') ?>" type="password" required
               autocomplete="<?= esc($autocomplete, 'attr') ?>" maxlength="128"
               <?= ! empty($minlength) ? 'minlength="' . (int) $minlength . '"' : '' ?>
               <?= ! empty($match) ? 'data-match="' . esc($match, 'attr') . '"' : '' ?>
               placeholder="<?= esc($placeholder ?? '••••••••', 'attr') ?>" class="glass-input pr-12">
        <button type="button" data-toggle-password="<?= esc($id, 'attr') ?>" aria-label="Show password" aria-pressed="false"
                class="absolute inset-y-0 right-0 flex items-center px-4 text-white/60 hover:text-white">
            <svg data-eye class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg data-eye-off class="hidden h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 3l18 18M10.6 10.6a3 3 0 004.2 4.2M9.9 5.1A10 10 0 0112 5c6.5 0 10 7 10 7a17 17 0 01-3.2 4.2M6.6 6.6C3.8 8.4 2 12 2 12s3.5 7 10 7a9.8 9.8 0 005.4-1.6"/></svg>
        </button>
    </div>
    <p data-capslock-for="<?= esc($id, 'attr') ?>" class="mt-1 hidden text-xs text-amber-300">⚠ Caps Lock is on</p>
    <?php if (! empty($match)): ?><p data-match-hint="<?= esc($id, 'attr') ?>" class="mt-1 hidden text-xs"></p><?php endif ?>
    <?php if (! empty($strength)): ?>
        <div data-strength-for="<?= esc($id, 'attr') ?>" class="mt-2">
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-white/10"><div data-bar class="h-full w-0 rounded-full"></div></div>
            <div class="mt-1 flex justify-between text-[11px] text-white/50">
                <span>Min <?= (int) ($minlength ?? 8) ?> chars · Aa · 0-9 · symbol</span><span data-label></span>
            </div>
        </div>
    <?php endif ?>
</div>
