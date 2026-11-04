<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark labdocs:import-specs [--force]
 *
 * Loads what ai-service/tools/lab103_convert.py read from #DTP-Lab-103 Material Specification Sheet (E:\NexusAI\vfm\lab103\) into dbnexus:
 * the register, one card per specification sheet (its tables as JSON blocks) and the Excel files. A sheet edited in NEXUS-L keeps its edit unless --force.
 */
class LabDocsImportSpecs extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'labdocs:import-specs';
    protected $description = 'Imports the converted #DTP-Lab-103 material specification sheets into the database.';

    private const DIR = 'E:\\NexusAI\\vfm\\lab103';

    public function run(array $params)
    {
        $force = CLI::getOption('force') !== null || in_array('--force', $params, true);
        $idxF  = self::DIR . '\\index.json';
        if (! is_file($idxF)) {
            CLI::error('Run ai-service/tools/lab103_convert.py first.');
            return;
        }
        $idx = json_decode((string) file_get_contents($idxF), true);
        $db  = \Config\Database::connect();
        if (! $db->tableExists('tbl_vfm_methods')) {
            CLI::error('Run php spark migrate first.');
            return;
        }
        $now = date('Y-m-d H:i:s');
        $reg = $db->table('tbl_labdoc_registers')->where('code', 'DTP-Lab-103')->get()->getRowArray();
        $row = [
            'code' => 'DTP-Lab-103', 'view_type' => 'specs', 'menu_label' => 'DTP-Lab-103 Material Specification Sheet', 'title' => 'Material Specification Sheet',
            'folder_name' => $idx['folder'], 'file_path' => '', 'sheet_name' => '', 'sort_order' => 103, 'is_active' => 1, 'updated_by' => 'import', 'updated_at' => $now,
            'meta_json' => json_encode(new \stdClass()),
            'source_files' => json_encode(array_map(static fn ($c) => ['name' => $c['file_name'], 'modified' => date('Y-m-d H:i', $c['mtime'])], $idx['cards']), JSON_UNESCAPED_UNICODE),
        ];
        if ($reg) {
            $db->table('tbl_labdoc_registers')->where('id', $reg['id'])->update($row);
            $rid = (int) $reg['id'];
        } else {
            $db->table('tbl_labdoc_registers')->insert($row);
            $rid = (int) $db->insertID();
        }
        $new = $kept = 0;
        foreach ($idx['cards'] as $i => $m) {
            $f = self::DIR . '\\' . $m['slug'] . '.json';
            if (! is_file($f)) {
                continue;
            }
            $json = (string) file_get_contents($f);
            $cur  = $db->table('tbl_vfm_methods')->where(['register_id' => $rid, 'code' => $m['slug']])->get()->getRowArray();
            $data = [
                'register_id' => $rid, 'code' => mb_substr($m['slug'], 0, 20), 'series' => '', 'title' => $m['name'], 'rev_label' => $m['year'], 'rev_date' => null,
                'pages' => $m['pages'], 'file_name' => $m['file_name'], 'cache_file' => 'lab103/' . $m['file_name'], 'sort_order' => $i, 'is_active' => 1, 'original_json' => $json,
            ];
            if (! $cur) {
                $db->table('tbl_vfm_methods')->insert($data + ['content_json' => $json]);
                $new++;
            } else {
                if ($force || (int) $cur['revision_no'] === 0) {
                    $data['content_json'] = $json;
                    if ($force) {
                        $data['revision_no'] = 0;
                        $data['edited_by']   = null;
                        $data['edited_at']   = null;
                    }
                } else {
                    $kept++;
                }
                $db->table('tbl_vfm_methods')->where('id', $cur['id'])->update($data);
            }
        }
        $db->table('tbl_vfm_docs')->where('register_id', $rid)->delete();
        foreach ($idx['docs'] as $i => $d) {
            $db->table('tbl_vfm_docs')->insert(['register_id' => $rid, 'doc_key' => $d['key'], 'label' => $d['label'], 'kind' => $d['kind'], 'file_name' => $d['file_name'],
                'cache_file' => 'lab103/' . $d['file_name'], 'file_size' => $d['size'], 'file_mtime' => date('Y-m-d H:i:s', $d['mtime']), 'sort_order' => $i]);
        }
        $db->table('tbl_labdoc_log')->insert(['register_id' => $rid, 'action' => 'import', 'user_name' => 'spark', 'created_at' => $now,
            'detail' => count($idx['cards']) . ' specification sheets (' . $new . ' new, ' . $kept . ' edited ones kept), ' . count($idx['docs']) . ' Excel files']);
        CLI::write('DTP-Lab-103: ' . count($idx['cards']) . " specification sheets ({$new} new, {$kept} edited kept), " . count($idx['docs']) . ' Excel files', 'green');
    }
}
