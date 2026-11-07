<?php

namespace App\Controllers;

use App\Libraries\Roles;
use App\Models\UserModel;
use Config\Auth as AuthConfig;

/**
 * Account Profile: every signed-in user sees and changes their own details, password, preferences and (Manager, Supervisor, Admin)
 * the signature used when a report is approved. What a user may open is shown read-only under "My access". Roles, accounts and the
 * access matrix are managed by an Admin under Settings > Users & roles (SettingsController).
 */
class AccountController extends BaseController
{
    private function json(array $data, int $code = 200)
    {
        return $this->response->setStatusCode($code)->setJSON($data + ['csrf' => csrf_hash()]);
    }

    private function users(): UserModel
    {
        return new UserModel();
    }

    /** the signed-in user's full record (never the password) */
    private function me(): array
    {
        $u = $this->users()->find((int) $this->auth->id()) ?: [];
        unset($u['Password']);

        return $u;
    }

    /** @return array<string,mixed> the saved preferences with the defaults filled in */
    public static function prefsOf(array $u): array
    {
        $p = json_decode((string) ($u['Prefs'] ?? ''), true);

        return (is_array($p) ? $p : []) + ['landing' => 'dashboard', 'sidebar' => 'expanded', 'notify_chat' => true, 'notify_approval' => true, 'toasts' => true, 'page_size' => 15];
    }

    public function index()
    {
        $u    = $this->me();
        $role = (string) ($u['Role'] ?? '');
        $sig  = null;
        try {
            $sig = \Config\Database::connect()->table('tbl_dtr_signatories')->select('id, full_name, title, signature_png, is_active')->where('user_id', (int) $u['UserID'])->get()->getRowArray();
        } catch (\Throwable $e) {
            $sig = null;
        }

        return $this->render('account/index', [
            'title'     => 'Account Profile',
            'withCss'   => 'account.css',
            'withJs'    => ['account.js'],
            'u'         => $u,
            'roles'     => Roles::parse($role),
            'legacy'    => Roles::isLegacy($role),
            'isAdmin'   => Roles::isAdmin($role),
            'levels'    => Roles::levels($role),
            'modules'   => Roles::MODULES,
            'prefs'     => self::prefsOf($u),
            'canSign'   => Roles::isAdmin($role) || array_intersect(['Manager', 'Supervisor'], Roles::parse($role)) !== [],
            'signature' => $sig && ! empty($sig['signature_png']) ? $sig['signature_png'] : null,
            'minPwd'    => config(AuthConfig::class)->minPasswordLength,
            'tab'       => in_array($this->request->getGet('tab'), ['profile', 'security', 'preferences', 'access', 'signature'], true) ? $this->request->getGet('tab') : 'profile',
        ]);
    }

    /** POST account/save {FullName, Email, Phone, Department, JobTitle, EmployeeNo} */
    public function save()
    {
        $p  = $this->request->getJSON(true) ?? [];
        $id = (int) $this->auth->id();
        $in = [
            'FullName'   => trim((string) ($p['FullName'] ?? '')),
            'Email'      => trim((string) ($p['Email'] ?? '')),
            'Phone'      => trim((string) ($p['Phone'] ?? '')),
            'Department' => trim((string) ($p['Department'] ?? '')),
            'JobTitle'   => trim((string) ($p['JobTitle'] ?? '')),
            'EmployeeNo' => trim((string) ($p['EmployeeNo'] ?? '')),
        ];
        if (mb_strlen($in['FullName']) < 3 || mb_strlen($in['FullName']) > 120) {
            return $this->json(['ok' => false, 'error' => 'Full name must be 3 to 120 characters.'], 422);
        }
        if ($in['Email'] !== '' && (! filter_var($in['Email'], FILTER_VALIDATE_EMAIL) || mb_strlen($in['Email']) > 150)) {
            return $this->json(['ok' => false, 'error' => 'That e-mail address is not valid.'], 422);
        }
        if ($in['Email'] !== '' && $this->users()->where('Email', $in['Email'])->where('UserID !=', $id)->countAllResults() > 0) {
            return $this->json(['ok' => false, 'error' => 'Another account already uses that e-mail address.'], 422);
        }
        if ($in['Phone'] !== '' && ! preg_match('/^[0-9+()\-\s.]{5,40}$/', $in['Phone'])) {
            return $this->json(['ok' => false, 'error' => 'The phone number may only have digits, spaces and + ( ) - .'], 422);
        }
        foreach (['Department' => 100, 'JobTitle' => 100, 'EmployeeNo' => 40] as $k => $max) {
            if (mb_strlen($in[$k]) > $max) {
                return $this->json(['ok' => false, 'error' => "{$k} is too long (at most {$max} characters)."], 422);
            }
        }
        $this->users()->update($id, [
            'FullName' => $in['FullName'], 'Email' => $in['Email'] ?: null, 'Phone' => $in['Phone'] ?: null,
            'Department' => $in['Department'] ?: null, 'JobTitle' => $in['JobTitle'] ?: null, 'EmployeeNo' => $in['EmployeeNo'] ?: null,
        ]);
        $data         = $this->auth->user();                              // keep the name in the header current
        $data['name'] = $in['FullName'];
        session()->set(\App\Libraries\Auth::SESSION_KEY, $data);

        return $this->json(['ok' => true, 'message' => 'Your details were saved.']);
    }

    /** POST account/password {current, password, confirm} */
    public function password()
    {
        $p    = $this->request->getJSON(true) ?? [];
        $user = $this->users()->find((int) $this->auth->id());
        $cur  = (string) ($p['current'] ?? '');
        $new  = (string) ($p['password'] ?? '');
        $min  = config(AuthConfig::class)->minPasswordLength;
        if (! $user || ! password_verify($cur, $user['Password'])) {
            return $this->json(['ok' => false, 'error' => 'The current password is not correct.'], 422);
        }
        if ($new !== (string) ($p['confirm'] ?? '')) {
            return $this->json(['ok' => false, 'error' => 'The new password and its confirmation are different.'], 422);
        }
        if (strlen($new) < $min || strlen($new) > 128 || ! preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).+$/', $new)) {
            return $this->json(['ok' => false, 'error' => "The new password needs at least {$min} characters with upper and lower case letters, a number and a symbol."], 422);
        }
        if (password_verify($new, $user['Password'])) {
            return $this->json(['ok' => false, 'error' => 'The new password must be different from the current one.'], 422);
        }
        $this->users()->update((int) $user['UserID'], ['Password' => $new, 'FailedAttempts' => 0, 'LockedUntil' => null]);   // hashed by the model; other sessions end
        // this session stays valid: it takes the new password stamp
        $fresh = $this->users()->find((int) $user['UserID']);
        $data  = $this->auth->user();
        $data['pwd_changed'] = $fresh['PasswordChangedAt'] ?? null;
        session()->set(\App\Libraries\Auth::SESSION_KEY, $data);

        return $this->json(['ok' => true, 'message' => 'Password changed. Your other sessions were signed out.']);
    }

    /** POST account/prefs {landing, sidebar, notify_chat, notify_approval, toasts, page_size} */
    public function prefs()
    {
        $p   = $this->request->getJSON(true) ?? [];
        $out = [
            'landing'         => in_array($p['landing'] ?? '', ['dashboard', 'inbox', 'dtr', 'interlink', 'analytics'], true) ? $p['landing'] : 'dashboard',
            'sidebar'         => ($p['sidebar'] ?? '') === 'collapsed' ? 'collapsed' : 'expanded',
            'notify_chat'     => ! empty($p['notify_chat']),
            'notify_approval' => ! empty($p['notify_approval']),
            'toasts'          => ! empty($p['toasts']),
            'page_size'       => in_array((int) ($p['page_size'] ?? 15), [10, 15, 25, 50, 100], true) ? (int) $p['page_size'] : 15,
        ];
        $this->users()->update((int) $this->auth->id(), ['Prefs' => json_encode($out)]);

        return $this->json(['ok' => true, 'message' => 'Preferences saved.', 'prefs' => $out]);
    }

    /** POST account/signature {png: "data:image/png;base64,..."} (Manager, Supervisor, Admin); an empty png removes it */
    public function signature()
    {
        $u    = $this->me();
        $role = (string) ($u['Role'] ?? '');
        if (! (Roles::isAdmin($role) || array_intersect(['Manager', 'Supervisor'], Roles::parse($role)))) {
            return $this->json(['ok' => false, 'error' => 'Only a Manager, Supervisor or Admin signs reports.'], 403);
        }
        $png = (string) (($this->request->getJSON(true) ?? [])['png'] ?? '');
        if ($png !== '' && (! preg_match('#^data:image/png;base64,[A-Za-z0-9+/=]+$#', $png) || strlen($png) > 600000)) {
            return $this->json(['ok' => false, 'error' => 'The signature must be a PNG picture of at most about 400 KB.'], 422);
        }
        $db  = \Config\Database::connect();
        $row = $db->table('tbl_dtr_signatories')->where('user_id', (int) $u['UserID'])->get()->getRowArray();
        $set = ['full_name' => $u['FullName'], 'title' => $u['JobTitle'] ?: (Roles::parse($role)[0] ?? 'Staff'), 'signature_png' => $png !== '' ? $png : null, 'is_active' => 1, 'updated_at' => date('Y-m-d H:i:s')];
        if ($row) {
            $db->table('tbl_dtr_signatories')->where('id', $row['id'])->update($set);
        } else {
            $db->table('tbl_dtr_signatories')->insert($set + ['user_id' => (int) $u['UserID'], 'created_by' => (int) $u['UserID'], 'created_at' => date('Y-m-d H:i:s')]);
        }

        return $this->json(['ok' => true, 'message' => $png !== '' ? 'Signature saved.' : 'Signature removed.']);
    }
}
