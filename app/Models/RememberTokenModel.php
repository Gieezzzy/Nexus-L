<?php

namespace App\Models;

use CodeIgniter\Model;

class RememberTokenModel extends Model
{
    protected $table         = 'tbl_remember_tokens';
    protected $primaryKey    = 'TokenID';
    protected $returnType    = 'array';
    protected $allowedFields = ['UserID', 'Selector', 'ValidatorHash', 'UserAgent', 'ExpiresAt'];

    protected $useTimestamps = true;
    protected $createdField  = 'CreatedAt';
    protected $updatedField  = '';

    public function purgeExpired(): void
    {
        $this->where('ExpiresAt <', date('Y-m-d H:i:s'))->delete();
    }
}
