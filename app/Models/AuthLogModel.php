<?php

namespace App\Models;

use CodeIgniter\Model;

class AuthLogModel extends Model
{
    protected $table         = 'tbl_auth_logs';
    protected $primaryKey    = 'LogID';
    protected $returnType    = 'array';
    protected $allowedFields = ['UserID', 'Username', 'Event', 'Success', 'IPAddress', 'UserAgent'];

    protected $useTimestamps = true;
    protected $createdField  = 'CreatedAt';
    protected $updatedField  = '';

    public function record(string $event, bool $success, ?int $userId = null, ?string $username = null): void
    {
        $req = service('request');
        $this->insert([
            'UserID'    => $userId,
            'Username'  => $username !== null ? mb_substr($username, 0, 50) : null,
            'Event'     => $event,
            'Success'   => $success ? 1 : 0,
            'IPAddress' => $req->getIPAddress(),
            'UserAgent' => mb_substr((string) $req->getUserAgent(), 0, 255),
        ]);
    }
}
