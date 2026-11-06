<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * tbl_labdoc_registers.grp   'lab' = a numbered "#" folder (sidebar: Laboratory Documents), 'other' = any other folder of the share (sidebar: Other Documents)
 * tbl_labdoc_files.disk_path a file kept as a copy on the server's disk (writable/labdocs) instead of in the database (used for the big "other" folders)
 */
class AddLabDocGroups extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('grp', 'tbl_labdoc_registers')) {
            $this->forge->addColumn('tbl_labdoc_registers', ['grp' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'lab']]);
        }
        if (! $this->db->fieldExists('disk_path', 'tbl_labdoc_files')) {
            $this->forge->addColumn('tbl_labdoc_files', ['disk_path' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true]]);
        }
    }

    public function down()
    {
        $this->forge->dropColumn('tbl_labdoc_registers', 'grp');
        $this->forge->dropColumn('tbl_labdoc_files', 'disk_path');
    }
}
