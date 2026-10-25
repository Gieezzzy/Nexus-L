<?php

namespace App\Controllers;

use App\Models\AuthLogModel;
use App\Models\UserModel;
use Config\Auth as AuthConfig;

class AuthController extends BaseController
{
    public function login(): string
    {
        return $this->render('auth/login', ['title' => 'Sign in'], 'layouts/auth');
    }

    public function attemptLogin()
    {
        // Per-IP rate limit (in addition to per-account lockout).
        $throttler = service('throttler');
        if ($throttler->check('login-' . md5($this->request->getIPAddress()), 20, MINUTE) === false) {
            return redirect()->back()->withInput()->with('error', 'Too many requests. Please wait a moment.');
        }

        $rules = [
            'login'    => 'required|max_length[150]',
            'password' => 'required|max_length[255]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $login    = trim((string) $this->request->getPost('login'));
        $password = (string) $this->request->getPost('password');
        $remember = (bool) $this->request->getPost('remember');

        $result = $this->auth->attempt($login, $password, $remember);
        if (! $result['ok']) {
            return redirect()->back()->withInput()->with('error', $result['message']);
        }

        // Only redirect to same-site URLs.
        $target = session()->getFlashdata('redirect_after_login') ?? session('redirect_after_login');
        session()->remove('redirect_after_login');
        if (! is_string($target) || ! str_starts_with($target, base_url())) {
            $target = url_to('dashboard');
        }

        return redirect()->to($target)->withCookies()->with('success', 'Welcome back, ' . esc($this->auth->user()['name']) . '!');
    }

    public function register(): string
    {
        return $this->render('auth/register', ['title' => 'Create account', 'minLen' => config(AuthConfig::class)->minPasswordLength], 'layouts/auth');
    }

    public function attemptRegister()
    {
        $min   = config(AuthConfig::class)->minPasswordLength;
        $rules = [
            'fullname'   => ['label' => 'Full name', 'rules' => 'required|min_length[3]|max_length[120]|alpha_numeric_punct'],
            'username'   => ['label' => 'Username', 'rules' => 'required|min_length[4]|max_length[50]|alpha_dash|is_unique[tbl_users.Username]'],
            'email'      => ['label' => 'Email', 'rules' => 'permit_empty|valid_email|max_length[150]|is_unique[tbl_users.Email]'],
            'synology_id' => ['label' => 'Synology Chat ID', 'rules' => 'required|is_natural_no_zero|max_length[10]'],
            'department' => ['label' => 'Department', 'rules' => 'permit_empty|max_length[100]'],
            'password'   => ['label' => 'Password', 'rules' => "required|min_length[{$min}]|max_length[128]|regex_match[/^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[^A-Za-z0-9]).+$/]",
                'errors' => ['regex_match' => 'Password must contain upper & lower case letters, a number and a symbol.']],
            'password_confirm' => ['label' => 'Confirm password', 'rules' => 'required|matches[password]'],
            'terms'      => ['label' => 'Terms', 'rules' => 'required', 'errors' => ['required' => 'You must accept the acceptable-use policy.']],
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $users = model(UserModel::class);
        $id    = $users->insert([
            'FullName'   => trim((string) $this->request->getPost('fullname')),
            'Username'   => strtolower(trim((string) $this->request->getPost('username'))),
            'Email'      => trim((string) $this->request->getPost('email')) ?: null,
            'SynologyID' => (int) $this->request->getPost('synology_id'),
            'Department' => trim((string) $this->request->getPost('department')) ?: null,
            'Password'   => (string) $this->request->getPost('password'), // hashed by UserModel callback
            'Role'       => 'user',
            'IsActive'   => 1,
        ]);

        if ($id === false) {
            return redirect()->back()->withInput()->with('errors', $users->errors() ?: ['Registration failed.']);
        }

        model(AuthLogModel::class)->record('register', true, (int) $id, (string) $this->request->getPost('username'));

        service('synologyChat')->send(
            (int) $this->request->getPost('synology_id'),
            "Welcome to NEXUS-L, {$this->request->getPost('fullname')}! Your account \"{$this->request->getPost('username')}\" was created. "
            . 'Password reset codes will be delivered here.'
        );

        return redirect()->route('login')->with('success', 'Account created. You can now sign in.');
    }

    public function logout()
    {
        $this->auth->logout();

        return redirect()->route('login')->withCookies()->with('success', 'You have been signed out.');
    }
}
