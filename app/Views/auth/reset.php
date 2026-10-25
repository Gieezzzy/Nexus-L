<h2>Set a new password</h2>
<p class="sub">Code verified. Choose a strong password you haven't used before. All other devices will be signed out.</p>

<?= form_open(url_to('forgot.reset'), ['data-loading' => '', 'autocomplete' => 'off']) ?>
    <?= view('partials/auth_password', ['id' => 'password', 'name' => 'password', 'label' => 'New password', 'autocomplete' => 'new-password', 'strength' => true, 'minlength' => $minLen]) ?>
    <?= view('partials/auth_password', ['id' => 'password_confirm', 'name' => 'password_confirm', 'label' => 'Confirm new password', 'autocomplete' => 'new-password', 'match' => 'password']) ?>
    <button type="submit" class="btn">Update password</button>
<?= form_close() ?>
