<?php

namespace App\Commands;

use App\Libraries\Brain;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class VfMethodsSeed extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'vf:seed';
    protected $description = 'Scans public/uploads/vf_test_methods, populates tbl_vf_test_methods and trains the AI Brain with VF test knowledge.';

    public function run(array $params)
    {
        $db = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');
        
        $baseDir = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'vf_test_methods';
        if (! is_dir($baseDir)) {
            CLI::error("VF Test Methods folder not found at: {$baseDir}");
            return;
        }

        CLI::write("Scanning directory: {$baseDir} ...", 'cyan');

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS));
        
        $inserted = 0;
        $updated = 0;
        $kbCount = 0;

        // Series category mapping
        $categories = [
            'A' => 'Physical & Dimensional Properties',
            'B' => 'Strength & Mechanical Properties',
            'C' => 'Abrasion Resistance',
            'D' => 'Flexing & Fatigue Resistance',
            'E' => 'Chemical, Aging & Environmental Fastness',
            'F' => 'Adhesion & Bond Strength',
            'G' => 'Assembled Footwear Performance',
            'H' => 'Sampling & Material Conditioning',
            'I' => 'Chemical Analysis & Identification',
            'J' => 'Electrical & Thermal Safety',
        ];

        // Detailed knowledge descriptions for prominent VF test methods
        $methodKnowledge = [
            'VF-A01' => [
                'name' => 'Hardness Test',
                'desc' => 'Determines Shore A, Shore C, or Shore D durometer hardness of rubber, soling materials, EVA, PU, and molded components per ASTM D2240.',
                'procedure' => 'Calibrate durometer. Position indentor perpendicular to specimen surface. Apply specified load without shock. Record reading within 1 second for standard, or after 15 seconds for creep.',
                'standards' => 'ASTM D2240, ISO 868, SATRA TM205'
            ],
            'VF-A02' => [
                'name' => 'Weight Determination',
                'desc' => 'Determines unit weight, areal weight (g/m²), or component weight of soling, uppers, textiles, and trims.',
                'procedure' => 'Condition samples in standard atmosphere (23±2°C, 50±5% RH). Measure mass using calibrated precision electronic analytical balance to 0.001g.',
                'standards' => 'ASTM D3776, ISO 3801'
            ],
            'VF-A03' => [
                'name' => 'Thickness Measurement',
                'desc' => 'Measures material thickness of leather, synthetic upper materials, soling, foam, and fabric under specified foot pressure.',
                'procedure' => 'Use calibrated dead-weight thickness dial gauge. Apply presser foot (typically 10mm diameter, 49.1 kPa pressure) gently without pinching.',
                'standards' => 'ASTM D1813, ISO 2589, SATRA TM1'
            ],
            'VF-A04' => [
                'name' => 'Specific Gravity / Density',
                'desc' => 'Measures density and specific gravity of solid rubber, cellular compounds, and polymers using water displacement (Archimedes principle).',
                'procedure' => 'Weigh specimen in air (W1), weigh immersed in distilled water (W2). SG = W1 / (W1 - W2). Ensure no air bubbles cling to sample.',
                'standards' => 'ASTM D792, ISO 1183, SATRA TM68'
            ],
            'VF-A06' => [
                'name' => 'Leather Softness',
                'desc' => 'Measures softness and compressibility of finished leather uppers using standard softness tester.',
                'procedure' => 'Clamp leather over aperture. Apply calibrated circular plunger and measure depth of deflection in millimeters.',
                'standards' => 'ISO 17235, SATRA TM136'
            ],
            'VF-A07' => [
                'name' => 'Yarn Count & Linear Density',
                'desc' => 'Measures yarn numbering system, linear density (tex, denier) for textile shoe components, laces, and threads.',
                'procedure' => 'Reel specified length of yarn under uniform tension. Condition and weigh on microbalance. Calculate Tex or Denier.',
                'standards' => 'ASTM D1907, ISO 2060'
            ],
            'VF-A08' => [
                'name' => 'Non-Volatile Content',
                'desc' => 'Measures solids content and non-volatile percentage of liquid adhesives, primers, inks, and surface coatings.',
                'procedure' => 'Weigh liquid specimen in foil dish. Bake in convection oven at 105±2°C for 2 hours. Cool in desiccator and weigh dry residue.',
                'standards' => 'ASTM D2369, ISO 3251'
            ],
            'VF-B01' => [
                'name' => 'Tensile Strength and Elongation',
                'desc' => 'Determines tensile strength, ultimate elongation at break, and tensile modulus for rubber outsoles, foxing, synthetic leather, textiles, laces, and sewing threads.',
                'procedure' => 'Die-cut dumbbell specimens (Type C or Type IV). Measure thickness. Clamp into tensile grips of UTM. Pull at crosshead speed (500 mm/min for rubber, 100-300 mm/min for synthetic/fabrics). Record max force, tensile strength (MPa / kg/cm²), and % elongation at break.',
                'standards' => 'ASTM D412 (Rubber), ASTM D5034 (Textiles), ISO 37, SATRA TM137'
            ],
            'VF-B02' => [
                'name' => 'Bursting Strength',
                'desc' => 'Measures hydraulic diaphragm or pneumatic bursting strength of woven, knitted, and nonwoven upper fabrics and linings.',
                'procedure' => 'Clamp circular specimen over elastic rubber diaphragm. Apply increasing hydraulic fluid pressure until bursting occurs. Record bursting pressure (kPa/PSI) and tare diaphragm resistance.',
                'standards' => 'ASTM D3786, ISO 13938, SATRA TM170'
            ],
            'VF-B03' => [
                'name' => 'Peel and Shear Strength of Fasteners',
                'desc' => 'Evaluates peel and shear holding strength of hook and loop fasteners (Velcro) and touch and close fasteners.',
                'procedure' => 'Overlap hook and loop under standard roller weight. Measure peel resistance at 180° and shear resistance in longitudinal pull at 300 mm/min.',
                'standards' => 'ASTM D5169, ASTM D5170, SATRA TM123'
            ],
            'VF-B04' => [
                'name' => 'Hardware Attachment & Lace Holder Strength',
                'desc' => 'Tests pull-out, detachment, and tensile breaking strength of eyelets, D-rings, hooks, buckles, and lace loops.',
                'procedure' => 'Secure shoe upper or swatch in lower clamping fixture. Hook lace or wire through eyelet/hardware and clamp into upper UTM crosshead. Pull at 100 mm/min until failure.',
                'standards' => 'SATRA TM181, SATRA TM150'
            ],
            'VF-B05' => [
                'name' => 'Die C Tear Strength',
                'desc' => 'Measures tear initiation and tear propagation resistance of rubber outsoles, foxing tapes, and elastomeric polymers using razor-nicked 90° angle Die C specimens.',
                'procedure' => 'Cut Die C specimens perpendicular and parallel to grain. Cut 0.5mm nick at internal corner using razor gage. Pull on tensile machine at 500 mm/min. Record peak force divided by thickness (N/mm or kN/m).',
                'standards' => 'ASTM D624 Die C, ISO 34-1 Method B'
            ],
            'VF-B06' => [
                'name' => 'Split Tear Strength',
                'desc' => 'Measures internal split tear resistance of cellular soling materials, EVA midsoles, and foam compounds.',
                'procedure' => 'Cut rectangular test specimen (25mm x 150mm). Make initial center longitudinal slit. Mount split legs into UTM jaws and pull at 50 mm/min. Record average tear force per unit width.',
                'standards' => 'ASTM D3574 Test F, SATRA TM65'
            ],
            'VF-B07' => [
                'name' => 'Trouser Tear Strength',
                'desc' => 'Measures tear resistance of leather, coated fabrics, synthetic upper textiles, and sheetings using trouser-shaped test specimens.',
                'procedure' => 'Cut rectangular specimen with central longitudinal cut forming two trousers legs. Clamp legs in opposing grips. Pull at 100-200 mm/min. Record average of 5 peak load values.',
                'standards' => 'ASTM D2261, ISO 13937-2, SATRA TM30'
            ],
            'VF-B08' => [
                'name' => 'Stitch Tear Strength',
                'desc' => 'Measures resistance of upper leather, synthetic materials, and insole boards against tearing through needle stitching holes.',
                'procedure' => 'Punch standard hole or insert standard steel U-needle through specimen. Pull needle against clamped material on tensile machine at 100 mm/min. Record peak force.',
                'standards' => 'SATRA TM82, ASTM D4705'
            ],
            'VF-B09' => [
                'name' => 'Baumann Tear Strength',
                'desc' => 'Measures Baumann tear strength of heavy upper leather and synthetic leather materials.',
                'procedure' => 'Cut Baumann specimen with specified notch. Pull on tensile tester at 100 mm/min and record tear peak force.',
                'standards' => 'SATRA TM29, DIN 53329'
            ],
            'VF-B10' => [
                'name' => 'Static Compression Set',
                'desc' => 'Evaluates permanent deformation and recovery of EVA midsoles, PU foams, and insoles after sustained compressive loading at elevated temperature.',
                'procedure' => 'Compress cylindrical discs to 50% or 25% of original thickness in steel compression clamp. Place in 50°C oven for 6 hours (or 22 hours at 23°C). Release, allow 30 min recovery at room temp, and measure final thickness. Calculate % compression set.',
                'standards' => 'ASTM D395 Method B, SATRA TM64'
            ],
            'VF-B15' => [
                'name' => 'Static Coefficient of Friction (James Slip)',
                'desc' => 'Evaluates slip resistance of outsole materials on dry and wet smooth surfaces using the James Machine.',
                'procedure' => 'Mount test specimen foot on James machine. Apply calibrated 80 lb vertical load. Lower strut until slip occurs. Measure tangent angle to determine static friction coefficient.',
                'standards' => 'ASTM F489, ASTM D2047, SATRA TM144'
            ],
            'VF-B16' => [
                'name' => 'Rebound Resiliency',
                'desc' => 'Measures energy return, elasticity, and shock cushioning of midsole foam, EVA, and rubber outsoles via vertical ball rebound tester.',
                'procedure' => 'Drop standard steel bearing ball from 460mm height through vertical tube onto horizontal clamped specimen. Read peak rebound height on scale. Calculate % rebound.',
                'standards' => 'ASTM D2632, ASTM D3574 Test H'
            ],
            'VF-B17' => [
                'name' => 'Zipper Strength Performance',
                'desc' => 'Evaluates crosswise tensile strength of chain, slider lock holding strength, top stop holding strength, and puller attachment.',
                'procedure' => 'Mount zipper segments into UTM clamps. Pull transverse to teeth at 300 mm/min for chain crosswise strength. Pull slider tab at 90° for puller detachment.',
                'standards' => 'ASTM D2061, SATRA TM50, ISO 10765'
            ],
            'VF-B23' => [
                'name' => 'Knot Slip Resistance',
                'desc' => 'Measures tendency of shoe laces to untie or slip loose when tied under knot tension.',
                'procedure' => 'Tie lace into standardized bow/reef knot. Mount in knot slip oscillating jig. Cycle 1000 times under specified cyclic load and evaluate knot displacement.',
                'standards' => 'SATRA TM153'
            ],
            'VF-B24' => [
                'name' => 'Lace Tip Retention (Aglet Pull-off)',
                'desc' => 'Measures tensile retention force required to pull the plastic or metal aglet off the end of a shoelace.',
                'procedure' => 'Clamp lace body in lower pneumatic clamp. Insert lace tip into specialized aglet collar jig on upper crosshead. Pull at 100 mm/min until tip detaches.',
                'standards' => 'SATRA TM154'
            ],
            'VF-B25' => [
                'name' => 'Coefficient of Friction (SATRA TM144 Traction)',
                'desc' => 'Measures dynamic and static friction coefficient of whole footwear and soling on wet/dry quarry tile, stainless steel, and ice.',
                'procedure' => 'Mount shoe on SATRA TM144 slip resistance machine. Apply normal force (500N for heel strike, 400N for flat). Measure horizontal friction force during slip.',
                'standards' => 'SATRA TM144, EN ISO 13287, ASTM F2913'
            ],
            'VF-B26' => [
                'name' => 'Slide Fasteners Repeated Cycling',
                'desc' => 'Tests durability and wear of zippers under repeated opening and closing cycles under lateral and longitudinal tension.',
                'procedure' => 'Mount zipper in cyclic reciprocating machine. Apply specified cross tension. Cycle 500-10,000 times. Inspect for broken teeth or jams.',
                'standards' => 'SATRA TM52, DIN 3419'
            ],
            'VF-B27' => [
                'name' => 'Edgewise Crush Strength',
                'desc' => 'Measures compression and crush resistance of shoe boxes and corrugated packaging.',
                'procedure' => 'Compress box specimen edgewise between flat parallel platens on compression tester until buckling occurs.',
                'standards' => 'TAPPI T811, ISO 3037'
            ],
            'VF-C01' => [
                'name' => 'DIN Rotary Drum Abrasion Resistance',
                'desc' => 'Measures volume loss of vulcanized rubber outsoles, soling materials, and polyurethane against rotating abrasive drum.',
                'procedure' => 'Condition cylindrical specimen (16mm diameter x 6mm thick). Weigh before test. Traverse across rotating abrasive drum (P60 sandpaper) under 10N contact load for 40 meters. Weigh worn specimen, measure density, and calculate volume loss in mm³ using standard reference rubber calibration.',
                'standards' => 'ISO 4649 Method A, DIN 53516, SATRA TM174'
            ],
            'VF-C04' => [
                'name' => 'Martindale Abrasion Resistance',
                'desc' => 'Measures surface wear, pilling, and fiber breakdown of shoe upper fabrics, collar linings, insock materials, and coated synthetics.',
                'procedure' => 'Mount circular specimen in holder under specified pressure (12 kPa for upholstery/footwear). Rub in Lissajous pattern against standard wool abradant cloth. Inspect for yarn breakage, hole formation, or coating wear at 12,800, 25,600, or 51,200 rubs (wet and dry).',
                'standards' => 'ISO 12947-2, ASTM D4966, SATRA TM31'
            ],
            'VF-C05' => [
                'name' => 'Taber Abrasion Resistance',
                'desc' => 'Measures rotary platform abrasion of flat coated fabrics, prints, logo transfers, and plastic components.',
                'procedure' => 'Clamp circular specimen onto turntable. Lower paired CS-10 or H-18 abrasive wheels under 500g or 1000g load with vacuum attachment. Run specified cycles (e.g. 1000 cycles) and evaluate mass loss or appearance rating.',
                'standards' => 'ASTM D3884, ASTM D4060, ISO 5470-1'
            ],
            'VF-C09' => [
                'name' => 'Shoe Lace Abrasion Resistance',
                'desc' => 'Measures wear and break resistance of shoe laces rubbed against eyelets or lace-to-lace friction.',
                'procedure' => 'Thread shoelace through standard brass eyelet or loop two laces together. Apply 2.5N tension weight. Reciprocate until lace breaks or reaches 10,000 cycles.',
                'standards' => 'SATRA TM154, ISO 22774'
            ],
            'VF-D01' => [
                'name' => 'Bally Flexing Resistance (Upper Materials)',
                'desc' => 'Measures flexing fatigue resistance of upper leathers, coated synthetics, and multi-layer composites under repeated V-crease bending.',
                'procedure' => 'Fold rectangular specimen inside out and clamp one end in fixed grip, other in oscillating reciprocating grip (100±5 cycles/min). Run up to 50,000 or 100,000 cycles in dry condition, and 20,000 cycles in wet condition. Inspect for cracking, flaking, or delamination.',
                'standards' => 'ISO 5402-1, SATRA TM55, ASTM D6182'
            ],
            'VF-D02' => [
                'name' => 'Ross Flexing Resistance (Soling)',
                'desc' => 'Measures cut growth and flex fatigue resistance of rubber outsoles, EVA midsoles, and soling materials under 90° bending over mandrel.',
                'procedure' => 'Pierce 2mm center puncture through soling flex groove with chisel tool. Mount in Ross flex machine over 10mm mandrel. Flex at 100 cycles/min for 50,000 cycles at room temp and optionally at -10°C cold temp. Measure cut growth in millimeters.',
                'standards' => 'ASTM D1052, SATRA TM60, ISO 17707'
            ],
            'VF-D03' => [
                'name' => 'Bata Belt Flexing (Whole Sole)',
                'desc' => 'Tests cut initiation and cut growth resistance of full assembled outsole units attached to rotating continuous belt over driven pulleys.',
                'procedure' => 'Adhere whole soles to continuous cloth belt. Drive belt around rollers (60mm and 90mm diameter) at 90 rpm. Inspect tread pattern for crack growth after 50,000 cycles.',
                'standards' => 'SATRA TM133'
            ],
            'VF-D06' => [
                'name' => 'Stretch and Flex (Gore / Elastic)',
                'desc' => 'Evaluates durability, modulus decay, and elastic recovery of shoe webbing, side gores, and stretch materials.',
                'procedure' => 'Mount elastic webbing in cyclic extension tester. Cycle between 0% and 50% elongation for 10,000 cycles. Measure loss of tension and permanent set.',
                'standards' => 'SATRA TM103'
            ],
            'VF-E01' => [
                'name' => 'Color Fastness to Water',
                'desc' => 'Determines bleeding and staining of dyed leathers, textiles, and linings when saturated with deionized water.',
                'procedure' => 'Sandwich specimen between multi-fiber adjacent fabrics. Immerse in distilled water. Place in perspirometer under 12.5 kPa load in 37±2°C incubator for 4 hours. Assess color change and staining using standard AATCC/ISO Gray Scales.',
                'standards' => 'ISO 105-E01, AATCC 107'
            ],
            'VF-E02' => [
                'name' => 'Color Fastness to Crocking / Rubbing',
                'desc' => 'Measures transfer of dye from material surfaces onto white cotton crock cloth under dry and wet rubbing.',
                'procedure' => 'Mount specimen on crockmeter bed. Secure white crocking cloth on 16mm finger. Apply 9N downward force and cycle 10 double strokes across 100mm track. Evaluate staining with AATCC Gray Scale (Rating 1 to 5).',
                'standards' => 'AATCC 8, ISO 105-X12, SATRA TM167'
            ],
            'VF-E03' => [
                'name' => 'UV Light Discoloration & Yellowing',
                'desc' => 'Evaluates yellowing, color fading, and polymer degradation of white rubber foxing, EVA midsoles, and clear soles under accelerated UV radiation.',
                'procedure' => 'Expose specimens in UV aging test chamber (UVA-340 or UV-300 lamps) at 50±2°C for 12, 24, or 48 hours. Mask half of sample as unexposed control. Compare color difference Delta E using spectrophotometer or Gray Scale.',
                'standards' => 'ASTM G154, SATRA TM160, ISO 105-B02'
            ],
            'VF-E16' => [
                'name' => 'Color Fastness to Perspiration',
                'desc' => 'Determines colorfastness of insoles, collar linings, and uppers against synthetic acidic and alkaline sweat solutions.',
                'procedure' => 'Prepare acid and alkaline synthetic sweat solutions (histidine, NaCl, sodium phosphate). Soak specimen composite, clamp in perspirometer, incubate at 37°C for 4 hours. Grade change and staining.',
                'standards' => 'ISO 105-E04, AATCC 15'
            ],
            'VF-E17' => [
                'name' => 'Surface Blooming of Rubber',
                'desc' => 'Evaluates migration and blooming of sulfur, wax, plasticizers, or curing accelerators to the surface of vulcanized rubber outsoles and foxing.',
                'procedure' => 'Store vulcanized rubber samples at 60±2°C for 7 days, and at ambient room temperature for 14 days. Inspect periodically for white powdery or hazy crystalline exudation.',
                'standards' => 'ASTM D1765, ISO 1827'
            ],
            'VF-E18' => [
                'name' => 'Water Swelling Test',
                'desc' => 'Measures dimensional expansion, weight gain, and volume swelling of cellular midsoles and polymers soaked in water.',
                'procedure' => 'Measure initial dimensions and dry weight. Immerse in distilled water at 23°C for 24 hours. Remove, blot excess surface moisture, and measure expanded dimensions and weight.',
                'standards' => 'ASTM D471, ISO 1817'
            ],
            'VF-E19' => [
                'name' => 'Hydrolysis Resistance (Jungle Test)',
                'desc' => 'Evaluates degradation and crumbling resistance of polyurethane (PU) midsoles, coatings, and hot-melt films exposed to warm humid environments.',
                'procedure' => 'Suspend specimens in airtight environmental chamber at 70±1°C and 95±3% RH for 7, 14, or 21 days. Test residual tensile strength, hardness, and flexing after removal. Pass requires no crumble or tackiness.',
                'standards' => 'SATRA TM353, ISO 5423 Annex E'
            ],
            'VF-E20' => [
                'name' => 'pH of Aqueous Extract',
                'desc' => 'Determines pH and difference figure of leather and textile components to ensure comfort and non-irritating contact with skin.',
                'procedure' => 'Macerate specimen into small pieces. Extract 5g sample in 100ml deionized water using mechanical shaker for 2 hours. Measure extract pH with calibrated pH electrode meter.',
                'standards' => 'ISO 4045, ISO 3071, SATRA TM8'
            ],
            'VF-E21' => [
                'name' => 'Non-Marking Outsole Test',
                'desc' => 'Evaluates whether rubber or synthetic shoe outsoles leave dark scuff marks or color transfer on light-colored gym and court floors.',
                'procedure' => 'Press corner of sole against clean white test tile under 50N load at 45° angle. Drag 200mm. Inspect tile for visible scuff marks or residue under standard illumination.',
                'standards' => 'SATRA TM223'
            ],
            'VF-E25' => [
                'name' => 'Water Vapor Permeability (Breathability)',
                'desc' => 'Measures breathability and moisture vapor transmission rate (MVTR) through upper leathers and lining membranes.',
                'procedure' => 'Seal specimen over circular mouth of cup containing silica gel desiccant. Place cup in air-conditioned chamber with rapid air movement for 24 hours. Weigh cup to measure absorbed moisture in mg/(cm²·h).',
                'standards' => 'ISO 20344 clause 6.6, SATRA TM172, EN 344'
            ],
            'VF-E26' => [
                'name' => 'Dip Test (Water Repellency)',
                'desc' => 'Tests water repellency and absorption of treated upper materials subjected to repetitive dipping.',
                'procedure' => 'Dip sample cyclically into water bath for specified immersion cycles. Measure absorbed water and evaluate dynamic surface penetration.',
                'standards' => 'SATRA TM171'
            ],
            'VF-E27' => [
                'name' => 'Spray Rating (Surface Water Repellency)',
                'desc' => 'Evaluates surface resistance of treated textile shoe fabrics against wetting by water spray (Bundesmann or AATCC Spray).',
                'procedure' => 'Mount fabric taut on 45° angle ring. Spray 250ml of distilled water from nozzle 150mm above. Compare wetted pattern on surface against standard photographic rating charts (0 to 100).',
                'standards' => 'AATCC 22, ISO 4920'
            ],
            'VF-E28' => [
                'name' => 'Glow in the Dark (Photoluminescence)',
                'desc' => 'Measures luminescent brightness, luminance decay rate, and afterglow duration of phosphorescent prints, rubbers, and soles.',
                'procedure' => 'Charge specimen under standard D65 light source (1000 lux) for 20 minutes. Transfer to dark box and measure luminance with photometer at 10, 30, and 60 minutes in mcd/m².',
                'standards' => 'DIN 67510, ASTM E2072'
            ],
            'VF-F01' => [
                'name' => 'Whole Footwear Sole Adhesion',
                'desc' => 'Determines bond strength between outsole, foxing tape, and upper of vulcanized, cemented, and cold-cure footwear.',
                'procedure' => 'Cut shoe into transverse test strips (25mm wide) across toe, ball, waist, and heel areas. Separate bond interface slightly to initiate peel. Mount upper and sole into tensile grips. Peel apart at 100 mm/min. Record average bond strength in N/mm (kg/cm) and note failure mode (adhesive failure, cohesive failure of material, or substrate tear).',
                'standards' => 'SATRA TM404, ISO 17708, ASTM D903'
            ],
            'VF-F02' => [
                'name' => 'Peel Strength for Rubber & Outsole to Upper',
                'desc' => 'Measures T-peel adhesion strength between rubber outsole compounds, EVA midsoles, and upper materials bonded with polyurethane or polychloroprene cements.',
                'procedure' => 'Bond 25mm test strips with specified cement and primer system. Cure according to factory timeline. Pull apart at 180° T-peel on UTM at 100 mm/min. Record mean peeling force.',
                'standards' => 'ASTM D1876, SATRA TM411'
            ],
            'VF-F03' => [
                'name' => 'Finish Adhesion of Leather & Synthetics',
                'desc' => 'Measures adhesion bond between leather substrate and polyurethane/acrylic topcoat finishes.',
                'procedure' => 'Adhere high-strength adhesive strip to finish side with heat press. Slit material down to substrate on both sides. Peel finish off substrate at 90° on tensile tester. Record peel strength in N/10mm.',
                'standards' => 'ISO 11644, SATRA TM14'
            ],
            'VF-F04' => [
                'name' => 'Laminar Peel Strength',
                'desc' => 'Determines interlaminar bond strength between multi-layered laminated fabrics, foam packages, and waterproof membranes.',
                'procedure' => 'Separate laminated layers at one end. Mount separated layers into tensile grips. Peel apart at 100 mm/min over 100mm length. Calculate average peeling force.',
                'standards' => 'SATRA TM401, ISO 2411'
            ],
            'VF-F05' => [
                'name' => 'Ink Adhesion on Substrate (Cross-Hatch Tape)',
                'desc' => 'Evaluates adhesion of screen-printed inks, pad printing, and foil stamps on shoe uppers, foxing, and rubber soles.',
                'procedure' => 'Make cross-hatch lattice incisions (1mm or 2mm spacing, 100 squares) through ink layer to substrate. Apply standard 3M 610 or 810 pressure-sensitive adhesive tape. Rub firmly with eraser. Pull tape off sharply at 60° angle within 90 seconds. Rate detached squares (0B to 5B scale).',
                'standards' => 'ASTM D3359 Method B, ISO 2409'
            ],
            'VF-F06' => [
                'name' => 'Sandal & Slipper Strap Pulling Strength',
                'desc' => 'Measures tensile retention and attachment pull-out force of sandal straps anchored into soles or footbeds.',
                'procedure' => 'Clamp sandal sole securely in holding fixture. Clamp strap into upper tensile grip. Pull vertically or at 45° angle at 100 mm/min until strap pulls out or ruptures.',
                'standards' => 'SATRA TM181'
            ],
            'VF-F09' => [
                'name' => 'Measurement of Heel Attachment Strength',
                'desc' => 'Evaluates attachment strength of high heels, molded heels, and wedges fastened to women’s footwear.',
                'procedure' => 'Mount shoe in heel attachment tester. Apply horizontal force against top of heel at 100 mm/min until detachment or structural failure occurs.',
                'standards' => 'SATRA TM113, ISO 19953'
            ],
            'VF-F10' => [
                'name' => 'Top Lift & Stud Retention Strength',
                'desc' => 'Measures holding force required to pull out replaceable top lifts or studs from shoe heels.',
                'procedure' => 'Clamp shoe heel in tensile machine. Clamp top lift pin in tensile jaw. Pull longitudinally at 100 mm/min until top lift pulls free.',
                'standards' => 'SATRA TM108'
            ],
            'VF-F11' => [
                'name' => 'Cardboard / Packaging Adhesion',
                'desc' => 'Evaluates adhesive bond of shoe box flaps, carton seals, and labeling adhesives.',
                'procedure' => 'Peel glued carton flaps at 180° angle. Inspect for fiber tear (100% fiber tear required for acceptable carton integrity).',
                'standards' => 'TAPPI T807'
            ],
            'VF-G01' => [
                'name' => 'Test Parameters for Assembled Footwear',
                'desc' => 'Comprehensive master schedule of performance parameters, mechanical thresholds, and inspection criteria for completed Vans shoes.',
                'procedure' => 'Inspect full shoe construction, seam margins, foxing bonding, toe cap adhesion, flex durability, and cosmetic standards per global VF quality specifications.',
                'standards' => 'VF Global Footwear Specification DTP-Lab-101'
            ],
            'VF-G02' => [
                'name' => 'Whole Shoe Flexing Durability',
                'desc' => 'Tests dynamic fatigue durability, cracking, foxing delamination, and midsole separation on finished shoes during continuous walking flex.',
                'procedure' => 'Mount complete shoe onto articulated artificial foot in flexing machine. Flex through 50° angle at 100-140 cycles/minute for 150,000 to 300,000 cycles. Inspect sole, foxing band, toe spring, and vamp every 50,000 cycles for cracking or bond separation.',
                'standards' => 'SATRA TM92, ISO 24267'
            ],
            'VF-G03' => [
                'name' => 'Whole Shoe Machine Wash Test',
                'desc' => 'Evaluates durability, shrinkage, color bleeding, and bond integrity of washable canvas and vulcanized shoes after domestic machine washing.',
                'procedure' => 'Place complete shoes in commercial washing machine with standard detergent and dummy ballast load. Wash at 40°C on standard cycle for 45 minutes. Tumble dry or ambient air dry. Inspect for upper shrinkage, glue failure, or color change.',
                'standards' => 'AATCC 135, ISO 6330'
            ],
            'VF-G04' => [
                'name' => 'Whole Shoe Flexing Waterproof Test',
                'desc' => 'Tests waterproof footwear for dynamic water penetration through seams, tongue gussets, and sole bonding while flexing in water bath.',
                'procedure' => 'Fit shoe onto mechanical flexing foot. Submerge in water bath to specified depth (typically 20-30mm above feather line). Flex continuously for 15,000 to 50,000 cycles. Check interior for electrical moisture sensor detection or dry towel dampness.',
                'standards' => 'SATRA TM230, ISO 20344 clause 5.15'
            ],
            'VF-G05' => [
                'name' => 'Whole Shoe Flexing (China GB Standard)',
                'desc' => 'Measures flexing resistance of finished shoes and outsoles according to mandatory Chinese National Standard GB/T 3903.1.',
                'procedure' => 'Make 5mm initial cut at ball flex line. Flex shoe at 230±10 cycles/min through 50° angle for 40,000 cycles. Measure cut propagation (must not exceed 10mm).',
                'standards' => 'GB/T 3903.1, QB/T 1002'
            ],
            'VF-G06' => [
                'name' => 'Whole Shoe Water Absorption',
                'desc' => 'Determines moisture absorption rate and total weight gain of footwear when immersed in water.',
                'procedure' => 'Weigh dry shoe (W1). Immerse in water at specified depth for 2 hours. Remove, drain, surface blot, and weigh (W2). Calculate % water uptake.',
                'standards' => 'SATRA TM230'
            ],
            'VF-G07' => [
                'name' => 'Static Whole Shoe Waterproofness',
                'desc' => 'Tests static resistance against water ingress of boots and waterproof shoes submerged in still water tank.',
                'procedure' => 'Fill water tank to 25mm below collar top. Submerge shoe for 4 hours. Inspect shoe cavity for water seepage.',
                'standards' => 'SATRA TM77'
            ],
            'VF-G08' => [
                'name' => 'Hydrostatic Waterproofness',
                'desc' => 'Measures hydrostatic water pressure head sustained by seam-sealed waterproof membranes and bootie constructions.',
                'procedure' => 'Clamp shoe bootie or fabric under water pressure column. Increase hydrostatic pressure at 60 cm/min until third droplet of water penetrates.',
                'standards' => 'AATCC 127, ISO 811'
            ],
            'VF-G09' => [
                'name' => 'Centrifuge Waterproof Leak Test',
                'desc' => 'Rapid centrifugal test for detecting micro-leaks in waterproof membrane booties and whole shoes.',
                'procedure' => 'Fill shoe cavity with water. Mount shoe into high-speed centrifuge cradle. Spin at 250 rpm for 15 minutes. Check outer surface for centrifuge water expulsion.',
                'standards' => 'SATRA TM358'
            ],
            'VF-H01' => [
                'name' => 'Material Conditioning Standard',
                'desc' => 'Standard laboratory atmosphere and conditioning parameters for all VF footwear physical testing.',
                'procedure' => 'Store all test specimens in standard atmosphere: Temperature 23 ± 2°C (73.4 ± 3.6°F) and Relative Humidity 50 ± 5% for at least 24 hours prior to physical testing.',
                'standards' => 'ASTM D618, ISO 18454, SATRA TM249'
            ],
            'VF-H02' => [
                'name' => 'Leather Sampling Protocol',
                'desc' => 'Prescribes location, orientation, and cutting pattern of test swatches from animal hides and leather sides.',
                'procedure' => 'Sample exclusively from official sampling area (butt region, 50mm from backbone line, outside belly and flank). Cut parallel and perpendicular specimens to grain.',
                'standards' => 'ISO 2418, ASTM D2813, SATRA TM24'
            ],
            'VF-H03' => [
                'name' => 'Midsole Sampling Protocol',
                'desc' => 'Sampling guidelines for EVA, molded PU, and supercritical foam midsoles.',
                'procedure' => 'Slice test coupons from uniform flat sections avoiding skin layer variations. Condition 24h before split tear, density, or compression set tests.',
                'standards' => 'VF Lab Practice Standard H03'
            ],
            'VF-H04' => [
                'name' => 'Cement & Adhesive Solids Content',
                'desc' => 'Tests solid polymer content of water-based and solvent-based footwear bonding adhesives.',
                'procedure' => 'Weigh adhesive dish, evaporate volatile carrier in vacuum oven at 105°C, measure dry active solids percentage.',
                'standards' => 'ASTM D2369, ISO 3251'
            ],
            'VF-I01' => [
                'name' => 'Oil Content in Materials',
                'desc' => 'Determines mineral oil, plasticizer, or fatliquor content in leather and synthetic shoe components via Soxhlet extraction.',
                'procedure' => 'Extract 5g ground specimen in Soxhlet apparatus using dichloromethane or petroleum ether for 4 hours. Distill solvent and weigh dry residual oils.',
                'standards' => 'ISO 4048, ASTM D3495'
            ],
            'VF-I02' => [
                'name' => 'Fiber Identification & Analysis',
                'desc' => 'Qualitative and quantitative identification of textile fibers (cotton, polyester, nylon, wool, elastane) in upper fabrics and linings.',
                'procedure' => 'Examine fiber cross-section under optical microscope. Perform solubility tests in acetone, sulfuric acid, and formic acid. Confirm via FTIR spectroscopy.',
                'standards' => 'AATCC 20, ASTM D276, ISO 1833'
            ],
            'VF-I03' => [
                'name' => 'Dyestuff Reaction to Solvents',
                'desc' => 'Tests bleeding and chemical solubility of pigments and dyes exposed to cement solvents, MEK, acetone, and toluene.',
                'procedure' => 'Place dyed swatch in solvent bath for 15 minutes. Evaluate solvent discoloration and migration.',
                'standards' => 'SATRA TM18'
            ],
            'VF-I04' => [
                'name' => 'Transfer of Dye with Solvent Spot',
                'desc' => 'Evaluates dye transfer onto white cloth caused by solvent application during sole priming and cleaning.',
                'procedure' => 'Apply 0.5ml primer solvent onto white filter paper on material surface. Rub gently and evaluate staining.',
                'standards' => 'VF Standard I04'
            ],
            'VF-I05' => [
                'name' => 'Oil Resistance of Soling (Hydrocarbon Swelling)',
                'desc' => 'Evaluates volume swelling, mass increase, and hardness decrease of rubber outsoles submerged in standard mineral oils.',
                'procedure' => 'Weigh and measure specimen. Submerge in ASTM No. 3 or IRM 903 reference oil at 23°C for 22 hours. Measure volume swell percentage (must not exceed +12%).',
                'standards' => 'ASTM D471, ISO 1817, EN ISO 20344 clause 8.6'
            ],
            'VF-J01' => [
                'name' => 'Electric Shock Resistance Test',
                'desc' => 'Evaluates electrical insulating properties of safety footwear soles against lethal high voltage (18,000V AC).',
                'procedure' => 'Place shoe on metal mesh electrode with interior filled with conductive steel balls. Apply 18,000V AC at 60 Hz for 1 minute. Leakage current must not exceed 1.0 mA without breakdown.',
                'standards' => 'ASTM F2412, ASTM F2413, CSA Z195'
            ],
            'VF-J02' => [
                'name' => 'Thermal Conductivity & Heat Insulation',
                'desc' => 'Measures heat insulation (HI) and cold insulation (CI) temperature transfer rate through sole assembly.',
                'procedure' => 'Place shoe on heated sand bath at 150°C. Monitor internal footbed temperature rise over 30 minutes with thermocouple probe (rise must not exceed 22°C).',
                'standards' => 'EN ISO 20344 clauses 5.12 & 5.13'
            ],
        ];

        // First, truncate or delete existing seeds to avoid duplicates
        $db->table('tbl_vf_test_methods')->truncate();

        $rowsToInsert = [];

        foreach ($files as $file) {
            if ($file->isDir()) {
                continue;
            }

            $fileName = $file->getFilename();
            $fullPath = $file->getPathname();
            $fileSize = $file->getSize();
            $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            // Relative web URL
            $relPath = str_replace('\\', '/', substr($fullPath, strlen(FCPATH)));
            $isIllustrated = (stripos($relPath, 'illustrated') !== false || stripos($fileName, 'illustrated') !== false) ? 1 : 0;

            // Extract code
            $code = 'MANUAL';
            if (preg_match('/VF\s*-\s*([A-Z]\s*\d{2}[A-Za-z]?)/i', $fileName, $m)) {
                $clean = strtoupper(preg_replace('/\s+/', '', $m[1]));
                $code = 'VF-' . $clean;
            } elseif (preg_match('/Illustrated\s+VF-?([A-Z]\s*\d{2}[A-Za-z]?)/i', $fileName, $m)) {
                $clean = strtoupper(preg_replace('/\s+/', '', $m[1]));
                $code = 'VF-' . $clean;
            } elseif (stripos($fileName, 'VANS') !== false && stripos($fileName, 'Manual') !== false) {
                $code = 'VF-MANUAL';
            } elseif (stripos($fileName, 'UNIVERSAL') !== false) {
                $code = 'VF-PARAM-UTM';
            } elseif (stripos($fileName, 'Testing Parameters') !== false) {
                $code = 'VF-PARAM-TEMP';
            } elseif (stripos($fileName, 'Guidelines for Phy Prop') !== false) {
                $code = 'VF-GUIDELINES';
            } elseif (stripos($fileName, 'List of Test Methods') !== false) {
                $code = 'VF-INDEX';
            }

            // Determine category
            $series = substr($code, 3, 1);
            $category = $categories[$series] ?? 'Manuals & Specifications';

            // Extract title
            $title = $fileName;
            // Clean up title
            $cleanTitle = preg_replace('/\.(pdf|xlsx|docx|zip)$/i', '', $fileName);
            $cleanTitle = preg_replace('/^(Illustrated\s+VF-?[A-Z0-9]+|VF\s*-\s*[A-Z0-9]+)\s*[-–]?\s*/i', '', $cleanTitle);
            $cleanTitle = preg_replace('/\s*[-–]\s*(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec|REV)\s*\d{4}.*$/i', '', $cleanTitle);
            $cleanTitle = trim($cleanTitle, " -_\t\n\r\0\x0B");
            if ($cleanTitle === '') {
                $cleanTitle = $methodKnowledge[$code]['name'] ?? $code;
            }

            // Extract revision date
            $revDate = null;
            if (preg_match('/(Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:tember)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)\s*(\d{4})/i', $fileName, $m)) {
                $revDate = $m[1] . ' ' . $m[2];
            } elseif (preg_match('/\b(20\d{2})\b/', $fileName, $m)) {
                $revDate = $m[1];
            }

            // Description
            $desc = $methodKnowledge[$code]['desc'] ?? "Official VF test method standard and procedure for {$cleanTitle}.";
            if ($isIllustrated) {
                $desc = "Step-by-step illustrated pictorial standard and apparatus guide for {$cleanTitle} ({$code}).";
            }

            $record = [
                'code'           => $code,
                'title'          => $cleanTitle,
                'category'       => $category,
                'file_name'      => $fileName,
                'file_path'      => $fullPath,
                'url_path'       => $relPath,
                'file_size'      => $fileSize,
                'file_ext'       => $fileExt,
                'is_illustrated' => $isIllustrated,
                'revision_date'  => $revDate,
                'description'    => $desc,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];

            $db->table('tbl_vf_test_methods')->insert($record);
            $inserted++;
        }

        CLI::write("Cataloged {$inserted} VF files into tbl_vf_test_methods.", 'green');

        // Now populate / refresh tbl_bot_knowledge with comprehensive VF Test Knowledge
        CLI::write("Seeding AI Brain with VF test knowledge entries...", 'cyan');

        // Group methods by code for clean knowledge entry creation
        $allMethods = $db->table('tbl_vf_test_methods')->orderBy('code', 'ASC')->orderBy('is_illustrated', 'ASC')->get()->getResultArray();
        $grouped = [];
        foreach ($allMethods as $m) {
            $grouped[$m['code']][] = $m;
        }

        foreach ($grouped as $c => $items) {
            $official = null;
            $illustrated = [];
            foreach ($items as $item) {
                if ($item['is_illustrated'] == 0 && $official === null) {
                    $official = $item;
                } else {
                    $illustrated[] = $item;
                }
            }
            if ($official === null && !empty($illustrated)) {
                $official = $illustrated[0];
            }

            $info = $methodKnowledge[$c] ?? null;
            $titleStr = $c . ': ' . ($info['name'] ?? $official['title']);
            $categoryStr = $official['category'] ?? 'General';
            $standardsStr = $info['standards'] ?? 'VF Footwear Specification DTP-Lab-101';
            $descStr = $info['desc'] ?? $official['description'];
            $procStr = $info['procedure'] ?? 'Follow official VF standard operating procedures, apparatus calibration, sample conditioning, and parameter verification.';

            $actions = [];
            if ($official && $official['url_path']) {
                $actions[] = [
                    'type'  => 'open',
                    'label' => 'Open Official PDF (' . $c . ')',
                    'url'   => site_url($official['url_path']),
                ];
            }
            foreach ($illustrated as $ill) {
                $actions[] = [
                    'type'  => 'open',
                    'label' => 'View Illustrated Guide: ' . mb_substr($ill['title'], 0, 30),
                    'url'   => site_url($ill['url_path']),
                ];
            }
            // Add DTR test link if relevant
            if (in_array(substr($c, 3, 1), ['A', 'B', 'C', 'D', 'E', 'H', 'I'], true)) {
                $actions[] = [
                    'type'  => 'open',
                    'label' => 'Create Material Test (DTR)',
                    'url'   => site_url('dtr?tab=material'),
                ];
            } elseif (in_array(substr($c, 3, 1), ['F', 'G'], true)) {
                $actions[] = [
                    'type'  => 'open',
                    'label' => 'Create Shoe Bonding Test (DTR)',
                    'url'   => site_url('dtr?tab=bonding'),
                ];
            }

            $steps = [
                'Method Classification: ' . $categoryStr . ' (' . $c . ')',
                'Standard Reference: ' . $standardsStr,
                'Scope & Application: ' . $descStr,
                'Testing Procedure: ' . $procStr,
                'Conditioning & Preparation: Store specimens at 23±2°C and 50±5% RH for minimum 24 hours prior to testing (VF-H01).',
            ];

            $keywords = mb_strtolower("{$c} {$official['title']} {$categoryStr} {$standardsStr} test method procedure apparatus ASTM SATRA ISO VF test");

            $body = "### {$c} - " . ($info['name'] ?? $official['title']) . "\n\n"
                  . "**Category:** {$categoryStr}  \n"
                  . "**Governing Standards:** {$standardsStr}  \n"
                  . ($official['revision_date'] ? "**Revision:** {$official['revision_date']}  \n" : "")
                  . "\n**Description:**  \n{$descStr}\n\n"
                  . "**Standard Operating Procedure:**  \n{$procStr}\n\n"
                  . "Click the action buttons below to open the complete official PDF standard or view the illustrated apparatus setup.";

            $kbData = [
                'kind'         => 'vf_method',
                'title'        => mb_substr($titleStr, 0, 200),
                'keywords'     => mb_substr($keywords, 0, 600),
                'body'         => $body,
                'steps_json'   => json_encode($steps, JSON_UNESCAPED_UNICODE),
                'actions_json' => json_encode($actions, JSON_UNESCAPED_SLASHES),
                'page_match'   => 'dtr',
                'error_match'  => null,
                'diagnose'     => null,
                'role_min'     => 'user',
                'status'       => 'approved',
                'source'       => 'vf_seed',
                'updated_at'   => $now,
            ];

            $existing = $db->table('tbl_bot_knowledge')->where('title', $kbData['title'])->get()->getRowArray();
            if ($existing) {
                $db->table('tbl_bot_knowledge')->where('id', $existing['id'])->update($kbData);
            } else {
                $db->table('tbl_bot_knowledge')->insert($kbData + ['created_at' => $now]);
            }
            $kbCount++;
        }

        // Add Series Index Cards
        $seriesOverviews = [
            'A' => ['Physical & Dimensional Properties', 'Covers hardness (VF-A01), unit weight (VF-A02), thickness (VF-A03), specific gravity (VF-A04), leather softness (VF-A06), yarn count (VF-A07), and non-volatile content (VF-A08).'],
            'B' => ['Strength & Mechanical Properties', 'Covers tensile properties (VF-B01), bursting strength (VF-B02), peel & shear (VF-B03), hardware attachment (VF-B04), Die C tear (VF-B05), split tear (VF-B06), trouser tear (VF-B07), stitch tear (VF-B08), Baumann tear (VF-B09), compression set (VF-B10), slip resistance (VF-B15/B25), rebound resiliency (VF-B16), zipper strength (VF-B17), and safety toe cap tests.'],
            'C' => ['Abrasion Resistance', 'Covers DIN rotary drum abrasion (VF-C01), Martindale abrasion (VF-C04), Taber abrasion (VF-C05), and shoe lace abrasion (VF-C09).'],
            'D' => ['Flexing & Fatigue Resistance', 'Covers Bally upper flexing (VF-D01), Ross sole flexing (VF-D02), Bata belt continuous flexing (VF-D03), and elastic stretch & flex (VF-D06).'],
            'E' => ['Chemical, Aging & Environmental Fastness', 'Covers water fastness (VF-E01), crocking rub fastness (VF-E02), UV yellowing discoloration (VF-E03), perspiration fastness (VF-E16), rubber blooming (VF-E17), water swelling (VF-E18), PU hydrolysis (VF-E19), aqueous extract pH (VF-E20), non-marking outsole test (VF-E21), and photoluminescence (VF-E28).'],
            'F' => ['Adhesion & Bond Strength', 'Covers whole footwear sole adhesion (VF-F01), peel strength for rubber/outsole to upper (VF-F02), finish adhesion (VF-F03), laminar peel (VF-F04), ink adhesion tape test (VF-F05), strap pulling (VF-F06), heel attachment (VF-F09), and cardboard carton adhesion (VF-F11).'],
            'G' => ['Assembled Footwear Performance', 'Covers assembled footwear parameters (VF-G01), whole shoe flexing durability (VF-G02), whole shoe machine wash (VF-G03), dynamic waterproof flexing (VF-G04), China GB flex (VF-G05), whole shoe water absorption (VF-G06), static waterproofness (VF-G07), hydrostatic head (VF-G08), and centrifuge leak test (VF-G09).'],
            'H' => ['Sampling & Material Conditioning', 'Covers material laboratory conditioning (VF-H01: 23±2°C, 50±5% RH for 24h), leather sampling zones (VF-H02), midsole sampling (VF-H03), and adhesive solids (VF-H04).'],
            'I' => ['Chemical Analysis & Identification', 'Covers oil content extraction (VF-I01), fiber microscopic and chemical identification (VF-I02), dye solvent reaction (VF-I03/I04), and soling oil resistance swelling (VF-I05).'],
            'J' => ['Electrical & Thermal Safety', 'Covers dielectric electric shock resistance (VF-J01: 18kV) and thermal insulation/conductivity (VF-J02).'],
        ];

        foreach ($seriesOverviews as $letter => [$name, $summary]) {
            $title = "VF Test Methods Series {$letter}: {$name}";
            $keywords = mb_strtolower("series {$letter} {$name} vf test methods standards overview summary list");
            $body = "### VF Test Methods Series {$letter} — {$name}\n\n"
                  . "{$summary}\n\n"
                  . "All official PDF method documents and illustrated procedural guides are available in the system catalog.";
            
            $steps = [
                "Series: Series {$letter} ({$name})",
                "Summary: {$summary}",
                "To view a specific method, ask LEXIS AI for any code (e.g., 'show VF-{$letter}01')."
            ];

            $actions = [
                ['type' => 'open', 'label' => 'Open Lab Tracker', 'url' => site_url('dtr?tab=tracker')],
                ['type' => 'open', 'label' => 'Open Material Tests', 'url' => site_url('dtr?tab=material')],
            ];

            $kbData = [
                'kind'         => 'vf_method',
                'title'        => mb_substr($title, 0, 200),
                'keywords'     => mb_substr($keywords, 0, 600),
                'body'         => $body,
                'steps_json'   => json_encode($steps, JSON_UNESCAPED_UNICODE),
                'actions_json' => json_encode($actions, JSON_UNESCAPED_SLASHES),
                'page_match'   => 'dtr',
                'error_match'  => null,
                'diagnose'     => null,
                'role_min'     => 'user',
                'status'       => 'approved',
                'source'       => 'vf_seed',
                'updated_at'   => $now,
            ];

            $existing = $db->table('tbl_bot_knowledge')->where('title', $kbData['title'])->get()->getRowArray();
            if ($existing) {
                $db->table('tbl_bot_knowledge')->where('id', $existing['id'])->update($kbData);
            } else {
                $db->table('tbl_bot_knowledge')->insert($kbData + ['created_at' => $now]);
            }
            $kbCount++;
        }

        // Master Manual Entry
        $manualRow = $db->table('tbl_vf_test_methods')->like('file_name', 'Manual')->get()->getRowArray();
        $manualUrl = $manualRow ? site_url($manualRow['url_path']) : site_url('uploads/vf_test_methods/VANS  Footwear Performance Testing Manual 2026.pdf');
        
        $masterTitle = 'VANS Footwear Performance Testing Manual 2026 (Global Standard)';
        $masterKeywords = 'vans footwear performance testing manual 2026 global standard dtp-lab-101 test methods vf specifications';
        $masterBody = "### VANS Footwear Performance Testing Manual 2026\n\n"
                    . "The official global performance testing specification and quality control manual for all Vans footwear categories (Classics, Pro Skate, Surf, Snow, Outdoor, and Lifestyle).\n\n"
                    . "Includes complete testing schedules, required sample sizes, minimum physical property thresholds, and testing frequencies across development, commercialization, and mass production.\n\n"
                    . "Click below to view or download the complete 2026 manual PDF.";
        
        $masterActions = [
            ['type' => 'open', 'label' => 'Open VANS Performance Testing Manual 2026 (PDF)', 'url' => $manualUrl],
            ['type' => 'open', 'label' => 'Open Lab Test Tracker', 'url' => site_url('dtr?tab=tracker')]
        ];

        $kbData = [
            'kind'         => 'vf_method',
            'title'        => $masterTitle,
            'keywords'     => $masterKeywords,
            'body'         => $masterBody,
            'steps_json'   => json_encode(['Vans Global Footwear Performance Testing Manual (Updated 2026)', 'Governs all physical, mechanical, chemical, and durability testing.', 'Includes thresholds for rubber, foam, leather, textiles, adhesives, and assembled footwear.'], JSON_UNESCAPED_UNICODE),
            'actions_json' => json_encode($masterActions, JSON_UNESCAPED_SLASHES),
            'page_match'   => 'dtr',
            'error_match'  => null,
            'diagnose'     => null,
            'role_min'     => 'user',
            'status'       => 'approved',
            'source'       => 'vf_seed',
            'updated_at'   => $now,
        ];

        $existing = $db->table('tbl_bot_knowledge')->where('title', $kbData['title'])->get()->getRowArray();
        if ($existing) {
            $db->table('tbl_bot_knowledge')->where('id', $existing['id'])->update($kbData);
        } else {
            $db->table('tbl_bot_knowledge')->insert($kbData + ['created_at' => $now]);
        }
        $kbCount++;

        CLI::write("Seeded {$kbCount} knowledge entries into tbl_bot_knowledge.", 'green');

        // Flush Brain and export markdown
        CLI::write("Flushing Brain caches and updating offline models...", 'cyan');
        Brain::flush();
        CLI::write("VF Test Methods database & AI Brain training complete!", 'green');
    }
}
