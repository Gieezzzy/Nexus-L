"""
Builds app/Libraries/material_catalog.json from the Access file V20261002.accdb (the lab's material test system):
  - the 14 Material Categories of its Main Menu and the form each one opens (the test list of the category = the test panel of that form),
  - the material types of every category (table Standards: 85 names) and which tests have a standard for each type (the Access form shows
    only those; the measurements without a standard - Gauge, Weight, Yarn Count, Hardness ... - are listed per category).
Run:  python tools/build_material_catalog.py <copy of the .accdb>
The test names / methods / units were read from the form designs ("Form - Fabric" ...), the type -> tests links from Standards.
"""
import json
import re
import sys
import collections

# ---- the tests: code -> (name, VF method, unit) -------------------------------------------------------------------------------
T = {
    'GAU': ('Gauge (Thickness)', 'VF-A03', 'mm'),
    'WGT': ('Weight', 'VF-A02', 'g/m2'),
    'YRN': ('Yarn Count (Warp / Weft)', 'VF-A07', '# of yarns'),
    'HRD': ('Hardness', 'VF-A01', 'C'),
    'SPG': ('Specific Gravity', 'VF-A04', 'g/cm3'),
    'TEN': ('Tensile', 'VF-B01', 'kgf/cm'),
    'TAA': ('Tensile after Abrasion', 'VF-B01', 'kgf/cm'),
    'ELO': ('Elongation', 'VF-B01', '%'),
    'DIC': ('Die-C Tear', 'VF-B05', 'kgf/cm'),
    'SPT': ('Split Tear', 'VF-B06', 'kgf/cm'),
    'TRT': ('Trouser Tear', 'VF-B07', 'kgf'),
    'STT': ('Stitch Tear', 'VF-B08', 'kgf/cm'),
    'BAU': ('Baumann Tear', 'VF-B09', 'kgf'),
    'MUL': ('Mullen Burst (Bursting Strength)', 'VF-B02', 'kgf/cm2'),
    'CMP': ('Compression', 'VF-B10', '%'),
    'PLS': ('Peel Strength', 'VF-B03', 'kgf/cm'),
    'SHS': ('Shear Strength', 'VF-B03', 'kgf/cm2'),
    'KNT': ('Knot Slip Resistance', 'VF-B23', 'kgf'),
    'TIP': ('Shoelace Tip Retention', 'VF-B24', 'kgf'),
    'MBI': ('Mullen Burst - Inside', 'VF-B02', 'kgf/cm2'),
    'MBO': ('Mullen Burst - Outside', 'VF-B02', 'kgf/cm2'),
    'EDG': ('Edge Crush Test', 'VF-B27', 'kgf/cm2'),
    'CAD': ('Cardboard Adhesion - Dry (L / W)', 'VF-F11', ''),
    'CAW': ('Cardboard Adhesion - Wet (L / W)', 'VF-F11', ''),
    'MAR': ('Martindale Abrasion', 'VF-C04', 'cycles'),
    'TAB': ('Taber Abrasion', 'VF-C05', 'cycles'),
    'LAB': ('Lace Abrasion', 'VF-C09', 'cycles'),
    'DIN': ('DIN Abrasion', 'VF-C01', 'mm3'),
    'BAL': ('Bally Flex', 'VF-D01', 'cycles'),
    'RFX': ('Ross Flex (23+/-2 C)', 'VF-D02', '% growth'),
    'SHR': ('Shrinkage', 'VF-E13', '%'),
    'SHA': ('Shrinkage after Aging (72 hr, 70 C)', 'VF-E13', '%'),
    'SHH': ('Shrinkage after Hydrolysis (168 hr, 60 C)', 'VF-E13', '%'),
    'CTP': ('Colorfastness to Perspiration (Top / Back)', 'VF-E16', 'AATCC'),
    'CTW': ('Colorfastness to Water (Top / Back)', 'VF-E01', 'AATCC'),
    'CRK': ('Colorfastness to Crocking (Dry / Wet)', 'VF-E02', 'AATCC'),
    'ACK': ('Abrasion Crocking (Dry / Wet / Sea Water)', 'VF-E02', 'rating'),
    'INK': ('Ink Adhesion on Substrate (3M tape #600)', 'VF-F05', 'rating'),
    'UVA': ('UV Light (24 hrs, 50 C)', 'VF-E03', 'AATCC'),
    'HAG': ('Heat Aging (72 hrs, 70 C)', 'VF-E04', 'AATCC'),
    'HYD': ('Hydrolysis (7 days, 70 C 95%RH)', 'VF-E19', 'rating'),
    'BLM': ('Blooming Test (7 days, 70 C 95%RH)', 'VF-E19', ''),
    'LAM': ('Laminar Peel Strength (Dry / Wet)', 'VF-F04', 'kgf/cm'),
    'PVC': ('PVC Migration Fastness', 'VF-E06', 'AATCC'),
    'COR': ('Corrosion Resistance', 'VF-E19', 'words'),
    'WAT': ('Water Absorption / Resistance', '', ''),
    'AGU': ('Aglet - UV Light', 'VF-E03', 'AATCC'),
    'AGH': ('Aglet - Heat Aging', 'VF-E04', 'AATCC'),
    'AGY': ('Aglet - Hydrolysis', 'VF-E19', 'rating'),
    'PER': ('Perspiration (Sockliner / Foam)', 'VF-E16', 'AATCC'),
    'DIS': ('Dip Staining', '', ''),
}

# ---- Access Standards key -> test code (first matching rule wins) ---------------------------------------------------------------
KEY_RULES = [
    (r'^MullenBurstIn', 'MBI'), (r'^MullenBurstO', 'MBO'), (r'^MullenBurst', 'MUL'), (r'^EdgeCrush', 'EDG'), (r'^CardboardAdhesionDry', 'CAD'), (r'^CardboardAdhesionWet', 'CAW'),
    (r'^CTCDry|^CtCDry|^CtCWet|^CTC_Dry|^CTC_Wet|^CrockingDry|^CrockingWet', 'CRK'),
    (r'^IAo|^IAOS', 'INK'), (r'^AbrasionCrocking', 'ACK'),
    (r'^Tensile.*AA', 'TAA'), (r'^Tensile', 'TEN'), (r'^Elongation', 'ELO'), (r'^TrouserTear', 'TRT'), (r'^StitchTear', 'STT'), (r'^SplitTear', 'SPT'), (r'^BaumannTear', 'BAU'),
    (r'^DieC', 'DIC'), (r'^Hardness', 'HRD'), (r'^SG_', 'SPG'), (r'^Compression', 'CMP'),
    (r'^Martindale', 'MAR'), (r'^Taber', 'TAB'), (r'^LaceAbrasion', 'LAB'), (r'^Bally', 'BAL'), (r'^PVC', 'PVC'),
    (r'^KnotSlip', 'KNT'), (r'^LaceTipRet', 'TIP'), (r'^PeelStrength', 'PLS'), (r'^ShearStrength', 'SHS'),
    (r'^LaminarPeel', 'LAM'), (r'^Shrinkage', 'SHR'),
    (r'^HeatAging_std_AGLET', 'AGH'), (r'^Hydro_std_AGLET', 'AGY'), (r'^UV_std_AGLET', 'AGU'),
    (r'^CTP|^Perspiration', 'CTP'), (r'^CTW|^Water', 'CTW'),
    (r'^UV', 'UVA'), (r'^HeatAging', 'HAG'), (r'^Hydrolysis|^Hydro', 'HYD'),
]

# ---- the 14 categories of the Main Menu: form order of the tests, the measurements that have no standard, and which Access types belong ---------
CATS = [
    dict(slug='fabric', name='Fabric', form='Form - Fabric', result_table='FabricTestResults',
         order=['GAU', 'WGT', 'YRN', 'TEN', 'ELO', 'TRT', 'STT', 'MAR', 'MUL', 'CTP', 'CTW', 'UVA', 'CRK', 'ACK', 'INK', 'HAG', 'HYD', 'LAM'],
         base=['GAU', 'WGT', 'YRN'], match=r'^(Fabric|Ink &)'),
    dict(slug='leather', name='Leather', form='Form - Leather', result_table='LeatherTestResults',
         order=['GAU', 'WGT', 'TEN', 'ELO', 'TRT', 'STT', 'BAU', 'BAL', 'MAR', 'MUL', 'TAB', 'CTP', 'CTW', 'UVA', 'CRK', 'ACK', 'INK', 'PVC', 'HAG', 'HYD'],
         base=['GAU', 'WGT'], match=r'^(Leather|Synthetic)'),
    dict(slug='sockliner-foam', name='Sockliner / Foam', form='Form - Sockliner and Foam', result_table='SocklinerFoamTestResults',
         order=['HRD', 'SPG', 'TEN', 'ELO', 'DIC', 'SPT', 'SHR', 'CMP', 'RFX', 'MAR', 'PER', 'INK', 'LAM', 'CRK', 'ACK', 'UVA', 'HAG', 'HYD'],
         base=['HRD', 'SPG'], match=r'^(Sockliner|PU Foam)'),
    dict(slug='foxing-heel-logo', name='Foxing Tape / Heel Logo', form='Form - Foxing Tape/Heel Logo', result_table='FoxingTapeHeelLogoTestResults',
         order=['HRD', 'SPG', 'TEN', 'ELO', 'DIC', 'SHR', 'CRK', 'ACK', 'INK', 'UVA', 'HAG', 'HYD', 'BLM'],
         base=['HRD', 'SPG'], match=r'^(FoxingTape|Heel Logo)'),
    dict(slug='rubber-outsole', name='Rubber Outsole', form='Form - Rubber Outsole', result_table='RubberTestResults',
         order=['HRD', 'SPG', 'TEN', 'ELO', 'DIC', 'DIN', 'RFX', 'SHR', 'SHA', 'SHH', 'UVA', 'HAG', 'HYD', 'BLM'],
         base=['HRD', 'SPG', 'DIN', 'RFX', 'SHR', 'SHA', 'SHH', 'HYD', 'BLM'], match=r'^Rubber$'),
    dict(slug='shoelace', name='Shoelace', form='Form - Webbing/Shoe Lace/Thread', result_table='WebbingShoelaceThreadTestResults',
         order=['GAU', 'WGT', 'TEN', 'TAA', 'ELO', 'STT', 'KNT', 'TIP', 'LAB', 'CTP', 'CTW', 'CRK', 'UVA', 'HAG', 'HYD', 'AGU', 'AGH', 'AGY'],
         base=['GAU', 'WGT'], match=r'^Shoe lace'),
    dict(slug='thread', name='Thread', form='Form - Webbing/Shoe Lace/Thread', result_table='WebbingShoelaceThreadTestResults',
         order=['GAU', 'WGT', 'TEN', 'CTW', 'CRK', 'CTP', 'UVA', 'HAG', 'HYD'], base=['GAU', 'WGT'], match=r'^Thread'),
    dict(slug='webbing', name='Webbing', form='Form - Webbing/Shoe Lace/Thread', result_table='WebbingShoelaceThreadTestResults',
         order=['GAU', 'WGT', 'TEN', 'ELO', 'STT', 'CTW', 'CRK', 'CTP', 'UVA', 'HAG', 'HYD'], base=['GAU', 'WGT'], match=r'^Webbing'),
    dict(slug='elastic-gore', name='Elastic Gore', form='Form - Webbing/Shoe Lace/Thread', result_table='WebbingShoelaceThreadTestResults',
         order=['GAU', 'WGT', 'TEN', 'STT', 'CTW', 'CRK', 'CTP', 'UVA', 'HAG', 'HYD'], base=['GAU', 'WGT'], match=r'^Elastic Gore'),
    dict(slug='insole', name='Insole', form='Form - Insole', result_table='InsoleTestResults',
         order=['GAU', 'WGT', 'TEN', 'ELO', 'MUL', 'SHR', 'UVA', 'HAG'], base=['GAU', 'WGT'], match=r'^Insole'),
    dict(slug='velcro', name='Velcro Hook and Loop', form='Form - Webbing/Shoe Lace/Thread', result_table='WebbingShoelaceThreadTestResults',
         order=['GAU', 'WGT', 'TEN', 'ELO', 'STT', 'PLS', 'SHS', 'CTW', 'CRK', 'UVA', 'HAG'], base=['GAU', 'WGT'], match=r'^Velcro'),
    dict(slug='label', name='Label', form='Form - Label', result_table='LabelTestResults',
         order=['STT', 'CRK', 'CTP', 'CTW', 'UVA', 'HAG', 'HYD'], base=[], match=r'(Label)'),
    dict(slug='box', name='Box', form='Form - Box', result_table='BoxTestResults',
         order=['MBI', 'MBO', 'EDG', 'CAD', 'CAW', 'CRK'], base=[], match=r'Box$'),
    dict(slug='eyelet-grommet', name='Eyelet / Grommet', form='Form - Hardware', result_table='HardwareTestResults',
         order=['CRK', 'UVA', 'HAG', 'HYD', 'COR', 'CTW', 'SHR'], base=['COR'], match=r'^(Eyelet|Grommet|Zipper)'),
]


def code_of(key):
    for rx, c in KEY_RULES:
        if re.search(rx, key, re.I):
            return c
    return None


def main(db):
    import pyodbc
    cn = pyodbc.connect(r'DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};DBQ=' + db + ';ReadOnly=1')
    cur = cn.cursor()
    std = collections.OrderedDict()
    for mt, key in cur.execute('SELECT MaterialType, TestMethod FROM Standards ORDER BY MaterialType, ID').fetchall():
        std.setdefault(mt.strip(), [])
        c = code_of(key)
        if c is None:
            print('UNMAPPED KEY', mt, key)
        elif c not in std[mt.strip()]:
            std[mt.strip()].append(c)
    out = {'source': 'V20261002.accdb (Access); VF method numbers checked against the VF method library (tbl_vfm_methods)', 'rev': 2, 'tests': {c: {'name': v[0], 'method': v[1], 'unit': v[2]} for c, v in T.items()}, 'categories': []}
    used = set()
    for cat in CATS:
        types = []
        for mt, codes in std.items():
            if re.search(cat['match'], mt, re.I) and mt not in used:
                # the form order, base measurements first; only tests that have a standard for the type (+ the base ones)
                tests = [c for c in cat['order'] if c in codes or c in cat['base']]
                types.append({'name': mt, 'tests': tests})
                used.add(mt)
        out['categories'].append({'slug': cat['slug'], 'name': cat['name'], 'form': cat['form'], 'result_table': cat['result_table'], 'all_tests': cat['order'], 'base': cat['base'], 'types': types})
    left = [m for m in std if m not in used]
    print('types not placed:', left)
    json.dump(out, open(r'E:\html\NEXUS-L\app\Libraries\material_catalog.json', 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
    print(len(out['categories']), 'categories,', sum(len(c['types']) for c in out['categories']), 'types')


if __name__ == '__main__':
    main(sys.argv[1])
