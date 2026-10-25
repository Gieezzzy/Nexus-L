<?php

namespace App\Controllers;

use App\Models\AuthLogModel;
use App\Models\PasswordResetModel;
use App\Models\UserModel;
use Config\Auth as AuthConfig;

/**
 * Forgot-password flow:
 *  1. user enters username/email  -> 6-digit code sent via Synology Chat bot
 *  2. user enters the code        -> request marked verified
 *  3. user sets a new password    -> all sessions/remember tokens revoked + confirmation sent
 * The reset request is bound to the browser via a random selector kept in the session.
 */
class PasswordResetController extends BaseController
{
    private const SESSION_SELECTOR = 'pwreset_selector';

    protected AuthConfig $cfg;
    protected PasswordResetModel $resets;
    protected UserModel $users;

    public function __construct()
    {
        $this->cfg    = config(AuthConfig::class);
        $this->resets = model(PasswordResetModel::class);
        $this->users  = model(UserModel::class);
    }

    public function forgot(): string
    {
        return $this->render('auth/forgot', ['title' => 'Forgot password'], 'layouts/auth');
    }

    public function sendCode()
    {
        if (service('throttler')->check('forgot-' . md5($this->request->getIPAddress()), 5, MINUTE) === false) {
            return redirect()->back()->withInput()->with('error', 'Too many requests. Please wait a minute.');
        }

        if (! $this->validate(['login' => ['label' => 'Username or email', 'rules' => 'required|max_length[150]']])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $login = trim((string) $this->request->getPost('login'));
        $user  = $this->users->findByLogin($login);

        // Generic message prevents account enumeration.
        $generic = 'If the account exists and is linked to Synology Chat, a confirmation code has been sent.';

        if ($user === null || (int) $user['IsActive'] !== 1 || empty($user['SynologyID'])) {
            model(AuthLogModel::class)->record('reset_request', false, null, $login);
            // Create a decoy selector so the flow looks identical.
            session()->set(self::SESSION_SELECTOR, bin2hex(random_bytes(16)));

            return redirect()->route('forgot.verify')->with('success', $generic);
        }

        if ($this->resets->recentRequestCount((int) $user['UserID']) >= 3) {
            return redirect()->back()->withInput()->with('error', 'Too many reset requests for this account. Try again in 15 minutes.');
        }

        if (! $this->issueCode($user)) {
            return redirect()->back()->withInput()->with('error', 'Unable to deliver the code through Synology Chat right now. Please contact the administrator.');
        }

        return redirect()->route('forgot.verify')->with('success', $generic);
    }

    public function resend()
    {
        $reset = $this->currentReset();
        if ($reset === null) {
            return redirect()->route('forgot')->with('error', 'Your reset session expired. Please start again.');
        }

        $user = $this->users->find($reset['UserID']);
        if ($user === null || $this->resets->recentRequestCount((int) $user['UserID']) >= 3) {
            return redirect()->route('forgot.verify')->with('error', 'Please wait before requesting another code.');
        }

        $this->issueCode($user);

        return redirect()->route('forgot.verify')->with('success', 'A new code has been sent to your Synology Chat.');
    }

    public function verify()
    {
        if (! session()->has(self::SESSION_SELECTOR)) {
            return redirect()->route('forgot');
        }

        return $this->render('auth/verify', ['title' => 'Enter confirmation code', 'ttl' => $this->cfg->resetCodeTtlMinutes], 'layouts/auth');
    }

    public function checkCode()
    {
        if (! $this->validate(['code' => ['label' => 'Code', 'rules' => 'required|exact_length[6]|numeric']])) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $reset = $this->currentReset();
        if ($reset === null) {
            return redirect()->back()->with('error', 'Invalid or expired code.');
        }

        if (! password_verify((string) $this->request->getPost('code'), $reset['CodeHash'])) {
            $attempts = (int) $reset['Attempts'] + 1;
            $data     = ['Attempts' => $attempts];
            if ($attempts >= $this->cfg->resetMaxAttempts) {
                $data['UsedAt'] = date('Y-m-d H:i:s'); // burn the code
            }
            $this->resets->update($reset['ResetID'], $data);
            model(AuthLogModel::class)->record('reset_code', false, (int) $reset['UserID']);

            $left = $this->cfg->resetMaxAttempts - $attempts;

            return $left > 0
                ? redirect()->back()->with('error', "Invalid code. {$left} attempt(s) left.")
                : redirect()->route('forgot')->with('error', 'Too many invalid attempts. Please request a new code.');
        }

        $this->resets->update($reset['ResetID'], ['Verified' => 1]);
        session()->regenerate(true);
        model(AuthLogModel::class)->record('reset_code', true, (int) $reset['UserID']);

        return redirect()->route('forgot.reset');
    }

    public function reset()
    {
        $reset = $this->currentReset();
        if ($reset === null || (int) $reset['Verified'] !== 1) {
            return redirect()->route('forgot')->with('error', 'Please verify your confirmation code first.');
        }

        return $this->render('auth/reset', ['title' => 'Set new password', 'minLen' => $this->cfg->minPasswordLength], 'layouts/auth');
    }

    public function saveReset()
    {
        $reset = $this->currentReset();
        if ($reset === null || (int) $reset['Verified'] !== 1) {
            return redirect()->route('forgot')->with('error', 'Your reset session expired. Please start again.');
        }

        $min   = $this->cfg->minPasswordLength;
        $rules = [
            'password' => ['label' => 'Password', 'rules' => "required|min_length[{$min}]|max_length[128]|regex_match[/^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[^A-Za-z0-9]).+$/]",
                'errors' => ['regex_match' => 'Password must contain upper & lower case letters, a number and a symbol.']],
            'password_confirm' => ['label' => 'Confirm password', 'rules' => 'required|matches[password]'],
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $user     = $this->users->find($reset['UserID']);
        $password = (string) $this->request->getPost('password');

        if (password_verify($password, $user['Password'])) {
            return redirect()->back()->with('error', 'New password must be different from your current password.');
        }

        $this->users->update($user['UserID'], [
            'Password'       => $password, // hashed by model callback (also bumps PasswordChangedAt -> kills other sessions)
            'FailedAttempts' => 0,
            'LockedUntil'    => null,
        ]);
        $this->resets->invalidateForUser((int) $user['UserID']);
        $this->auth->revokeAllTokens((int) $user['UserID']);
        session()->remove(self::SESSION_SELECTOR);
        model(AuthLogModel::class)->record('reset_password', true, (int) $user['UserID'], $user['Username']);

        service('synologyChat')->send(
            (int) $user['SynologyID'],
            "Confirmation: the password for your account \"{$user['Username']}\" was changed on " . date('M d, Y h:i A')
            . ' from IP ' . $this->request->getIPAddress() . '. If this was not you, contact the administrator immediately.'
        );

        return redirect()->route('login')->with('success', 'Password updated. Please sign in with your new password.');
    }

    // ------------------------------------------------------------------

    private function issueCode(array $user): bool
    {
        $code     = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $selector = bin2hex(random_bytes(16));

        $this->resets->invalidateForUser((int) $user['UserID']);
        $this->resets->insert([
            'UserID'    => $user['UserID'],
            'Selector'  => $selector,
            'CodeHash'  => password_hash($code, PASSWORD_DEFAULT),
            'ExpiresAt' => date('Y-m-d H:i:s', time() + $this->cfg->resetCodeTtlMinutes * 60),
            'RequestIP' => $this->request->getIPAddress(),
        ]);

        $sent = service('synologyChat')->send(
            (int) $user['SynologyID'],
            "Password reset requested for \"{$user['Username']}\".\nYour confirmation code is: *{$code}*\n"
            . "It expires in {$this->cfg->resetCodeTtlMinutes} minutes. Never share this code. If you did not request this, ignore this message."
        );

        model(AuthLogModel::class)->record('reset_request', $sent, (int) $user['UserID'], $user['Username']);

        if (! $sent) {
            $this->resets->invalidateForUser((int) $user['UserID']);

            return false;
        }

        session()->set(self::SESSION_SELECTOR, $selector);

        return true;
    }

    private function currentReset(): ?array
    {
        $selector = session(self::SESSION_SELECTOR);

        return is_string($selector) ? $this->resets->findActive($selector) : null;
    }
}
