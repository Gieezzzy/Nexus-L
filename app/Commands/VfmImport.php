<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark vfm:import [--force]
 *
 * Loads what ai-service/tools/vfm_convert.py read from #DTP-Lab-101 VF Test Methods Updated 2025 (E:\NexusAI\vfm\) into dbnexus:
 * the register, the test methods (with their document as JSON blocks), the illustrated PDFs and the documents of the folder.
 * A method that was edited in NEXUS-L keeps its edit unless --force is given.
 */
class VfmImport extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'vfm:import';
    protected $description = 'Imports the converted #DTP-Lab-101 test methods into the database.';

    private const DIR = 'E:\\NexusAI\\vfm';

    public function run(array $params)
    {
        $force = CLI::getOption('force') !== null || in_array('--force', $params, true);
        $idxF  = self::DIR . '\\index.json';
        if (! is_file($idxF)) {
            CLI::error('Run ai-service/tools/vfm_convert.py first.');
            return;
        }
        $idx = json_decode((string) file_get_contents($idxF), true);
        $db  = \Config\Database::connect();
        if (! $db->tableExists('tbl_vfm_methods')) {
            CLI::error('Run php spark migrate first.');
            return;
        }
        $now = date('Y-m-d H:i:s');
        $reg = $db->table('tbl_labdoc_registers')->where('code', 'DTP-Lab-101')->get()->getRowArray();
        $row = [
            'code' => 'DTP-Lab-101', 'view_type' => 'methods', 'menu_label' => 'DTP-Lab-101 VF Test Methods', 'title' => 'VF Test Methods Updated 2025',
            'folder_name' => $idx['folder'], 'file_path' => '', 'sheet_name' => '', 'sort_order' => 101, 'is_active' => 1, 'updated_by' => 'import', 'updated_at' => $now,
            'meta_json' => json_encode(['sections' => $idx['sections']], JSON_UNESCAPED_UNICODE),
            'source_files' => json_encode(array_map(static fn ($d) => ['name' => $d['file_name'], 'size' => $d['size'], 'modified' => date('Y-m-d H:i', $d['mtime'])], $idx['docs']), JSON_UNESCAPED_UNICODE),
        ];
        if ($reg) {
            $db->table('tbl_labdoc_registers')->where('id', $reg['id'])->update($row);
            $rid = (int) $reg['id'];
        } else {
            $db->table('tbl_labdoc_registers')->insert($row);
            $rid = (int) $db->insertID();
        }
        $new = $kept = 0;
        foreach ($idx['methods'] as $i => $m) {
            $f = self::DIR . '\\' . $m['code'] . '.json';
            if (! is_file($f)) {
                continue;
            }
            $json = (string) file_get_contents($f);
            $conv = json_decode($json, true);
            $cur  = $db->table('tbl_vfm_methods')->where(['register_id' => $rid, 'code' => $m['code']])->get()->getRowArray();
            $data = [
                'register_id' => $rid, 'code' => $m['code'], 'series' => $m['series'], 'title' => $m['title'], 'rev_label' => $m['rev_label'], 'rev_date' => $m['rev_date'],
                'pages' => $m['pages'], 'file_name' => $m['file_name'], 'cache_file' => 'lab101/' . $m['file_name'], 'sort_order' => $i, 'is_active' => 1,
                'original_json' => $json,
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
            unset($conv);
        }
        $db->table('tbl_vfm_illustrated')->where('register_id', $rid)->delete();
        foreach ($idx['illustrated'] as $il) {
            $db->table('tbl_vfm_illustrated')->insert(['register_id' => $rid, 'code' => $il['code'], 'label' => $il['label'], 'file_name' => $il['file_name'],
                'cache_file' => 'lab101/illustrated/' . $il['file_name'], 'file_size' => $il['size'], 'file_mtime' => date('Y-m-d H:i:s', $il['mtime'])]);
        }
        $db->table('tbl_vfm_docs')->where('register_id', $rid)->delete();
        foreach ($idx['docs'] as $i => $d) {
            $db->table('tbl_vfm_docs')->insert(['register_id' => $rid, 'doc_key' => $d['key'], 'label' => $d['label'], 'kind' => $d['kind'], 'file_name' => $d['file_name'],
                'cache_file' => 'lab101/' . $d['file_name'], 'file_size' => $d['size'], 'file_mtime' => date('Y-m-d H:i:s', $d['mtime']), 'sort_order' => $i]);
        }
        $db->table('tbl_labdoc_log')->insert(['register_id' => $rid, 'action' => 'import', 'user_name' => 'spark', 'created_at' => $now,
            'detail' => count($idx['methods']) . ' methods (' . $new . ' new, ' . $kept . ' edited ones kept), ' . count($idx['illustrated']) . ' illustrated, ' . count($idx['docs']) . ' documents']);
        CLI::write('DTP-Lab-101: ' . count($idx['methods']) . " methods ({$new} new, {$kept} edited kept), " . count($idx['illustrated']) . ' illustrated, ' . count($idx['docs']) . ' documents', 'green');
    }
}
