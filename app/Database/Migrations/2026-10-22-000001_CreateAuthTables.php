<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Builds the NEXUS-L authentication schema.
 * NOTE: replaces the placeholder `tbl_users` (all-INT columns, no rows).
 */
class CreateAuthTables extends Migration
{
    public function up()
    {
        $this->forge->dropTable('tbl_users', true);

        // ---------------- tbl_users ----------------
        $this->forge->addField([
            'UserID'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'Username'       => ['type' => 'VARCHAR', 'constraint' => 50],
            'FullName'       => ['type' => 'VARCHAR', 'constraint' => 120],
            'Email'          => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'SynologyID'     => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'comment' => 'Synology Chat user_id used for bot notifications'],
            'Department'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'Password'       => ['type' => 'VARCHAR', 'constraint' => 255, 'comment' => 'password_hash() output (Argon2id/bcrypt)'],
            'Role'           => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'user'],
            'IsActive'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'FailedAttempts' => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
            'LockedUntil'    => ['type' => 'DATETIME', 'null' => true],
            'LastLoginAt'    => ['type' => 'DATETIME', 'null' => true],
            'LastLoginIP'    => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'PasswordChangedAt' => ['type' => 'DATETIME', 'null' => true],
            'CreatedAt'      => ['type' => 'DATETIME', 'null' => true],
            'UpdatedAt'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('UserID');
        $this->forge->addUniqueKey('Username');
        $this->forge->addUniqueKey('Email');
        $this->forge->addKey('SynologyID');
        $this->forge->createTable('tbl_users', false, ['ENGINE' => 'InnoDB']);

        // ---------------- tbl_password_resets ----------------
        $this->forge->addField([
            'ResetID'   => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'UserID'    => ['type' => 'INT', 'unsigned' => true],
            'Selector'  => ['type' => 'CHAR', 'constraint' => 32, 'comment' => 'Public random id bound to the browser session'],
            'CodeHash'  => ['type' => 'VARCHAR', 'constraint' => 255, 'comment' => 'password_hash() of the 6-digit code'],
            'Attempts'  => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
            'Verified'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'ExpiresAt' => ['type' => 'DATETIME'],
            'UsedAt'    => ['type' => 'DATETIME', 'null' => true],
            'RequestIP' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'CreatedAt' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('ResetID');
        $this->forge->addUniqueKey('Selector');
        $this->forge->addKey('UserID');
        $this->forge->addForeignKey('UserID', 'tbl_users', 'UserID', 'CASCADE', 'CASCADE');
        $this->forge->createTable('tbl_password_resets', true, ['ENGINE' => 'InnoDB']);

        // ---------------- tbl_remember_tokens ----------------
        $this->forge->addField([
            'TokenID'       => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'UserID'        => ['type' => 'INT', 'unsigned' => true],
            'Selector'      => ['type' => 'CHAR', 'constraint' => 24],
            'ValidatorHash' => ['type' => 'CHAR', 'constraint' => 64, 'comment' => 'sha256 of the validator half'],
            'UserAgent'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'ExpiresAt'     => ['type' => 'DATETIME'],
            'CreatedAt'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('TokenID');
        $this->forge->addUniqueKey('Selector');
        $this->forge->addKey('UserID');
        $this->forge->addForeignKey('UserID', 'tbl_users', 'UserID', 'CASCADE', 'CASCADE');
        $this->forge->createTable('tbl_remember_tokens', true, ['ENGINE' => 'InnoDB']);

        // ---------------- tbl_auth_logs (audit trail) ----------------
        $this->forge->addField([
            'LogID'     => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'UserID'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'Username'  => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'Event'     => ['type' => 'VARCHAR', 'constraint' => 40],
            'Success'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'IPAddress' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'UserAgent' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'CreatedAt' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('LogID');
        $this->forge->addKey(['UserID', 'Event']);
        $this->forge->createTable('tbl_auth_logs', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('tbl_auth_logs', true);
        $this->forge->dropTable('tbl_remember_tokens', true);
        $this->forge->dropTable('tbl_password_resets', true);
        $this->forge->dropTable('tbl_users', true);
    }
}
