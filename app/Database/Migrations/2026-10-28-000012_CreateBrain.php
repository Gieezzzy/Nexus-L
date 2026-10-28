<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * NEXUS-L Brain: what users do (events), what the help bot knows (knowledge), what was asked and answered (threads +
 * chats, including the chat with an administrator), questions the bot could not answer yet (unanswered) and repeated
 * problems (issues). Rows are exported to the Brain folders (.back files) by `php spark brain:flush`.
 */
class CreateBrain extends Migration
{
    public function up()
    {
        $db = $this->db;

        if (! $db->tableExists('tbl_bot_events')) {
            $this->forge->addField([
                'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'user_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'username'   => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'role'       => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'event_type' => ['type' => 'VARCHAR', 'constraint' => 24],      // view|click|save|error|http_error|stuck|bot
                'page'       => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
                'target'     => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
                'detail'     => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'status'     => ['type' => 'SMALLINT', 'null' => true],
                'signature'  => ['type' => 'CHAR', 'constraint' => 32, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'flushed'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['user_id', 'created_at']);
            $this->forge->addKey(['event_type', 'created_at']);
            $this->forge->addKey(['signature']);
            $this->forge->addKey(['flushed']);
            $this->forge->createTable('tbl_bot_events', true, ['ENGINE' => 'InnoDB']);
        }

        if (! $db->tableExists('tbl_bot_knowledge')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'kind'        => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'playbook'], // playbook|qa|system|learned
                'title'       => ['type' => 'VARCHAR', 'constraint' => 200],
                'keywords'    => ['type' => 'VARCHAR', 'constraint' => 600, 'null' => true],
                'body'        => ['type' => 'TEXT', 'null' => true],
                'steps_json'  => ['type' => 'TEXT', 'null' => true],
                'actions_json' => ['type' => 'TEXT', 'null' => true],
                'page_match'  => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'error_match' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'diagnose'    => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'role_min'    => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'user'],
                'status'      => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'approved'], // draft|approved|retired
                'source'      => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'seed'],
                'hits'        => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
                'helpful'     => ['type' => 'INT', 'default' => 0],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
                'updated_at'  => ['type' => 'DATETIME', 'null' => true],
                'flushed_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['status', 'kind']);
            $this->forge->createTable('tbl_bot_knowledge', true, ['ENGINE' => 'InnoDB']);
            try {
                $db->query('ALTER TABLE tbl_bot_knowledge ADD FULLTEXT KEY ft_kb (title, keywords, body)');
            } catch (\Throwable $e) {
                // search falls back to LIKE
            }
        }

        if (! $db->tableExists('tbl_bot_threads')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'user_id'     => ['type' => 'INT', 'unsigned' => true],
                'username'    => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'status'      => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'bot'],   // bot|escalated|resolved
                'topic'       => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
                'page'        => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
                'admin_user'  => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'admin_unread' => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
                'user_unread' => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
                'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['user_id', 'status']);
            $this->forge->addKey(['status', 'updated_at']);
            $this->forge->createTable('tbl_bot_threads', true, ['ENGINE' => 'InnoDB']);
        }

        if (! $db->tableExists('tbl_bot_chats')) {
            $this->forge->addField([
                'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'thread_id'  => ['type' => 'INT', 'unsigned' => true],
                'sender'     => ['type' => 'VARCHAR', 'constraint' => 8],                       // user|bot|admin
                'sender_name' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'message'    => ['type' => 'TEXT'],
                'knowledge_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'confidence' => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => true],
                'feedback'   => ['type' => 'TINYINT', 'null' => true],                          // 1 helpful, -1 not
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'flushed'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['thread_id', 'id']);
            $this->forge->addKey(['flushed']);
            $this->forge->createTable('tbl_bot_chats', true, ['ENGINE' => 'InnoDB']);
        }

        if (! $db->tableExists('tbl_bot_unanswered')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'norm'       => ['type' => 'VARCHAR', 'constraint' => 200],
                'sample'     => ['type' => 'VARCHAR', 'constraint' => 400, 'null' => true],
                'page'       => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
                'times'      => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
                'status'     => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'open'],  // open|answered|ignored
                'first_seen' => ['type' => 'DATETIME', 'null' => true],
                'last_seen'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['status', 'times']);
            $this->forge->addUniqueKey('norm');
            $this->forge->createTable('tbl_bot_unanswered', true, ['ENGINE' => 'InnoDB']);
        }

        if (! $db->tableExists('tbl_bot_issues')) {
            $this->forge->addField([
                'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'signature'    => ['type' => 'CHAR', 'constraint' => 32],
                'summary'      => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
                'page'         => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
                'times'        => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
                'users'        => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
                'status'       => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'open'],   // open|resolved|ignored
                'knowledge_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'first_seen'   => ['type' => 'DATETIME', 'null' => true],
                'last_seen'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey('signature');
            $this->forge->addKey(['status', 'times']);
            $this->forge->createTable('tbl_bot_issues', true, ['ENGINE' => 'InnoDB']);
        }
    }

    public function down()
    {
        foreach (['tbl_bot_issues', 'tbl_bot_unanswered', 'tbl_bot_chats', 'tbl_bot_threads', 'tbl_bot_knowledge', 'tbl_bot_events'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
