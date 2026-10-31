<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Auto-learning for the assistant: why a question was a gap (no answer / low confidence / thumbs-down) and the suggestions the
 * Brain builds from those gaps for an administrator to approve (nothing goes live without an approval).
 */
class AddBotLearning extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('tbl_bot_unanswered')) {
            if (! $this->db->fieldExists('reason', 'tbl_bot_unanswered')) {
                $this->forge->addColumn('tbl_bot_unanswered', [
                    'reason' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'none'], // none|low|down
                    'kb_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],            // the answer that was given (low|down)
                ]);
            }
        }
        if (! $this->db->tableExists('tbl_bot_suggestions')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'kind'        => ['type' => 'VARCHAR', 'constraint' => 8],                        // alias|draft|gap
                'sig'         => ['type' => 'VARCHAR', 'constraint' => 40],                       // stable key so a refresh does not duplicate
                'kb_id'       => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'title'       => ['type' => 'VARCHAR', 'constraint' => 200],
                'keywords'    => ['type' => 'VARCHAR', 'constraint' => 600, 'null' => true],
                'body'        => ['type' => 'TEXT', 'null' => true],
                'evidence'    => ['type' => 'TEXT', 'null' => true],                              // json: questions, ids, where the answer text came from
                'times'       => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
                'status'      => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'open'],  // open|applied|rejected
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
                'decided_at'  => ['type' => 'DATETIME', 'null' => true],
                'decided_by'  => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey('sig');
            $this->forge->addKey(['status', 'times']);
            $this->forge->createTable('tbl_bot_suggestions', true, ['ENGINE' => 'InnoDB']);
        }
    }

    public function down()
    {
        $this->forge->dropTable('tbl_bot_suggestions', true);
        if ($this->db->fieldExists('reason', 'tbl_bot_unanswered')) {
            $this->forge->dropColumn('tbl_bot_unanswered', ['reason', 'kb_id']);
        }
    }
}
