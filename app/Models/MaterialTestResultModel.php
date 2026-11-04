<?php

namespace App\Models;

use CodeIgniter\Model;

class MaterialTestResultModel extends Model
{
    protected $table          = 'tbl_material_test_results';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = false;

    protected $allowedFields = [
        'test_id',
        'labreference',
        'group_type',
        'sample_num',
        'cycle',
        'recorded_cycles',
        'is_recorded',
        'is_stopped',
        'findings',
        'recorded_remarks',
        'result',
        'recorded_by',
        'recorded_at',
    ];

    protected $validationRules = [
        'test_id'      => 'required|integer',
        'labreference' => 'required|max_length[64]',
        'group_type'   => 'required|in_list[dry,wet,hydro]',
        'sample_num'   => 'required|integer',
        'cycle'        => 'required|integer',
        'result'       => 'permit_empty|in_list[PASS,FAIL,TOLERABLE,STOPPED]',
    ];
}
