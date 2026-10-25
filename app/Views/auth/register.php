<h2>Create account</h2>
<p class="sub">Register to join the NEXUS-L laboratory network.</p>

<?= form_open(url_to('register'), ['data-loading' => '', 'autocomplete' => 'off']) ?>
    <div class="field">
        <label for="fullname" class="lbl">Full name</label>
        <input id="fullname" name="fullname" type="text" required minlength="3" maxlength="120" autocomplete="name" class="input"
               value="<?= esc(old('fullname') ?? '', 'attr') ?>" placeholder="Juan Dela Cruz">
    </div>

    <div class="grid2">
        <div class="field">
            <label for="username" class="lbl">Username</label>
            <input id="username" name="username" type="text" required minlength="4" maxlength="50" pattern="[A-Za-z0-9_\-]+" autocomplete="username" class="input"
                   value="<?= esc(old('username') ?? '', 'attr') ?>" placeholder="jdelacruz">
        </div>
        <div class="field">
            <label for="synology_id" class="lbl">Synology Chat ID</label>
            <input id="synology_id" name="synology_id" type="text" inputmode="numeric" pattern="[0-9]+" required maxlength="10" class="input"
                   value="<?= esc(old('synology_id') ?? '', 'attr') ?>" placeholder="e.g. 12">
        </div>
    </div>

    <div class="grid2">
        <div class="field">
            <label for="email" class="lbl">Email <span class="opt">(optional)</span></label>
            <input id="email" name="email" type="email" maxlength="150" autocomplete="email" class="input"
                   value="<?= esc(old('email') ?? '', 'attr') ?>" placeholder="you@company.com">
        </div>
        <div class="field">
            <label for="department" class="lbl">Department <span class="opt">(optional)</span></label>
            <input id="department" name="department" type="text" maxlength="100" class="input"
                   value="<?= esc(old('department') ?? '', 'attr') ?>" placeholder="QC Laboratory">
        </div>
    </div>

    <?= view('partials/auth_password', ['id' => 'password', 'name' => 'password', 'label' => 'Password', 'autocomplete' => 'new-password', 'strength' => true, 'minlength' => $minLen]) ?>
    <?= view('partials/auth_password', ['id' => 'password_confirm', 'name' => 'password_confirm', 'label' => 'Confirm password', 'autocomplete' => 'new-password', 'match' => 'password']) ?>

    <label class="check">
        <input name="terms" type="checkbox" value="1" required <?= old('terms') ? 'checked' : '' ?>>
        I agree to use NEXUS-L in accordance with the laboratory acceptable-use policy.
    </label>

    <p class="note">Your <b>Synology Chat ID</b> is used to deliver password-reset codes. Make sure you have started a conversation with the NEXUS-L bot in Synology Chat.</p>

    <button type="submit" class="btn">Create account</button>
<?= form_close() ?>

<p class="foot">Already registered? <a href="<?= url_to('login') ?>">Sign in</a></p>
