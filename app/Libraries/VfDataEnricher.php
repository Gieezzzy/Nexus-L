<?php

namespace App\Libraries;

use Config\Database;

/**
 * VfDataEnricher
 * 
 * Injects detailed laboratory data specifications into tbl_bot_knowledge for all VF test methods:
 * - ⚙️ Test Parameters & Machine Settings (pull speed, temperature, cycles, load, duration)
 * - 📐 Specimen Dimensions & Cutting Tools (die size, gauge length, thickness, quantity)
 * - 🎯 Standard Acceptance Criteria & Pass/Fail Thresholds
 * - 🧮 Mathematical Formulas & Calculations
 * - 📋 Required Report Entry Fields (DTR)
 */
class VfDataEnricher
{
    public static function run(): array
    {
        $db = Database::connect();
        $now = date('Y-m-d H:i:s');
        $updated = 0;

        $specs = self::getDataSpecifications();

        foreach ($specs as $code => $data) {
            $row = $db->table('tbl_bot_knowledge')->like('title', $code)->get()->getRowArray();
            if (! $row) {
                continue;
            }

            // Build rich technical markdown body
            $body = "### {$code}: {$data['name']}\n\n"
                  . "**Category:** {$data['category']}  \n"
                  . "**Governing Standards:** {$data['standards']}  \n\n"
                  . "#### ⚙️ Test Parameters & Machine Settings\n"
                  . "| Parameter | Value / Setting |\n"
                  . "| :--- | :--- |\n";
            foreach ($data['parameters'] as $param => $val) {
                $body .= "| {$param} | **{$val}** |\n";
            }

            $body .= "\n#### 📐 Specimen Dimensions & Preparation\n"
                  . "| Specimen Property | Specification |\n"
                  . "| :--- | :--- |\n";
            foreach ($data['specimen'] as $sp => $val) {
                $body .= "| {$sp} | {$val} |\n";
            }

            $body .= "\n#### 🎯 Standard Acceptance Criteria & Requirements\n"
                  . "| Evaluation Item | Passing Threshold / Requirement |\n"
                  . "| :--- | :--- |\n";
            foreach ($data['criteria'] as $item => $val) {
                $body .= "| {$item} | **{$val}** |\n";
            }

            if (! empty($data['formula'])) {
                $body .= "\n#### 🧮 Calculation Formula\n"
                      . "`{$data['formula']}`\n\n"
                      . ($data['formula_desc'] ?? '') . "\n";
            }

            $body .= "\n#### 📋 Standard Operating Procedure\n"
                  . "{$data['procedure']}\n\n"
                  . "#### 🧪 Required Report Fields in DTR\n"
                  . implode(', ', $data['report_fields']) . "\n\n"
                  . "Click the action buttons below to open the complete official PDF standard or view the illustrated apparatus setup.";

            // Steps
            $steps = [
                "Method Classification: {$data['category']} ({$code})",
                "Standard Reference: {$data['standards']}",
                "Machine Settings: " . self::summarizeKeyValue($data['parameters']),
                "Specimen Setup: " . self::summarizeKeyValue($data['specimen']),
                "Passing Criteria: " . self::summarizeKeyValue($data['criteria']),
                "Conditioning: Store specimens at 23±2°C and 50±5% RH for minimum 24 hours prior to testing (VF-H01).",
            ];

            // Keywords enrichment
            $extraKeywords = implode(', ', array_merge(
                array_keys($data['parameters']),
                array_values($data['parameters']),
                array_keys($data['criteria']),
                array_values($data['criteria']),
                $data['report_fields'],
                $data['extra_keywords'] ?? []
            ));

            $combinedList = array_unique(array_filter(array_map('trim', explode(',', $extraKeywords . ', ' . $row['keywords']))));
            $combined = '';
            foreach ($combinedList as $kw) {
                if ($combined === '') {
                    $combined = $kw;
                } elseif (mb_strlen($combined . ', ' . $kw) <= 590) {
                    $combined .= ', ' . $kw;
                } else {
                    break;
                }
            }
            $cleanKeywords = mb_strtolower($combined);

            $updateData = [
                'body' => $body,
                'steps_json' => json_encode($steps, JSON_UNESCAPED_UNICODE),
                'keywords' => $cleanKeywords,
                'updated_at' => $now,
            ];

            $db->table('tbl_bot_knowledge')->where('id', $row['id'])->update($updateData);
            $updated++;
        }

        return ['updated' => $updated];
    }

    private static function summarizeKeyValue(array $arr): string
    {
        $parts = [];
        foreach ($arr as $k => $v) {
            $parts[] = "{$k}: {$v}";
        }
        return implode('; ', array_slice($parts, 0, 3));
    }

    public static function getDataSpecifications(): array
    {
        return [
            'VF-A01' => [
                'name' => 'Hardness Test (Durometer Shore A, C, D)',
                'category' => 'Physical & Dimensional Properties',
                'standards' => 'ASTM D2240, ISO 868, SATRA TM205',
                'parameters' => [
                    'Apparatus' => 'Shore A, Shore C, or Shore D calibrated durometer with operating stand',
                    'Dwell Time' => 'Instantaneous (within 1.0 second) or 15 seconds for creep reading',
                    'Contact Distance' => 'Points must be at least 6.0 mm from specimen edge, 12.0 mm apart',
                    'Number of Readings' => 'Minimum 5 readings taken across different sample locations',
                ],
                'specimen' => [
                    'Minimum Thickness' => '6.0 mm (plies of 2.0 mm flat sheets may be layered up to 3 plies)',
                    'Surface' => 'Completely flat and smooth, zero surface texture or embossment',
                    'Conditioning' => 'Minimum 24 hours at 23±2°C and 50±5% RH (VF-H01)',
                ],
                'criteria' => [
                    'Vans Vulcanized Waffle Rubber' => '62 ± 3 Shore A',
                    'Vulcanized Foxing Tape' => '58 ± 3 Shore A',
                    'EVA Molded Midsole Foam' => '45 - 55 Shore C',
                    'Toe Bumper / Heel Counter' => '65 ± 3 Shore A',
                ],
                'formula' => 'Average Hardness = (Reading_1 + Reading_2 + Reading_3 + Reading_4 + Reading_5) / 5',
                'formula_desc' => 'Average of 5 test points. Values outside ±3 Shore units from nominal fail inspection.',
                'procedure' => 'Place smooth 6mm specimen on solid hard base. Lower durometer presser foot firmly without shock. Record needle reading immediately within 1 second for standard hardness.',
                'report_fields' => ['Durometer Type (A/C/D)', 'Specimen Thickness (mm)', '5 Individual Readings', 'Mean Hardness', 'Pass/Fail Status'],
                'extra_keywords' => ['shore a durometer', 'shore c foam', 'rubber hardness reading', 'durometer reading time', 'hardness test tolerance', 'waffle hardness standard']
            ],

            'VF-A03' => [
                'name' => 'Thickness Measurement',
                'category' => 'Physical & Dimensional Properties',
                'standards' => 'ASTM D1813, ISO 2589, SATRA TM1',
                'parameters' => [
                    'Apparatus' => 'Calibrated dead-weight thickness dial gauge with solid anvil base',
                    'Presser Foot Diameter' => '10.0 ± 0.1 mm circular flat foot',
                    'Applied Pressure' => '49.1 ± 2.5 kPa (500 grams dead weight load)',
                    'Reading Time' => '5.0 seconds after presser foot touches sample surface',
                ],
                'specimen' => [
                    'Quantity' => '3 representative points along center and outer edges',
                    'Surface' => 'Clean, free of dust, wrinkles, or uneven folds',
                    'Conditioning' => '24 hours at standard laboratory atmosphere',
                ],
                'criteria' => [
                    'Outsole Rubber Base Thickness' => '1.80 - 2.20 mm (Nominal 2.0 mm ± 0.2 mm)',
                    'Foxing Band Outer Layer' => '1.00 - 1.20 mm',
                    'Upper Canvas / Suede Leather' => 'Per Bill of Materials (BOM) ± 0.15 mm',
                ],
                'formula' => 'Mean Thickness = (T1 + T2 + T3) / 3 mm',
                'formula_desc' => 'Measure at 3 distinct points. Record mean to 0.01 mm precision.',
                'procedure' => 'Raise presser foot. Place sample on lower anvil. Release foot gently onto material surface. Read dial after 5 seconds dwell time.',
                'report_fields' => ['Specimen Location', 'Dial Gauge ID', '3 Thickness Readings (mm)', 'Average Thickness', 'Specification Tolerance'],
                'extra_keywords' => ['thickness gauge', 'dead weight foot', 'outsole thickness limit', 'foxing thickness', 'dial gauge reading time']
            ],

            'VF-A04' => [
                'name' => 'Specific Gravity / Density (Archimedes Displacement)',
                'category' => 'Physical & Dimensional Properties',
                'standards' => 'ASTM D792, ISO 1183, SATRA TM134',
                'parameters' => [
                    'Apparatus' => 'Precision electronic analytical balance with hydrostatic immersion rig',
                    'Immersion Liquid' => 'Distilled water with 0.1% wetting agent (surfactant) to prevent air bubbles',
                    'Test Temperature' => '23.0 ± 1.0°C (Water density factor: 0.9975 g/cm³)',
                    'Measurement Precision' => 'Analytical balance readable to 0.0001 g (0.1 mg)',
                ],
                'specimen' => [
                    'Sample Mass' => 'Minimum 2.5 grams (typically 3 to 5 grams)',
                    'Shape' => 'Solid lump or rectangular piece free of voids and porous skin',
                    'Cleanliness' => 'Wiped clean with isopropyl alcohol to remove mold release agents',
                ],
                'criteria' => [
                    'Vulcanized Soling Rubber' => '1.15 - 1.25 g/cm³ (Nominal 1.18 g/cm³)',
                    'Cellular EVA Midsole' => '0.20 - 0.35 g/cm³',
                    'Solid Foxing Tape' => '1.20 - 1.30 g/cm³',
                ],
                'formula' => 'Specific Gravity (SG) = Mass_in_Air / (Mass_in_Air - Mass_in_Water)',
                'formula_desc' => 'Density rho = SG * 0.9975 g/cm³. Essential parameter used in DIN Abrasion volume loss calculations.',
                'procedure' => 'Weigh clean specimen in air (M1). Suspend specimen in distilled water bath completely submerged without touching container walls (M2). Calculate specific gravity.',
                'report_fields' => ['Mass in Air (g)', 'Mass in Water (g)', 'Calculated Specific Gravity', 'Density (g/cm³)', 'Compound ID'],
                'extra_keywords' => ['specific gravity formula', 'archimedes density', 'rubber specific gravity', 'water displacement test', 'density for din abrasion']
            ],

            'VF-B01' => [
                'name' => 'Tensile Strength and Elongation at Break',
                'category' => 'Strength & Mechanical Properties',
                'standards' => 'ASTM D412 (Rubber), ASTM D5034 (Textiles), ISO 37, SATRA TM137',
                'parameters' => [
                    'Crosshead Pull Speed (Rubber)' => '500 ± 50 mm/min (20 inches/min)',
                    'Crosshead Pull Speed (Textiles/Synthetic)' => '100 - 300 mm/min',
                    'Benchmark Gauge Length' => '25.0 ± 0.5 mm (or 33.0 mm for Type IV die)',
                    'Grip Pressure' => 'Pneumatic serrated jaws at 6.0 bar air pressure to prevent slippage',
                ],
                'specimen' => [
                    'Cutter Die' => 'ASTM D412 Die C or Die IV dumbbell cutter',
                    'Specimen Thickness' => '2.0 ± 0.2 mm (ground or buffed flat if embossed)',
                    'Quantity' => '3 specimens parallel to calendar grain + 3 specimens perpendicular',
                    'Conditioning' => 'Minimum 24 hours at 23±2°C and 50±5% RH',
                ],
                'criteria' => [
                    'Vulcanized Outsole Rubber Tensile' => '>= 120 kg/cm² (>= 11.8 MPa)',
                    'Vulcanized Outsole Elongation' => '>= 450% ultimate elongation at break',
                    'Foxing Tape Rubber Tensile' => '>= 90 kg/cm² (>= 8.8 MPa)',
                    'Foxing Tape Elongation' => '>= 500% elongation at break',
                    'EVA Foam Tensile Strength' => '>= 25 kg/cm² (>= 2.45 MPa)',
                ],
                'formula' => 'Tensile (kg/cm²) = Max_Force_kg / (Width_cm * Thickness_cm) | Elongation (%) = ((L_break - L_0) / L_0) * 100',
                'formula_desc' => 'Where L_0 is original 25mm bench marks and L_break is gauge length at rupture.',
                'procedure' => 'Die-cut 6 dumbbell specimens. Measure width and thickness. Mark 25mm gauge lines with stamp. Clamp into UTM jaws. Pull at 500 mm/min until rupture. Record peak force and elongation.',
                'report_fields' => ['Thickness (mm)', 'Width (mm)', 'Peak Load (N / kgf)', 'Tensile Strength (kg/cm²)', 'Elongation (%)', 'Break Location'],
                'extra_keywords' => ['pull speed', 'rubber tensile', 'tensile pull speed', 'pull rate rubber', '500 mm/min', 'tensile strength requirement', 'elongation limit', 'die c dumbbell', 'gauge length 25mm']
            ],

            'VF-B04' => [
                'name' => 'Hardware Attachment & Eyelet Pull-Out Strength',
                'category' => 'Strength & Mechanical Properties',
                'standards' => 'SATRA TM181, SATRA TM150',
                'parameters' => [
                    'Pull Speed' => '100 ± 10 mm/min crosshead pull rate',
                    'Fixtures' => 'Lower clamping base with slotted insert plate; Upper hook tensile grip',
                    'Pull Angle' => '90° vertical pull perpendicular to shoe upper face',
                ],
                'specimen' => [
                    'Sample Size' => 'Complete shoe upper or 50mm x 50mm section with installed eyelet/rivet',
                    'Quantity' => 'Minimum 3 installed hardware components per style',
                ],
                'criteria' => [
                    'Standard Eyelet Pull-Out' => '>= 200 N (>= 20.4 kgf) without detachment',
                    'D-Ring / Speed Hook Attachment' => '>= 300 N (>= 30.6 kgf) without breaking',
                    'Plastic Lace Loop' => '>= 250 N (>= 25.5 kgf)',
                ],
                'formula' => 'Attachment Strength = Peak Tensile Load (N)',
                'formula_desc' => 'Record failure mode: eyelet pull-out, metal deformation, or upper material tear.',
                'procedure' => 'Mount upper section into lower clamping fixture. Hook tensile link through eyelet. Pull upward at 100 mm/min until hardware pulls out or ruptures.',
                'report_fields' => ['Hardware Type', 'Peak Pull-Out Force (N)', 'Failure Mode', 'Pass/Fail Status'],
                'extra_keywords' => ['eyelet pull speed', 'eyelet passing strength', 'd-ring pull force', 'hardware pull rate 100mm']
            ],

            'VF-B05' => [
                'name' => 'Die C Tear Strength (Tear Initiation)',
                'category' => 'Strength & Mechanical Properties',
                'standards' => 'ASTM D624 Die C, ISO 34-1 Method B',
                'parameters' => [
                    'Crosshead Pull Speed' => '500 ± 50 mm/min (20 inches/min)',
                    'Razor Nick Depth' => '0.50 ± 0.05 mm nick cut at 90° interior apex using micrometer razor fixture',
                    'Apparatus' => 'Universal Tensile Machine with self-tightening eccentric roller grips',
                ],
                'specimen' => [
                    'Cutter Die' => 'ASTM D624 Die C (90° internal angle specimen)',
                    'Specimen Thickness' => '2.0 ± 0.2 mm flat sheet',
                    'Quantity' => '3 with grain + 3 across grain (total 6 specimens)',
                    'Conditioning' => '24 hours at 23±2°C and 50±5% RH',
                ],
                'criteria' => [
                    'Vulcanized Outsole Rubber' => '>= 30.0 kg/cm (>= 29.4 kN/m)',
                    'Foxing Tape Rubber' => '>= 25.0 kg/cm (>= 24.5 kN/m)',
                    'EVA Midsole Foam' => '>= 15.0 kg/cm',
                ],
                'formula' => 'Die C Tear Strength (kg/cm) = Peak_Force_kg / Specimen_Thickness_cm',
                'formula_desc' => 'Force divided by thickness. In SI units: N / mm (kN/m).',
                'procedure' => 'Cut 6 Die C specimens. Make 0.5mm nick at inside 90° corner. Measure thickness at nick. Clamp in UTM jaws and pull at 500 mm/min. Record peak force.',
                'report_fields' => ['Specimen Direction (Parallel/Perpendicular)', 'Thickness (mm)', 'Peak Force (kgf / N)', 'Tear Strength (kg/cm)', 'Mean Tear Strength'],
                'extra_keywords' => ['die c pull speed', 'die c nick 0.5mm', 'die c tear requirement', 'tear strength formula', 'astm d624 parameters']
            ],

            'VF-B06' => [
                'name' => 'Split Tear Strength of Cellular Materials',
                'category' => 'Strength & Mechanical Properties',
                'standards' => 'ASTM D3574 Test F, SATRA TM65',
                'parameters' => [
                    'Crosshead Pull Speed' => '50 ± 5 mm/min',
                    'Initial Center Slit' => '25.0 mm deep slit cut horizontally in center plane',
                    'Apparatus' => 'Tensile machine with flat pneumatic jaw grips',
                ],
                'specimen' => [
                    'Dimensions' => '25 mm width x 150 mm length',
                    'Thickness' => '10.0 ± 1.0 mm uniform thickness',
                    'Quantity' => 'Minimum 3 specimens from molded midsole foam',
                ],
                'criteria' => [
                    'EVA Molded Midsole' => '>= 2.5 kg/cm (>= 2.45 N/mm)',
                    'PU Foam Footbed' => '>= 2.0 kg/cm',
                ],
                'formula' => 'Split Tear (kg/cm) = Average_Tear_Force_kg / Specimen_Width_cm',
                'formula_desc' => 'Average of lowest and highest plateau peaks across 50mm tearing length.',
                'procedure' => 'Cut 25mm slit in 10mm thick foam. Mount split legs in opposing tensile jaws. Pull at 50 mm/min. Record median tear force across 50mm travel.',
                'report_fields' => ['Specimen Width (cm)', 'Thickness (mm)', 'Average Tear Force (kgf)', 'Split Tear (kg/cm)', 'Pass/Fail'],
                'extra_keywords' => ['split tear speed 50mm', 'split tear passing threshold', 'eva split tear formula', 'satra tm65 parameters']
            ],

            'VF-B07' => [
                'name' => 'Trouser Tear Strength',
                'category' => 'Strength & Mechanical Properties',
                'standards' => 'ASTM D2261, ISO 13937-2, SATRA TM30',
                'parameters' => [
                    'Crosshead Pull Speed' => '100 ± 10 mm/min',
                    'Grip Separation' => '50.0 mm initial clamp distance',
                    'Evaluation Distance' => 'Average 5 highest load peaks across 50mm tear progression',
                ],
                'specimen' => [
                    'Dimensions' => '50 mm width x 150 mm length with 75 mm center longitudinal cut',
                    'Quantity' => '5 specimens along warp/length + 5 along weft/width',
                ],
                'criteria' => [
                    'Upper Leather (Suede / Nubuck)' => '>= 3.0 kgf (>= 29.4 N)',
                    'Synthetic Coated Fabric Upper' => '>= 2.5 kgf (>= 24.5 N)',
                    'Collar / Tongue Textile Lining' => '>= 1.5 kgf (>= 14.7 N)',
                ],
                'formula' => 'Trouser Tear Strength = Mean of 5 Highest Load Peaks (N / kgf)',
                'formula_desc' => 'Calculated from load-displacement plateau curve.',
                'procedure' => 'Clamp one trouser leg in upper grip and opposing leg in lower grip. Pull at 100 mm/min. Discard first 20mm and record 5 peaks over subsequent 50mm.',
                'report_fields' => ['Material Type', 'Direction (Warp/Weft)', 'Peak Values', 'Mean Tear Force (kgf)', 'Pass/Fail'],
                'extra_keywords' => ['trouser tear speed', 'leather tear pass mark', 'trouser specimen 50x150', 'astm d2261 pull rate']
            ],

            'VF-B10' => [
                'name' => 'Static Compression Set (Foam Cushioning Recovery)',
                'category' => 'Strength & Mechanical Properties',
                'standards' => 'ASTM D395 Method B, SATRA TM64',
                'parameters' => [
                    'Oven Temperature' => '50.0 ± 1.0°C (122°F)',
                    'Compression Duration' => '6.0 hours (standard VF DTP-Lab-101 protocol) or 22.0 hours (ASTM)',
                    'Deflection / Compression Ratio' => '25% constant strain deflection using spacer bars (9.5mm spacer for 12.5mm sample)',
                    'Cooling Recovery Period' => '30 minutes at room temperature (23°C) after release before final thickness reading',
                ],
                'specimen' => [
                    'Dimensions' => 'Cylindrical disc 29.0 ± 0.5 mm diameter, thickness 12.5 ± 0.5 mm',
                    'Quantity' => 'Minimum 3 specimens cut from EVA midsole or PU insole',
                ],
                'criteria' => [
                    'EVA Molded Midsole Foam' => '<= 55% permanent compression set (lower is superior)',
                    'High Performance PU Insole' => '<= 15% permanent compression set',
                ],
                'formula' => 'Compression Set (%) = ((T_original - T_final) / (T_original - T_spacer)) * 100',
                'formula_desc' => 'Where T_original is initial thickness, T_spacer is spacer bar height (9.5mm), and T_final is thickness after 30 min recovery.',
                'procedure' => 'Measure initial thickness (T0). Compress specimen 25% between steel plates with spacer bars. Bake in 50°C oven for 6 hours. Remove, release immediately, rest for 30 minutes, measure final thickness (Tf).',
                'report_fields' => ['T_initial (mm)', 'T_spacer (mm)', 'T_final (mm)', 'Compression Set (%)', 'Pass/Fail Status'],
                'extra_keywords' => ['compression set temperature 50c', '6 hours compression set', 'compression set formula', 'eva compression set requirement', '55 percent limit']
            ],

            'VF-B15' => [
                'name' => 'James Slip Resistance (Static Coefficient of Friction)',
                'category' => 'Strength & Mechanical Properties',
                'standards' => 'ASTM F489, SATRA TM144',
                'parameters' => [
                    'Apparatus' => 'James Slip Machine with articulated strut and chart recorder',
                    'Vertical Load' => '80.0 lbs (355 N / 36.3 kgf)',
                    'Test Surfaces' => 'Standard dry quarry tile and wet tile with distilled water',
                ],
                'specimen' => [
                    'Size' => '76 mm x 76 mm (3 inch x 3 inch) flat soling square',
                    'Quantity' => 'Minimum 3 specimens',
                ],
                'criteria' => [
                    'Dry Quarry Tile Friction' => '>= 0.50 Static Coefficient of Friction (SCOF)',
                    'Wet Tile Friction' => '>= 0.40 SCOF',
                ],
                'formula' => 'Coefficient of Friction (mu) = Tangent of strut angle at slip',
                'formula_desc' => 'Direct reading from calibrated James Machine chart recorder.',
                'procedure' => 'Position specimen on tile table under vertical strut. Drive table horizontally until strut slips. Record friction tangent value.',
                'report_fields' => ['Test Surface (Dry/Wet)', 'Strut Angle', 'Friction Coefficient', 'Pass/Fail'],
                'extra_keywords' => ['james slip load', 'james machine 80 lbs', 'static slip requirement 0.50', 'astm f489 friction']
            ],

            'VF-B16' => [
                'name' => 'Vertical Ball Rebound Resiliency',
                'category' => 'Strength & Mechanical Properties',
                'standards' => 'ASTM D2632',
                'parameters' => [
                    'Drop Height' => '460.0 ± 1.0 mm (18.0 inches)',
                    'Steel Ball Spec' => '5/8 inch diameter (16.3 mm), mass 16.3 grams',
                    'Release Mechanism' => 'Electromagnetic release pin in clear calibrated drop tube',
                ],
                'specimen' => [
                    'Dimensions' => 'Flat circular or square block, minimum thickness 12.5 mm',
                    'Quantity' => 'Minimum 3 specimens; drop ball 3 times per specimen and record last 3 bounces',
                ],
                'criteria' => [
                    'High Rebound EVA Cushioning' => '>= 50% rebound resiliency',
                    'Standard Vulcanized Insole' => '40 - 50% rebound resiliency',
                ],
                'formula' => 'Rebound (%) = (Rebound_Bounce_Height_mm / 460 mm) * 100',
                'formula_desc' => 'Percentage of initial drop height.',
                'procedure' => 'Level instrument. Place 12.5mm specimen below tube. Release steel ball from 460mm height. Observe highest bounce apex through clear tube. Average 3 readings.',
                'report_fields' => ['Specimen Thickness', 'Drop Height (460mm)', '3 Bounce Heights (mm)', 'Rebound Resiliency (%)', 'Pass/Fail'],
                'extra_keywords' => ['ball rebound drop height 460mm', 'steel ball 5/8 inch', 'rebound percentage formula', 'eva energy return limit']
            ],

            'VF-B23' => [
                'name' => 'Knot Slip Resistance (Shoelace Untying Test)',
                'category' => 'Strength & Mechanical Properties',
                'standards' => 'SATRA TM153',
                'parameters' => [
                    'Knot Type' => 'Standard bow knot tied under 10.0 N calibrated pre-tension',
                    'Pulling Force' => 'Cyclic tensile pulling load applied to lace loop ends',
                    'Cycle Frequency' => '1.0 Hz (60 cycles per minute)',
                ],
                'specimen' => [
                    'Shoelace Sample' => 'Complete finished shoelace with factory tips',
                    'Quantity' => '3 pairs of laces conditioned at 23°C 50% RH',
                ],
                'criteria' => [
                    'Standard Requirement' => '>= 500 cycles without knot loosening or untying',
                    'Performance Requirement' => '>= 1,000 cycles without knot slip',
                ],
                'formula' => 'Knot Durability = Total cycles to complete knot separation',
                'formula_desc' => 'Test automatically halts via optical sensor when knot unties.',
                'procedure' => 'Tie standard bow knot around instrument mandrel under 10N tension. Mount lace loop ends in reciprocating pneumatic jaws. Start cyclic pulling. Record cycles to failure.',
                'report_fields' => ['Lace Construction (Flat/Round)', 'Lace Length (cm)', 'Cycles Completed', 'Failure Mode (Slip/Broken)', 'Pass/Fail'],
                'extra_keywords' => ['shoelace knot cycles 500', 'bow knot slip rate', 'satra tm153 parameter', 'lace untying standard']
            ],

            'VF-B24' => [
                'name' => 'Lace Tip (Aglet) Retention Strength',
                'category' => 'Strength & Mechanical Properties',
                'standards' => 'SATRA TM154',
                'parameters' => [
                    'Crosshead Pull Speed' => '100 ± 10 mm/min',
                    'Aglet Clamping Plate' => 'Steel retaining plate with hole diameter 0.2mm larger than lace body',
                    'Lace Clamping Grip' => 'Upper serrated pneumatic tensile jaw',
                ],
                'specimen' => [
                    'Sample' => 'Cut lace ends with intact plastic or metal aglets (100mm length)',
                    'Quantity' => 'Minimum 6 aglets tested per production batch',
                ],
                'criteria' => [
                    'Plastic Film / Molded Aglet' => '>= 60 N (>= 6.1 kgf) pull-off retention force',
                    'Metal Aglet' => '>= 80 N (>= 8.1 kgf)',
                ],
                'formula' => 'Retention Strength = Peak Force (N) to pull tip off lace',
                'formula_desc' => 'Measure force at moment of tip detachment or sleeve rupture.',
                'procedure' => 'Thread lace body through orifice plate until aglet seats against underside. Clamp lace in upper UTM grip. Pull at 100 mm/min until aglet slips off or fractures.',
                'report_fields' => ['Aglet Material', 'Peak Retention Force (N)', 'Detachment Mode', 'Pass/Fail'],
                'extra_keywords' => ['aglet pull speed 100mm', 'aglet passing force 60n', 'plastic tip retention limit', 'satra tm154 standard']
            ],

            'VF-C01' => [
                'name' => 'DIN Rotary Drum Abrasion Resistance',
                'category' => 'Abrasion Resistance',
                'standards' => 'ISO 4649 Method A, SATRA TM174, DIN 53516',
                'parameters' => [
                    'Rotating Drum Diameter' => '150.0 ± 0.2 mm, length 460 mm',
                    'Drum Rotation Speed' => '40 ± 1 RPM',
                    'Abrasive Paper Spec' => 'P60 grade aluminum oxide cloth (abrasive mass loss: 180 - 220 mg on reference standard rubber)',
                    'Specimen Rotation' => '0.32 RPM (rotates during traverse)',
                    'Specimen Contact Load' => '10.0 ± 0.2 N downward contact force',
                    'Total Abrasion Run Distance' => '40.0 meters (84 drum revolutions)',
                ],
                'specimen' => [
                    'Dimensions' => 'Cylindrical plug 16.0 ± 0.2 mm diameter, minimum thickness 6.0 mm',
                    'Quantity' => '3 specimens cut from outsole bottom flat surface',
                    'Pre-Grind' => 'Pre-grind for 20 meters if sample surface is molded or embossed',
                ],
                'criteria' => [
                    'Vans Vulcanized Waffle Outsole Rubber' => '<= 150 mm³ volume loss (lower volume loss is superior)',
                    'Foxing Band Rubber' => '<= 200 mm³ volume loss',
                    'Cellular EVA Midsole' => '<= 200 mm³ volume loss',
                ],
                'formula' => 'Volume Loss (mm³) = ((M1 - M2) * 200 mg) / (Density_g_cm3 * Standard_Rubber_Loss_mg)',
                'formula_desc' => 'Where M1 is initial mass (mg), M2 is final mass (mg), Density is Archimedes specific gravity from VF-A04, and Standard Rubber Loss is calibrated reference loss (nominal 200 mg).',
                'procedure' => 'Determine specimen density via VF-A04. Weigh specimen (M1). Clamp in holder with 2.0mm protrusion. Run 40-meter drum cycle under 10N load. Brush off debris, weigh (M2). Calculate mm3 volume loss.',
                'report_fields' => ['Specific Gravity (g/cm³)', 'Initial Mass (mg)', 'Final Mass (mg)', 'Mass Loss (mg)', 'Standard Rubber Loss (mg)', 'Volume Loss (mm³)', 'Pass/Fail'],
                'extra_keywords' => ['din abrasion speed 40 rpm', 'din abrasion distance 40 meters', 'p60 sandpaper', 'din volume loss formula', '150 mm3 standard limit', '10n load abrasion']
            ],

            'VF-C04' => [
                'name' => 'Martindale Abrasion Resistance',
                'category' => 'Abrasion Resistance',
                'standards' => 'ISO 12947-2, SATRA TM180',
                'parameters' => [
                    'Contact Pressure' => '12.0 ± 0.3 kPa (795 g load) or 9.0 kPa for delicate fabrics',
                    'Lissajous Figure' => '16 cycles per completed Lissajous figure pattern',
                    'Abradant Fabric' => 'Standard worsted wool abradant cloth on woven wool felt pad',
                ],
                'specimen' => [
                    'Dimensions' => 'Circular discs 38.0 mm diameter mounted on polyurethane foam backing',
                    'Quantity' => '4 specimens for Dry testing + 4 specimens for Wet testing',
                ],
                'criteria' => [
                    'Dry Collar / Heel Lining Fabric' => '>= 25,600 rubs without yarn breakdown or hole formation',
                    'Wet Collar Lining Fabric' => '>= 12,800 rubs without breakdown',
                    'Upper Canvas / Mesh Fabric' => '>= 51,200 rubs without fiber rupture',
                ],
                'formula' => 'Abrasion Durability = Total rubs completed without hole or yarn severance',
                'formula_desc' => 'Inspect at intervals of 1,600, 3,200, 6,400, 12,800, 25,600, and 51,200 rubs.',
                'procedure' => 'Mount circular specimen in holder over foam backing. Position on worsted wool abradant table under 12 kPa load. Run specified cycles. Remove and inspect under magnifying lamp for thread severance.',
                'report_fields' => ['Condition (Dry/Wet)', 'Contact Load (12 kPa)', 'Rubs Reached', 'Breakdown Observed (Yes/No)', 'Pass/Fail'],
                'extra_keywords' => ['martindale pressure 12 kpa', 'martindale dry 25600', 'martindale wet 12800', 'lining abrasion requirement']
            ],

            'VF-C05' => [
                'name' => 'Taber Abrasion Resistance',
                'category' => 'Abrasion Resistance',
                'standards' => 'ASTM D3884, ISO 5470-1',
                'parameters' => [
                    'Abrasive Wheels' => 'Calibrated CS-10 or H-18 abrasive wheels',
                    'Wheel Load' => '1000 grams per wheel arm (total 2000 g load)',
                    'Vacuum Nozzle' => 'Continuous suction at 100% capacity, 1.5mm nozzle clearance',
                    'Cycle Count' => '1,000 revolutions',
                ],
                'specimen' => [
                    'Dimensions' => 'Square flat plate 100 mm x 100 mm with 6.3 mm center arbor hole',
                    'Quantity' => '3 specimens conditioned for 24 hours',
                ],
                'criteria' => [
                    'Synthetic Upper Coating' => '<= 100 mg mass loss after 1,000 cycles',
                    'Sockliner Top Cover' => '<= 150 mg mass loss after 1,000 cycles',
                ],
                'formula' => 'Mass Loss (mg) = M_initial - M_final',
                'formula_desc' => 'Weighed to 0.1 mg precision after 1,000 rotary cycles.',
                'procedure' => 'Mount specimen on turntable. Lower 1000g abrasive wheels onto surface. Activate vacuum nozzle. Run 1,000 revolutions. Clean surface and weigh final mass loss.',
                'report_fields' => ['Wheel Type (CS-10/H-18)', 'Load (1000g)', 'Initial Mass (mg)', 'Final Mass (mg)', 'Mass Loss (mg)', 'Pass/Fail'],
                'extra_keywords' => ['taber wheel load 1000g', 'taber 1000 cycles', 'cs-10 wheels', 'taber mass loss limit']
            ],

            'VF-D01' => [
                'name' => 'Bally Flexing Endurance (Upper Leather & Coated Fabrics)',
                'category' => 'Flexing & Fatigue Resistance',
                'standards' => 'ISO 5402, SATRA TM55',
                'parameters' => [
                    'Flexing Frequency' => '100 ± 5 cycles per minute',
                    'Flexing Angle' => '22.5° inverted V-crease fold angle',
                    'Clamping Mechanism' => 'Upper rotating clamp with lower stationary clamp',
                ],
                'specimen' => [
                    'Dimensions' => 'Rectangular swatch 70 mm x 45 mm',
                    'Quantity' => '4 specimens Dry + 4 specimens Wet (immersed in water for 15 min prior)',
                ],
                'criteria' => [
                    'Dry Upper Leather / Suede' => '>= 50,000 cycles without finish cracking, flaking, or grain rupture',
                    'Wet Upper Leather' => '>= 20,000 cycles without finish damage',
                    'Synthetic PU Upper' => '>= 100,000 cycles Dry without delamination',
                ],
                'formula' => 'Flex Endurance = Cycles achieved without visual surface crack',
                'formula_desc' => 'Evaluated under 10x optical magnification at 10k, 20k, 50k, and 100k cycles.',
                'procedure' => 'Fold 70x45mm specimen with finish side facing inwards to form V-crease. Clamp securely in Bally flexometer. Run at 100 cpm. Inspect at interval milestones under 10x magnifier.',
                'report_fields' => ['Condition (Dry/Wet)', 'Target Cycles', 'Observed Cycles', 'Crack Severity Grade', 'Pass/Fail'],
                'extra_keywords' => ['bally flex speed 100 cpm', 'bally flex dry 50000', 'bally flex wet 20000', 'bally 22.5 degree angle']
            ],

            'VF-D02' => [
                'name' => 'Ross Flexing Fatigue Resistance (Soling Materials)',
                'category' => 'Flexing & Fatigue Resistance',
                'standards' => 'ASTM D1052, SATRA TM60',
                'parameters' => [
                    'Flexing Frequency' => '100 ± 5 cycles per minute',
                    'Bending Angle' => '90° sharp bend over 10.0 mm diameter steel mandrel pin',
                    'Initial Chisel Cut' => '2.00 ± 0.05 mm center piercing cut using standard chisel punch',
                    'Test Temperatures' => 'Room Temperature (23 ± 2°C) and Cold Temperature (-10 ± 1°C)',
                ],
                'specimen' => [
                    'Dimensions' => '25.0 mm width x 150.0 mm length x 6.0 mm thickness',
                    'Quantity' => '3 specimens at room temp + 3 specimens at -10°C cold chamber',
                ],
                'criteria' => [
                    'Room Temperature (23°C) @ 150,000 Cycles' => 'Maximum <= 4.0 mm cut growth (total cut <= 6.0 mm)',
                    'Cold Temperature (-10°C) @ 50,000 Cycles' => 'Maximum <= 4.0 mm cut growth',
                    'Zero Complete Rupture' => 'Any complete sample snap or break is an immediate fail',
                ],
                'formula' => 'Cut Growth (mm) = Final_Cut_Length - Initial_2.0mm_Cut',
                'formula_desc' => 'Measured across piercing cut along flex groove.',
                'procedure' => 'Cut 25x150mm strip. Pierce 2.0mm center slit over flex groove. Insert into Ross flex machine with cut centered directly over 10mm mandrel. Flex at 100 cpm for 150k cycles (23°C) or 50k cycles (-10°C). Measure cut growth with optical caliper.',
                'report_fields' => ['Test Temp (23°C / -10°C)', 'Cycles Flexed', 'Initial Cut (2.0mm)', 'Final Cut (mm)', 'Cut Growth (mm)', 'Pass/Fail'],
                'extra_keywords' => ['ross flex cut growth limit', 'ross flex cold temp -10c', 'ross flex 150000 cycles', 'ross flex 50000 cycles', 'ross 90 degree bend', 'chisel cut 2mm']
            ],

            'VF-E02' => [
                'name' => 'Color Fastness to Crocking / Rubbing',
                'category' => 'Chemical, Aging & Environmental Fastness',
                'standards' => 'AATCC 8, ISO 105-X12, SATRA TM8',
                'parameters' => [
                    'Apparatus' => 'Standard AATCC Crockmeter with 16.0 mm diameter rubbing finger',
                    'Downward Vertical Force' => '9.0 ± 0.2 N (900 grams dead weight)',
                    'Stroke Length' => '104 ± 3 mm straight reciprocating stroke',
                    'Rubbing Rate' => '10 complete double strokes in 10 seconds (1 stroke/sec)',
                    'Wet Crock Moisture' => 'Standard white crocking cloth soaked with distilled water to 65 ± 5% pickup',
                ],
                'specimen' => [
                    'Dimensions' => '50 mm x 130 mm material swatch',
                    'Quantity' => '2 Dry crock tests + 2 Wet crock tests along length and width',
                ],
                'criteria' => [
                    'Dry Rubbing Staining' => '>= Grade 4.0 on AATCC 9-step Gray Scale for Staining',
                    'Wet Rubbing Staining' => '>= Grade 3.0 on Gray Scale for Staining',
                    'Dark / Highly Saturated Colors' => 'Wet Staining >= Grade 2.5 (by concession only)',
                ],
                'formula' => 'Fastness Grade = AATCC Gray Scale Staining Rating (1.0 to 5.0)',
                'formula_desc' => 'Evaluated in standard D65 light booth against standard gray scale masks.',
                'procedure' => 'Clamp sample on crockmeter bed. Mount dry standard square white cotton cloth on 16mm finger. Rub 10 double strokes under 9N load. Repeat with wet cotton cloth (65% moisture). Grade staining under D65 lighting.',
                'report_fields' => ['Colorway', 'Direction (Length/Width)', 'Dry Crock Grade', 'Wet Crock Grade', 'Pass/Fail'],
                'extra_keywords' => ['crockmeter load 9n', '10 double strokes', 'crock dry grade 4', 'crock wet grade 3', 'gray scale staining', 'aatcc 8 crocking']
            ],

            'VF-E03' => [
                'name' => 'UV Light Discoloration & Accelerated Yellowing',
                'category' => 'Chemical, Aging & Environmental Fastness',
                'standards' => 'ASTM G154, SATRA TM160',
                'parameters' => [
                    'Light Source' => 'Fluorescent UVA-340 ultraviolet lamps',
                    'UV Peak Irradiance' => '0.76 W/m² at 340 nm wavelength',
                    'Chamber Temperature' => '50.0 ± 2.0°C (122°F)',
                    'Exposure Duration' => '24.0 consecutive hours continuous UV radiation',
                    'Masking' => '50% of specimen masked with aluminum foil as unexposed reference',
                ],
                'specimen' => [
                    'Dimensions' => '75 mm x 50 mm flat plate or molded shoe component',
                    'Target Materials' => 'White vulcanized rubber foxing tape, white waffle sole, white canvas, EVA midsole',
                ],
                'criteria' => [
                    'White Vulcanized Foxing Tape' => '>= Grade 4.0 on Gray Scale for Color Change (minimal yellowing)',
                    'White Outsole Rubber' => '>= Grade 4.0',
                    'Clear Translucent Soling' => '>= Grade 3.5',
                ],
                'formula' => 'Yellowing Resistance = Gray Scale for Color Change Grade (1.0 to 5.0)',
                'formula_desc' => 'Delta E colorimeter measurement or visual gray scale comparison against masked half.',
                'procedure' => 'Cover half of white rubber specimen with heavy aluminum foil. Place in UV chamber at 50°C under UVA-340 lamps. Expose for 24 hours. Remove foil, allow 30 min recovery, evaluate color shift under D65 daylight.',
                'report_fields' => ['Chamber Temp (50°C)', 'Duration (24h)', 'UVA Irradiance', 'Color Change Grade', 'Delta E Reading', 'Pass/Fail'],
                'extra_keywords' => ['uv chamber temp 50c', 'uv exposure 24 hours', 'uva-340 lamps', 'yellowing grade 4', 'white sole yellowing limit']
            ],

            'VF-E19' => [
                'name' => 'Hydrolysis Resistance (Jungle Test for Polyurethane)',
                'category' => 'Chemical, Aging & Environmental Fastness',
                'standards' => 'SATRA TM344, VF Standard E19',
                'parameters' => [
                    'Environmental Chamber Temp' => '70.0 ± 1.0°C (158°F)',
                    'Relative Humidity' => '95 ± 3% RH saturated moisture humidity',
                    'Aging Duration' => '10 consecutive days (240 hours continuous exposure)',
                    'Stabilization Recovery' => '24 hours at standard laboratory conditions (23°C 50% RH) after removal',
                ],
                'specimen' => [
                    'Target Materials' => 'Molded Polyurethane (PU) midsoles, PU outsoles, PU synthetic leathers',
                    'Quantity' => '3 aged pairs + 3 unaged reference pairs',
                ],
                'criteria' => [
                    'Visual Inspection' => 'Zero crumbling, cracking, powdering, stickiness, or surface tack',
                    'Manual 180° Pinch Bend' => 'Zero crack formation when bent completely double 180°',
                    'Post-Aging Flex Durability' => '>= 50,000 cycles on Ross / Bally flexometer without fracture',
                    'Hardness Shift' => 'Maximum ± 5 Shore units change from initial unaged hardness',
                ],
                'formula' => 'Hydrolysis Resistance = PASS (zero cracking/crumbling after 10 days @ 70°C 95% RH)',
                'formula_desc' => 'Accelerated simulation of 3-5 years tropical storage.',
                'procedure' => 'Suspend PU specimens in humidity chamber at 70°C and 95% RH for 10 days. Remove and condition at 23°C for 24 hours. Perform 180° manual pinch bend and test flexing durability. Inspect for degradation.',
                'report_fields' => ['Exposure Temp (70°C)', 'Humidity (95% RH)', 'Duration (10 Days)', 'Pinch Bend Result', 'Post-Aging Flex Cycles', 'Pass/Fail'],
                'extra_keywords' => ['jungle test temp 70c', 'jungle test humidity 95rh', '10 days hydrolysis', 'pu sole crumbling test', 'hydrolysis standard']
            ],

            'VF-F01' => [
                'name' => 'Whole Footwear Sole Adhesion (Bonding Strength)',
                'category' => 'Adhesion & Bond Strength',
                'standards' => 'SATRA TM404, ASTM D2558',
                'parameters' => [
                    'Crosshead Pull Speed' => '300 ± 20 mm/min (or 100 mm/min for precise separation recording)',
                    'Pull Angle' => '90° vertical peel angle along sole-to-upper interface line',
                    'Measurement Locations' => '5 Standard Points: Toe, Inside Ball, Outside Ball, Waist/Shank, and Heel',
                    'Apparatus' => 'Universal Tensile Machine with articulated lasted shoe holder and serrated sole clamp',
                ],
                'specimen' => [
                    'Sample' => 'Fully assembled, vulcanized or cemented production footwear',
                    'Aging / Stabilization' => 'Tested at least 24 hours after final assembly / autoclave vulcanization',
                    'Quantity' => 'Minimum 1 complete pair (Left shoe and Right shoe) per production line/shift',
                ],
                'criteria' => [
                    'Whole Bottom Average Bonding' => '>= 3.0 kg/cm (>= 29.4 N/cm) across all test locations',
                    'Individual Point Minimum' => '>= 2.5 kg/cm (>= 24.5 N/cm)',
                    'Tolerance Band (Concession)' => '2.0 - 2.4 kg/cm allowed ONLY IF failure mode is 100% rubber substrate tear',
                    'Clean Glue Separation (Adhesive Failure)' => 'Immediate FAIL if bonding is < 3.0 kg/cm with smooth cement failure',
                ],
                'formula' => 'Bonding Strength (kg/cm) = Peak_Separation_Force_kg / Bond_Width_cm | Average = Sum(All_Points) / N',
                'formula_desc' => 'Average of Toe, Ball (In/Out), Waist, and Heel. Both individual minimum and overall average must pass.',
                'procedure' => 'Mount lasted shoe into lower UTM fixture. Clamp sole edge at toe in upper pneumatic jaw. Peel at 300 mm/min at 90° angle. Record continuous load trace. Repeat for Ball, Waist, and Heel. Record failure mode.',
                'report_fields' => ['Style / Mold Size', 'Toe Strength (kg/cm)', 'Inside Ball', 'Outside Ball', 'Waist', 'Heel', 'Whole Bottom Average', 'Failure Mode (% Rubber Tear)', 'Pass/Fail'],
                'extra_keywords' => ['sole adhesion pull speed 300mm', 'bonding average requirement 3.0', 'individual point minimum 2.5', 'shoe bonding formula', 'sole peeling test', 'toe bonding pass mark']
            ],

            'VF-G02' => [
                'name' => 'Whole Shoe Flexing Resistance (Walking Simulator)',
                'category' => 'Assembled Footwear Performance',
                'standards' => 'SATRA TM92',
                'parameters' => [
                    'Flexing Frequency' => '100 ± 5 cycles per minute',
                    'Flexing Angle' => '45.0° forward walking flexion bend angle',
                    'Total Cycles' => '150,000 continuous flexing cycles (approx 25 hours)',
                    'Apparatus' => 'Reciprocating pneumatic whole shoe flexing machine with mechanical foot forms',
                ],
                'specimen' => [
                    'Sample' => '1 complete finished production pair (Left and Right shoe)',
                    'Sizes' => 'Sample shoe sizes: Men US 9.0 / Women US 7.0',
                ],
                'criteria' => [
                    'Outsole Rubber Flex Cracking' => 'Maximum <= 4.0 mm crack growth in flex grooves',
                    'Foxing Band Delamination' => 'Maximum <= 2.0 mm separation along sole edge',
                    'Upper Material / Stitching' => 'Zero stitch rupture, zero canvas/leather cracking',
                    'Zero Complete Rupture' => 'Sole must not crack through to footbed',
                ],
                'formula' => 'Flexing Durability = Cycles completed without exceeding defect limits',
                'formula_desc' => 'Inspect at 50k, 100k, and 150k cycle milestones.',
                'procedure' => 'Fit shoe onto mechanical articulated foot. Clamp heel into fixture. Flex at 45° angle at 100 cpm up to 150,000 cycles. Stop at inspection intervals to evaluate sole cracking and foxing adhesion.',
                'report_fields' => ['Shoe Style', 'Cycles Completed (150,000)', 'Foxing Delamination (mm)', 'Sole Crack Length (mm)', 'Upper Condition', 'Pass/Fail'],
                'extra_keywords' => ['whole shoe flex 150000 cycles', 'walking flex angle 45 degrees', 'whole shoe flex speed 100 cpm', 'foxing delamination limit 2mm']
            ],

            'VF-G04' => [
                'name' => 'Whole Shoe Dynamic Waterproof Flexing',
                'category' => 'Assembled Footwear Performance',
                'standards' => 'SATRA TM230, ISO 20344 clause 5.15',
                'parameters' => [
                    'Flexing Frequency' => '60 ± 2 cycles per minute',
                    'Water Bath Level' => '25.0 ± 1.0 mm above feather line (sole-upper junction)',
                    'Water Detection Sensor' => 'Gold-plated electrical conductivity sensing foot probe',
                    'Target Test Cycles' => '15,000 continuous flex cycles in water bath',
                ],
                'specimen' => [
                    'Sample' => 'Finished waterproof boot or gusseted shoe pair',
                    'Preparation' => 'Interior dry; sensor socks fitted snugly inside footwear',
                ],
                'criteria' => [
                    'Waterproof Benchmark' => '>= 15,000 flexes without water leakage into interior',
                    'Electrical Sensor Alarm' => 'Zero alarm triggered (electrical resistance must remain > 100 kOhm)',
                    'Moisture Mass Gain' => 'Internal lining mass gain must not exceed 0.5 grams',
                ],
                'formula' => 'Waterproof Performance = Flex cycles achieved before first water ingress',
                'formula_desc' => 'Sensor triggers automatic timer shutdown upon first water droplet detection.',
                'procedure' => 'Insert electrical detection sock into shoe. Mount shoe on flexing machine in water tank at 25mm depth above feather line. Flex at 60 cpm. Monitor for conductivity alarm. Record flexes to failure.',
                'report_fields' => ['Water Depth (25mm)', 'Cycles Reached', 'Water Penetration Time', 'Leak Location (Seam/Sole)', 'Pass/Fail'],
                'extra_keywords' => ['waterproof flex 15000 cycles', 'water bath depth 25mm', 'dynamic waterproof test', 'shoe water leakage standard', 'satra tm230 speed 60 cpm']
            ],

            'VF-H01' => [
                'name' => 'Standard Material Conditioning & Testing Atmosphere',
                'category' => 'Sampling & Material Conditioning',
                'standards' => 'ASTM D618, ISO 291, SATRA TM70',
                'parameters' => [
                    'Standard Laboratory Temperature' => '23.0 ± 2.0°C (73.4 ± 3.6°F)',
                    'Standard Relative Humidity' => '50.0 ± 5.0% RH',
                    'Minimum Conditioning Period' => '24.0 consecutive hours prior to any physical testing',
                    'Vulcanization Stabilization' => 'Minimum 16.0 hours post-autoclave before cutting test specimens',
                ],
                'specimen' => [
                    'Placement' => 'Specimens laid flat on perforated stainless steel mesh shelves allowing air circulation on all sides',
                ],
                'criteria' => [
                    'Environmental Chamber Tolerance' => 'Temperature must remain within 21°C - 25°C; Humidity within 45% - 55% RH',
                    'Invalid Test Condition' => 'Testing performed outside these atmospheric limits is technically invalid',
                ],
                'formula' => 'Conditioning Compliance = Temperature (23±2°C) + Humidity (50±5% RH) * 24 Hours',
                'formula_desc' => 'Mandatory pre-test standard for all laboratory physical properties.',
                'procedure' => 'Place raw components or shoes on wire shelves in conditioning room controlled at 23±2°C and 50±5% RH. Maintain for at least 24 hours before conducting tensile, tear, abrasion, or adhesion testing.',
                'report_fields' => ['Chamber Temperature (°C)', 'Chamber Humidity (% RH)', 'Conditioning Start Date/Time', 'Test Date/Time', 'Operator'],
                'extra_keywords' => ['conditioning temperature 23c', 'conditioning humidity 50rh', '24 hours conditioning', 'vulcanization stabilization 16 hours', 'lab atmospheric standard']
            ],

            'VF-J01' => [
                'name' => 'High Voltage Electric Shock Resistance',
                'category' => 'Electrical & Thermal Safety',
                'standards' => 'ASTM F2412, ASTM F2413, CSA Z195',
                'parameters' => [
                    'Applied Test Voltage' => '18,000 Volts AC (18.0 kV RMS)',
                    'AC Frequency' => '60.0 Hz sinusoidal waveform',
                    'Test Duration' => '1.0 minute (60 consecutive seconds)',
                    'Electrodes' => 'Interior filled with 3.0mm conductive steel balls; Outer sole placed on wet metal mesh electrode',
                ],
                'specimen' => [
                    'Sample' => 'Complete finished electrical hazard (EH) safety footwear pair',
                    'Conditioning' => 'Dry conditioning for 24 hours at 23°C 50% RH',
                ],
                'criteria' => [
                    'Maximum Leakage Current' => '<= 1.00 mA (milliampere) maximum current leakage',
                    'Dielectric Breakdown' => 'Zero dielectric puncture, arcing, or flashover through the sole',
                ],
                'formula' => 'Current Leakage = Measured milliampere (mA) across sole dielectric at 18kV',
                'formula_desc' => 'High-potential dielectric breakdown tester monitor.',
                'procedure' => 'Fill interior of dry safety shoe with conductive steel balls connected to high-voltage probe. Place outsole on grounded metal electrode mesh. Ramp voltage to 18,000V AC over 5 seconds. Hold for 60 seconds. Record leakage current.',
                'report_fields' => ['Applied Voltage (18kV)', 'Frequency (60Hz)', 'Duration (60s)', 'Peak Leakage Current (mA)', 'Dielectric Breakdown (None/Arc)', 'Pass/Fail'],
                'extra_keywords' => ['electric shock 18000v', 'leakage current limit 1.0 ma', '18kv test', 'astm f2413 dielectric test', 'electric hazard safety']
            ],
        ];
    }
}
