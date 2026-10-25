<?php
/** Vars: $id, $name, $label, $autocomplete, $strength? (bool), $match? (id), $minlength? */
?>
<div class="field">
    <label for="<?= esc($id, 'attr') ?>" class="lbl"><?= esc($label) ?></label>
    <div class="pw">
        <input id="<?= esc($id, 'attr') ?>" name="<?= esc($name, 'attr') ?>" type="password" required class="input"
               autocomplete="<?= esc($autocomplete, 'attr') ?>" maxlength="128"
               <?= ! empty($minlength) ? 'minlength="' . (int) $minlength . '"' : '' ?>
               <?= ! empty($match) ? 'data-match="' . esc($match, 'attr') . '"' : '' ?>>
        <button type="button" class="eye" data-toggle-password="<?= esc($id, 'attr') ?>" aria-label="Show password" aria-pressed="false">
            <svg data-eye fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg data-eye-off class="hidden" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 3l18 18M10.6 10.6a3 3 0 004.2 4.2M9.9 5.1A10 10 0 0112 5c6.5 0 10 7 10 7a17 17 0 01-3.2 4.2M6.6 6.6C3.8 8.4 2 12 2 12s3.5 7 10 7a9.8 9.8 0 005.4-1.6"/></svg>
        </button>
    </div>
    <p data-capslock-for="<?= esc($id, 'attr') ?>" class="warn hidden">⚠ Caps Lock is on</p>
    <?php if (! empty($match)): ?><p data-match-hint="<?= esc($id, 'attr') ?>" class="hint hidden"></p><?php endif ?>
    <?php if (! empty($strength)): ?>
        <div data-strength-for="<?= esc($id, 'attr') ?>" class="meter">
            <div class="track"><div data-bar class="bar"></div></div>
            <div class="meter-t"><span>Min <?= (int) ($minlength ?? 8) ?> chars · Aa · 0-9 · symbol</span><span data-label></span></div>
        </div>
    <?php endif ?>
</div>
