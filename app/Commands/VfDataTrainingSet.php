<?php

namespace App\Commands;

/**
 * VfDataTrainingSet
 * 
 * Master repository of 1,050+ data-centric laboratory prompts across VF test methods:
 * - ⚙️ Pull speeds, crosshead speeds & machine rates (mm/min, RPM, cpm)
 * - 🌡️ Temperatures, relative humidity & conditioning durations (°C, % RH, hours)
 * - ⏱️ Cycle counts, revolutions, durations & flex frequencies
 * - ⚖️ Contact loads, forces, dead weights & pressures (N, kgf, kPa, PSI, Volts)
 * - 🎯 Acceptance criteria, pass/fail limits & tolerance thresholds
 * - 📐 Specimen dimensions, cutter die types & sample thicknesses
 * - 🧮 Mathematical formulas, calculations & unit conversions
 * - 🛠️ Equipment, testing apparatus & machine fixtures
 * - 📋 Required DTR report fields & operator data entries
 * - 🔍 Failure symptoms, defect root causes & CAPA remediation protocols
 */
class VfDataTrainingSet
{
    public static function get1000DataQueries(): array
    {
        $queries = [];
        $add = function (string $code, string $q) use (&$queries) {
            $queries[] = ['code' => $code, 'q' => trim($q)];
        };

        // =========================================================================
        // 1. PULL SPEEDS, CROSSHEAD SPEEDS & MACHINE RATES (110 queries)
        // =========================================================================
        $pullSpeeds = [
            'VF-B01' => [
                'what is the pull speed for rubber tensile test', 'what is the crosshead speed for vf-b01',
                'crosshead speed for rubber tensile', 'pull rate for astm d412 tensile test',
                'what speed does the utm pull tensile specimens', 'tensile test machine speed mm per minute',
                'how fast does universal machine pull rubber dumbbell', 'speed setting for vf-b01 tensile test',
                'rubber tensile crosshead speed 500 mm per min', 'what is the pull speed for synthetic leather tensile',
                'tensile pull rate for upper fabrics 100 to 300 mm/min', 'pull speed setting on universal tensile machine',
                'what crosshead rate is specified in astm d412', 'tensile test pull rate', '500 mm/min tensile speed setting'
            ],
            'VF-B04' => [
                'what is the pull speed for eyelet pull out test', 'eyelet detachment crosshead speed',
                'how fast to pull shoe hardware on tensile machine', 'crosshead speed for vf-b04 hardware test',
                'pull rate for d-ring attachment test', 'eyelet pull out test speed 100 mm/min',
                'speed of utm for hardware attachment test', 'pull speed for satra tm181 hardware test'
            ],
            'VF-B05' => [
                'what is the pull speed for die c tear strength', 'crosshead speed for vf-b05 tear test',
                'how fast does machine pull die c tear specimens', 'astm d624 die c pull rate 500 mm/min',
                'pull speed setting for rubber tear initiation test', 'die c crosshead speed on utm machine',
                'tear strength test pull speed mm per min', 'speed of tensile machine for die c tear'
            ],
            'VF-B06' => [
                'what is the pull speed for split tear test', 'crosshead speed for eva split tear',
                'how fast to pull split tear specimen on utm', 'satra tm65 split tear pull rate 50 mm/min',
                'split tear crosshead speed 50 mm per min', 'speed setting for foam split tear test',
                'what pull rate is used for astm d3574 split tear', 'foam split tear machine pull speed'
            ],
            'VF-B07' => [
                'what is the pull speed for trouser tear test', 'crosshead speed for leather trouser tear',
                'how fast to pull trouser tear specimen', 'astm d2261 trouser tear pull speed 100 mm/min',
                'pull rate for upper leather trouser tear', 'speed of universal machine for vf-b07',
                'trouser tear test speed setting', 'crosshead rate for trouser shaped tear test'
            ],
            'VF-B08' => [
                'what is the pull speed for stitch tear test', 'crosshead speed for u-needle stitch tear',
                'how fast does machine pull needle through leather', 'satra tm82 stitch tear pull speed 100 mm/min',
                'pull rate for upper stitch tear resistance', 'stitch tear crosshead speed setting on utm'
            ],
            'VF-B24' => [
                'what is the pull speed for aglet retention test', 'crosshead speed for shoelace tip pull test',
                'how fast to pull plastic tip off shoelace', 'satra tm154 aglet pull speed 100 mm/min',
                'pull rate for lace tip detachment test', 'aglet test crosshead speed setting'
            ],
            'VF-F01' => [
                'what is the pull speed for whole footwear sole adhesion', 'crosshead speed for vf-f01 sole bond test',
                'how fast to pull sole peeling test on utm', 'peel speed for shoe bonding test 300 mm/min',
                'pull rate for sole to upper bond adhesion', 'what speed is used for satra tm404 sole adhesion',
                'peeling crosshead speed for vulcanized footwear', 'sole adhesion pull speed setting on tensile machine',
                'speed of peeling for toe and heel bonding', 'sole bond pull rate 300 mm per minute'
            ],
            'VF-F02' => [
                'what is the pull speed for 180 degree peel strength', 'crosshead speed for material peel test',
                'how fast to pull laminated material peel', 'peel rate for satra tm416 100 mm/min'
            ],
            'VF-F05' => [
                'what is the pull speed for cross hatch tape test', 'tape peeling rate for ink adhesion test',
                'how fast to pull 3m 610 tape off foxing', 'tape test pull angle and speed'
            ],
            'VF-C01' => [
                'what is the rotating drum speed for din abrasion', 'drum rotation rpm for vf-c01 abrasion',
                'how fast does din abrasion drum rotate', 'din drum speed 40 rpm',
                'rotation speed of cylindrical drum in din test', 'drum rpm setting for iso 4649 abrasion'
            ],
            'VF-D01' => [
                'what is the flexing speed for bally flex test', 'how many cycles per minute for bally flexometer',
                'flexing frequency for upper leather bally flex', 'bally flex speed 100 cycles per minute',
                'motor speed setting for iso 5402 bally flex', 'flexing rate for coated fabric upper'
            ],
            'VF-D02' => [
                'what is the flexing speed for ross flex test', 'how many cycles per minute for ross flex machine',
                'flexing frequency for vf-d02 ross flex', 'ross flex speed 100 cycles per minute',
                'motor speed for astm d1052 ross flex', 'ross flexer cycle frequency'
            ],
            'VF-G02' => [
                'what is the flexing speed for whole shoe walking test', 'how many cycles per minute for satra tm92',
                'whole shoe flexing machine frequency 100 cpm', 'walking flexer speed setting'
            ],
            'VF-G04' => [
                'what is the flexing speed for dynamic waterproof test', 'how many cycles per minute for waterproof flexer',
                'flexing rate in water bath 60 cycles per minute', 'satra tm230 waterproof flexing speed'
            ],
        ];

        foreach ($pullSpeeds as $code => $list) {
            foreach ($list as $q) {
                $add($code, $q);
            }
        }

        // =========================================================================
        // 2. TEMPERATURE, HUMIDITY & CONDITIONING PARAMETERS (110 queries)
        // =========================================================================
        $tempHumidity = [
            'VF-H01' => [
                'what is the standard laboratory conditioning temperature', 'what is the required relative humidity for testing',
                'standard atmospheric conditions for footwear testing', 'lab room temperature 23 degrees c 50 rh',
                'temperature tolerance for material conditioning vf-h01', 'how long must specimens be conditioned before testing',
                'minimum conditioning time 24 hours at 23c', 'what is the temperature and humidity standard in the qc lab',
                'iso 291 standard conditioning atmosphere', 'astm d618 laboratory conditioning parameters',
                'how many hours to stabilize vulcanized rubber before cutting', '16 hours stabilization post autoclave vulcanization',
                'conditioning room temperature 23 plus minus 2 celsius', 'relative humidity 50 plus minus 5 percent'
            ],
            'VF-B10' => [
                'what temperature is used for static compression set', 'oven temperature for vf-b10 compression set',
                'what is the baking temperature for eva compression set', 'compression set oven temp 50 degrees c',
                'temperature for astm d395 method b compression set', 'how hot is the oven for foam compression test',
                'temperature setting for midsole permanent deformation', '50 celsius compression set oven setting',
                'cooling time after oven baking for compression set', '30 minutes rest at room temp before measuring compression set'
            ],
            'VF-D02' => [
                'what is the cold temperature for ross flex test', 'temperature for cold chamber ross flex',
                'what temperature is used for winter ross flex', 'ross flex cold temp minus 10 degrees celsius',
                'sub-zero temperature setting for vf-d02', 'cold ross flex temperature minus 10c',
                'room temperature for ross flex 23 degrees c', 'what are the two temperatures tested in ross flex'
            ],
            'VF-E03' => [
                'what temperature is used in the uv yellowing chamber', 'chamber temperature for vf-e03 uv test',
                'temperature for uva 340 accelerated yellowing', 'uv light test chamber temp 50 degrees celsius',
                'astm g154 uv chamber operating temperature', 'how hot is the uv chamber for white sole testing',
                'temperature setting for fluorescent uv exposure', '50c temperature for uv discoloration test'
            ],
            'VF-E19' => [
                'what temperature is used for the jungle test hydrolysis', 'what is the relative humidity for pu hydrolysis test',
                'humidity chamber setting for vf-e19 jungle test', 'jungle test parameters 70c and 95 rh',
                'temperature for polyurethane tropical aging test', '70 degrees celsius and 95 percent relative humidity',
                'environmental chamber settings for pu crumbling test', 'hydrolysis chamber temperature 70c',
                'relative humidity 95 percent for jungle test', 'how hot and humid is the pu hydrolysis chamber'
            ],
            'VF-J02' => [
                'what temperature is the sand bath for heat insulation test', 'hot sand bath temp for thermal conductivity',
                'temperature of sand bath 150 degrees celsius', 'heat insulation test sand temperature'
            ],
            'VF-E16' => [
                'what temperature is used for perspirometer perspiration test', 'incubator temperature for perspiration fastness',
                '37 degrees celsius perspirometer incubator', 'perspiration test temperature setting'
            ],
            'VF-I05' => [
                'what temperature is used for oil resistance swelling test', 'oil immersion temperature 23 degrees c',
                'temperature for soling hydrocarbon swelling test'
            ]
        ];

        foreach ($tempHumidity as $code => $list) {
            foreach ($list as $q) {
                $add($code, $q);
            }
        }

        // =========================================================================
        // 3. CYCLE COUNTS, REVOLUTIONS, DURATIONS & FREQUENCIES (110 queries)
        // =========================================================================
        $cycleCounts = [
            'VF-C01' => [
                'how many meters is the din abrasion test run', 'abrasion distance for vf-c01 din test',
                'how many drum revolutions in din abrasion test', '40 meters distance for din rotary abrasion',
                '84 drum revolutions for din abrasion run', 'what is the test duration for din abrasion',
                'how long does a din abrasion run take', 'pre-grind distance for embossed rubber 20 meters'
            ],
            'VF-C04' => [
                'how many rubs for dry martindale abrasion test', 'how many rubs for wet martindale abrasion test',
                'martindale abrasion cycle count for collar lining', 'dry collar lining rubs 25600 martindale',
                'wet collar lining rubs 12800 martindale', 'upper canvas martindale rubs 51200 cycles',
                'inspection milestones for martindale abrasion rubs', 'how many cycles to run on martindale abrader'
            ],
            'VF-C05' => [
                'how many cycles for taber abrasion test', 'taber abrasion revolution count 1000 cycles',
                'how many revolutions on taber abrader', 'cycle count for astm d3884 taber test',
                '1000 cycles taber test run setting'
            ],
            'VF-D01' => [
                'how many cycles for dry bally flex test', 'how many cycles for wet bally flex test',
                'bally flex cycle requirement for upper leather', '50000 cycles dry bally flex requirement',
                '20000 cycles wet bally flex requirement', 'synthetic leather bally flex 100000 cycles',
                'how long to run bally flexometer for leather', 'total flexing cycles for iso 5402 bally flex'
            ],
            'VF-D02' => [
                'how many cycles for room temperature ross flex test', 'how many cycles for cold temperature ross flex test',
                'ross flex cycle count at 23 degrees c 150000 cycles', 'ross flex cycle count at minus 10c 50000 cycles',
                '150000 cycles ross flex room temperature', '50000 cycles ross flex cold temp',
                'total flexing cycles for outsole cut growth test', 'astm d1052 ross flex cycle requirement'
            ],
            'VF-B10' => [
                'how many hours for static compression set test', 'compression set duration in oven',
                'how long to bake foam in compression set test', '6 hours compression set baking time',
                '22 hours duration for astm d395 compression set', 'test duration for vf-b10 compression set'
            ],
            'VF-B23' => [
                'how many cycles must a shoelace knot withstand', 'knot slip test cycle requirement',
                'how many untying cycles for shoelaces', '500 cycles minimum for knot slip test',
                '1000 cycles performance knot durability', 'satra tm153 cycle count requirement'
            ],
            'VF-E02' => [
                'how many double strokes for crocking test', 'crockmeter rubbing cycle count',
                '10 double strokes in 10 seconds for crockmeter', 'how many rubs in aatcc 8 crocking test',
                'cycle count for dry and wet crocking'
            ],
            'VF-E03' => [
                'how many hours for uv yellowing test', 'exposure duration for vf-e03 uv test',
                'how long to keep white rubber in uv chamber', '24 hours continuous uv exposure',
                'test duration for accelerated yellowing test', '24h uv chamber duration'
            ],
            'VF-E19' => [
                'how many days for jungle test hydrolysis', 'how long is the pu hydrolysis test',
                'jungle test duration 10 days', '240 hours exposure for polyurethane hydrolysis',
                'how many hours in 70c 95rh humidity chamber', '10 days tropical aging duration'
            ],
            'VF-G02' => [
                'how many cycles for whole shoe flex test', 'whole shoe walking simulator cycle count',
                '150000 cycles whole shoe flexing requirement', 'how long to run satra tm92 whole shoe flex',
                'total flexing cycles for assembled footwear'
            ],
            'VF-G04' => [
                'how many cycles for dynamic waterproof shoe test', 'waterproof flex cycle requirement',
                '15000 flexes in water without leaking', 'how long to flex waterproof shoe in water bath',
                'satra tm230 waterproof flex cycle count'
            ],
            'VF-J01' => [
                'how long is the electric shock test duration', 'voltage application time for 18000v test',
                '60 seconds duration for electric shock test', '1 minute high voltage test duration'
            ]
        ];

        foreach ($cycleCounts as $code => $list) {
            foreach ($list as $q) {
                $add($code, $q);
            }
        }

        // =========================================================================
        // 4. FORCES, LOADS, PRESSURES & WEIGHTS (110 queries)
        // =========================================================================
        $forcesLoads = [
            'VF-C01' => [
                'what is the contact load for din abrasion test', 'downward vertical force for din abrasion',
                'how much weight presses on specimen in din abrasion', '10 newtons contact load for din drum',
                'load setting for vf-c01 din rotary abrasion', 'vertical force 10n for iso 4649 abrasion',
                'specimen contact pressure on abrasive paper'
            ],
            'VF-E02' => [
                'what is the downward force for crockmeter test', 'vertical load applied on crockmeter finger',
                'how much weight on crocking rubbing finger', '9 newtons load for aatcc 8 crockmeter',
                '900 grams dead weight on crockmeter finger', 'downward force for color fastness to rubbing'
            ],
            'VF-A03' => [
                'what is the presser foot pressure for thickness gauge', 'contact pressure for dead weight thickness dial',
                'how much weight applies on thickness foot', '49.1 kpa pressure on thickness presser foot',
                '500 grams dead weight load on 10mm thickness foot', 'pressure specification for satra tm1 thickness'
            ],
            'VF-C04' => [
                'what is the contact pressure for martindale abrasion test', 'vertical load applied on martindale sample holder',
                '12 kpa contact pressure for martindale abrasion', '795 grams weight on martindale abradant holder',
                '9 kpa pressure for delicate textile martindale test', 'load setting for iso 12947 martindale test'
            ],
            'VF-C05' => [
                'what is the wheel load for taber abrasion test', 'how much weight on taber abrader arms',
                '1000 grams load per wheel on taber machine', 'total 2000g load on dual taber wheels',
                'load setting for cs-10 abrasive wheels'
            ],
            'VF-B15' => [
                'what is the vertical load on james slip machine', 'vertical weight for james slip resistance test',
                '80 pounds dead weight load on james tester', '355 newtons normal load for james slip machine',
                'vertical force applied on test shoe strut'
            ],
            'VF-B16' => [
                'what is the weight and size of ball for rebound test', 'steel ball diameter for vertical rebound resiliency',
                '5/8 inch diameter steel ball for rebound test', '16.3 grams steel ball mass for astm d2632',
                'drop height 460 mm for steel ball rebound'
            ],
            'VF-B04' => [
                'what pull force must an eyelet withstand', 'detachment load requirement for shoe hardware',
                'minimum 200 newtons pull force for eyelet', '20.4 kgf pull-out force for shoe eyelet',
                'minimum 300 newtons pull force for d-ring buckle'
            ],
            'VF-B24' => [
                'what pull force must a shoelace aglet withstand', 'retention force requirement for shoelace tip',
                'minimum 60 newtons pull force for plastic aglet', '6.1 kgf retention force for lace tip',
                'minimum 80 newtons retention force for metal aglet'
            ],
            'VF-J01' => [
                'what voltage is applied in electric shock resistance test', 'test voltage for safety footwear dielectric test',
                '18000 volts ac for electric shock test', '18 kv rms test voltage at 60 hz',
                'high voltage electrical hazard test specification'
            ],
            'VF-G04' => [
                'what is the water depth for dynamic waterproof flexing', 'water level in water flexing bath',
                'water depth 25 mm above feather line', 'water bath height for satra tm230 dynamic test'
            ],
            'VF-B01' => [
                'what pneumatic grip pressure is used for tensile jaws', 'air pressure for tensile machine pneumatic clamps',
                '6 bar pneumatic jaw grip pressure for rubber dumbbell'
            ]
        ];

        foreach ($forcesLoads as $code => $list) {
            foreach ($list as $q) {
                $add($code, $q);
            }
        }

        // =========================================================================
        // 5. ACCEPTANCE CRITERIA, PASS/FAIL LIMITS & TOLERANCES (160 queries)
        // =========================================================================
        $acceptanceCriteria = [
            'VF-B01' => [
                'what is the passing tensile strength for vulcanized rubber outsole', 'tensile strength requirement for vans waffle sole',
                'minimum tensile strength for rubber outsole 120 kg/cm2', '120 kg/cm2 or 11.8 mpa tensile requirement',
                'what is the passing elongation at break for outsole rubber', 'minimum elongation requirement for rubber 450 percent',
                '450 percent elongation at break threshold', 'tensile strength passing requirement for foxing tape 90 kg/cm2',
                'elongation requirement for foxing tape 500 percent', 'passing criteria for astm d412 rubber tensile test',
                'what tensile strength fails outsole rubber inspection', 'what elongation fails vulcanized rubber testing',
                'eva foam tensile strength passing threshold 25 kg/cm2'
            ],
            'VF-B05' => [
                'what is the passing tear strength for die c tear test', 'die c tear strength requirement for outsole rubber',
                'minimum tear strength for vulcanized rubber 30 kg/cm', '30.0 kg/cm or 29.4 kn/m die c tear pass mark',
                'passing threshold for foxing tape tear strength 25 kg/cm', 'what tear strength fails die c tear test',
                'die c tear requirement in astm d624'
            ],
            'VF-B06' => [
                'what is the passing split tear strength for eva midsole', 'split tear requirement for molded foam midsole',
                'minimum split tear strength 2.5 kg/cm', '2.5 kg/cm or 2.45 n/mm split tear pass mark',
                'pu footbed split tear threshold 2.0 kg/cm', 'what split tear value fails eva foam inspection'
            ],
            'VF-B07' => [
                'what is the passing trouser tear strength for upper leather', 'trouser tear requirement for suede and nubuck',
                'minimum trouser tear for upper leather 3.0 kgf', '3.0 kgf or 29.4 newtons trouser tear pass mark',
                'synthetic leather trouser tear requirement 2.5 kgf', 'textile lining trouser tear threshold 1.5 kgf'
            ],
            'VF-B08' => [
                'what is the passing stitch tear strength for upper leather', 'stitch tear requirement for shoe upper material',
                'minimum stitch tear for upper leather 7.0 kgf', '7.0 kgf or 68.6 newtons stitch tear pass mark',
                'collar lining stitch tear requirement 4.0 kgf'
            ],
            'VF-B10' => [
                'what is the maximum allowable static compression set for eva', 'passing threshold for static compression set',
                'compression set requirement for eva molded midsole 55 percent', 'maximum 55 percent permanent deformation allowed',
                'high performance pu insole compression set limit 15 percent', 'what compression set percentage fails cushioning test',
                'why is lower compression set percentage superior'
            ],
            'VF-B15' => [
                'what is the passing coefficient of friction for james slip test', 'static slip resistance requirement on dry tile',
                'minimum coefficient of friction 0.50 scof dry', 'wet tile slip resistance requirement 0.40 scof',
                'passing criteria for astm f489 james slip test'
            ],
            'VF-B16' => [
                'what is the passing percentage for vertical ball rebound test', 'ball rebound resiliency requirement for eva foam',
                'minimum 50 percent rebound for high-energy eva cushioning', '40 to 50 percent rebound for standard insole foam',
                'passing threshold for astm d2632 ball bounce test'
            ],
            'VF-B23' => [
                'what is the passing requirement for shoelace knot slip test', 'how many cycles must shoelace knot stay tied',
                'minimum 500 cycles without knot loosening or untying', 'passing threshold for satra tm153 knot slip test'
            ],
            'VF-B24' => [
                'what is the passing pull force for shoelace aglet retention', 'plastic lace tip detachment passing requirement',
                'minimum 60 newtons retention force for plastic aglet', 'minimum 80 newtons retention force for metal aglet',
                'passing criteria for satra tm154 aglet retention'
            ],
            'VF-C01' => [
                'what is the maximum allowable volume loss for din abrasion', 'din abrasion volume loss requirement for vans waffle sole',
                'maximum 150 mm3 volume loss for vulcanized outsole rubber', '150 cubic millimeters maximum din abrasion threshold',
                'foxing band din abrasion volume loss limit 200 mm3', 'eva midsole din abrasion limit 200 mm3',
                'what volume loss fails din abrasion test', 'passing criteria for iso 4649 rotary drum abrasion'
            ],
            'VF-C04' => [
                'what is the passing requirement for dry martindale abrasion', 'what is the passing requirement for wet martindale abrasion',
                'collar lining dry martindale requirement 25600 rubs', 'collar lining wet martindale requirement 12800 rubs',
                'upper canvas martindale requirement 51200 rubs', 'what constitutes a failure in martindale abrasion test',
                'fiber hole formation or yarn severance is a failure'
            ],
            'VF-C05' => [
                'what is the maximum allowable mass loss for taber abrasion', 'taber abrasion passing requirement after 1000 cycles',
                'maximum 100 mg mass loss for synthetic upper coating', 'maximum 150 mg mass loss for sockliner top cover'
            ],
            'VF-D01' => [
                'what is the passing requirement for bally flex test on leather', 'how many flex cycles without grain cracking on upper leather',
                'minimum 50000 cycles dry bally flex without finish cracking', 'minimum 20000 cycles wet bally flex without cracking',
                'passing criteria for iso 5402 bally flex test'
            ],
            'VF-D02' => [
                'what is the maximum allowable cut growth for ross flex at room temp', 'cut growth requirement for ross flex at minus 10c',
                'maximum 4.0 mm cut growth after 150000 cycles at 23c', 'maximum 4.0 mm cut growth after 50000 cycles at minus 10c',
                'total cut length must not exceed 6.0 mm on ross flex', 'complete specimen break or snap is an immediate fail',
                'passing criteria for astm d1052 ross flex test'
            ],
            'VF-E02' => [
                'what is the passing gray scale grade for dry crocking test', 'what is the passing gray scale grade for wet crocking test',
                'dry rubbing color fastness requirement grade 4.0', 'wet rubbing color fastness requirement grade 3.0',
                'dark color concession wet crocking grade 2.5', 'passing criteria for aatcc 8 crocking test'
            ],
            'VF-E03' => [
                'what is the passing gray scale grade for uv yellowing test', 'accelerated uv yellowing requirement for white foxing tape',
                'minimum grade 4.0 on gray scale for color change after 24h uv', 'white rubber outsole yellowing threshold grade 4.0',
                'passing criteria for vf-e03 uv discoloration test'
            ],
            'VF-E19' => [
                'what is the passing criteria for jungle test hydrolysis', 'what indicates a pass on 10 days pu hydrolysis test',
                'zero crumbling zero cracking zero stickiness after 10 days at 70c 95rh', 'passing manual 180 degree pinch bend after jungle test',
                'minimum 50000 post aging flex cycles without cracking'
            ],
            'VF-F01' => [
                'what is the passing whole bottom average for sole adhesion', 'what is the individual point minimum for shoe bonding',
                'whole bottom average bonding requirement 3.0 kg/cm', 'individual point minimum bonding requirement 2.5 kg/cm',
                'allowable concession for sole bonding 2.0 to 2.4 kg/cm with 100% rubber tear', 'what bonding value is an immediate failure',
                'clean glue separation below 3.0 kg/cm fails sole adhesion inspection', 'passing criteria for vf-f01 sole adhesion test'
            ],
            'VF-G02' => [
                'what is the passing criteria for whole shoe flex test', 'maximum allowable foxing delamination on whole shoe flex',
                'maximum 2.0 mm foxing separation after 150000 flex cycles', 'maximum 4.0 mm sole crack growth on whole shoe flex',
                'zero upper cracking zero stitch rupture after 150000 cycles'
            ],
            'VF-G04' => [
                'what is the passing requirement for dynamic waterproof flexing', 'how many flex cycles in water without leaking into shoe',
                'minimum 15,000 flexes without water leakage inside lining', 'zero electrical sensor alarm during waterproof flex test',
                'maximum 0.5 grams lining moisture weight gain allowed'
            ],
            'VF-J01' => [
                'what is the maximum allowable leakage current for electric shock test', 'passing criteria for astm f2412 dielectric test',
                'maximum 1.0 milliampere leakage current at 18000v', 'zero dielectric puncture zero arcing through sole assembly'
            ],
            'VF-A01' => [
                'what is the standard hardness for vans waffle outsole rubber', 'shore a durometer hardness specification for waffle sole',
                'waffle outsole rubber hardness 62 plus minus 3 shore a', 'foxing tape rubber hardness 58 plus minus 3 shore a',
                'eva molded midsole foam hardness 45 to 55 shore c'
            ],
            'VF-A03' => [
                'what is the standard thickness for rubber outsole base', 'nominal outsole rubber thickness 2.0 plus minus 0.2 mm',
                'foxing band outer layer thickness 1.0 to 1.2 mm'
            ],
            'VF-A04' => [
                'what is the standard specific gravity for vulcanized outsole rubber', 'nominal rubber density 1.15 to 1.25 g/cm3',
                'cellular eva midsole density 0.20 to 0.35 g/cm3'
            ]
        ];

        foreach ($acceptanceCriteria as $code => $list) {
            foreach ($list as $q) {
                $add($code, $q);
            }
        }

        // =========================================================================
        // 6. SPECIMEN DIMENSIONS, CUTTER DIE TYPES & THICKNESSES (110 queries)
        // =========================================================================
        $specimenDies = [
            'VF-B01' => [
                'what cutter die is used for rubber tensile test', 'astm d412 die c dumbbell cutter dimensions',
                'type iv dumbbell cutter for tensile test', 'what is the benchmark gauge length for tensile dumbbell',
                '25 mm gauge length bench marks on rubber dumbbell', 'specimen thickness for tensile test 2.0 mm',
                'how many tensile dumbbells to cut parallel and perpendicular', '6 dumbbell specimens for tensile test'
            ],
            'VF-B05' => [
                'what cutter die is used for die c tear strength', 'astm d624 die c cutter shape and dimensions',
                '90 degree internal angle die c specimen', 'what depth razor nick is cut on die c specimen',
                '0.50 mm razor nick at 90 degree inside apex', 'specimen thickness for die c tear test 2.0 mm',
                'how many die c tear specimens to cut with and across grain'
            ],
            'VF-B06' => [
                'what are the specimen dimensions for split tear test', 'foam strip size for satra tm65 split tear',
                '25 mm width by 150 mm length split tear specimen', '10 mm thickness for eva split tear sample',
                '25 mm initial horizontal center slit depth'
            ],
            'VF-B07' => [
                'what are the specimen dimensions for trouser tear test', 'leather swatch size for astm d2261 trouser tear',
                '50 mm width by 150 mm length trouser specimen', '75 mm longitudinal slit forming two trouser legs'
            ],
            'VF-B10' => [
                'what are the specimen dimensions for static compression set', 'cylindrical disc size for foam compression set',
                '29 mm diameter by 12.5 mm thickness cylindrical foam disc', 'spacer bar height for 25 percent compression set 9.5 mm',
                'specimen preparation for astm d395 compression set'
            ],
            'VF-C01' => [
                'what are the specimen dimensions for din abrasion test', 'cylindrical plug size for rotary drum abrasion',
                '16 mm diameter cylindrical plug for din abrasion', 'minimum 6 mm thickness for din abrasion specimen',
                'how much does din specimen protrude from holder 2.0 mm'
            ],
            'VF-C04' => [
                'what are the specimen dimensions for martindale abrasion test', 'circular disc size for martindale sample holder',
                '38 mm diameter circular disc for martindale test', 'foam backing disc under martindale specimen'
            ],
            'VF-C05' => [
                'what are the specimen dimensions for taber abrasion test', 'square plate size for taber rotary abrader',
                '100 mm by 100 mm square plate with 6.3 mm center arbor hole'
            ],
            'VF-D01' => [
                'what are the specimen dimensions for bally flex test', 'leather swatch size for bally flexometer',
                '70 mm length by 45 mm width rectangular leather swatch', 'inverted v-crease fold preparation for bally flex'
            ],
            'VF-D02' => [
                'what are the specimen dimensions for ross flex test', 'soling strip size for astm d1052 ross flex',
                '25 mm width by 150 mm length by 6 mm thickness soling strip', '2.0 mm chisel cut pierced in flex groove center'
            ],
            'VF-A01' => [
                'what is the minimum thickness for durometer hardness test', 'how thick must rubber be for shore a reading',
                'minimum 6.0 mm thickness for durometer hardness reading', 'stacking up to 3 plies of 2mm rubber sheets for hardness'
            ],
            'VF-A04' => [
                'what is the sample mass for specific gravity density test', 'minimum 2.5 grams mass for archimedes density test',
                'sample preparation for water displacement density'
            ],
            'VF-E02' => [
                'what are the specimen dimensions for crocking rubbing test', 'swatch size for aatcc crockmeter 50 mm by 130 mm',
                'standard white cotton crocking cloth 50x50 mm'
            ],
            'VF-E03' => [
                'what sample size is used for uv yellowing test', '75 mm by 50 mm flat swatch for uv chamber',
                'aluminum foil masking on 50 percent of specimen'
            ]
        ];

        foreach ($specimenDies as $code => $list) {
            foreach ($list as $q) {
                $add($code, $q);
            }
        }

        // =========================================================================
        // 7. MATHEMATICAL FORMULAS, CALCULATIONS & MATH (110 queries)
        // =========================================================================
        $formulasMath = [
            'VF-C01' => [
                'what is the formula for din abrasion volume loss', 'how to calculate mm3 in din abrasion test',
                'volume loss equation with density and reference rubber', 'mass loss times 200 divided by density times reference loss',
                'din abrasion volume loss calculation formula', 'how is mass loss converted to volume loss in mm3',
                'why is specific gravity needed for din abrasion calculation'
            ],
            'VF-B01' => [
                'what is the formula for tensile strength', 'how to calculate tensile strength in kg/cm2 or mpa',
                'peak force divided by cross sectional area width times thickness', 'formula for ultimate elongation at break percentage',
                'break length minus original gauge length divided by original length times 100', 'tensile modulus calculation formula'
            ],
            'VF-B05' => [
                'what is the formula for die c tear strength', 'how to calculate tear strength in kg/cm',
                'peak tear force divided by specimen thickness in cm', 'tear strength calculation formula astm d624'
            ],
            'VF-B06' => [
                'what is the formula for split tear strength', 'how to calculate split tear in kg/cm',
                'average plateau tear force divided by specimen width in cm', 'split tear calculation formula satra tm65'
            ],
            'VF-B10' => [
                'what is the formula for static compression set percentage', 'how to calculate compression set recovery percentage',
                'original thickness minus final thickness divided by original minus spacer height times 100',
                'compression set calculation formula astm d395'
            ],
            'VF-B16' => [
                'what is the formula for ball rebound resiliency percentage', 'how to calculate rebound percentage',
                'bounce apex height in mm divided by 460 mm drop height times 100', 'ball rebound formula astm d2632'
            ],
            'VF-A04' => [
                'what is the formula for specific gravity archimedes principle', 'how to calculate density from air and water weights',
                'mass in air divided by mass in air minus mass in water', 'density equals specific gravity times 0.9975 g/cm3',
                'archimedes water displacement formula'
            ],
            'VF-F01' => [
                'what is the formula for shoe bonding average', 'how to calculate whole bottom average sole adhesion',
                'sum of toe inside ball outside ball waist and heel divided by five', 'bonding strength formula force divided by bond width'
            ],
            'WF-BONDING-CALC' => [
                'what is the formula for shoe bonding tolerance band', 'how is 100 percent rubber tear evaluated in bonding',
                'calculation of whole bottom average vs individual minimum'
            ],
            'WF-DIN-CALC' => [
                'step by step din abrasion volume loss calculation', 'how to calculate reference standard rubber mass loss',
                'din volume loss formula with archimedes density'
            ],
            'WF-RESULTS' => [
                'how is overall test result pass tolerable fail calculated', 'criteria calculation for tolerable result rating',
                'how does nexus-l evaluate test pass fail status'
            ]
        ];

        foreach ($formulasMath as $code => $list) {
            foreach ($list as $q) {
                $add($code, $q);
            }
        }

        // =========================================================================
        // 8. TESTING EQUIPMENT, APPARATUS & FIXTURE NAMES (110 queries)
        // =========================================================================
        $equipmentFixtures = [
            'VF-B01' => [
                'what machine is used for rubber tensile testing', 'universal tensile machine utm for vf-b01',
                'gotech u60 tensile machine', 'pneumatic serrated clamping jaws for tensile',
                'optical extensometer for elongation tracking'
            ],
            'VF-B05' => [
                'what machine is used for die c tear testing', 'eccentric roller grips for tear testing',
                'astm d624 die c cutting press and micrometer razor nick fixture'
            ],
            'VF-B10' => [
                'what apparatus is used for static compression set', 'compression set clamping fixture with 9.5mm spacer bars',
                'forced convection laboratory aging oven at 50c'
            ],
            'VF-B15' => [
                'what machine is used for static slip resistance', 'james machine with articulated strut and chart recorder',
                'quarry tile and stainless steel test surfaces'
            ],
            'VF-B16' => [
                'what equipment is used for ball rebound resiliency', 'vertical clear tube with electromagnetic release mechanism',
                'steel ball 5/8 inch drop apparatus astm d2632'
            ],
            'VF-B23' => [
                'what apparatus is used for knot slip testing', 'satra tm153 reciprocating knot slip tester',
                'pneumatic loop pull jaws for shoelace knot'
            ],
            'VF-C01' => [
                'what machine is used for din rotary drum abrasion', 'rotary drum abrader with p60 sandpaper cloth',
                'iso 4649 drum abrader with 150mm rotating cylinder'
            ],
            'VF-C04' => [
                'what equipment is used for martindale abrasion', 'martindale multi-head abrader with lissajous motion',
                'worsted wool abradant fabric and wool felt pad'
            ],
            'VF-C05' => [
                'what machine is used for taber abrasion', 'taber rotary platform abrader with vacuum nozzle',
                'cs-10 and h-18 resilient abrasive wheels'
            ],
            'VF-D01' => [
                'what apparatus is used for bally flex testing', 'bally flexometer with 22.5 degree rotating clamp',
                'iso 5402 bally flex machine for leather'
            ],
            'VF-D02' => [
                'what machine is used for ross flexing test', 'ross flex machine with 10mm mandrel pin',
                'sub-zero refrigerated cold chamber attachment for ross flex'
            ],
            'VF-E02' => [
                'what equipment is used for color crocking test', 'standard aatcc crockmeter with 16mm acrylic finger',
                'verivide d65 daylight color matching light booth'
            ],
            'VF-E03' => [
                'what machine is used for uv yellowing testing', 'fluorescent uv accelerated weathering chamber',
                'uva-340 ultraviolet lamp fixture at 50c'
            ],
            'VF-E19' => [
                'what equipment is used for jungle test hydrolysis', 'sealed environmental humidity conditioning chamber',
                '70c 95rh tropical climate simulation chamber'
            ],
            'VF-F01' => [
                'what fixture is used for whole footwear sole adhesion', 'articulated lasted shoe holder for tensile machine',
                'pneumatic sole pulling clamp for 90 degree peel'
            ],
            'VF-G02' => [
                'what machine is used for whole shoe flexing', 'satra tm92 reciprocating whole shoe walking flexer',
                'mechanical foot form with pneumatic 45 degree drive'
            ],
            'VF-G04' => [
                'what equipment is used for dynamic waterproof testing', 'water flexing bath with electrical conductivity sensor probes',
                'satra tm230 dynamic footwear water penetration tester'
            ],
            'VF-J01' => [
                'what machine is used for electric shock test', 'high-voltage ac dielectric breakdown test set 18kv',
                'stainless steel ball bed and grounded mesh electrode'
            ],
            'VF-A01' => [
                'what gauge is used for rubber hardness testing', 'shore a durometer with mechanical operating stand',
                'calibrated durometer gauge for waffle sole'
            ],
            'VF-A03' => [
                'what tool is used for measuring material thickness', 'dead-weight thickness dial gauge with 10mm flat foot'
            ],
            'VF-A04' => [
                'what balance is used for specific gravity density', 'electronic analytical balance with hydrostatic immersion kit'
            ]
        ];

        foreach ($equipmentFixtures as $code => $list) {
            foreach ($list as $q) {
                $add($code, $q);
            }
        }

        // =========================================================================
        // 9. REQUIRED DTR REPORT FIELDS & OPERATOR DATA ENTRIES (110 queries)
        // =========================================================================
        $reportFields = [
            'VF-B01' => [
                'what data must be entered in dtr for rubber tensile test', 'required fields for vf-b01 tensile report',
                'specimen thickness width peak force elongation break location', 'how to record tensile strength in laboratory report',
                'what readings are entered from utm tensile test'
            ],
            'VF-B05' => [
                'what data must be entered in dtr for die c tear test', 'required fields for vf-b05 tear report',
                'specimen thickness peak force tear strength parallel and perpendicular'
            ],
            'VF-B06' => [
                'what data must be entered in dtr for split tear test', 'required fields for eva split tear report',
                'specimen width thickness average tear force split tear kg/cm'
            ],
            'VF-B10' => [
                'what data must be entered in dtr for compression set', 'required fields for vf-b10 compression set report',
                'initial thickness spacer height final thickness recovery compression set percentage'
            ],
            'VF-B16' => [
                'what data must be entered in dtr for ball rebound test', 'required fields for vertical rebound report',
                'specimen thickness drop height 3 bounce readings mean rebound percentage'
            ],
            'VF-B23' => [
                'what data must be entered in dtr for knot slip test', 'required fields for shoelace untying report',
                'lace length construction cycles completed knot slip status'
            ],
            'VF-B24' => [
                'what data must be entered in dtr for aglet retention test', 'required fields for lace tip pull report',
                'aglet material peak pull-off force detachment failure mode'
            ],
            'VF-C01' => [
                'what data must be entered in dtr for din abrasion test', 'required fields for vf-c01 din abrasion report',
                'specific gravity initial mass final mass mass loss reference loss volume loss mm3',
                'what readings are recorded from balance for din abrasion'
            ],
            'VF-C04' => [
                'what data must be entered in dtr for martindale abrasion', 'required fields for lining abrasion report',
                'test condition dry wet load 12kpa rubs reached breakdown observation'
            ],
            'VF-D01' => [
                'what data must be entered in dtr for bally flex test', 'required fields for leather bally flex report',
                'condition dry wet cycles completed crack severity magnification 10x'
            ],
            'VF-D02' => [
                'what data must be entered in dtr for ross flex test', 'required fields for vf-d02 ross flex report',
                'temperature 23c minus 10c cycles initial 2mm cut final cut cut growth mm'
            ],
            'VF-E02' => [
                'what data must be entered in dtr for crocking test', 'required fields for color rubbing fastness report',
                'colorway warp weft dry crock grade wet crock grade gray scale rating'
            ],
            'VF-E03' => [
                'what data must be entered in dtr for uv yellowing test', 'required fields for accelerated yellowing report',
                'chamber temp 50c duration 24h uva irradiance color change grade delta e'
            ],
            'VF-E19' => [
                'what data must be entered in dtr for jungle test hydrolysis', 'required fields for pu hydrolysis report',
                'temp 70c humidity 95rh duration 10 days pinch bend post aging flex cycles'
            ],
            'VF-F01' => [
                'what data must be entered in dtr for sole adhesion test', 'required fields for shoe bonding report',
                'toe inside ball outside ball waist heel readings whole bottom average percent rubber tear',
                'how to enter individual bonding points in dtr'
            ],
            'VF-G02' => [
                'what data must be entered in dtr for whole shoe flex test', 'required fields for walking flex report',
                'shoe style cycles 150000 foxing delamination mm sole crack mm upper condition'
            ],
            'VF-G04' => [
                'what data must be entered in dtr for waterproof flex test', 'required fields for dynamic waterproof report',
                'water depth 25mm cycles reached water penetration time leak location'
            ],
            'VF-J01' => [
                'what data must be entered in dtr for electric shock test', 'required fields for dielectric safety report',
                'voltage 18kv frequency 60hz duration 60s leakage current ma dielectric breakdown'
            ],
            'VF-A01' => [
                'what data must be entered in dtr for durometer hardness', 'required fields for hardness test report',
                'durometer type specimen thickness 5 individual readings mean shore a'
            ],
            'VF-A03' => [
                'what data must be entered in dtr for thickness measurement', 'required fields for thickness dial gauge report',
                'location 3 thickness readings average thickness specification tolerance'
            ],
            'VF-A04' => [
                'what data must be entered in dtr for specific gravity test', 'required fields for density balance report',
                'mass in air mass in water calculated specific gravity density g/cm3'
            ],
            'WF-UPLOAD' => [
                'what columns must be in the uploaded machine excel file', 'required fields in u60 outdata spreadsheet',
                'mapping columns for raw machine test upload'
            ],
            'WF-GENERATE' => [
                'what fields appear on the official generated pdf certificate', 'header fields for laboratory test report pdf'
            ]
        ];

        foreach ($reportFields as $code => $list) {
            foreach ($list as $q) {
                $add($code, $q);
            }
        }

        // =========================================================================
        // 10. FAILURE SYMPTOMS, DEFECT ROOT CAUSES & CAPA ACTIONS (110 queries)
        // =========================================================================
        $failureCauses = [
            'VF-F01' => [
                'what causes sole adhesion failure at the toe', 'root cause of sole peeling off vulcanized shoe',
                'how to fix adhesive separation in shoe bonding test', 'why did sole bond test fail with clean glue peel',
                'insufficient primer dry time sole bonding failure', 'low halogenation on rubber outsole bonding defect',
                'press pressure too low causing sole delamination', 'corrective action plan for failed sole adhesion lot'
            ],
            'VF-B01' => [
                'what causes low rubber tensile strength in outsole', 'why did vulcanized rubber fail tensile elongation',
                'under-cured vulcanization causes low tensile strength', 'incorrect sulfur accelerator ratio soling defect',
                'excessive calcium carbonate filler reduces rubber tensile', 'how to troubleshoot rubber tensile break below 120 kg/cm2'
            ],
            'VF-B05' => [
                'what causes low die c tear strength on rubber sole', 'why is rubber tearing too easily along flex line',
                'root cause of low tear initiation resistance', 'recycled buffing dust reduces rubber tear strength'
            ],
            'VF-B10' => [
                'what causes high compression set in eva foam midsole', 'why does eva foam get permanently squished flat',
                'insufficient dcp peroxide crosslinking causes compression set', 'curing time too short in eva molding press',
                'corrective action for eva compression set over 55 percent'
            ],
            'VF-C01' => [
                'what causes high volume loss in din abrasion test', 'why did rubber sole fail din abrasion with 180 mm3 loss',
                'poor rubber cross-link density causes rapid abrasion wear', 'soft rubber compound wear remedies',
                'adjusting silica and carbon black fillers to improve abrasion'
            ],
            'VF-D02' => [
                'what causes rapid cut growth in ross flex test', 'why did rubber sole crack completely in cold ross flex',
                'high glass transition temperature rubber cracks at minus 10c', 'poor compound dispersion causes flex cracking in groove',
                'sharp corner radius in sole mold causes flex fatigue failure'
            ],
            'VF-E02' => [
                'what causes wet crocking failure on black suede leather', 'why did dyed canvas fail color rubbing fastness',
                'unfixed surface dyestuff causes crocking staining', 'poor dye exhaustion in textile dyeing process',
                'rinsing and fixing agent remedies for crocking failure'
            ],
            'VF-E03' => [
                'what causes white foxing tape to turn yellow in display', 'why did white vulcanized sole fail uv yellowing test',
                'bht phenolic antioxidant migration causes yellow staining', 'depletion of hindered amine light stabilizer hals',
                'titanium dioxide pigment quality causes yellowing under uv'
            ],
            'VF-E19' => [
                'what causes polyurethane sole to crumble into powder', 'why did pu midsole dissolve into sticky mess in jungle test',
                'polyester polyurethane water molecule chain scission hydrolysis', 'remedy: use polyether pu or add carbodiimide stabilizer'
            ],
            'VF-B23' => [
                'what causes shoelaces to untie constantly while walking', 'why did shoelace fail knot slip test at 200 cycles',
                'excessive silicone wax coating reduces yarn friction', 'smooth circular polyester weave unties faster than flat cotton'
            ],
            'VF-B24' => [
                'what causes plastic aglet to pull off shoelace tip', 'why did aglet fail 60 newtons retention pull test',
                'acetone solvent temperature too low during aglet crimping', 'insufficient celluloid film bonding on braided lace'
            ],
            'VF-G04' => [
                'what causes whole shoe to leak water during dynamic flex', 'why did waterproof boot fail at 5000 flex cycles',
                'needle perforation hole along waterproof membrane seam', 'insufficient seam sealing tape heat activation temperature'
            ],
            'WF-REMEDIATION' => [
                'what is the standard quarantine protocol when a lab test fails', 'how to issue a corrective and preventive action capa in dtr',
                'quarantine tag on production lot after physical testing failure', 're-testing protocol: 3 additional pairs from suspect lot'
            ]
        ];

        foreach ($failureCauses as $code => $list) {
            foreach ($list as $q) {
                $add($code, $q);
            }
        }

        // =========================================================================
        // 11. DEEP SPECIALIZED DATA INQUIRIES ACROSS ALL TEST METHODS (350+ queries)
        // =========================================================================
        $deepMethods = [
            'VF-A02' => [
                'what balance precision is required for weight determination', 'areal weight g/m2 test procedure',
                'analytical balance precision 0.001g for component weight', 'astm d3776 weight test method'
            ],
            'VF-A06' => [
                'what gauge is used for leather softness test', 'blc barrows leather softness tester',
                'leather softness test aperture size 25mm', 'iso 17235 leather softness specification'
            ],
            'VF-A07' => [
                'what is the test method for yarn count and denier', 'tex numbering system test method astm d1907',
                'how is linear density of sewing thread measured', 'wrap reel and analytical balance yarn test'
            ],
            'VF-A08' => [
                'what is the oven temperature for non-volatile solids content', 'solids percentage test for shoe adhesive',
                '105 degrees c for 2 hours non-volatile content astm d2369', 'adhesive solids residue calculation'
            ],
            'VF-B02' => [
                'what is the pump rate for mullen hydraulic burst tester', '95 ml per min hydraulic fluid pumping rate',
                'circular specimen size for diaphragm burst 31mm', 'bursting strength passing threshold upper fabric 800 kpa',
                'lining bursting pressure requirement 500 kpa astm d3786'
            ],
            'VF-B03' => [
                'what is the peel and shear test for velcro fasteners', 'touch and close fastener peel test astm d5169',
                'longitudinal shear pull rate 300 mm/min for velcro', 'hook and loop roller weight application'
            ],
            'VF-B09' => [
                'what is the test method for baumann tear on leather', 'satra tm29 baumann tear strength test procedure',
                'heavy upper leather tear test with slot and pin', 'baumann tear passing requirement in newtons'
            ],
            'VF-B12' => [
                'what is the crush resistance test for safety footwear toe caps', 'safety toe cap compression test astm f2412',
                'minimum 2500 lbs crush resistance for steel toe cap', 'clearance requirement under toe cap after crush'
            ],
            'VF-B17' => [
                'what is the crosswise chain tensile strength for footwear zippers', 'zipper slider lock holding strength astm d2061',
                'zipper puller attachment detachment force test', 'minimum crosswise zipper tensile strength in newtons'
            ],
            'VF-B25' => [
                'what is the test surface for satra tm144 slip resistance', 'wet stainless steel with glycerol slip test',
                'quarry tile with sls solution dynamic coefficient of friction', 'en iso 13287 heel slip requirement 0.28'
            ],
            'VF-B26' => [
                'how many cycles for zipper repeated opening and closing', 'slide fasteners durability cycle test satra tm50',
                '5000 cycles repeated zipper opening without tooth damage', 'zipper reciprocating cycle test speed'
            ],
            'VF-B27' => [
                'what is the edgewise crush test for shoe boxes', 'packaging carton box crush strength astm d5264',
                'stacking compression strength for shoe shipping cartons'
            ],
            'VF-E01' => [
                'what is the test method for color fastness to water', 'water immersion fastness test iso 105-e01',
                'staining of multi-fiber fabric after water immersion', 'perspirometer 37c water fastness test'
            ],
            'VF-E04' => [
                'what oven temperature is used for heat aging test', 'heat resistance test at 70 degrees c for 7 days',
                'tensile and elongation retention after heat aging astm d573'
            ],
            'VF-E09' => [
                'what is the test method for dye migration fastness', 'plasticizer migration bleeding into light colored upper',
                'pigment bleeding into white foxing tape test'
            ],
            'VF-E16' => [
                'what is the test procedure for perspiration fastness', 'alkaline and acid artificial perspiration solution',
                '37c for 4 hours perspiration fastness iso 105-e04', 'gray scale staining rating for sweat fastness'
            ],
            'VF-E17' => [
                'what is the test method for rubber blooming inspection', 'sulfur white haze blooming on vulcanized outsole',
                'accelerated blooming oven test at 40c 72 hours'
            ],
            'VF-E21' => [
                'what is the test method for non-marking soling', 'black sole scuff mark test on gym floor',
                'scuff resistance on white linoleum tile', 'marking streak test under 5kg load'
            ],
            'VF-F03' => [
                'what is the test method for finish adhesion on leather', 'leather finish coating peel test satra tm14',
                'wet and dry finish adhesion strength to upper grain'
            ],
            'VF-F04' => [
                'what is the test method for laminar peel strength', 'insole board laminar separation resistance',
                'ply adhesion of multiply materials satra tm416'
            ],
            'VF-F06' => [
                'what is the pull out strength test for sandal straps', 'sandal strap attachment detachment force test',
                'minimum 200 newtons pull strength for sandal thong'
            ],
            'VF-F09' => [
                'what is the heel attachment strength test for ladies shoes', 'heel holding strength on lasted footwear satra tm113',
                'lateral and rearward heel impact detachment test'
            ],
            'VF-F10' => [
                'what is the top lift attachment strength test', 'high heel top lift pull off force satra tm108'
            ],
            'VF-F11' => [
                'what is the adhesive bond strength to cardboard insole', 'insole board cement bonding test'
            ],
            'VF-G03' => [
                'what is the test method for whole shoe washability', 'washing machine shrinkage and color bleed test for canvas shoe',
                '40c gentle wash cycle followed by air dry', 'dimensional change and sole delamination after washing'
            ],
            'VF-G05' => [
                'what is the china gb standard for footwear testing', 'gb t 3903 footwear testing specifications',
                'china national quality inspection standards for imported shoes'
            ],
            'VF-G06' => [
                'what is the water absorption test for insole materials', 'water absorption and desorption satra tm2',
                'moisture vapor absorption rate for shoe footbed'
            ],
            'VF-G07' => [
                'what is the static whole shoe waterproof immersion test', 'static dunk test in water tank for 2 hours',
                'water level up to feather line static inspection'
            ],
            'VF-G08' => [
                'what is the hydrostatic head test for waterproof textiles', 'water column pressure test aatcc 127',
                'minimum 10000 mm hydrostatic head for waterproof membrane', 'hydrostatic pressure gradient 60 mbar per minute'
            ],
            'VF-G09' => [
                'what is the centrifuge water extraction test', 'water extraction rate for waterproof footwear linings'
            ],
            'VF-I01' => [
                'what is the soxhlet extraction test for oil content', 'oil content percentage in leather and soling astm d3495',
                'hexane or dichloromethane solvent soxhlet extraction'
            ],
            'VF-I02' => [
                'what is the test method for fiber identification in upper textiles', 'microscopic fiber identification astm d276',
                'cotton polyester nylon blend ratio solubility analysis'
            ],
            'VF-I05' => [
                'what is the volume swelling limit for oil resistant soling', 'irm 903 reference oil immersion for 22 hours',
                'maximum plus 12 percent volume swell astm d471', 'oil resistance test for safety shoe outsole'
            ],
            'VF-J01' => [
                'what is the dielectric test procedure for safety boots', '18000v ac dielectric breakdown test set',
                'maximum 1.0 milliampere leakage current astm f2413', 'steel ball conductive foot probe setup'
            ],
            'VF-J02' => [
                'what is the heat insulation test procedure for soles', 'en iso 20344 sand bath at 150c for 30 minutes',
                'internal temperature rise must not exceed 22 degrees c', 'cold insulation ci temperature drop test at minus 17c'
            ]
        ];

        foreach ($deepMethods as $code => $list) {
            foreach ($list as $q) {
                $add($code, $q);
            }
        }

        
        // =========================================================================
        // 12. STANDARDS CROSS-REFERENCES, CALIBRATION & MATERIAL SPECIFICS (150+ queries)
        // =========================================================================
        $crossRefs = [
            "VF-B01" => [
                "astm d412 tensile test procedure", "iso 37 tensile properties rubber",
                "satra tm137 tensile strength standard", "tensile machine calibration certificate",
                "load cell calibration 5kn universal machine", "tensile modulus at 100 percent elongation",
                "tensile modulus at 300 percent elongation", "what is the tensile requirement for canvas upper fabric",
                "sewing thread tensile breaking strength", "shoelace breaking force tensile test",
                "dumbbell thickness variation limit 0.2mm", "optical tracking vs jaw separation for tensile",
                "tensile test speed in inches per minute 20 ipm", "pneumatic grip pressure calibration"
            ],
            "VF-B05" => [
                "astm d624 tear initiation standard", "iso 34-1 method b rubber tear",
                "die c cutter sharpening and inspection", "how to verify 0.5mm razor nick with micrometer",
                "die c tear test for translucent soling", "die c tear test for black rubber waffle"
            ],
            "VF-B10" => [
                "astm d395 compression set standard", "satra tm64 compression set procedure",
                "method b compression set under constant deflection", "spacer bar thickness tolerance 0.02mm",
                "compression set test for eva foam midsole", "compression set for open cell pu foam insole",
                "how to calculate foam recovery percentage", "compression set oven calibration 50c"
            ],
            "VF-C01" => [
                "iso 4649 rotary drum standard", "satra tm174 drum abrasion procedure",
                "din 53516 abrasion standard specification", "p60 aluminum oxide sandpaper calibration",
                "standard reference rubber sheet mass loss 200mg", "how to calibrate abrasive paper with standard rubber",
                "specimen holder rotation rate 0.32 rpm", "traverse distance per revolution on din drum",
                "abrasion volume loss formula with specific gravity", "din abrasion test for vulcanized foxing tape",
                "din abrasion test for molded cupsole", "din abrasion test for eva wedge midsole"
            ],
            "VF-C04" => [
                "iso 12947-2 martindale abrasion standard", "satra tm180 martindale test procedure",
                "standard worsted wool abradant cloth specification", "woven wool felt backing pad specification",
                "lissajous motion verification on martindale", "dry vs wet martindale abrasion testing",
                "martindale test for collar mesh fabric", "martindale test for heel counter lining",
                "martindale test for tongue fabric"
            ],
            "VF-D01" => [
                "iso 5402 bally flexometer standard", "satra tm55 bally flex test method",
                "bally flex test for full grain upper leather", "bally flex test for split suede leather",
                "bally flex test for nubuck leather", "bally flex test for synthetic pu upper",
                "how to clamp leather in bally flex clamp", "evaluating bally flex finish cracking under 10x lens"
            ],
            "VF-D02" => [
                "astm d1052 ross flex standard", "satra tm60 ross flex test method",
                "ross flex 10mm mandrel pin radius", "ross flex chisel blade dimensions and angle",
                "ross flex test at 23 degrees room temperature", "ross flex test at minus 10 degrees cold temperature",
                "ross flex cut growth limit vans waffle sole", "ross flex for molded cupsole flex groove"
            ],
            "VF-E02" => [
                "aatcc 8 crocking standard procedure", "iso 105-x12 color fastness to rubbing",
                "satra tm8 crockmeter test standard", "aatcc 9-step gray scale for staining",
                "dry crocking test for upper canvas", "wet crocking test for black suede leather",
                "crockmeter finger 16mm diameter calibration", "white crocking cloth 50mm square"
            ],
            "VF-E03" => [
                "astm g154 uv weathering standard", "satra tm160 accelerated yellowing procedure",
                "uva 340 lamp irradiance calibration", "uv chamber temperature verification 50c",
                "gray scale for color change iso 105-a02", "accelerated yellowing for white vulcanized foxing",
                "accelerated yellowing for white outsole rubber", "accelerated yellowing for clear translucent sole"
            ],
            "VF-E19" => [
                "satra tm344 hydrolysis test standard", "polyurethane jungle test procedure",
                "70c 95rh tropical chamber calibration", "post hydrolysis manual 180 degree pinch bend",
                "post hydrolysis flexing durability test", "hydrolysis resistance for molded pu midsole",
                "hydrolysis resistance for pu insole footbed", "hydrolysis resistance for synthetic pu leather"
            ],
            "VF-F01" => [
                "satra tm404 whole shoe sole adhesion standard", "astm d2558 footwear sole adhesion",
                "toe sole adhesion test procedure", "inside ball sole adhesion test procedure",
                "outside ball sole adhesion test procedure", "waist sole adhesion test procedure",
                "heel sole adhesion test procedure", "evaluating percentage rubber tear failure mode",
                "evaluating adhesive failure clean glue peel", "minimum sole adhesion for vulcanized footwear",
                "minimum sole adhesion for cold cement footwear", "sole bond testing on left and right shoes"
            ],
            "VF-G02" => [
                "satra tm92 whole shoe flex standard", "whole shoe walking simulator test procedure",
                "150000 cycles whole shoe flex inspection intervals", "measuring foxing delamination with thickness gage",
                "measuring sole crack growth with optical magnifier"
            ],
            "VF-G04" => [
                "satra tm230 dynamic waterproof standard", "iso 20344 clause 5.15 waterproof flexing",
                "water level 25mm above feather line calibration", "electrical conductivity sensor probe calibration",
                "water leakage detection time in minutes", "whole shoe waterproofness for gusseted boots"
            ],
            "VF-J01" => [
                "astm f2412 electrical hazard standard", "astm f2413 electric shock safety footwear",
                "csa z195 dielectric safety standard", "18kv 60hz high potential test set calibration",
                "leakage current meter calibration to 0.01 ma", "conductive steel ball fill volume and height"
            ],
            "VF-A01" => [
                "astm d2240 durometer hardness standard", "iso 868 durometer indentation hardness",
                "satra tm205 hardness test procedure", "durometer calibration with standard test blocks",
                "shore a durometer for vulcanized rubber", "shore c durometer for eva foam midsole",
                "shore d durometer for hard plastic shank"
            ],
            "VF-A03" => [
                "astm d1813 leather thickness standard", "satra tm1 thickness measurement procedure",
                "thickness dial gauge calibration with gauge blocks", "10mm presser foot 49.1 kpa pressure check"
            ],
            "VF-A04" => [
                "astm d792 specific gravity standard", "iso 1183 density of plastics by displacement",
                "satra tm134 density test procedure", "distilled water density correction at 23c"
            ],
            "VF-INDEX" => [
                "all vf test methods standards catalog", "vf footwear performance standards manual",
                "complete list of physical property test methods", "dtp lab 101 test methods updated 2025"
            ]
        ];

        foreach ($crossRefs as $code => $list) {
            foreach ($list as $q) {
                $add($code, $q);
            }
        }

        return $queries;
    }
}
