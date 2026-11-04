<?php

namespace App\Controllers;

use App\Models\MaterialTestModel;
use App\Models\MaterialTestResultModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Controller for Material Testing Suite
 * Dedicated controller separated from Whole Shoe testing.
 */
class MaterialsController extends BaseController
{
    public const MATERIAL_CATEGORIES = [
        'martindale-abrasion',
        'taber-abrasion',
        'lace-abrasion',
        'crocking',
        'ross-flex',
    ];

    private const CATEGORIES = [
        'bally-flex' => [
            'name'       => 'Bally Flexing',
            'code'       => 'WSF',
            'remarkMode' => 'checkbox',
        ],
        'ross-flex' => [
            'name'       => 'Ross Flexing',
            'code'       => 'RB',
            'remarkMode' => 'checkbox',
        ],
        'martindale-abrasion' => [
            'name'       => 'Martindale Abrasion',
            'code'       => 'MDA',
            'remarkMode' => 'text',
        ],
        'lace-abrasion' => [
            'name'       => 'Lace Abrasion',
            'code'       => 'LC',
            'remarkMode' => 'text',
        ],
        'taber-abrasion' => [
            'name'       => 'Taber Abrasion',
            'code'       => 'TBR',
            'remarkMode' => 'text',
        ],
        'crocking' => [
            'name'       => 'Crocking',
            'code'       => 'CRK',
            'remarkMode' => 'text',
        ],
    ];

    /**
     * GET /materials
     * Top-level Material Testing & Monitoring page
     */
    public function index(): string
    {
        return $this->render('materials/index', [
            'title'   => 'Material Testing',
            'withCss' => 'dtr.css',
            'withJs'  => ['materials.js'],
        ]);
    }
}
