<h2>Forgot password</h2>
<p class="sub">Enter your username or email. We'll send a 6-digit confirmation code to your <b>Synology Chat</b>.</p>

<?= form_open(url_to('forgot'), ['data-loading' => '']) ?>
    <div class="field">
        <label for="login" class="lbl">Username or email</label>
        <input id="login" name="login" type="text" required maxlength="150" autofocus autocomplete="username" class="input"
               value="<?= esc(old('login') ?? '', 'attr') ?>" placeholder="e.g. jdelacruz">
    </div>

    <button type="submit" class="btn">Send code via Synology Chat</button>
<?= form_close() ?>

<p class="foot">Remembered it? <a href="<?= url_to('login') ?>">Back to sign in</a></p>
