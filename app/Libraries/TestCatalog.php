<?php

namespace App\Libraries;

/**
 * What each test category can test, per material ("Type").
 *
 * For every material:
 *   code   - suffix of the auto lab reference: DTP + yyyymmdd + code (e.g. DTP20261011LEU)
 *   groups - the sample groups it supports (dry / wet / hydro). Each has the
 *            default sample count and the cycle checkpoints to monitor.
 *
 * This is the one place to edit when a material, cycle list or sample option changes.
 */
class TestCatalog
{
    private static ?array $data = null;

    public static function all(): array
    {
        return self::$data ??= self::build();
    }

    /** Materials of a category, keyed by the Type label saved in the *_tests table. */
    public static function materials(string $slug): array
    {
        return self::all()[$slug] ?? [];
    }

    /** Material config for a Type (case-insensitive), or null. */
    public static function material(string $slug, ?string $type): ?array
    {
        foreach (self::materials($slug) as $label => $material) {
            if (strcasecmp($label, (string) $type) === 0) {
                return $material;
            }
        }
        return null;
    }

    /**
     * Cycle checkpoints for a sample group. Records whose Type isn't in the catalog
     * (older data) fall back to the first material of the category that has the group.
     */
    public static function cycles(string $slug, ?string $type, string $group): array
    {
        $material = self::material($slug, $type);
        if ($material && isset($material['groups'][$group])) {
            return $material['groups'][$group]['cycles'];
        }
        foreach (self::materials($slug) as $m) {
            if (isset($m['groups'][$group])) {
                return $m['groups'][$group]['cycles'];
            }
        }
        return [];
    }

    /** Default samples per reference batch for a material group. */
    public static function defaultCount(string $slug, ?string $type, string $group): int
    {
        $material = self::material($slug, $type);
        if ($material && isset($material['groups'][$group])) {
            return $material['groups'][$group]['count'];
        }
        foreach (self::materials($slug) as $material) {
            if (isset($material['groups'][$group])) {
                return $material['groups'][$group]['count'];
            }
        }
        return 1;
    }

    private static function group(array $cycles, int $count = 2): array
    {
        return ['count' => $count, 'cycles' => $cycles];
    }

    private static function build(): array
    {
        $step = static fn (int $from, int $to, int $by): array => range($from, $to, $by);

        $abr1000 = [200, 400, 600, 800, 1000];
        $lining  = [2000, 4000, 6000, 8000, 10000, 12000, 14000, 16000, 18000, 20000, 22000, 25000];
        $liningW = [2000, 4000, 6000, 6500];
        $ballyDry = [5000, 10000, 15000, 20000, 25000, 35000, 45000, 55000, 65000, 75000, 85000, 95000, 100000];
        $ballyWet = [5000, 10000, 15000, 20000];
        $ballyHydro = $step(5000, 50000, 5000);

        return [
            'hydrolysis' => [
                'Standard Hydrolysis (70°C x 95% RH)' => ['code' => 'HYD', 'groups' => [
                    'dry'   => self::group([24, 48, 72, 96, 120, 144, 168], 2),
                    'wet'   => self::group([24, 48, 72, 120, 168], 2),
                    'hydro' => self::group([24, 48, 72, 96, 120, 144, 168], 2),
                ]],
                'Extended Hydrolysis (14 Days)' => ['code' => 'HYD', 'groups' => [
                    'dry'   => self::group([48, 96, 144, 192, 240, 288, 336], 2),
                    'wet'   => self::group([48, 96, 144, 192, 240, 336], 2),
                    'hydro' => self::group([48, 96, 144, 192, 240, 288, 336], 2),
                ]],
                'PU Midsole / Sole Composite' => ['code' => 'HYD', 'groups' => [
                    'dry'   => self::group([24, 48, 72, 96, 120, 144, 168], 2),
                    'wet'   => self::group([24, 48, 72, 120, 168], 2),
                    'hydro' => self::group([24, 48, 72, 96, 120, 144, 168], 2),
                ]],
            ],

            'heat-aging' => [
                'Standard Heat Aging (70°C x 7 Days)' => ['code' => 'HTA', 'groups' => [
                    'dry'   => self::group([24, 48, 72, 96, 120, 144, 168], 2),
                    'wet'   => self::group([24, 48, 72, 120, 168], 2),
                ]],
                'Accelerated Heat Aging (70°C x 72 Hrs)' => ['code' => 'HTA', 'groups' => [
                    'dry'   => self::group([12, 24, 36, 48, 60, 72], 2),
                    'wet'   => self::group([12, 24, 36, 48, 72], 2),
                ]],
                'Upper / Sole Bond Heat Aging' => ['code' => 'HTA', 'groups' => [
                    'dry'   => self::group([24, 48, 72, 96, 120, 168], 2),
                    'wet'   => self::group([24, 48, 72, 120, 168], 2),
                ]],
            ],

            'washing' => [
                'Standard Machine Wash' => ['code' => 'WSH', 'groups' => [
                    'dry'   => self::group([1, 2, 3, 5, 10], 2),
                    'wet'   => self::group([1, 2, 3, 5, 10], 2),
                ]],
                'Delicate / Hand Wash' => ['code' => 'WSH', 'groups' => [
                    'dry'   => self::group([1, 2, 3, 5], 2),
                    'wet'   => self::group([1, 2, 3, 5], 2),
                ]],
                'Heavy Duty Extended Wash' => ['code' => 'WSH', 'groups' => [
                    'dry'   => self::group([1, 3, 5, 10, 15, 20], 2),
                    'wet'   => self::group([1, 3, 5, 10, 15, 20], 2),
                ]],
            ],

            'uv-light' => [
                'UV Yellowing Resistance (24 Hrs)' => ['code' => 'UVL', 'groups' => [
                    'dry'   => self::group([4, 8, 12, 18, 24], 2),
                    'wet'   => self::group([4, 8, 12, 18, 24], 2),
                ]],
                'UV Weathering Exposure (QUV 48 Hrs)' => ['code' => 'UVL', 'groups' => [
                    'dry'   => self::group([6, 12, 24, 36, 48], 2),
                    'wet'   => self::group([6, 12, 24, 36, 48], 2),
                ]],
                'Solar UV Radiation (72 Hrs)' => ['code' => 'UVL', 'groups' => [
                    'dry'   => self::group([12, 24, 36, 48, 60, 72], 2),
                    'wet'   => self::group([12, 24, 36, 48, 60, 72], 2),
                ]],
            ],

            'flexing' => [
                'Standard Whole Shoe Flexing' => ['code' => 'WSF', 'groups' => [
                    'dry'   => self::group($ballyDry, 4),
                    'wet'   => self::group($ballyWet, 4),
                    'hydro' => self::group($ballyHydro, 4),
                ]],
                'Fabric/Leather Upper' => ['code' => 'LEU', 'groups' => [
                    'dry'   => self::group($ballyDry, 4),
                    'wet'   => self::group($ballyWet, 4),
                    'hydro' => self::group($ballyHydro, 4),
                ]],
                'Heavy Duty Flexing (150K)' => ['code' => 'WSF', 'groups' => [
                    'dry'   => self::group([10000, 25000, 50000, 75000, 100000, 125000, 150000], 4),
                    'wet'   => self::group([10000, 25000, 50000], 4),
                ]],
            ],

            'deeptest' => [
                'Standard Whole Shoe Deep Test' => ['code' => 'DPT', 'groups' => [
                    'dry'   => self::group([5000, 10000, 15000, 20000, 25000, 30000, 40000, 50000], 2),
                    'wet'   => self::group([5000, 10000, 15000, 20000, 25000, 30000, 40000, 50000], 2),
                ]],
                'Dynamic Water Immersion Flexing' => ['code' => 'DPT', 'groups' => [
                    'dry'   => self::group([5000, 10000, 15000, 20000, 25000, 35000, 50000, 75000, 100000], 2),
                    'wet'   => self::group([5000, 10000, 15000, 20000, 25000, 35000, 50000, 75000, 100000], 2),
                ]],
                'Static Submersion (Hours)' => ['code' => 'DPT', 'groups' => [
                    'dry'   => self::group([2, 4, 6, 8, 12, 24, 48, 72], 2),
                    'wet'   => self::group([2, 4, 6, 8, 12, 24, 48, 72], 2),
                ]],
            ],

            'deep-test' => [
                'Standard Whole Shoe Deep Test' => ['code' => 'DPT', 'groups' => [
                    'dry'   => self::group([5000, 10000, 15000, 20000, 25000, 30000, 40000, 50000], 2),
                    'wet'   => self::group([5000, 10000, 15000, 20000, 25000, 30000, 40000, 50000], 2),
                ]],
                'Dynamic Water Immersion Flexing' => ['code' => 'DPT', 'groups' => [
                    'dry'   => self::group([5000, 10000, 15000, 20000, 25000, 35000, 50000, 75000, 100000], 2),
                    'wet'   => self::group([5000, 10000, 15000, 20000, 25000, 35000, 50000, 75000, 100000], 2),
                ]],
                'Static Submersion (Hours)' => ['code' => 'DPT', 'groups' => [
                    'dry'   => self::group([2, 4, 6, 8, 12, 24, 48, 72], 2),
                    'wet'   => self::group([2, 4, 6, 8, 12, 24, 48, 72], 2),
                ]],
            ],

            'bally-flex' => [
                'Standard Flexing' => ['code' => 'LEL', 'groups' => [
                    'dry'   => self::group($ballyDry, 4),
                    'wet'   => self::group($ballyWet, 4),
                    'hydro' => self::group($ballyHydro, 4),
                ]],
                'Fabric/Leather' => ['code' => 'LEU', 'groups' => [
                    'dry'   => self::group($ballyDry, 4),
                    'wet'   => self::group($ballyWet, 4),
                    'hydro' => self::group($ballyHydro, 4),
                ]],
            ],

            'martindale-abrasion' => [
                'Polymer' => ['code' => 'POLYMER', 'groups' => [
                    'dry' => self::group($abr1000),
                    'wet' => self::group([50, 100, 150, 200, 250]),
                ]],
                'Fabric Lining' => ['code' => 'FBL', 'groups' => [
                    'dry' => self::group($lining),
                    'wet' => self::group($liningW),
                ]],
                'Logo' => ['code' => 'LOGO', 'groups' => [
                    'dry' => self::group($abr1000),
                    'wet' => self::group([50, 100, 150, 200, 250]),
                ]],
                'Synthetic Lining' => ['code' => 'SYNTHETICLINING', 'groups' => [
                    'dry' => self::group($lining),
                    'wet' => self::group($liningW),
                ]],
                'Printed Fabric' => ['code' => 'FBU', 'groups' => [
                    'dry' => self::group($abr1000),
                    'wet' => self::group([50, 100, 150, 200, 300]),
                ]],
                'Leather Lining' => ['code' => 'LEATHERLINING', 'groups' => [
                    'dry' => self::group([2000, 4000, 6000, 8000]),
                ]],
            ],

            'lace-abrasion' => [
                'Standard'   => ['code' => 'LC', 'groups' => ['dry' => self::group([2500, 5000], 1)]],
                'Heavy Duty' => ['code' => 'LC', 'groups' => ['dry' => self::group([2500, 5000], 1)]],
            ],

            'taber-abrasion' => [
                'Coated Leather'    => ['code' => 'LEU', 'groups' => ['dry' => self::group([50, 100, 150])]],
                'Synthetic Upper'   => ['code' => 'SYU', 'groups' => ['dry' => self::group([25, 50, 250, 500])]],
                'Full Grain Leather' => ['code' => 'FGL', 'groups' => ['dry' => self::group([10, 20, 30, 40, 50])]],
            ],

            'ross-flex' => [
                '100K Requirement' => ['code' => 'RB', 'groups' => ['dry' => self::group([10000, 25000, 50000, 100000])]],
                '25K Requirement'  => ['code' => 'RB', 'groups' => ['dry' => self::group([5000, 10000, 15000, 20000, 25000])]],
            ],

            'crocking' => [
                'CF: Fabric/Leather' => ['code' => 'LEU', 'groups' => [
                    'dry' => self::group([10]),
                    'wet' => self::group([10]),
                ]],
                'CF: Polymer' => ['code' => 'CRK', 'groups' => [
                    'dry' => self::group([10]),
                    'wet' => self::group([10]),
                ]],
                'Abrasion: Ink/Printed' => ['code' => 'FBU', 'groups' => [
                    'dry' => self::group([50]),
                    'wet' => self::group([20]),
                ]],
                'Abrasion: Polymer' => ['code' => 'CRK', 'groups' => [
                    'dry' => self::group([50, 100, 150, 200, 250, 300]),
                    'wet' => self::group([50, 100, 150, 200, 250, 300]),
                ]],
            ],
        ];
    }
}
