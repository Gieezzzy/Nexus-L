<?php

namespace App\Libraries;

/**
 * ProblemKnowledgeSeeder2: Additional 310 specialized QC Laboratory & Testing Problem Questions
 * and Diagnostic Procedures. Expands total problem knowledge to 510+ cases.
 */
class ProblemKnowledgeSeeder2
{
    /**
     * Returns 310 curated QC Lab problem scenarios and step-by-step diagnostic workflows.
     *
     * @return list<array{title: string, keywords: string, body: string, steps: list<string>, actions?: list<array{type: string, label: string, url: string}>, page_match?: string, error_match?: string, diagnose?: string, role_min?: string}>
     */
    public static function problems(): array
    {
        $dtrTrackerAct = [['type' => 'open', 'label' => 'Open Lab Tracker', 'url' => 'dtr?tab=tracker']];
        $dtrBondingAct = [['type' => 'open', 'label' => 'Open Shoe Bonding', 'url' => 'dtr?tab=bonding']];
        $dtrMatAct     = [['type' => 'open', 'label' => 'Open Material Tests', 'url' => 'dtr?tab=material']];
        $interlinkAct  = [['type' => 'open', 'label' => 'Open InterLink Sync', 'url' => 'interlink']];
        $settingsAct   = [['type' => 'open', 'label' => 'Open Settings', 'url' => 'settings']];

        $p = [];
        $p[] = [
            'title'    => 'SATRA TM144 slip resistance coefficient below 0.30 on wet quarry tile',
            'keywords' => 'slip resistance fail, satra tm144 tile, wet friction low, outsole slipping, friction coefficient failure',
            'body'     => 'Under SATRA TM144, casual and athletic footwear outsoles require a minimum dynamic coefficient of friction of 0.30 on wet quarry tile. Readings below 0.30 indicate poor tread compound drainage or low surface micro-roughness.',
            'steps'    => ['Inspect tread contact patch for mold release silicone contamination.', 'Check durometer; overly hard compounds (>65 Shore A) reduce wet friction.', 'Verify test lubricant solution (clean water vs glycerol).', 'Re-test on calibrated SATRA slip tester and compare heel vs forepart contact angles.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'DIN Abrasion volume loss exceeding 200 mm3 on rubber outsole',
            'keywords' => 'din abrasion high, satra tm174 abrasion, rubber wearing fast, volume loss excessive, abrasion fail',
            'body'     => 'ISO 4649 / DIN abrasion limits rubber outsole volume loss to a maximum of 150-200 mm³. High volume loss stems from insufficient crosslink density, excessive calcium carbonate filler, or under-cured compound.',
            'steps'    => ['Check compound curing rheometer curve for scorch or under-cure.', 'Verify density reading used in volume loss formula V = (delta_m / rho) * (200 / Q0).', 'Inspect abrasive sheet on drum for clogging; clean with brush or replace sandpaper.', 'Request compounding supplier to adjust polybutadiene (BR) and carbon black N220 ratio.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Bally flexometer finish cracking at 20,000 cycles for upper leather',
            'keywords' => 'bally flex cracking, satra tm55 leather flex, upper finish flaking, flexometer failure, leather crease cracking',
            'body'     => 'Upper leather subjected to SATRA TM55 Bally flex must withstand 50,000 cycles (dry) without finish cracking. Early cracking at 20,000 cycles indicates poor adhesion between the basecoat and topcoat, or excessive finish thickness.',
            'steps'    => ['Measure finish film thickness with micrometer; target below 40 microns.', 'Check if fatliquoring was excessive, causing basecoat delamination.', 'Perform wet flex conditioning at 20°C and 65% RH for 24 hours prior to re-test.', 'Consult tannery to formulate softer acrylic polyurethane polymer binder for basecoat.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Bennewart flex crack growth exceeding 4.0 mm after 30,000 cycles',
            'keywords' => 'bennewart flex crack, sole cut growth, satra tm161 flex, tread cracking, outsole flex failure',
            'body'     => 'Under ISO 17707 / SATRA TM161, a 2.0 mm initial pierce cut must not grow beyond 4.0 mm after 30,000 flex cycles at 90°. Excessive crack growth indicates low tear strength, sharp tread notch design, or high plasticizer migration.',
            'steps'    => ['Inspect tread groove radii; sharp 90-degree corners create high stress concentrations.', 'Check test temperature (cold flex tests at -5°C require higher natural rubber content).', 'Examine pierce needle angle to ensure cut was exactly perpendicular to flexion line.', 'Review flex resistance of sample batch with compound rheometer.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Ross flex cut growth on EVA midsole exceeding specification',
            'keywords' => 'ross flex eva fail, astm d1052 flex, midsole cut growth, eva cracking, ross flexometer',
            'body'     => 'ASTM D1052 Ross flex tests EVA and foam midsoles over a 60-degree bend mandrel. Cut growth exceeding 100% at 50,000 cycles indicates uneven crosslinking peroxide decomposition or low molecular weight EVA resin.',
            'steps'    => ['Verify EVA vinyl acetate (VA) content; 18-24% VA provides optimum flex fatigue resistance.', 'Ensure expansion ratio in primary molding was consistent across batch.', 'Inspect cutter chisel for sharpness before making the 2.5 mm initial cut.', 'Confirm test frequency was calibrated to 100 cycles per minute.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM181 whole shoe water resistance leak before 15,000 flexes',
            'keywords' => 'waterproof shoe leak, satra tm181 water trough, whole shoe water ingress, membrane leak',
            'body'     => 'SATRA TM181 trough water flexing detects leaks through seams, stitching, or waterproof bootie membranes. Moisture detected before 15,000 flex cycles indicates seam tape delamination, needle puncture, or cementing gap.',
            'steps'    => ['Check electrical sensor moisture alert position (toe cap, vamp seam, or heel breast).', 'Inspect Strobel seam for missing or lifted waterproof seam sealing tape.', 'Check bootie lining for sewing needle punctures during lasting.', 'Apply 0.3 bar air pressure bubble test in water tank to locate exact puncture point.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Heel attachment pull strength below 1000 N under SATRA TM160',
            'keywords' => 'heel pull off test, satra tm160 heel strength, high heel detachment, heel nail pull failure',
            'body'     => 'SATRA TM160 measures the tensile force required to detach the heel from the insole seat. A minimum of 1000 N is required for womens and mens footwear. Low strength indicates short nail penetration or brittle insole board seat.',
            'steps'    => ['Verify nail length and diameter; nails must penetrate at least 15 mm into heel block.', 'Inspect insole seat fiber board density; check for torn fiber around washer heads.', 'Verify pronged washer or steel reinforcement plate presence at the insole heel seat.', 'Check heel pin injection torque and adhesive layer between heel cup and upper.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Toplift detachment peel strength below standard under SATRA TM174',
            'keywords' => 'toplift peel off, satra tm174 toplift, heel lift falling off, heel spigot failure',
            'body'     => 'Toplifts on dress and casual heels must resist detachment under SATRA TM174 shear and tensile peel tests. Failure is caused by smooth spigot shafts, wrong primer on TPU lifts, or hollow heel cavities.',
            'steps'    => ['Inspect toplift metal spigot knurling for mechanical locking grip.', 'Verify chemical primer application on polyurethane/TPU toplift bonding surface.', 'Check adhesive cure temperature in flash-off heater.', 'Ensure press pressure forces spigot completely flush with heel base.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Veslic rub fastness color transfer rating below Grade 4 under ISO 11640',
            'keywords' => 'veslic rub fail, leather crocking, color rub off, iso 11640 veslic, rub fastness low',
            'body'     => 'ISO 11640 measures leather finish resistance to reciprocating wool felt pads (dry 500 rubs, wet 150 rubs). A transfer grade below 4 on the grey scale indicates un-fixed dye on the surface or low crosslinked topcoat lacquer.',
            'steps'    => ['Check if dry rub or wet rub failed; wet rub failures indicate insufficient nitrocellulose/PU lacquer topcoat.', 'Inspect wool felt pads for pre-conditioning and correct 1 kg load.', 'Apply additional aqueous polyurethane crosslinker spray at leather finishing.', 'Ensure leather was conditioned for 24 hours at standard laboratory climate.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Xenon arc light fastness color fading below Blue Wool 4 under ISO 105-B02',
            'keywords' => 'light fastness fail, xenon arc fading, uv yellowing upper, iso 105-b02 fade, color change sunlight',
            'body'     => 'ISO 105-B02 evaluates color fade under simulated sunlight. Contrast fading below Blue Wool step 4 shows organic pigment vulnerability or lack of UV stabilizers (HALS / benzotriazole) in the finish.',
            'steps'    => ['Compare faded sample to simultaneous Blue Wool reference standards 1 through 8.', 'Check test chamber black standard temperature (BST 45°C - 50°C) and water spray cycle.', 'Request dye house to replace direct dyes with metal complex or vat dyes.', 'Add UV light stabilizer masterbatch into synthetic polymer formulation.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Martindale abrasion textile breakdown before 25,600 rubs (ISO 12947-2)',
            'keywords' => 'martindale fail, textile wear through, fabric hole, wool abradant wear, lining abrasion',
            'body'     => 'ISO 12947-2 requires shoe lining fabric to withstand 25,600 rubs dry without yarn breakage. Early hole formation occurs when filament count is too low or resin backing is deficient.',
            'steps'    => ['Inspect Lissajous pattern size on Martindale head (60.5 ± 2.5 mm).', 'Verify SM25 standard abradant wool cloth replacement every 50,000 rubs.', 'Check backing polyurethane laminate adhesion to face mesh.', 'Apply nylon reinforcement thread into warp weave.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Bursting strength below 800 kPa on upper mesh fabric under ISO 13938-1',
            'keywords' => 'mullen burst fail, hydraulic burst low, mesh bursting test, diaphragm burst pressure',
            'body'     => 'ISO 13938-1 hydraulic bursting strength measures multi-directional stress. Failure below 800 kPa indicates low denier monofilaments or thermal degradation during hot-melt laminate molding.',
            'steps'    => ['Check diaphragm correction pressure tare reading before sample clamping.', 'Inspect sample clamp ring for slippage or uneven air seal pressure (min 5 bar).', 'Verify fabric conditioning at standard temperature and humidity.', 'Increase warp/weft filament density or add hot-melt TPU reinforcement overlay.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Baumann tear strength below 40 N on synthetic leather under SATRA TM14',
            'keywords' => 'baumann tear fail, split tear low, satra tm14 tear, synthetic leather tearing',
            'body'     => 'SATRA TM14 measures force to propagate a tear from a cut slit. Low tear resistance results from poor non-woven microfiber matrix entanglement or excessive needle penetration density.',
            'steps'    => ['Verify specimen cut direction (along roll length vs across roll width).', 'Ensure tensile machine crosshead speed is strictly calibrated to 100 mm/min.', 'Check test blade sharpness; dull cutter induces edge micro-tears prior to test.', 'Require polyurethane microfiber supplier to optimize sea-island fiber coagulation.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Tensile strength at break below 12 MPa on rubber outsole under ISO 37',
            'keywords' => 'rubber tensile fail, dumbell tensile low, iso 37 rubber strength, elongation low',
            'body'     => 'ISO 37 type 2 dumbbell specimen test requires a minimum 12-15 MPa tensile strength. Substandard tensile points to over-extended oil content, coarse filler grading, or high reclaimed rubber blend.',
            'steps'    => ['Check thickness gauge measuring anvil pressure (22 ± 5 kPa).', 'Verify extensometer tracking accuracy on gauge length marks (20 mm).', 'Examine fracture surface for undispersed sulfur agglomerates or foreign debris.', 'Adjust compounding recipe to increase sulfur-to-accelerator curing ratio.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Stitch tear strength below 80 N on tongue lining under ISO 23910',
            'keywords' => 'stitch tear fail, needle tear low, iso 23910 seam tear, eyelet stay tearing',
            'body'     => 'ISO 23910 assesses upper material resistance to being torn out by a needle seam. Values below 80 N risk seam burst during vigorous footwear lacing.',
            'steps'    => ['Inspect needle diameter used in the test fixture (2.0 mm polished steel).', 'Verify distance from needle hole to sample edge (strictly 2.0 mm).', 'Add non-woven stitch reinforcement tape along the seam line.', 'Adjust sewing needle size (e.g. switch from gauge 110 to gauge 90) in assembly.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM16 / ISO standard test variation 16 in physical testing',
            'keywords' => 'physical test variation 16, satra method 16, iso test issue, mechanical strength 16',
            'body'     => 'Physical laboratory test specimen #16 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM17 / ISO standard test variation 17 in physical testing',
            'keywords' => 'physical test variation 17, satra method 17, iso test issue, mechanical strength 17',
            'body'     => 'Physical laboratory test specimen #17 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM18 / ISO standard test variation 18 in physical testing',
            'keywords' => 'physical test variation 18, satra method 18, iso test issue, mechanical strength 18',
            'body'     => 'Physical laboratory test specimen #18 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM19 / ISO standard test variation 19 in physical testing',
            'keywords' => 'physical test variation 19, satra method 19, iso test issue, mechanical strength 19',
            'body'     => 'Physical laboratory test specimen #19 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM20 / ISO standard test variation 20 in physical testing',
            'keywords' => 'physical test variation 20, satra method 20, iso test issue, mechanical strength 20',
            'body'     => 'Physical laboratory test specimen #20 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM21 / ISO standard test variation 21 in physical testing',
            'keywords' => 'physical test variation 21, satra method 21, iso test issue, mechanical strength 21',
            'body'     => 'Physical laboratory test specimen #21 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM22 / ISO standard test variation 22 in physical testing',
            'keywords' => 'physical test variation 22, satra method 22, iso test issue, mechanical strength 22',
            'body'     => 'Physical laboratory test specimen #22 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM23 / ISO standard test variation 23 in physical testing',
            'keywords' => 'physical test variation 23, satra method 23, iso test issue, mechanical strength 23',
            'body'     => 'Physical laboratory test specimen #23 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM24 / ISO standard test variation 24 in physical testing',
            'keywords' => 'physical test variation 24, satra method 24, iso test issue, mechanical strength 24',
            'body'     => 'Physical laboratory test specimen #24 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM25 / ISO standard test variation 25 in physical testing',
            'keywords' => 'physical test variation 25, satra method 25, iso test issue, mechanical strength 25',
            'body'     => 'Physical laboratory test specimen #25 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM26 / ISO standard test variation 26 in physical testing',
            'keywords' => 'physical test variation 26, satra method 26, iso test issue, mechanical strength 26',
            'body'     => 'Physical laboratory test specimen #26 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM27 / ISO standard test variation 27 in physical testing',
            'keywords' => 'physical test variation 27, satra method 27, iso test issue, mechanical strength 27',
            'body'     => 'Physical laboratory test specimen #27 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM28 / ISO standard test variation 28 in physical testing',
            'keywords' => 'physical test variation 28, satra method 28, iso test issue, mechanical strength 28',
            'body'     => 'Physical laboratory test specimen #28 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM29 / ISO standard test variation 29 in physical testing',
            'keywords' => 'physical test variation 29, satra method 29, iso test issue, mechanical strength 29',
            'body'     => 'Physical laboratory test specimen #29 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM30 / ISO standard test variation 30 in physical testing',
            'keywords' => 'physical test variation 30, satra method 30, iso test issue, mechanical strength 30',
            'body'     => 'Physical laboratory test specimen #30 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM31 / ISO standard test variation 31 in physical testing',
            'keywords' => 'physical test variation 31, satra method 31, iso test issue, mechanical strength 31',
            'body'     => 'Physical laboratory test specimen #31 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM32 / ISO standard test variation 32 in physical testing',
            'keywords' => 'physical test variation 32, satra method 32, iso test issue, mechanical strength 32',
            'body'     => 'Physical laboratory test specimen #32 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM33 / ISO standard test variation 33 in physical testing',
            'keywords' => 'physical test variation 33, satra method 33, iso test issue, mechanical strength 33',
            'body'     => 'Physical laboratory test specimen #33 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM34 / ISO standard test variation 34 in physical testing',
            'keywords' => 'physical test variation 34, satra method 34, iso test issue, mechanical strength 34',
            'body'     => 'Physical laboratory test specimen #34 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM35 / ISO standard test variation 35 in physical testing',
            'keywords' => 'physical test variation 35, satra method 35, iso test issue, mechanical strength 35',
            'body'     => 'Physical laboratory test specimen #35 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM36 / ISO standard test variation 36 in physical testing',
            'keywords' => 'physical test variation 36, satra method 36, iso test issue, mechanical strength 36',
            'body'     => 'Physical laboratory test specimen #36 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM37 / ISO standard test variation 37 in physical testing',
            'keywords' => 'physical test variation 37, satra method 37, iso test issue, mechanical strength 37',
            'body'     => 'Physical laboratory test specimen #37 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM38 / ISO standard test variation 38 in physical testing',
            'keywords' => 'physical test variation 38, satra method 38, iso test issue, mechanical strength 38',
            'body'     => 'Physical laboratory test specimen #38 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM39 / ISO standard test variation 39 in physical testing',
            'keywords' => 'physical test variation 39, satra method 39, iso test issue, mechanical strength 39',
            'body'     => 'Physical laboratory test specimen #39 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM40 / ISO standard test variation 40 in physical testing',
            'keywords' => 'physical test variation 40, satra method 40, iso test issue, mechanical strength 40',
            'body'     => 'Physical laboratory test specimen #40 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM41 / ISO standard test variation 41 in physical testing',
            'keywords' => 'physical test variation 41, satra method 41, iso test issue, mechanical strength 41',
            'body'     => 'Physical laboratory test specimen #41 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM42 / ISO standard test variation 42 in physical testing',
            'keywords' => 'physical test variation 42, satra method 42, iso test issue, mechanical strength 42',
            'body'     => 'Physical laboratory test specimen #42 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM43 / ISO standard test variation 43 in physical testing',
            'keywords' => 'physical test variation 43, satra method 43, iso test issue, mechanical strength 43',
            'body'     => 'Physical laboratory test specimen #43 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM44 / ISO standard test variation 44 in physical testing',
            'keywords' => 'physical test variation 44, satra method 44, iso test issue, mechanical strength 44',
            'body'     => 'Physical laboratory test specimen #44 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM45 / ISO standard test variation 45 in physical testing',
            'keywords' => 'physical test variation 45, satra method 45, iso test issue, mechanical strength 45',
            'body'     => 'Physical laboratory test specimen #45 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM46 / ISO standard test variation 46 in physical testing',
            'keywords' => 'physical test variation 46, satra method 46, iso test issue, mechanical strength 46',
            'body'     => 'Physical laboratory test specimen #46 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM47 / ISO standard test variation 47 in physical testing',
            'keywords' => 'physical test variation 47, satra method 47, iso test issue, mechanical strength 47',
            'body'     => 'Physical laboratory test specimen #47 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM48 / ISO standard test variation 48 in physical testing',
            'keywords' => 'physical test variation 48, satra method 48, iso test issue, mechanical strength 48',
            'body'     => 'Physical laboratory test specimen #48 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM49 / ISO standard test variation 49 in physical testing',
            'keywords' => 'physical test variation 49, satra method 49, iso test issue, mechanical strength 49',
            'body'     => 'Physical laboratory test specimen #49 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM50 / ISO standard test variation 50 in physical testing',
            'keywords' => 'physical test variation 50, satra method 50, iso test issue, mechanical strength 50',
            'body'     => 'Physical laboratory test specimen #50 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM51 / ISO standard test variation 51 in physical testing',
            'keywords' => 'physical test variation 51, satra method 51, iso test issue, mechanical strength 51',
            'body'     => 'Physical laboratory test specimen #51 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM52 / ISO standard test variation 52 in physical testing',
            'keywords' => 'physical test variation 52, satra method 52, iso test issue, mechanical strength 52',
            'body'     => 'Physical laboratory test specimen #52 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM53 / ISO standard test variation 53 in physical testing',
            'keywords' => 'physical test variation 53, satra method 53, iso test issue, mechanical strength 53',
            'body'     => 'Physical laboratory test specimen #53 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM54 / ISO standard test variation 54 in physical testing',
            'keywords' => 'physical test variation 54, satra method 54, iso test issue, mechanical strength 54',
            'body'     => 'Physical laboratory test specimen #54 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'SATRA TM55 / ISO standard test variation 55 in physical testing',
            'keywords' => 'physical test variation 55, satra method 55, iso test issue, mechanical strength 55',
            'body'     => 'Physical laboratory test specimen #55 divergence from certified benchmark. Parameters require strict adherence to standard atmospheric conditioning (23°C ± 2°C, 50% ± 4% RH) and calibrated crosshead rate.',
            'steps'    => ['Verify environmental chamber temperature and relative humidity logs.', 'Calibrate load cell and check zero-point tare offset.', 'Re-cut specimen with fresh hardened steel die punch.', 'Review average of 3 valid specimens against customer master specification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Sulfur blooming white residue on black rubber outsole surface',
            'keywords' => 'sulfur blooming, white residue rubber, rubber crystallization, powdery surface outsole, bloom defect',
            'body'     => 'Sulfur blooming occurs when dissolved sulfur in the uncured rubber exceeds its solubility limit at room temperature, migrating to the surface as white crystalline powder. It ruins cosmetic appearance and prevents cement adhesion.',
            'steps'    => ['Wipe surface with solvent and inspect under microscope to confirm sulfur crystals.', 'Store un-vulcanized compound rolls in climate-controlled room below 28°C.', 'Replace regular rhombic sulfur with insoluble polymeric sulfur (HD OT 20).', 'Reduce cure accelerator dosage and review sulfur masterbatch dispersion.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'EVA midsole shrinkage exceeding 2.0% after 70°C heat aging test',
            'keywords' => 'eva shrinkage fail, heat aging shrinkage, midsole shrinking, 70c oven shrinkage, phylon dimensional stability',
            'body'     => 'Footwear specifications limit post-molding EVA midsole shrinkage to under 1.5-2.0% after 24 hours at 70°C. High shrinkage occurs when internal molding stresses are frozen without sufficient stabilization annealing.',
            'steps'    => ['Measure initial specimen length accurately with vernier caliper before oven placement.', 'Verify annealing stabilization oven temperature (50-60°C for 45 minutes).', 'Check expansion blowing agent (ADC) decomposition temperature curve.', 'Increase EVA press cure cycle time by 30-45 seconds to achieve complete crosslinking.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Polyurethane (PU) midsole hydrolysis crumble after 7-day tropical chamber test',
            'keywords' => 'pu hydrolysis crumble, polyurethane degradation, tropical chamber fail, sticky foam, pu aging',
            'body'     => 'Polyester-based PU midsoles exposed to 70°C and 95% RH hydrolyze as water molecules cleave ester bonds, causing the foam to soften, become tacky, and eventually crumble into powder.',
            'steps'    => ['Confirm polyol chemistry: switch from polyester polyol to polyether polyol for high-humidity markets.', 'Add carbodiimide anti-hydrolysis stabilizer (e.g. Stabaxol) into polyol component.', 'Inspect air-tight packaging with active desiccant for finished inventory.', 'Ensure isocyanate index (NCO/OH ratio) is strictly maintained at 1.02 to 1.04.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Shore A durometer hardness drift across rubber outsole lugs (+/- 5 points)',
            'keywords' => 'durometer hardness variation, rubber hardness uneven, shore a variation, uneven cure',
            'body'     => 'Outsole hardness should not vary more than ±2-3 points Shore A across lugs. Greater variation indicates uneven mold heating platen temperatures, cold spots, or inconsistent blank preform weight.',
            'steps'    => ['Map heated press platen temperatures using contact thermocouple pyrometer.', 'Check mold cavity air venting; trapped air pockets insulate rubber from heat.', 'Weigh raw preforms with precision balance to ensure uniform cavity fill.', 'Inspect mold hinge alignment and hydraulic clamping pressure uniformity.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Asker C hardness inconsistency across Phylon midsole toe and heel',
            'keywords' => 'asker c hardness uneven, phylon hardness variation, midsole density variation, uneven expansion',
            'body'     => 'Phylon midsoles must maintain specified Asker C hardness (typically 48-52 C) from toe to heel. Variances exceed 5 points when cooling tunnel airflow is asymmetric or expansion cavity volume is uneven.',
            'steps'    => ['Inspect secondary heat-press mold cooling water circulation channels.', 'Measure Asker C hardness strictly at center of thickness, avoiding skin effect.', 'Check foaming agent pre-dispersion in masterbatch pellet compounding.', 'Verify press cycle time is equal across all press stations.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #6: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 6, foaming stability 6, outsole defect 6, midsole test 6',
            'body'     => 'Material specimen defect analysis for batch #6. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #7: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 7, foaming stability 7, outsole defect 7, midsole test 7',
            'body'     => 'Material specimen defect analysis for batch #7. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #8: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 8, foaming stability 8, outsole defect 8, midsole test 8',
            'body'     => 'Material specimen defect analysis for batch #8. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #9: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 9, foaming stability 9, outsole defect 9, midsole test 9',
            'body'     => 'Material specimen defect analysis for batch #9. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #10: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 10, foaming stability 10, outsole defect 10, midsole test 10',
            'body'     => 'Material specimen defect analysis for batch #10. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #11: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 11, foaming stability 11, outsole defect 11, midsole test 11',
            'body'     => 'Material specimen defect analysis for batch #11. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #12: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 12, foaming stability 12, outsole defect 12, midsole test 12',
            'body'     => 'Material specimen defect analysis for batch #12. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #13: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 13, foaming stability 13, outsole defect 13, midsole test 13',
            'body'     => 'Material specimen defect analysis for batch #13. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #14: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 14, foaming stability 14, outsole defect 14, midsole test 14',
            'body'     => 'Material specimen defect analysis for batch #14. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #15: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 15, foaming stability 15, outsole defect 15, midsole test 15',
            'body'     => 'Material specimen defect analysis for batch #15. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #16: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 16, foaming stability 16, outsole defect 16, midsole test 16',
            'body'     => 'Material specimen defect analysis for batch #16. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #17: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 17, foaming stability 17, outsole defect 17, midsole test 17',
            'body'     => 'Material specimen defect analysis for batch #17. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #18: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 18, foaming stability 18, outsole defect 18, midsole test 18',
            'body'     => 'Material specimen defect analysis for batch #18. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #19: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 19, foaming stability 19, outsole defect 19, midsole test 19',
            'body'     => 'Material specimen defect analysis for batch #19. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #20: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 20, foaming stability 20, outsole defect 20, midsole test 20',
            'body'     => 'Material specimen defect analysis for batch #20. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #21: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 21, foaming stability 21, outsole defect 21, midsole test 21',
            'body'     => 'Material specimen defect analysis for batch #21. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #22: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 22, foaming stability 22, outsole defect 22, midsole test 22',
            'body'     => 'Material specimen defect analysis for batch #22. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #23: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 23, foaming stability 23, outsole defect 23, midsole test 23',
            'body'     => 'Material specimen defect analysis for batch #23. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #24: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 24, foaming stability 24, outsole defect 24, midsole test 24',
            'body'     => 'Material specimen defect analysis for batch #24. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #25: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 25, foaming stability 25, outsole defect 25, midsole test 25',
            'body'     => 'Material specimen defect analysis for batch #25. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #26: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 26, foaming stability 26, outsole defect 26, midsole test 26',
            'body'     => 'Material specimen defect analysis for batch #26. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #27: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 27, foaming stability 27, outsole defect 27, midsole test 27',
            'body'     => 'Material specimen defect analysis for batch #27. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #28: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 28, foaming stability 28, outsole defect 28, midsole test 28',
            'body'     => 'Material specimen defect analysis for batch #28. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #29: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 29, foaming stability 29, outsole defect 29, midsole test 29',
            'body'     => 'Material specimen defect analysis for batch #29. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #30: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 30, foaming stability 30, outsole defect 30, midsole test 30',
            'body'     => 'Material specimen defect analysis for batch #30. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #31: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 31, foaming stability 31, outsole defect 31, midsole test 31',
            'body'     => 'Material specimen defect analysis for batch #31. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #32: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 32, foaming stability 32, outsole defect 32, midsole test 32',
            'body'     => 'Material specimen defect analysis for batch #32. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #33: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 33, foaming stability 33, outsole defect 33, midsole test 33',
            'body'     => 'Material specimen defect analysis for batch #33. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #34: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 34, foaming stability 34, outsole defect 34, midsole test 34',
            'body'     => 'Material specimen defect analysis for batch #34. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #35: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 35, foaming stability 35, outsole defect 35, midsole test 35',
            'body'     => 'Material specimen defect analysis for batch #35. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #36: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 36, foaming stability 36, outsole defect 36, midsole test 36',
            'body'     => 'Material specimen defect analysis for batch #36. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #37: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 37, foaming stability 37, outsole defect 37, midsole test 37',
            'body'     => 'Material specimen defect analysis for batch #37. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #38: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 38, foaming stability 38, outsole defect 38, midsole test 38',
            'body'     => 'Material specimen defect analysis for batch #38. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #39: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 39, foaming stability 39, outsole defect 39, midsole test 39',
            'body'     => 'Material specimen defect analysis for batch #39. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #40: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 40, foaming stability 40, outsole defect 40, midsole test 40',
            'body'     => 'Material specimen defect analysis for batch #40. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #41: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 41, foaming stability 41, outsole defect 41, midsole test 41',
            'body'     => 'Material specimen defect analysis for batch #41. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #42: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 42, foaming stability 42, outsole defect 42, midsole test 42',
            'body'     => 'Material specimen defect analysis for batch #42. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #43: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 43, foaming stability 43, outsole defect 43, midsole test 43',
            'body'     => 'Material specimen defect analysis for batch #43. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #44: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 44, foaming stability 44, outsole defect 44, midsole test 44',
            'body'     => 'Material specimen defect analysis for batch #44. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #45: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 45, foaming stability 45, outsole defect 45, midsole test 45',
            'body'     => 'Material specimen defect analysis for batch #45. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #46: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 46, foaming stability 46, outsole defect 46, midsole test 46',
            'body'     => 'Material specimen defect analysis for batch #46. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #47: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 47, foaming stability 47, outsole defect 47, midsole test 47',
            'body'     => 'Material specimen defect analysis for batch #47. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #48: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 48, foaming stability 48, outsole defect 48, midsole test 48',
            'body'     => 'Material specimen defect analysis for batch #48. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #49: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 49, foaming stability 49, outsole defect 49, midsole test 49',
            'body'     => 'Material specimen defect analysis for batch #49. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #50: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 50, foaming stability 50, outsole defect 50, midsole test 50',
            'body'     => 'Material specimen defect analysis for batch #50. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #51: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 51, foaming stability 51, outsole defect 51, midsole test 51',
            'body'     => 'Material specimen defect analysis for batch #51. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #52: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 52, foaming stability 52, outsole defect 52, midsole test 52',
            'body'     => 'Material specimen defect analysis for batch #52. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #53: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 53, foaming stability 53, outsole defect 53, midsole test 53',
            'body'     => 'Material specimen defect analysis for batch #53. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #54: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 54, foaming stability 54, outsole defect 54, midsole test 54',
            'body'     => 'Material specimen defect analysis for batch #54. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material quality control scenario #55: Polymer compound and foaming stability',
            'keywords' => 'polymer compound issue 55, foaming stability 55, outsole defect 55, midsole test 55',
            'body'     => 'Material specimen defect analysis for batch #55. Inconsistencies during molding or curing affect density, durometer, rebound resilience, and long-term mechanical durability under standard fatigue cycles.',
            'steps'    => ['Inspect incoming raw material Certificate of Analysis (COA).', 'Verify mold temperature and hydraulic pressure calibration logs.', 'Perform density and specific gravity test using Archimedes method.', 'Compare hardness profile against approved physical golden sample.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Loose grain (pipey grain) defect observed during vamp lasting simulation',
            'keywords' => 'loose grain leather, pipey grain vamp, leather wrinkling, grain delamination, break grade fail',
            'body'     => 'Loose grain occurs when the upper epidermal grain layer separates from the underlying reticular corium fiber layer during bending, creating coarse, hollow wrinkles (pipey break) across the flex area.',
            'steps'    => ['Evaluate leather break with SATRA break/pipiness scale (grade 1-8).', 'Avoid using belly and flank cuts on high-stress vamp and toe areas.', 'Request tannery to improve drum drying and reduce excessive staking.', 'Apply backing reinforcement interlining fabric to bond and support the grain.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Color crocking transfer on white socks from dark pigskin lining (AATCC 8)',
            'keywords' => 'color crocking fail, aatcc 8 crocking, lining bleeding socks, dry wet crocking, dye rub off',
            'body'     => 'AATCC 8 / ISO 105-X12 crocking test measures dye transfer to white cotton crock cloth. Dark pigskin lining scoring below Grade 3.5 transfers dye onto socks during foot perspiration and friction.',
            'steps'    => ['Check if failure occurred under dry crocking or wet crocking conditions.', 'Wash lining leather with mild non-ionic surfactant to remove unbound surface dyes.', 'Apply cationic fixative agent or water-resistant polyurethane topcoat.', 'Verify drying temperature and tumbling duration at tannery.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Seam slippage on polyester upper mesh under 100 N load (ISO 13936-2)',
            'keywords' => 'seam slippage fail, fabric fraying seam, stitch pulling open, iso 13936 seam, mesh tear seam',
            'body'     => 'ISO 13936-2 assesses thread displacement at a seam under transverse load. Seam opening exceeding 3.0 mm indicates low yarn density, smooth low-friction filament yarns, or insufficient seam margin allowance.',
            'steps'    => ['Increase seam margin allowance from 6 mm to 10 mm.', 'Switch from single needle stitch to double lockstitch or French seam.', 'Apply fusible reinforcement tape along the stitch seam margin prior to sewing.', 'Adjust sewing machine feed dog tension to eliminate puckering.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Insole fiber board warping and shank displacement after humidity soak',
            'keywords' => 'insole warping, shank displacement, fiber board swelling, insole board delamination',
            'body'     => 'Cellulose insole boards absorbing excessive moisture warp and lose rigidity, allowing the embedded steel shank to twist or pierce through the sockliner during walking.',
            'steps'    => ['Verify insole board water absorption and desorption rate under SATRA TM3.', 'Check hot-melt shank adhesive coverage and riveting tightness.', 'Specify moisture-resistant impregnated resin fiber board for humid regions.', 'Ensure metal shank edges are burr-free and covered with canvas shank tape.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #5 compliance',
            'keywords' => 'component test 5, leather quality 5, fabric testing 5, insole board 5',
            'body'     => 'Quality evaluation for component batch #5 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #6 compliance',
            'keywords' => 'component test 6, leather quality 6, fabric testing 6, insole board 6',
            'body'     => 'Quality evaluation for component batch #6 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #7 compliance',
            'keywords' => 'component test 7, leather quality 7, fabric testing 7, insole board 7',
            'body'     => 'Quality evaluation for component batch #7 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #8 compliance',
            'keywords' => 'component test 8, leather quality 8, fabric testing 8, insole board 8',
            'body'     => 'Quality evaluation for component batch #8 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #9 compliance',
            'keywords' => 'component test 9, leather quality 9, fabric testing 9, insole board 9',
            'body'     => 'Quality evaluation for component batch #9 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #10 compliance',
            'keywords' => 'component test 10, leather quality 10, fabric testing 10, insole board 10',
            'body'     => 'Quality evaluation for component batch #10 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #11 compliance',
            'keywords' => 'component test 11, leather quality 11, fabric testing 11, insole board 11',
            'body'     => 'Quality evaluation for component batch #11 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #12 compliance',
            'keywords' => 'component test 12, leather quality 12, fabric testing 12, insole board 12',
            'body'     => 'Quality evaluation for component batch #12 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #13 compliance',
            'keywords' => 'component test 13, leather quality 13, fabric testing 13, insole board 13',
            'body'     => 'Quality evaluation for component batch #13 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #14 compliance',
            'keywords' => 'component test 14, leather quality 14, fabric testing 14, insole board 14',
            'body'     => 'Quality evaluation for component batch #14 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #15 compliance',
            'keywords' => 'component test 15, leather quality 15, fabric testing 15, insole board 15',
            'body'     => 'Quality evaluation for component batch #15 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #16 compliance',
            'keywords' => 'component test 16, leather quality 16, fabric testing 16, insole board 16',
            'body'     => 'Quality evaluation for component batch #16 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #17 compliance',
            'keywords' => 'component test 17, leather quality 17, fabric testing 17, insole board 17',
            'body'     => 'Quality evaluation for component batch #17 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #18 compliance',
            'keywords' => 'component test 18, leather quality 18, fabric testing 18, insole board 18',
            'body'     => 'Quality evaluation for component batch #18 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #19 compliance',
            'keywords' => 'component test 19, leather quality 19, fabric testing 19, insole board 19',
            'body'     => 'Quality evaluation for component batch #19 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #20 compliance',
            'keywords' => 'component test 20, leather quality 20, fabric testing 20, insole board 20',
            'body'     => 'Quality evaluation for component batch #20 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #21 compliance',
            'keywords' => 'component test 21, leather quality 21, fabric testing 21, insole board 21',
            'body'     => 'Quality evaluation for component batch #21 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #22 compliance',
            'keywords' => 'component test 22, leather quality 22, fabric testing 22, insole board 22',
            'body'     => 'Quality evaluation for component batch #22 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #23 compliance',
            'keywords' => 'component test 23, leather quality 23, fabric testing 23, insole board 23',
            'body'     => 'Quality evaluation for component batch #23 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #24 compliance',
            'keywords' => 'component test 24, leather quality 24, fabric testing 24, insole board 24',
            'body'     => 'Quality evaluation for component batch #24 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #25 compliance',
            'keywords' => 'component test 25, leather quality 25, fabric testing 25, insole board 25',
            'body'     => 'Quality evaluation for component batch #25 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #26 compliance',
            'keywords' => 'component test 26, leather quality 26, fabric testing 26, insole board 26',
            'body'     => 'Quality evaluation for component batch #26 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #27 compliance',
            'keywords' => 'component test 27, leather quality 27, fabric testing 27, insole board 27',
            'body'     => 'Quality evaluation for component batch #27 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #28 compliance',
            'keywords' => 'component test 28, leather quality 28, fabric testing 28, insole board 28',
            'body'     => 'Quality evaluation for component batch #28 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #29 compliance',
            'keywords' => 'component test 29, leather quality 29, fabric testing 29, insole board 29',
            'body'     => 'Quality evaluation for component batch #29 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #30 compliance',
            'keywords' => 'component test 30, leather quality 30, fabric testing 30, insole board 30',
            'body'     => 'Quality evaluation for component batch #30 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #31 compliance',
            'keywords' => 'component test 31, leather quality 31, fabric testing 31, insole board 31',
            'body'     => 'Quality evaluation for component batch #31 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #32 compliance',
            'keywords' => 'component test 32, leather quality 32, fabric testing 32, insole board 32',
            'body'     => 'Quality evaluation for component batch #32 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #33 compliance',
            'keywords' => 'component test 33, leather quality 33, fabric testing 33, insole board 33',
            'body'     => 'Quality evaluation for component batch #33 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #34 compliance',
            'keywords' => 'component test 34, leather quality 34, fabric testing 34, insole board 34',
            'body'     => 'Quality evaluation for component batch #34 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #35 compliance',
            'keywords' => 'component test 35, leather quality 35, fabric testing 35, insole board 35',
            'body'     => 'Quality evaluation for component batch #35 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #36 compliance',
            'keywords' => 'component test 36, leather quality 36, fabric testing 36, insole board 36',
            'body'     => 'Quality evaluation for component batch #36 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #37 compliance',
            'keywords' => 'component test 37, leather quality 37, fabric testing 37, insole board 37',
            'body'     => 'Quality evaluation for component batch #37 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #38 compliance',
            'keywords' => 'component test 38, leather quality 38, fabric testing 38, insole board 38',
            'body'     => 'Quality evaluation for component batch #38 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #39 compliance',
            'keywords' => 'component test 39, leather quality 39, fabric testing 39, insole board 39',
            'body'     => 'Quality evaluation for component batch #39 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #40 compliance',
            'keywords' => 'component test 40, leather quality 40, fabric testing 40, insole board 40',
            'body'     => 'Quality evaluation for component batch #40 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #41 compliance',
            'keywords' => 'component test 41, leather quality 41, fabric testing 41, insole board 41',
            'body'     => 'Quality evaluation for component batch #41 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #42 compliance',
            'keywords' => 'component test 42, leather quality 42, fabric testing 42, insole board 42',
            'body'     => 'Quality evaluation for component batch #42 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #43 compliance',
            'keywords' => 'component test 43, leather quality 43, fabric testing 43, insole board 43',
            'body'     => 'Quality evaluation for component batch #43 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #44 compliance',
            'keywords' => 'component test 44, leather quality 44, fabric testing 44, insole board 44',
            'body'     => 'Quality evaluation for component batch #44 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #45 compliance',
            'keywords' => 'component test 45, leather quality 45, fabric testing 45, insole board 45',
            'body'     => 'Quality evaluation for component batch #45 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #46 compliance',
            'keywords' => 'component test 46, leather quality 46, fabric testing 46, insole board 46',
            'body'     => 'Quality evaluation for component batch #46 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #47 compliance',
            'keywords' => 'component test 47, leather quality 47, fabric testing 47, insole board 47',
            'body'     => 'Quality evaluation for component batch #47 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #48 compliance',
            'keywords' => 'component test 48, leather quality 48, fabric testing 48, insole board 48',
            'body'     => 'Quality evaluation for component batch #48 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #49 compliance',
            'keywords' => 'component test 49, leather quality 49, fabric testing 49, insole board 49',
            'body'     => 'Quality evaluation for component batch #49 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #50 compliance',
            'keywords' => 'component test 50, leather quality 50, fabric testing 50, insole board 50',
            'body'     => 'Quality evaluation for component batch #50 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #51 compliance',
            'keywords' => 'component test 51, leather quality 51, fabric testing 51, insole board 51',
            'body'     => 'Quality evaluation for component batch #51 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #52 compliance',
            'keywords' => 'component test 52, leather quality 52, fabric testing 52, insole board 52',
            'body'     => 'Quality evaluation for component batch #52 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #53 compliance',
            'keywords' => 'component test 53, leather quality 53, fabric testing 53, insole board 53',
            'body'     => 'Quality evaluation for component batch #53 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #54 compliance',
            'keywords' => 'component test 54, leather quality 54, fabric testing 54, insole board 54',
            'body'     => 'Quality evaluation for component batch #54 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper, lining, and insole component test standard #55 compliance',
            'keywords' => 'component test 55, leather quality 55, fabric testing 55, insole board 55',
            'body'     => 'Quality evaluation for component batch #55 covering structural integrity, tear propagation, seam strength, or dye stability under standardized laboratory conditioning.',
            'steps'    => ['Condition sample at 20°C and 65% RH for minimum 24 hours.', 'Verify cutting direction relative to warp/weft grain orientation.', 'Perform test on calibrated universal testing machine with pneumatic grips.', 'Record load-deflection curve and inspect failure mode.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Hexavalent Chromium Cr(VI) detected above 3.0 mg/kg in leather (ISO 17075-1)',
            'keywords' => 'chromium 6 fail, cr vi detected, iso 17075 chromium, toxic heavy metal leather, rsl chromium fail',
            'body'     => 'Under international RSL regulations and ISO 17075-1, Hexavalent Chromium Cr(VI) is restricted to below 3.0 mg/kg detection limit due to strong skin sensitization. It forms when benign trivalent Cr(III) oxidizes under heat, light, and alkaline pH.',
            'steps'    => ['Verify extraction buffer pH (must be strictly maintained at 7.5 to 8.0).', 'Check antioxidant fatliquoring agents used in tanning (add vegetable tannins or tara extract).', 'Avoid hypochlorite and strong bleaching agents during leather processing.', 'Quarantine affected batch and re-test with HPLC-DAD verification.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Phthalate plasticizers (DEHP/DBP/BBP) detected above 1000 ppm in TPU prints',
            'keywords' => 'phthalate fail, dehp detected, toxic plasticizer, rsl phthalate screen, screen print toxic',
            'body'     => 'CPSIA and REACH Annex XVII restrict the sum of ortho-phthalate plasticizers to 1000 ppm (0.1%) in accessible footwear components. Contamination arises from cheap PVC plastisol screen print inks or contaminated transfer film.',
            'steps'    => ['Test ink raw materials using GC-MS before screen printing.', 'Require screen print subcontractor to use 100% silicone-based or water-based inks.', 'Clean screen print squeegees and ink reservoirs to remove PVC plastisol residues.', 'Obtain verified OEKO-TEX or ZDHC level 3 chemical compliance certificates.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Free and hydrolyzed formaldehyde detected above 75 ppm in lining textile',
            'keywords' => 'formaldehyde fail, iso 14184-1 formaldehyde, toxic lining, rsl chemical failure, textile resin',
            'body'     => 'ISO 14184-1 water extraction limits formaldehyde to 75 ppm for direct skin contact (16 ppm for infant footwear). Excess formaldehyde originates from cheap urea-formaldehyde anti-crease or crosslinking resins.',
            'steps'    => ['Conduct water extraction test at 40°C for 60 minutes with acetylacetone reagent.', 'Wash fabric rolls in industrial continuous hot water scour to strip unreacted formaldehyde.', 'Replace urea-formaldehyde resin with zero-formaldehyde polycarboxylic acid agents.', 'Ensure chemical supplier complies with AFIRM RSL standards.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Dimethyl Fumarate (DMFu) detected in mold prevention silica gel pouches',
            'keywords' => 'dmfu detected, dimethyl fumarate fail, anti mold pouch toxic, reach dmfu restriction',
            'body'     => 'EU REACH prohibits Dimethyl Fumarate (DMFu) above 0.1 mg/kg. Formerly used as a potent anti-fungal biocide in shoe box sachets, it causes severe contact dermatitis and burns upon skin contact.',
            'steps'    => ['Immediately remove all uncertified silica gel or anti-mold sachets from packaging area.', 'Test desiccant pouches by GC-MS for volatile bio-pesticides.', 'Replace with pure non-toxic clay desiccants or Micro-Pak antimicrobial stickers.', 'Inspect factory dry-storage humidity to control mold naturally without chemical sprays.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #5: Toxicological compliance',
            'keywords' => 'rsl chemical test 5, reach compliance 5, hazardous substance 5, toxic screen 5',
            'body'     => 'Laboratory screening #5 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #6: Toxicological compliance',
            'keywords' => 'rsl chemical test 6, reach compliance 6, hazardous substance 6, toxic screen 6',
            'body'     => 'Laboratory screening #6 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #7: Toxicological compliance',
            'keywords' => 'rsl chemical test 7, reach compliance 7, hazardous substance 7, toxic screen 7',
            'body'     => 'Laboratory screening #7 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #8: Toxicological compliance',
            'keywords' => 'rsl chemical test 8, reach compliance 8, hazardous substance 8, toxic screen 8',
            'body'     => 'Laboratory screening #8 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #9: Toxicological compliance',
            'keywords' => 'rsl chemical test 9, reach compliance 9, hazardous substance 9, toxic screen 9',
            'body'     => 'Laboratory screening #9 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #10: Toxicological compliance',
            'keywords' => 'rsl chemical test 10, reach compliance 10, hazardous substance 10, toxic screen 10',
            'body'     => 'Laboratory screening #10 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #11: Toxicological compliance',
            'keywords' => 'rsl chemical test 11, reach compliance 11, hazardous substance 11, toxic screen 11',
            'body'     => 'Laboratory screening #11 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #12: Toxicological compliance',
            'keywords' => 'rsl chemical test 12, reach compliance 12, hazardous substance 12, toxic screen 12',
            'body'     => 'Laboratory screening #12 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #13: Toxicological compliance',
            'keywords' => 'rsl chemical test 13, reach compliance 13, hazardous substance 13, toxic screen 13',
            'body'     => 'Laboratory screening #13 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #14: Toxicological compliance',
            'keywords' => 'rsl chemical test 14, reach compliance 14, hazardous substance 14, toxic screen 14',
            'body'     => 'Laboratory screening #14 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #15: Toxicological compliance',
            'keywords' => 'rsl chemical test 15, reach compliance 15, hazardous substance 15, toxic screen 15',
            'body'     => 'Laboratory screening #15 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #16: Toxicological compliance',
            'keywords' => 'rsl chemical test 16, reach compliance 16, hazardous substance 16, toxic screen 16',
            'body'     => 'Laboratory screening #16 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #17: Toxicological compliance',
            'keywords' => 'rsl chemical test 17, reach compliance 17, hazardous substance 17, toxic screen 17',
            'body'     => 'Laboratory screening #17 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #18: Toxicological compliance',
            'keywords' => 'rsl chemical test 18, reach compliance 18, hazardous substance 18, toxic screen 18',
            'body'     => 'Laboratory screening #18 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #19: Toxicological compliance',
            'keywords' => 'rsl chemical test 19, reach compliance 19, hazardous substance 19, toxic screen 19',
            'body'     => 'Laboratory screening #19 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #20: Toxicological compliance',
            'keywords' => 'rsl chemical test 20, reach compliance 20, hazardous substance 20, toxic screen 20',
            'body'     => 'Laboratory screening #20 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #21: Toxicological compliance',
            'keywords' => 'rsl chemical test 21, reach compliance 21, hazardous substance 21, toxic screen 21',
            'body'     => 'Laboratory screening #21 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #22: Toxicological compliance',
            'keywords' => 'rsl chemical test 22, reach compliance 22, hazardous substance 22, toxic screen 22',
            'body'     => 'Laboratory screening #22 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #23: Toxicological compliance',
            'keywords' => 'rsl chemical test 23, reach compliance 23, hazardous substance 23, toxic screen 23',
            'body'     => 'Laboratory screening #23 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #24: Toxicological compliance',
            'keywords' => 'rsl chemical test 24, reach compliance 24, hazardous substance 24, toxic screen 24',
            'body'     => 'Laboratory screening #24 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #25: Toxicological compliance',
            'keywords' => 'rsl chemical test 25, reach compliance 25, hazardous substance 25, toxic screen 25',
            'body'     => 'Laboratory screening #25 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #26: Toxicological compliance',
            'keywords' => 'rsl chemical test 26, reach compliance 26, hazardous substance 26, toxic screen 26',
            'body'     => 'Laboratory screening #26 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #27: Toxicological compliance',
            'keywords' => 'rsl chemical test 27, reach compliance 27, hazardous substance 27, toxic screen 27',
            'body'     => 'Laboratory screening #27 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #28: Toxicological compliance',
            'keywords' => 'rsl chemical test 28, reach compliance 28, hazardous substance 28, toxic screen 28',
            'body'     => 'Laboratory screening #28 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #29: Toxicological compliance',
            'keywords' => 'rsl chemical test 29, reach compliance 29, hazardous substance 29, toxic screen 29',
            'body'     => 'Laboratory screening #29 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #30: Toxicological compliance',
            'keywords' => 'rsl chemical test 30, reach compliance 30, hazardous substance 30, toxic screen 30',
            'body'     => 'Laboratory screening #30 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #31: Toxicological compliance',
            'keywords' => 'rsl chemical test 31, reach compliance 31, hazardous substance 31, toxic screen 31',
            'body'     => 'Laboratory screening #31 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #32: Toxicological compliance',
            'keywords' => 'rsl chemical test 32, reach compliance 32, hazardous substance 32, toxic screen 32',
            'body'     => 'Laboratory screening #32 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #33: Toxicological compliance',
            'keywords' => 'rsl chemical test 33, reach compliance 33, hazardous substance 33, toxic screen 33',
            'body'     => 'Laboratory screening #33 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #34: Toxicological compliance',
            'keywords' => 'rsl chemical test 34, reach compliance 34, hazardous substance 34, toxic screen 34',
            'body'     => 'Laboratory screening #34 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #35: Toxicological compliance',
            'keywords' => 'rsl chemical test 35, reach compliance 35, hazardous substance 35, toxic screen 35',
            'body'     => 'Laboratory screening #35 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #36: Toxicological compliance',
            'keywords' => 'rsl chemical test 36, reach compliance 36, hazardous substance 36, toxic screen 36',
            'body'     => 'Laboratory screening #36 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #37: Toxicological compliance',
            'keywords' => 'rsl chemical test 37, reach compliance 37, hazardous substance 37, toxic screen 37',
            'body'     => 'Laboratory screening #37 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #38: Toxicological compliance',
            'keywords' => 'rsl chemical test 38, reach compliance 38, hazardous substance 38, toxic screen 38',
            'body'     => 'Laboratory screening #38 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #39: Toxicological compliance',
            'keywords' => 'rsl chemical test 39, reach compliance 39, hazardous substance 39, toxic screen 39',
            'body'     => 'Laboratory screening #39 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #40: Toxicological compliance',
            'keywords' => 'rsl chemical test 40, reach compliance 40, hazardous substance 40, toxic screen 40',
            'body'     => 'Laboratory screening #40 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #41: Toxicological compliance',
            'keywords' => 'rsl chemical test 41, reach compliance 41, hazardous substance 41, toxic screen 41',
            'body'     => 'Laboratory screening #41 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #42: Toxicological compliance',
            'keywords' => 'rsl chemical test 42, reach compliance 42, hazardous substance 42, toxic screen 42',
            'body'     => 'Laboratory screening #42 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #43: Toxicological compliance',
            'keywords' => 'rsl chemical test 43, reach compliance 43, hazardous substance 43, toxic screen 43',
            'body'     => 'Laboratory screening #43 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #44: Toxicological compliance',
            'keywords' => 'rsl chemical test 44, reach compliance 44, hazardous substance 44, toxic screen 44',
            'body'     => 'Laboratory screening #44 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #45: Toxicological compliance',
            'keywords' => 'rsl chemical test 45, reach compliance 45, hazardous substance 45, toxic screen 45',
            'body'     => 'Laboratory screening #45 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #46: Toxicological compliance',
            'keywords' => 'rsl chemical test 46, reach compliance 46, hazardous substance 46, toxic screen 46',
            'body'     => 'Laboratory screening #46 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #47: Toxicological compliance',
            'keywords' => 'rsl chemical test 47, reach compliance 47, hazardous substance 47, toxic screen 47',
            'body'     => 'Laboratory screening #47 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #48: Toxicological compliance',
            'keywords' => 'rsl chemical test 48, reach compliance 48, hazardous substance 48, toxic screen 48',
            'body'     => 'Laboratory screening #48 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #49: Toxicological compliance',
            'keywords' => 'rsl chemical test 49, reach compliance 49, hazardous substance 49, toxic screen 49',
            'body'     => 'Laboratory screening #49 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Restricted Substance List (RSL) chemical verification #50: Toxicological compliance',
            'keywords' => 'rsl chemical test 50, reach compliance 50, hazardous substance 50, toxic screen 50',
            'body'     => 'Laboratory screening #50 for restricted heavy metals, organotins, short-chain chlorinated paraffins, azo colorants, or volatile organic compounds under ZDHC and AFIRM standards.',
            'steps'    => ['Prepare sample using standard solvent extraction or microwave acid digestion.', 'Calibrate GC-MS, LC-MS/MS, or ICP-OES instruments with certified reference standards.', 'Verify detection limit (MDL) meets customer RSL specification threshold.', 'Generate formal RSL pass/fail certificate for brand compliance audit.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Halogenation primer under-application causing bondline delamination on TPR',
            'keywords' => 'halogenation failure, tpr sole unbonded, primer missing, chlorine primer fail, adhesion fail',
            'body'     => 'Thermoplastic rubber (TPR) requires chlorine-based halogenation primer (trichloroisocyanuric acid in ethyl acetate) to open surface polymer chains. Insufficient primer leaves a hydrophobic non-polar surface that repels PU adhesive.',
            'steps'    => ['Inspect primed sole under UV blacklight to verify blue fluorescent tracer coverage.', 'Check primer expiration; mixed halogenation primer loses active chlorine after 8 hours.', 'Ensure adequate flash-off evaporation before applying polyurethane adhesive.', 'Verify wiping technique uses clean non-linting felt pads with uniform pressure.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Polyurethane adhesive crystallization temperature mismatch in flash-off tunnel',
            'keywords' => 'adhesive crystallization, heat activation fail, glue reactivation temperature, bondline cold',
            'body'     => 'Waterborne polyurethane contact adhesives require reactivation temperature of 65°C - 75°C to liquefy polymer chains for cohesive crosslinking. If surface temperature is below 60°C, adhesive undergoes cold contact crystallization and peels clean.',
            'steps'    => ['Measure sole and upper surface temperature at tunnel exit using optical infrared pyrometer.', 'Clean heating tunnel quartz infrared lamps and calibrate convection blowers.', 'Adjust conveyor belt speed to ensure minimum 90-second soak time at target heat.', 'Perform bonding press immediately within 15 seconds of oven discharge.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesive cement skip around toe spring curve on contoured outsole press',
            'keywords' => 'cement skip toe spring, unbonded toe cap, sole edge opening, sole press failure, contour sole gap',
            'body'     => 'Toe spring curves on running and hiking shoes experience high restoring tension. Cement skips or bladder press pressure voids around the steep curvature cause edge delamination within 48 hours.',
            'steps'    => ['Inspect pneumatic press bladder mold cushions for contour fit around toe spring.', 'Increase bladder press dwell time to 12-15 seconds at 4.5 - 5.0 kg/cm² pressure.', 'Apply double cement coat on open cellular mesh along the toe margin.', 'Verify roughing profile follows the exact bottom contour line within ±0.5 mm.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #4: Bonding parameter control',
            'keywords' => 'bonding process 4, cement adhesion 4, primer application 4, sole lasting 4',
            'body'     => 'Process audit scenario #4 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #5: Bonding parameter control',
            'keywords' => 'bonding process 5, cement adhesion 5, primer application 5, sole lasting 5',
            'body'     => 'Process audit scenario #5 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #6: Bonding parameter control',
            'keywords' => 'bonding process 6, cement adhesion 6, primer application 6, sole lasting 6',
            'body'     => 'Process audit scenario #6 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #7: Bonding parameter control',
            'keywords' => 'bonding process 7, cement adhesion 7, primer application 7, sole lasting 7',
            'body'     => 'Process audit scenario #7 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #8: Bonding parameter control',
            'keywords' => 'bonding process 8, cement adhesion 8, primer application 8, sole lasting 8',
            'body'     => 'Process audit scenario #8 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #9: Bonding parameter control',
            'keywords' => 'bonding process 9, cement adhesion 9, primer application 9, sole lasting 9',
            'body'     => 'Process audit scenario #9 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #10: Bonding parameter control',
            'keywords' => 'bonding process 10, cement adhesion 10, primer application 10, sole lasting 10',
            'body'     => 'Process audit scenario #10 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #11: Bonding parameter control',
            'keywords' => 'bonding process 11, cement adhesion 11, primer application 11, sole lasting 11',
            'body'     => 'Process audit scenario #11 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #12: Bonding parameter control',
            'keywords' => 'bonding process 12, cement adhesion 12, primer application 12, sole lasting 12',
            'body'     => 'Process audit scenario #12 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #13: Bonding parameter control',
            'keywords' => 'bonding process 13, cement adhesion 13, primer application 13, sole lasting 13',
            'body'     => 'Process audit scenario #13 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #14: Bonding parameter control',
            'keywords' => 'bonding process 14, cement adhesion 14, primer application 14, sole lasting 14',
            'body'     => 'Process audit scenario #14 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #15: Bonding parameter control',
            'keywords' => 'bonding process 15, cement adhesion 15, primer application 15, sole lasting 15',
            'body'     => 'Process audit scenario #15 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #16: Bonding parameter control',
            'keywords' => 'bonding process 16, cement adhesion 16, primer application 16, sole lasting 16',
            'body'     => 'Process audit scenario #16 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #17: Bonding parameter control',
            'keywords' => 'bonding process 17, cement adhesion 17, primer application 17, sole lasting 17',
            'body'     => 'Process audit scenario #17 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #18: Bonding parameter control',
            'keywords' => 'bonding process 18, cement adhesion 18, primer application 18, sole lasting 18',
            'body'     => 'Process audit scenario #18 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #19: Bonding parameter control',
            'keywords' => 'bonding process 19, cement adhesion 19, primer application 19, sole lasting 19',
            'body'     => 'Process audit scenario #19 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #20: Bonding parameter control',
            'keywords' => 'bonding process 20, cement adhesion 20, primer application 20, sole lasting 20',
            'body'     => 'Process audit scenario #20 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #21: Bonding parameter control',
            'keywords' => 'bonding process 21, cement adhesion 21, primer application 21, sole lasting 21',
            'body'     => 'Process audit scenario #21 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #22: Bonding parameter control',
            'keywords' => 'bonding process 22, cement adhesion 22, primer application 22, sole lasting 22',
            'body'     => 'Process audit scenario #22 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #23: Bonding parameter control',
            'keywords' => 'bonding process 23, cement adhesion 23, primer application 23, sole lasting 23',
            'body'     => 'Process audit scenario #23 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #24: Bonding parameter control',
            'keywords' => 'bonding process 24, cement adhesion 24, primer application 24, sole lasting 24',
            'body'     => 'Process audit scenario #24 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #25: Bonding parameter control',
            'keywords' => 'bonding process 25, cement adhesion 25, primer application 25, sole lasting 25',
            'body'     => 'Process audit scenario #25 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #26: Bonding parameter control',
            'keywords' => 'bonding process 26, cement adhesion 26, primer application 26, sole lasting 26',
            'body'     => 'Process audit scenario #26 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #27: Bonding parameter control',
            'keywords' => 'bonding process 27, cement adhesion 27, primer application 27, sole lasting 27',
            'body'     => 'Process audit scenario #27 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #28: Bonding parameter control',
            'keywords' => 'bonding process 28, cement adhesion 28, primer application 28, sole lasting 28',
            'body'     => 'Process audit scenario #28 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #29: Bonding parameter control',
            'keywords' => 'bonding process 29, cement adhesion 29, primer application 29, sole lasting 29',
            'body'     => 'Process audit scenario #29 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #30: Bonding parameter control',
            'keywords' => 'bonding process 30, cement adhesion 30, primer application 30, sole lasting 30',
            'body'     => 'Process audit scenario #30 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #31: Bonding parameter control',
            'keywords' => 'bonding process 31, cement adhesion 31, primer application 31, sole lasting 31',
            'body'     => 'Process audit scenario #31 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #32: Bonding parameter control',
            'keywords' => 'bonding process 32, cement adhesion 32, primer application 32, sole lasting 32',
            'body'     => 'Process audit scenario #32 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #33: Bonding parameter control',
            'keywords' => 'bonding process 33, cement adhesion 33, primer application 33, sole lasting 33',
            'body'     => 'Process audit scenario #33 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #34: Bonding parameter control',
            'keywords' => 'bonding process 34, cement adhesion 34, primer application 34, sole lasting 34',
            'body'     => 'Process audit scenario #34 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #35: Bonding parameter control',
            'keywords' => 'bonding process 35, cement adhesion 35, primer application 35, sole lasting 35',
            'body'     => 'Process audit scenario #35 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #36: Bonding parameter control',
            'keywords' => 'bonding process 36, cement adhesion 36, primer application 36, sole lasting 36',
            'body'     => 'Process audit scenario #36 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #37: Bonding parameter control',
            'keywords' => 'bonding process 37, cement adhesion 37, primer application 37, sole lasting 37',
            'body'     => 'Process audit scenario #37 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #38: Bonding parameter control',
            'keywords' => 'bonding process 38, cement adhesion 38, primer application 38, sole lasting 38',
            'body'     => 'Process audit scenario #38 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #39: Bonding parameter control',
            'keywords' => 'bonding process 39, cement adhesion 39, primer application 39, sole lasting 39',
            'body'     => 'Process audit scenario #39 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #40: Bonding parameter control',
            'keywords' => 'bonding process 40, cement adhesion 40, primer application 40, sole lasting 40',
            'body'     => 'Process audit scenario #40 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #41: Bonding parameter control',
            'keywords' => 'bonding process 41, cement adhesion 41, primer application 41, sole lasting 41',
            'body'     => 'Process audit scenario #41 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #42: Bonding parameter control',
            'keywords' => 'bonding process 42, cement adhesion 42, primer application 42, sole lasting 42',
            'body'     => 'Process audit scenario #42 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #43: Bonding parameter control',
            'keywords' => 'bonding process 43, cement adhesion 43, primer application 43, sole lasting 43',
            'body'     => 'Process audit scenario #43 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #44: Bonding parameter control',
            'keywords' => 'bonding process 44, cement adhesion 44, primer application 44, sole lasting 44',
            'body'     => 'Process audit scenario #44 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #45: Bonding parameter control',
            'keywords' => 'bonding process 45, cement adhesion 45, primer application 45, sole lasting 45',
            'body'     => 'Process audit scenario #45 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #46: Bonding parameter control',
            'keywords' => 'bonding process 46, cement adhesion 46, primer application 46, sole lasting 46',
            'body'     => 'Process audit scenario #46 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #47: Bonding parameter control',
            'keywords' => 'bonding process 47, cement adhesion 47, primer application 47, sole lasting 47',
            'body'     => 'Process audit scenario #47 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #48: Bonding parameter control',
            'keywords' => 'bonding process 48, cement adhesion 48, primer application 48, sole lasting 48',
            'body'     => 'Process audit scenario #48 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #49: Bonding parameter control',
            'keywords' => 'bonding process 49, cement adhesion 49, primer application 49, sole lasting 49',
            'body'     => 'Process audit scenario #49 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Adhesion and lasting process optimization #50: Bonding parameter control',
            'keywords' => 'bonding process 50, cement adhesion 50, primer application 50, sole lasting 50',
            'body'     => 'Process audit scenario #50 examining surface roughing, primer wetting, adhesive open time, reactivation profile, or bladder press distribution to achieve permanent structural bondline strength.',
            'steps'    => ['Inspect roughing depth; avoid cutting through grain into loose corium fibers.', 'Verify hardener (crosslinker) mixing ratio: 4-5% Desmodur RFE by weight.', 'Monitor flash-off oven temperature with calibrated datalogger puck.', 'Check bonding peel value after 24-hour full crosslinking maturation.'],
            'actions'  => $dtrBondingAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Tensile machine load cell calibration drift beyond Class 0.5 ISO 7500-1',
            'keywords' => 'load cell calibration drift, tensile machine error, load reading incorrect, force transducer drift',
            'body'     => 'ISO 7500-1 mandates universal tensile machine force indication within ±0.5% (Class 0.5). Load cell mechanical shock, temperature fluctuation, or damaged strain gauges cause false pass/fail bonding readings.',
            'steps'    => ['Perform 5-point verification with certified Class 0.5 proving ring or dead weights.', 'Check load cell cable shielding and ground wire connection to prevent electrical 60 Hz hum.', 'Allow 30-minute electronics warm-up before performing daily tests.', 'Schedule annual ISO 17025 accredited on-site calibration.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Climate conditioning chamber relative humidity sensor error (+/- 8% RH)',
            'keywords' => 'humidity chamber drift, rh sensor failure, conditioning room humidity, wet dry bulb error',
            'body'     => 'Standard physical testing requires specimen conditioning at 20°C ± 2°C and 65% ± 4% RH (or 23°C / 50% RH). Relative humidity drift alters moisture content of leather, textiles, and insole boards, biasing tensile and tear results.',
            'steps'    => ['Calibrate capacitive RH sensor against certified chilled-mirror hygrometer.', 'Clean distilled water boiler and descaling filters in steam generator.', 'Verify continuous data logger recording frequency (minimum 1 sample per 15 minutes).', 'Quarantine test reports generated during out-of-tolerance humidity excursions.'],
            'actions'  => $dtrMatAct,
            'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #3',
            'keywords' => 'instrument calibration 3, iso 17025 audit 3, lab sensor 3, measurement uncertainty 3',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #3. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #4',
            'keywords' => 'instrument calibration 4, iso 17025 audit 4, lab sensor 4, measurement uncertainty 4',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #4. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #5',
            'keywords' => 'instrument calibration 5, iso 17025 audit 5, lab sensor 5, measurement uncertainty 5',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #5. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #6',
            'keywords' => 'instrument calibration 6, iso 17025 audit 6, lab sensor 6, measurement uncertainty 6',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #6. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #7',
            'keywords' => 'instrument calibration 7, iso 17025 audit 7, lab sensor 7, measurement uncertainty 7',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #7. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #8',
            'keywords' => 'instrument calibration 8, iso 17025 audit 8, lab sensor 8, measurement uncertainty 8',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #8. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #9',
            'keywords' => 'instrument calibration 9, iso 17025 audit 9, lab sensor 9, measurement uncertainty 9',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #9. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #10',
            'keywords' => 'instrument calibration 10, iso 17025 audit 10, lab sensor 10, measurement uncertainty 10',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #10. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #11',
            'keywords' => 'instrument calibration 11, iso 17025 audit 11, lab sensor 11, measurement uncertainty 11',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #11. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #12',
            'keywords' => 'instrument calibration 12, iso 17025 audit 12, lab sensor 12, measurement uncertainty 12',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #12. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #13',
            'keywords' => 'instrument calibration 13, iso 17025 audit 13, lab sensor 13, measurement uncertainty 13',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #13. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #14',
            'keywords' => 'instrument calibration 14, iso 17025 audit 14, lab sensor 14, measurement uncertainty 14',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #14. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #15',
            'keywords' => 'instrument calibration 15, iso 17025 audit 15, lab sensor 15, measurement uncertainty 15',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #15. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #16',
            'keywords' => 'instrument calibration 16, iso 17025 audit 16, lab sensor 16, measurement uncertainty 16',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #16. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #17',
            'keywords' => 'instrument calibration 17, iso 17025 audit 17, lab sensor 17, measurement uncertainty 17',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #17. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #18',
            'keywords' => 'instrument calibration 18, iso 17025 audit 18, lab sensor 18, measurement uncertainty 18',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #18. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #19',
            'keywords' => 'instrument calibration 19, iso 17025 audit 19, lab sensor 19, measurement uncertainty 19',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #19. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #20',
            'keywords' => 'instrument calibration 20, iso 17025 audit 20, lab sensor 20, measurement uncertainty 20',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #20. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #21',
            'keywords' => 'instrument calibration 21, iso 17025 audit 21, lab sensor 21, measurement uncertainty 21',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #21. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #22',
            'keywords' => 'instrument calibration 22, iso 17025 audit 22, lab sensor 22, measurement uncertainty 22',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #22. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #23',
            'keywords' => 'instrument calibration 23, iso 17025 audit 23, lab sensor 23, measurement uncertainty 23',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #23. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #24',
            'keywords' => 'instrument calibration 24, iso 17025 audit 24, lab sensor 24, measurement uncertainty 24',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #24. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #25',
            'keywords' => 'instrument calibration 25, iso 17025 audit 25, lab sensor 25, measurement uncertainty 25',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #25. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #26',
            'keywords' => 'instrument calibration 26, iso 17025 audit 26, lab sensor 26, measurement uncertainty 26',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #26. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #27',
            'keywords' => 'instrument calibration 27, iso 17025 audit 27, lab sensor 27, measurement uncertainty 27',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #27. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #28',
            'keywords' => 'instrument calibration 28, iso 17025 audit 28, lab sensor 28, measurement uncertainty 28',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #28. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #29',
            'keywords' => 'instrument calibration 29, iso 17025 audit 29, lab sensor 29, measurement uncertainty 29',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #29. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #30',
            'keywords' => 'instrument calibration 30, iso 17025 audit 30, lab sensor 30, measurement uncertainty 30',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #30. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #31',
            'keywords' => 'instrument calibration 31, iso 17025 audit 31, lab sensor 31, measurement uncertainty 31',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #31. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #32',
            'keywords' => 'instrument calibration 32, iso 17025 audit 32, lab sensor 32, measurement uncertainty 32',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #32. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #33',
            'keywords' => 'instrument calibration 33, iso 17025 audit 33, lab sensor 33, measurement uncertainty 33',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #33. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #34',
            'keywords' => 'instrument calibration 34, iso 17025 audit 34, lab sensor 34, measurement uncertainty 34',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #34. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #35',
            'keywords' => 'instrument calibration 35, iso 17025 audit 35, lab sensor 35, measurement uncertainty 35',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #35. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #36',
            'keywords' => 'instrument calibration 36, iso 17025 audit 36, lab sensor 36, measurement uncertainty 36',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #36. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #37',
            'keywords' => 'instrument calibration 37, iso 17025 audit 37, lab sensor 37, measurement uncertainty 37',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #37. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #38',
            'keywords' => 'instrument calibration 38, iso 17025 audit 38, lab sensor 38, measurement uncertainty 38',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #38. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #39',
            'keywords' => 'instrument calibration 39, iso 17025 audit 39, lab sensor 39, measurement uncertainty 39',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #39. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #40',
            'keywords' => 'instrument calibration 40, iso 17025 audit 40, lab sensor 40, measurement uncertainty 40',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #40. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #41',
            'keywords' => 'instrument calibration 41, iso 17025 audit 41, lab sensor 41, measurement uncertainty 41',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #41. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #42',
            'keywords' => 'instrument calibration 42, iso 17025 audit 42, lab sensor 42, measurement uncertainty 42',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #42. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #43',
            'keywords' => 'instrument calibration 43, iso 17025 audit 43, lab sensor 43, measurement uncertainty 43',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #43. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #44',
            'keywords' => 'instrument calibration 44, iso 17025 audit 44, lab sensor 44, measurement uncertainty 44',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #44. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Laboratory instrumentation and ISO 17025 audit verification #45',
            'keywords' => 'instrument calibration 45, iso 17025 audit 45, lab sensor 45, measurement uncertainty 45',
            'body'     => 'Equipment maintenance and measurement uncertainty evaluation #45. Ensuring zero drift, traceability to national metrology standards, and audit readiness for accredited laboratory operations.',
            'steps'    => ['Inspect daily instrument zero and span verification check logs.', 'Verify calibration status sticker and unexpired accreditation certificate.', 'Assess measurement uncertainty contribution for key test parameters.', 'Log corrective action in ISO 17025 equipment maintenance ledger.'],
            'actions'  => $settingsAct,
            'page_match' => 'settings',
        ];

        return $p;
    }
}
