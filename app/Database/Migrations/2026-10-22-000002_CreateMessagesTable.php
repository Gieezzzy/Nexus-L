<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Direct messages for the Inbox page. */
class CreateMessagesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'MessageID'   => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'SenderID'    => ['type' => 'INT', 'unsigned' => true],
            'RecipientID' => ['type' => 'INT', 'unsigned' => true],
            'Body'        => ['type' => 'TEXT'],
            'ReadAt'      => ['type' => 'DATETIME', 'null' => true],
            'CreatedAt'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('MessageID');
        $this->forge->addKey(['SenderID', 'RecipientID', 'MessageID']);
        $this->forge->addKey(['RecipientID', 'ReadAt']);
        $this->forge->createTable('tbl_messages', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('tbl_messages', true);
    }
}
