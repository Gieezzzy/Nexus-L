<h2>Welcome back</h2>
<p class="sub">Sign in to access the laboratory workspace.</p>

<?= form_open(url_to('login'), ['id' => 'login-form', 'data-loading' => '', 'autocomplete' => 'on']) ?>
    <div class="field">
        <label for="login" class="lbl">Username or email</label>
        <input id="login" name="login" type="text" required maxlength="150" autofocus autocomplete="username" class="input"
               value="<?= esc(old('login') ?? '', 'attr') ?>" placeholder="e.g. jdelacruz">
    </div>

    <?= view('partials/auth_password', ['id' => 'password', 'name' => 'password', 'label' => 'Password', 'autocomplete' => 'current-password']) ?>

    <div class="row">
        <label class="check"><input id="remember" name="remember" type="checkbox" value="1"> Remember me</label>
        <a href="<?= url_to('forgot') ?>">Forgot password?</a>
    </div>

    <button type="submit" class="btn">Sign in</button>
<?= form_close() ?>

<p class="foot">No account yet? <a href="<?= url_to('register') ?>">Create one</a></p>
