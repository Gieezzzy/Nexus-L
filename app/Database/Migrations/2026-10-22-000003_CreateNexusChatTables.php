<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * NEXUS-L's own copy of the RMS messaging tables (same names/columns as dbRMSAndroid, which keeps the code
 * portable between both databases) plus attachment columns for files.
 * Replaces the empty placeholder `tbl_messages` created by migration 000002 (Windows MySQL is case-insensitive,
 * so it would clash with tbl_Messages).
 */
class CreateNexusChatTables extends Migration
{
    public function up()
    {
        // The placeholder from migration 000002: only dropped when it is empty.
        if ($this->db->tableExists('tbl_messages') && $this->db->table('tbl_messages')->countAllResults() === 0) {
            $this->forge->dropTable('tbl_messages', true);
        }

        // ---------------- tbl_Messages ----------------
        $this->forge->addField([
            'MessageID'          => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'TargetReferenceID'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'SenderID'           => ['type' => 'VARCHAR', 'constraint' => 100],
            'ReceiverID'         => ['type' => 'VARCHAR', 'constraint' => 255],
            'MessageContent'     => ['type' => 'MEDIUMTEXT', 'null' => true],
            'DateSent'           => ['type' => 'DATETIME', 'null' => true],
            'SynologyPostToken'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'IsSyncedToSynology' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'ChannelID'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'ConnectedID'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'IsRead'             => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'imagePath'          => ['type' => 'TEXT', 'null' => true],
            'EmoteTag'           => ['type' => 'MEDIUMTEXT', 'null' => true],
            'IsGroup'            => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'No'],
            'GroupID'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'SingleID'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            // attachments (web)
            'FilePath'           => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'comment' => 'relative to writable/uploads/inbox'],
            'FileName'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'FileSize'           => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'FileType'           => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('MessageID');
        $this->forge->addKey(['SenderID', 'ReceiverID', 'MessageID']);
        $this->forge->addKey(['ReceiverID', 'IsRead']);
        $this->forge->addKey('SingleID');
        $this->forge->createTable('tbl_Messages', true, ['ENGINE' => 'InnoDB']);

        // ---------------- SingleUserMessage (per 1:1 chat settings) ----------------
        $this->forge->addField([
            'ID'                   => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'SingleID'             => ['type' => 'VARCHAR', 'constraint' => 255],
            'Members'              => ['type' => 'TEXT', 'null' => true],
            'ChatName'             => ['type' => 'TEXT', 'null' => true],
            'ChatPic'              => ['type' => 'TEXT', 'null' => true],
            'Nickname'             => ['type' => 'TEXT', 'null' => true],
            'Background'           => ['type' => 'TEXT', 'null' => true],
            'Policies'             => ['type' => 'TEXT', 'null' => true],
            'SecuredChat'          => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'PinnedMessage'        => ['type' => 'TEXT', 'null' => true],
            'CurrentTheme'         => ['type' => 'TEXT', 'null' => true],
            'CurrentEmoji'         => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'IsMute'               => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'IsDisapearingMessage' => ['type' => 'TEXT', 'null' => true],
            'IsReadReceipt'        => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'IsRestrict'           => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'IsBlocked'            => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'IsReport'             => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'UpdatedAt'            => ['type' => 'DATETIME', 'null' => true],
            'CreatedAt'            => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('ID');
        $this->forge->addUniqueKey('SingleID');
        $this->forge->createTable('SingleUserMessage', true, ['ENGINE' => 'InnoDB']);

        // ---------------- tbl_GroupMessage (structure copy; group chat is not on the web yet) ----------------
        $this->forge->addField([
            'GroupID'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'members'              => ['type' => 'TEXT', 'null' => true],
            'GroupName'            => ['type' => 'TEXT', 'null' => true],
            'GroupPic'             => ['type' => 'TEXT', 'null' => true],
            'Admin'                => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'Nickname'             => ['type' => 'TEXT', 'null' => true],
            'BackGround'           => ['type' => 'TEXT', 'null' => true],
            'Policies'             => ['type' => 'TEXT', 'null' => true],
            'RemovedMembers'       => ['type' => 'TEXT', 'null' => true],
            'SecuredChat'          => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'PinnedMessage'        => ['type' => 'TEXT', 'null' => true],
            'CurrentTheme'         => ['type' => 'TEXT', 'null' => true],
            'CurrentEmoji'         => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'IsMute'               => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'IsDisapearingMessage' => ['type' => 'TEXT', 'null' => true],
            'IsReadReceipt'        => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'IsRestrict'           => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'IsBlocked'            => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'IsReport'             => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'CreatedAt'            => ['type' => 'DATETIME', 'null' => true],
            'UpdatedAt'            => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('GroupID');
        $this->forge->createTable('tbl_GroupMessage', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('tbl_GroupMessage', true);
        $this->forge->dropTable('SingleUserMessage', true);
        $this->forge->dropTable('tbl_Messages', true);
    }
}
