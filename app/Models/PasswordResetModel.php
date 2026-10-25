<?php

namespace App\Models;

use CodeIgniter\Model;

class PasswordResetModel extends Model
{
    protected $table         = 'tbl_password_resets';
    protected $primaryKey    = 'ResetID';
    protected $returnType    = 'array';
    protected $allowedFields = ['UserID', 'Selector', 'CodeHash', 'Attempts', 'Verified', 'ExpiresAt', 'UsedAt', 'RequestIP'];

    protected $useTimestamps = true;
    protected $createdField  = 'CreatedAt';
    protected $updatedField  = '';

    /** Returns a still-usable reset request for the given selector. */
    public function findActive(string $selector): ?array
    {
        return $this->where('Selector', $selector)
            ->where('UsedAt', null)
            ->where('ExpiresAt >', date('Y-m-d H:i:s'))
            ->first();
    }

    /** Invalidate every outstanding request of a user. */
    public function invalidateForUser(int $userId): void
    {
        $this->where('UserID', $userId)->where('UsedAt', null)
            ->set('UsedAt', date('Y-m-d H:i:s'))->update();
    }

    public function recentRequestCount(int $userId, int $minutes = 15): int
    {
        return $this->where('UserID', $userId)
            ->where('CreatedAt >', date('Y-m-d H:i:s', time() - $minutes * 60))
            ->countAllResults();
    }
}
