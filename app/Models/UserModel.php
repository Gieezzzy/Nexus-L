<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'tbl_users';
    protected $primaryKey       = 'UserID';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'Username', 'FullName', 'Email', 'SynologyID', 'Department', 'Avatar', 'Password', 'Role',
        'IsActive', 'FailedAttempts', 'LockedUntil', 'LastLoginAt', 'LastLoginIP', 'PasswordChangedAt', 'Phone', 'EmployeeNo', 'JobTitle', 'Prefs',
    ];

    public static function avatarUrl(?string $avatar, int $userId): ?string
    {
        $a = trim((string) $avatar);
        if ($a === '' || $userId <= 0) {
            return null;
        }
        if (str_starts_with($a, 'http://') || str_starts_with($a, 'https://') || str_starts_with($a, '/')) {
            return $a;
        }

        $path = 'account/avatar/' . $userId . '?v=' . substr(md5($a), 0, 8);
        if (function_exists('site_url')) {
            try {
                return site_url($path);
            } catch (\Throwable $e) {
            }
        }

        return '/' . $path;
    }

    private static ?array $synAvatarCache = null;

    public static function clearAvatarCache(): void
    {
        self::$synAvatarCache = null;
    }

    public static function synologyAvatar(?string $synId): ?string
    {
        $syn = trim((string) $synId);
        if ($syn === '') {
            return null;
        }
        if (self::$synAvatarCache === null) {
            self::$synAvatarCache = [];
            try {
                $rows = db_connect()->query("SELECT UserID, SynologyID, Avatar FROM tbl_users WHERE Avatar IS NOT NULL AND Avatar <> '' AND SynologyID IS NOT NULL AND SynologyID <> ''")->getResultArray();
                foreach ($rows as $r) {
                    $url = self::avatarUrl($r['Avatar'] ?? null, (int) ($r['UserID'] ?? 0));
                    if ($url !== null) {
                        self::$synAvatarCache[strtolower(trim((string) $r['SynologyID']))] = $url;
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        return self::$synAvatarCache[strtolower($syn)] ?? null;
    }

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'CreatedAt';
    protected $updatedField  = 'UpdatedAt';

    // Password is ALWAYS hashed through the model callbacks, never stored raw.
    protected $beforeInsert = ['hashPassword'];
    protected $beforeUpdate = ['hashPassword'];

    /** Fields never exposed in the session / views. */
    public const HIDDEN = ['Password', 'FailedAttempts', 'LockedUntil'];

    public static function passwordAlgo(): string|int|null
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    }

    public static function makeHash(string $plain): string
    {
        return password_hash($plain, self::passwordAlgo());
    }

    protected function hashPassword(array $data): array
    {
        if (isset($data['data']['Password']) && $data['data']['Password'] !== '') {
            $pwd = $data['data']['Password'];
            // Only hash if not already a hash (prevents double hashing on rehash()).
            if (password_get_info($pwd)['algo'] === null) {
                $data['data']['Password']          = self::makeHash($pwd);
                $data['data']['PasswordChangedAt'] = date('Y-m-d H:i:s');
            }
        }

        return $data;
    }

    public function findByLogin(string $login): ?array
    {
        return $this->groupStart()
            ->where('Username', $login)
            ->orWhere('Email', $login)
            ->groupEnd()
            ->first();
    }

    public function isLocked(array $user): bool
    {
        return ! empty($user['LockedUntil']) && strtotime($user['LockedUntil']) > time();
    }

    public function registerFailure(array $user, int $max, int $lockMinutes): void
    {
        $attempts = (int) $user['FailedAttempts'] + 1;
        $data     = ['FailedAttempts' => $attempts];
        if ($attempts >= $max) {
            $data['LockedUntil']    = date('Y-m-d H:i:s', time() + $lockMinutes * 60);
            $data['FailedAttempts'] = 0;
        }
        $this->update($user['UserID'], $data);
    }

    public function registerSuccess(array $user, string $ip): void
    {
        $data = [
            'FailedAttempts' => 0,
            'LockedUntil'    => null,
            'LastLoginAt'    => date('Y-m-d H:i:s'),
            'LastLoginIP'    => $ip,
        ];
        $this->update($user['UserID'], $data);
    }

    public static function publicData(array $user): array
    {
        return array_diff_key($user, array_flip(self::HIDDEN));
    }
}
