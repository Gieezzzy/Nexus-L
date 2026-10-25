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
        'Username', 'FullName', 'Email', 'SynologyID', 'Department', 'Password', 'Role',
        'IsActive', 'FailedAttempts', 'LockedUntil', 'LastLoginAt', 'LastLoginIP', 'PasswordChangedAt',
    ];

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
