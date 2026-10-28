<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Background InterLink sync center:
 *  - tbl_interlink_session      : one row (id 1) with the state of the InterLink login session used by the worker
 *  - tbl_interlink_queue        : Lab References queued for the worker (survive an expired session)
 *  - tbl_interlink_live_events  : what changed in InterLink since the last pull (other users' edits / new rows)
 *  - tbl_interlink_grid_columns : every column InterLink's grid has delivered (new columns show up as events)
 */
class CreateInterlinkSyncCenter extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('tbl_interlink_session')) {
            $this->forge->addField([
                'id'              => ['type' => 'TINYINT', 'unsigned' => true],
                'status'          => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'none'],
                'owner_user'      => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'owner_user_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'connected_at'    => ['type' => 'DATETIME', 'null' => true],
                'last_checked_at' => ['type' => 'DATETIME', 'null' => true],
                'last_ok_at'      => ['type' => 'DATETIME', 'null' => true],
                'last_error'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'notified_at'     => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->createTable('tbl_interlink_session', true, ['ENGINE' => 'InnoDB']);
        }
        if (! $this->db->table('tbl_interlink_session')->where('id', 1)->countAllResults()) {
            $this->db->table('tbl_interlink_session')->insert(['id' => 1, 'status' => 'none']);
        }

        if (! $this->db->tableExists('tbl_interlink_queue')) {
            $this->forge->addField([
                'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'lab_ref_no'     => ['type' => 'VARCHAR', 'constraint' => 80],
                'application_no' => ['type' => 'VARCHAR', 'constraint' => 40],
                'fields_json'    => ['type' => 'MEDIUMTEXT', 'null' => true],
                'status'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'], // pending|running|prepared|failed|cancelled
                'attempts'       => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
                'requested_by'   => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'created_at'     => ['type' => 'DATETIME', 'null' => true],
                'started_at'     => ['type' => 'DATETIME', 'null' => true],
                'finished_at'    => ['type' => 'DATETIME', 'null' => true],
                'message'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['status', 'id']);
            $this->forge->addKey(['application_no', 'lab_ref_no']);
            $this->forge->createTable('tbl_interlink_queue', true, ['ENGINE' => 'InnoDB']);
        }

        if (! $this->db->tableExists('tbl_interlink_live_events')) {
            $this->forge->addField([
                'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'event_type'     => ['type' => 'VARCHAR', 'constraint' => 20], // changed|new_column|session
                'application_no' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'summary'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'created_at'     => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('created_at');
            $this->forge->createTable('tbl_interlink_live_events', true, ['ENGINE' => 'InnoDB']);
        }

        if (! $this->db->tableExists('tbl_interlink_grid_columns')) {
            $this->forge->addField([
                'col_key'    => ['type' => 'VARCHAR', 'constraint' => 80],
                'first_seen' => ['type' => 'DATETIME', 'null' => true],
                'last_seen'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('col_key');
            $this->forge->createTable('tbl_interlink_grid_columns', true, ['ENGINE' => 'InnoDB']);
        }
    }

    public function down()
    {
        foreach (['tbl_interlink_grid_columns', 'tbl_interlink_live_events', 'tbl_interlink_queue', 'tbl_interlink_session'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
