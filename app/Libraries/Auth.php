<?php

namespace App\Libraries;

use App\Models\AuthLogModel;
use App\Models\RememberTokenModel;
use App\Models\UserModel;
use Config\Auth as AuthConfig;

/**
 * Central authentication service for NEXUS-L.
 * Handles login/logout, session hardening, idle timeout and remember-me tokens.
 */
class Auth
{
    public const SESSION_KEY = 'nexus_auth';

    protected AuthConfig $config;
    protected UserModel $users;
    protected RememberTokenModel $tokens;
    protected AuthLogModel $logs;

    public function __construct()
    {
        $this->config = config(AuthConfig::class);
        $this->users  = model(UserModel::class);
        $this->tokens = model(RememberTokenModel::class);
        $this->logs   = model(AuthLogModel::class);
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function attempt(string $login, string $password, bool $remember = false): array
    {
        $generic = 'Invalid username or password.';
        $user    = $this->users->findByLogin($login);

        if ($user === null) {
            // Burn similar CPU time to avoid user-enumeration via timing.
            password_verify($password, UserModel::makeHash(bin2hex(random_bytes(8))));
            $this->logs->record('login', false, null, $login);

            return ['ok' => false, 'message' => $generic];
        }

        if ($this->users->isLocked($user)) {
            $mins = (int) ceil((strtotime($user['LockedUntil']) - time()) / 60);
            $this->logs->record('login_locked', false, (int) $user['UserID'], $login);

            return ['ok' => false, 'message' => "Account temporarily locked. Try again in {$mins} minute(s)."];
        }

        if (! password_verify($password, $user['Password'])) {
            $this->users->registerFailure($user, $this->config->maxLoginAttempts, $this->config->lockoutMinutes);
            $this->logs->record('login', false, (int) $user['UserID'], $login);
            $left = $this->config->maxLoginAttempts - ((int) $user['FailedAttempts'] + 1);

            return ['ok' => false, 'message' => $left > 0
                ? $generic . " {$left} attempt(s) remaining."
                : "Too many failed attempts. Account locked for {$this->config->lockoutMinutes} minutes."];
        }

        if ((int) $user['IsActive'] !== 1) {
            $this->logs->record('login_inactive', false, (int) $user['UserID'], $login);

            return ['ok' => false, 'message' => 'Your account is disabled. Please contact the administrator.'];
        }

        // Transparent hash upgrade (e.g. bcrypt -> argon2id or cost change).
        if (password_needs_rehash($user['Password'], UserModel::passwordAlgo())) {
            $this->users->update($user['UserID'], ['Password' => $password]);
        }

        $this->users->registerSuccess($user, service('request')->getIPAddress());
        $this->login($user);

        if ($remember) {
            $this->issueRememberToken((int) $user['UserID']);
        }

        $this->logs->record('login', true, (int) $user['UserID'], $user['Username']);

        return ['ok' => true];
    }

    /** Establish an authenticated session for the user. */
    public function login(array $user): void
    {
        $session = session();
        $session->regenerate(true); // prevent session fixation

        $session->set(self::SESSION_KEY, [
            'id'          => (int) $user['UserID'],
            'username'    => $user['Username'],
            'name'        => $user['FullName'],
            'role'        => $user['Role'],
            'fingerprint' => $this->fingerprint(),
            'login_at'    => time(),
            'last_seen'   => time(),
            'pwd_changed' => $user['PasswordChangedAt'] ?? null,
        ]);
    }

    public function logout(): void
    {
        $id = $this->id();
        $this->forgetRememberToken();
        if ($id !== null) {
            $this->logs->record('logout', true, $id);
        }
        session()->remove(self::SESSION_KEY);
        session()->remove('redirect_after_login');
        session()->regenerate(true); // new id, old session data file destroyed
    }

    public function id(): ?int
    {
        return session(self::SESSION_KEY)['id'] ?? null;
    }

    public function user(): ?array
    {
        return session(self::SESSION_KEY);
    }

    /**
     * Validates the current session. Returns false (and clears it) when
     * missing, hijacked, idle-expired, or the account was disabled/changed.
     */
    public function check(): bool
    {
        $data = session(self::SESSION_KEY);

        if (! is_array($data)) {
            return $this->loginFromRememberCookie();
        }

        $idle = $this->config->idleTimeoutMinutes * 60;
        if (! hash_equals($data['fingerprint'], $this->fingerprint()) || (time() - $data['last_seen']) > $idle) {
            session()->remove(self::SESSION_KEY);

            return $this->loginFromRememberCookie();
        }

        // Re-validate account against DB at most once per minute.
        if (time() - ($data['verified_at'] ?? 0) > 60) {
            $user = $this->users->find($data['id']);
            if ($user === null || (int) $user['IsActive'] !== 1 || ($user['PasswordChangedAt'] ?? null) !== $data['pwd_changed']) {
                $this->logout();

                return false;
            }
            $data['verified_at'] = time();
        }

        $data['last_seen'] = time();
        session()->set(self::SESSION_KEY, $data);

        return true;
    }

    protected function fingerprint(): string
    {
        return hash('sha256', (string) service('request')->getUserAgent());
    }

    // ------------------------------------------------------------------
    // Remember-me (selector/validator split token, validator stored hashed)
    // ------------------------------------------------------------------

    protected function issueRememberToken(int $userId): void
    {
        $selector  = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $expires   = time() + $this->config->rememberDays * 86400;

        $this->tokens->insert([
            'UserID'        => $userId,
            'Selector'      => $selector,
            'ValidatorHash' => hash('sha256', $validator),
            'UserAgent'     => mb_substr((string) service('request')->getUserAgent(), 0, 255),
            'ExpiresAt'     => date('Y-m-d H:i:s', $expires),
        ]);

        $this->setRememberCookie($selector . ':' . $validator, $expires - time());
    }

    protected function setRememberCookie(string $value, int $ttl): void
    {
        service('response')->setCookie([
            'name'     => $this->config->rememberCookie,
            'value'    => $value,
            'expire'   => $ttl,
            'httponly' => true,
            'secure'   => service('request')->isSecure(),
            'samesite' => 'Lax',
            'path'     => '/',
        ]);
    }

    protected function loginFromRememberCookie(): bool
    {
        $cookie = service('request')->getCookie($this->config->rememberCookie);
        if (! is_string($cookie) || ! str_contains($cookie, ':')) {
            return false;
        }

        [$selector, $validator] = explode(':', $cookie, 2);
        $row                    = $this->tokens->where('Selector', $selector)->first();

        if ($row === null || strtotime($row['ExpiresAt']) < time()
            || ! hash_equals($row['ValidatorHash'], hash('sha256', $validator))) {
            if ($row !== null) {
                // Possible token theft: wipe all of this user's tokens.
                $this->tokens->where('UserID', $row['UserID'])->delete();
            }
            service('response')->deleteCookie($this->config->rememberCookie, '', '/');

            return false;
        }

        $user = $this->users->find($row['UserID']);
        if ($user === null || (int) $user['IsActive'] !== 1 || $this->users->isLocked($user)) {
            return false;
        }

        // Rotate token on every use.
        $this->tokens->delete($row['TokenID']);
        $this->login($user);
        $this->issueRememberToken((int) $user['UserID']);
        $this->logs->record('login_remember', true, (int) $user['UserID'], $user['Username']);

        return true;
    }

    protected function forgetRememberToken(): void
    {
        $cookie = service('request')->getCookie($this->config->rememberCookie);
        if (is_string($cookie) && str_contains($cookie, ':')) {
            $this->tokens->where('Selector', explode(':', $cookie, 2)[0])->delete();
        }
        service('response')->deleteCookie($this->config->rememberCookie, '', '/');
    }

    /** Revoke all remember-me tokens (used after password reset). */
    public function revokeAllTokens(int $userId): void
    {
        $this->tokens->where('UserID', $userId)->delete();
    }
}
