<h2>Enter confirmation code</h2>
<p class="sub">Check your Synology Chat for a message from the NEXUS-L bot. The code expires in
    <span data-countdown="<?= (int) $ttl * 60 ?>" class="mono"></span>.</p>

<?= form_open(url_to('forgot.verify'), ['data-loading' => '']) ?>
    <input type="hidden" id="code" name="code">
    <div data-otp class="otp">
        <?php for ($i = 0; $i < 6; $i++): ?>
            <input type="text" inputmode="numeric" maxlength="1" class="input" autocomplete="<?= $i === 0 ? 'one-time-code' : 'off' ?>" aria-label="Digit <?= $i + 1 ?>">
        <?php endfor ?>
    </div>
    <button type="submit" class="btn">Verify code</button>
<?= form_close() ?>

<?= form_open(url_to('forgot.resend'), ['class' => 'resend']) ?>
    Didn't receive it?
    <button id="resend-btn" type="submit" disabled class="link-btn">Resend code</button>
    (<span data-countdown="60" data-enable="resend-btn"></span>)
<?= form_close() ?>

<p class="foot"><a href="<?= url_to('forgot') ?>">Use a different account</a> · <a href="<?= url_to('login') ?>">Sign in</a></p>
