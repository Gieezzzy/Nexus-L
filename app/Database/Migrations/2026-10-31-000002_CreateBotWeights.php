<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * What the assistant has learned about WHICH WORDS point to WHICH ANSWER, from thumbs-up / thumbs-down and rephrases.
 * One row per (answer, word); the word '#' is the answer's own bias. Only the ranking changes, never the answer text.
 */
class CreateBotWeights extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('tbl_bot_weights')) {
            return;
        }
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'kb_id'      => ['type' => 'INT', 'unsigned' => true],
            'term'       => ['type' => 'VARCHAR', 'constraint' => 40],
            'w'          => ['type' => 'FLOAT', 'default' => 0],
            'updates'    => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['kb_id', 'term']);
        $this->forge->addKey('term');
        $this->forge->createTable('tbl_bot_weights', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('tbl_bot_weights', true);
    }
}
