<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Live sessions with an administrator inside the assistant chat.
 *  tbl_bot_sessions   one row per "Talk to an administrator" session (open -> closed by the admin or after 2 minutes
 *                     without activity); the chat is named <username>-S<id> on the admin side
 *  tbl_bot_chats.session_id   the messages that belong to that session (system lines mark its start and end)
 */
class CreateBotSessions extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('tbl_bot_sessions')) {
            $this->forge->addField([
                'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'thread_id'        => ['type' => 'INT', 'unsigned' => true],
                'user_id'          => ['type' => 'INT', 'unsigned' => true],
                'username'         => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'user_name'        => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'status'           => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'open'],   // open|closed
                'admin_user'       => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'admin_name'       => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'admin_unread'     => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
                'topic'            => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
                'page'             => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
                'started_at'       => ['type' => 'DATETIME', 'null' => true],
                'last_activity_at' => ['type' => 'DATETIME', 'null' => true],
                'notified_at'      => ['type' => 'DATETIME', 'null' => true],
                'closed_at'        => ['type' => 'DATETIME', 'null' => true],
                'closed_by'        => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],      // admin username | timeout
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['status', 'last_activity_at']);
            $this->forge->addKey('thread_id');
            $this->forge->createTable('tbl_bot_sessions', true, ['ENGINE' => 'InnoDB']);
        }
        if (! $this->db->fieldExists('session_id', 'tbl_bot_chats')) {
            $this->forge->addColumn('tbl_bot_chats', ['session_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'thread_id']]);
            $this->db->query('ALTER TABLE tbl_bot_chats ADD INDEX idx_bot_chats_session (session_id)');
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('session_id', 'tbl_bot_chats')) {
            $this->forge->dropColumn('tbl_bot_chats', 'session_id');
        }
        $this->forge->dropTable('tbl_bot_sessions', true);
    }
}
