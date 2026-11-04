<?php

namespace App\Models;

use CodeIgniter\Model;

class MaterialTestModel extends Model
{
    protected $table          = 'tbl_material_tests';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';

    protected $allowedFields = [
        'category',
        'labreference',
        'testdate',
        'type',
        'machine_number',
        'samplecountdry',
        'samplecountwet',
        'samplecounthydro',
        'target_cycles',
        'current_cycles',
        'recorded_cycles',
        'status',
        'is_running',
        'operator',
        'technician',
        'temperature',
        'humidity',
        'remarks',
        'recorded_remarks',
    ];

    protected $validationRules = [
        'category'     => 'required|in_list[hydrolysis,heat-aging,washing,uv-light,flexing,deeptest,deep-test,bally-flex,ross-flex,martindale-abrasion,lace-abrasion,taber-abrasion,crocking]',
        'labreference' => 'required|min_length[5]|max_length[64]|is_unique[tbl_material_tests.labreference,id,{id}]',
        'status'       => 'permit_empty|in_list[PENDING,RUNNING,COMPLETED,STOPPED]',
    ];

    protected $validationMessages = [
        'labreference' => [
            'is_unique' => 'The Lab Reference is already in use by another Material Testing session.',
        ],
    ];
}
