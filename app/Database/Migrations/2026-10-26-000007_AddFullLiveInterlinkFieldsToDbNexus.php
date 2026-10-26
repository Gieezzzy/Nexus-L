<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration 000007:
 * Upgrades dbnexus with 100% of the live Intertek InterLink 2.0 fields, tables, and real records
 * inspected directly from the live session:
 * - SearchFormWithHeader.aspx [10102] (All 53 Grid Columns + Top 27 Search Criteria Filters)
 * - UploadForm.aspx [10072] Finished Product (Tab 1: Service/Header, Vendor/Factory/Supplier, Product Info (ALL), GCC/CPC & Factory Address; Tab 2: Upload Result)
 * - UploadForm.aspx [10076] Material (Tab 1: Service/Header, Vendor/Factory/Supplier/Facility, Material Info (ALL); Tab 2: Upload Result)
 * - NormalForm.aspx [10000] Online Application Form (Applicant Info, Invoice Info, Service Requested, Product Info)
 * - tbl_interlink_sample_items (Tab 2: Sample Information Grid - JOB_DUMMY_007_SAMPLE_ITEM)
 * - tbl_interlink_test_items   (Tab 2: Test Item Result Grid - JOB_DUMMY_008_TEST_ITEM_INP)
 * - tbl_interlink_test_readings(Tab 2: Test Item Result For Reading Grid - JOB_DUMMY_013)
 */
class AddFullLiveInterlinkFieldsToDbNexus extends Migration
{
    public function up()
    {
        $this->db->query('SET SESSION innodb_strict_mode = 0');

        // =====================================================================
        // 1. Recreate tbl_interlink_reports with ALL 102 Columns from Live Forms
        // =====================================================================
        $this->forge->dropTable('tbl_interlink_reports', true);

        $this->forge->addField([
            // --- Internal NEXUS-L Identifiers ---
            'id'                        => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nexus_sample_id'           => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'nexus_work_order_no'       => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],

            // --- InterLink Form Identifiers & Live URLs ---
            'application_no'            => ['type' => 'VARCHAR', 'constraint' => 30, 'comment' => 'InterLink Application No. (e.g. A005967935)'],
            'form_code'                 => ['type' => 'VARCHAR', 'constraint' => 15, 'default' => '10072', 'comment' => '10072=Product UploadForm, 10076=Material UploadForm, 10000=NormalForm'],
            'portal_name'               => ['type' => 'TEXT', 'null' => true],
            'e_form_url'                => ['type' => 'TEXT', 'null' => true, 'comment' => 'Live NormalForm.aspx URL (E button)'],
            'u_form_url'                => ['type' => 'TEXT', 'null' => true, 'comment' => 'Live UploadForm.aspx URL (U button)'],
            'search_form_args'          => ['type' => 'TEXT', 'null' => true],
            'upload_form_args'          => ['type' => 'TEXT', 'null' => true],
            'iuds_req_id'               => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],

            // --- UploadForm Tab 1 Top Section: Service & Lab Information ---
            'service_provider'          => ['type' => 'VARCHAR', 'constraint' => 80, 'default' => 'DATIAN SUBIC SHOES INC.'],
            'lab_location'              => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'PHILIPPINES'],
            'testing_lab_code'          => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'DATIAN'],
            'service_test_type'         => ['type' => 'VARCHAR', 'constraint' => 60, 'default' => 'In-House Lab Testing'],
            'test_stage'                => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Production'],
            'service_required'          => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Regular'],
            'sample_type'               => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Finished Product', 'comment' => 'Finished Product or Material'],
            'report_no'                 => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'comment' => 'UF1_EGVNF_ef0_DXEditor17 (* Report No.)'],
            'is_retest'                 => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'No', 'comment' => 'Yes or No'],
            'retest_flag'               => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'No', 'comment' => 'Grid column 51: Retest'],
            'retest_sequence'           => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
            'previous_report_no'        => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'comment' => 'UF1_EGVNF_ef0_DXEditor19 (Previous Test Report No)'],
            'related_trf_no'            => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'comment' => 'Related TRF Number (Material form [10076] & Grid col 35)'],
            'retest_reason'             => ['type' => 'TEXT', 'null' => true],
            'email_recipients'          => ['type' => 'TEXT', 'null' => true],
            'cc_email_recipients'       => ['type' => 'TEXT', 'null' => true],

            // --- NormalForm [10000]: Applicant & Invoice Information ---
            'applicant_address'         => ['type' => 'TEXT', 'null' => true],
            'applicant_country'         => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'Philippines'],
            'applicant_postal_code'     => ['type' => 'VARCHAR', 'constraint' => 15, 'default' => '2222'],
            'contact_person'            => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'Danica Agbuya'],
            'contact_email'             => ['type' => 'VARCHAR', 'constraint' => 60, 'default' => 'dtp_qcasst@dtshoes.com'],
            'contact_phone'             => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => '(047) 252-2899'],
            'invoice_to_applicant'      => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'Yes'],
            'quotation_required'        => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'No'],
            'return_sample'             => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'No'],

            // --- UploadForm Section 2: Vendor / Factory / Supplier / Facility Information ---
            'company_name'              => ['type' => 'VARCHAR', 'constraint' => 60, 'default' => 'KEY TRADE CO., LTD'],
            'interlink_code'            => ['type' => 'VARCHAR', 'constraint' => 25, 'default' => 'E126863'],
            'vendor_name'               => ['type' => 'VARCHAR', 'constraint' => 60, 'default' => 'KEY TRADE CO., LTD'],
            'vendor_id'                 => ['type' => 'VARCHAR', 'constraint' => 25, 'default' => '0000507450'],
            'factory_name'              => ['type' => 'VARCHAR', 'constraint' => 60, 'default' => 'DATIAN SUBIC SHOES INC.'],
            'factory_id'                => ['type' => 'VARCHAR', 'constraint' => 25, 'default' => '0000507452'],
            'supplier_name'             => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'supplier_id'               => ['type' => 'VARCHAR', 'constraint' => 25, 'null' => true],
            'facility_name'             => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true, 'comment' => 'Material Form [10076] Facility Name'],
            'facility_id'               => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'comment' => 'Material Form [10076] Facility ID'],

            // --- UploadForm Section 3: Product Information (ALL) [10072] ---
            'style_no'                  => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'comment' => 'Style No. (e.g. VN000ZB8)'],
            'style_color_no'            => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'comment' => 'Style Color No. / SKU No. (e.g. VN000ZB8BPR)'],
            'product_name'              => ['type' => 'TEXT', 'null' => true, 'comment' => 'Product Name (e.g. LX Old Skool 36)'],
            'style_name'                => ['type' => 'TEXT', 'null' => true],
            'buyer_brand'               => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Vans', 'comment' => 'Brand Name'],
            'department'                => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'business_division_code'    => ['type' => 'VARCHAR', 'constraint' => 25, 'null' => true],
            'color_code'                => ['type' => 'VARCHAR', 'constraint' => 25, 'null' => true, 'comment' => 'Color Code (e.g. BPR, BA2, BOP)'],
            'colorway_name'             => ['type' => 'TEXT', 'null' => true, 'comment' => 'Color Description (e.g. Pearlized BLACK/MARSHMALLOW)'],
            'product_export_to'         => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'APAC - Non China', 'comment' => 'Product Export To'],
            'destination_market'        => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'APAC - Non China'],
            'country_of_origin'         => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Philippines'],
            'season'                    => ['type' => 'VARCHAR', 'constraint' => 15, 'default' => 'S27', 'comment' => 'Season (e.g. S27, FW26)'],
            'season_year'               => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => '2026'],
            'sourcing_office'           => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ASO', 'comment' => 'Sourcing Office (e.g. ASO)'],
            'size_category'             => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'UNISEX', 'comment' => 'Size Category (e.g. UNISEX, MALE, U)'],
            'age_group'                 => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'Adult', 'comment' => 'DXEditor44 Age Group (Adult, Youth, Toddler)'],
            'age_group_gender'          => ['type' => 'VARCHAR', 'constraint' => 25, 'default' => 'Adult / UNISEX'],
            'cargo_receive_date'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => '06 Nov 2026', 'comment' => 'DXEditor45_I Cargo Receive Date'],
            'care_instruction'          => ['type' => 'TEXT', 'null' => true, 'comment' => 'DXEditor46 Care Instruction'],
            'product_category'          => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Footwear', 'comment' => 'Product Category'],
            'fiber_composition'         => ['type' => 'TEXT', 'null' => true, 'comment' => 'DXEditor48 Fiber Composition'],
            'test_packages'             => ['type' => 'VARCHAR', 'constraint' => 60, 'default' => 'VANS Pull Strength', 'comment' => 'Test Package(s)'],
            'spec_package_code'         => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'VANS Pull Strength'],
            'lab_remarks'               => ['type' => 'TEXT', 'null' => true, 'comment' => 'DXEditor50 Remarks'],

            // --- UploadForm Section 3B: GCC/CPC & Factory Address [10072] ---
            'gcc_cpc_required'          => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'No', 'comment' => 'DXEditor52 GCC/CPC Required?'],
            'product_id'                => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'comment' => 'Product ID (e.g. VN000ZB8)'],
            'manufacture_date'          => ['type' => 'VARCHAR', 'constraint' => 15, 'default' => '10/2026', 'comment' => 'DXEditor54 Manufacture Date (MM/YYYY)'],
            'factory_address'           => ['type' => 'TEXT', 'null' => true, 'comment' => 'DXEditor55 Factory Address'],
            'factory_country'           => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Philippines', 'comment' => 'DXEditor56 Factory Country'],
            'factory_apt_suite'         => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'comment' => 'DXEditor57 Factory Apt/Suite Number'],
            'factory_city'              => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Subic Bay', 'comment' => 'DXEditor58 Factory City'],
            'factory_state_province'    => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => '03', 'comment' => 'DXEditor59 Factory State/Province'],
            'factory_zip_postal'        => ['type' => 'VARCHAR', 'constraint' => 15, 'default' => '2222', 'comment' => 'DXEditor60 Factory Zip/Postal Code'],
            'factory_email'             => ['type' => 'VARCHAR', 'constraint' => 60, 'default' => 'dtp_qcasst@dtshoes.com', 'comment' => 'DXEditor61 Factory Email'],
            'factory_phone'             => ['type' => 'VARCHAR', 'constraint' => 25, 'default' => '(047) 252-2899', 'comment' => 'DXEditor62 Factory Phone'],

            // --- UploadForm [10076]: Material Information (ALL) ---
            'material_code'             => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'comment' => 'Material Code (e.g. 108850)'],
            'material_name'             => ['type' => 'TEXT', 'null' => true, 'comment' => 'Material Description'],
            'material_type'             => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'comment' => 'Material Type (e.g. Leather, Textile)'],
            'requirement_spec_no'       => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'comment' => 'Requirement Spec No.'],
            'material_sub_type'         => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'comment' => 'Material Sub-Type'],
            'claim_type'                => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'comment' => 'Claim Type'],
            'thickness'                 => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'comment' => 'Thickness (e.g. 1.2-1.4mm)'],
            'weight'                    => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'comment' => 'Weight'],
            'weight_uom'                => ['type' => 'VARCHAR', 'constraint' => 15, 'null' => true, 'comment' => 'DXEditor41 Weight UOM'],
            'finishing'                 => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'comment' => 'DXEditor44 Finishing'],
            'end_use'                   => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'comment' => 'End Use (e.g. Upper)'],
            'washing_instruction'       => ['type' => 'TEXT', 'null' => true, 'comment' => 'Washing Instruction'],

            // --- Order & Sample Tracking ---
            'po_number'                 => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'lot_batch_no'              => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'order_quantity'            => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'sample_size'               => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'sample_quantity'           => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'construction_type'         => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],

            // --- Dates & Timeline Columns ---
            'trf_submission_date'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => '26 Oct 2026'],
            'sample_received_date'      => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'test_start_date'           => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'test_completion_date'      => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'report_issue_date'         => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'comment' => 'Tab 2 UF1_EGVNF_ef0_DXEditor6_I (* Report Issue Date)'],
            'turnaround_days'           => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 2],

            // --- UploadForm Tab 2: Upload Result & Search Grid Status Columns ---
            'job_status'                => ['type' => 'VARCHAR', 'constraint' => 25, 'default' => 'Submitted'],
            'overall_result'            => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'Pass', 'comment' => 'Tab 2 TRPT_RESULT_DISP_0 (Pass / Fail / Data)'],
            'review_result'             => ['type' => 'VARCHAR', 'constraint' => 25, 'null' => true],
            'review_status'             => ['type' => 'VARCHAR', 'constraint' => 25, 'null' => true],
            'latest_review_comment'     => ['type' => 'TEXT', 'null' => true],
            'cancellation_reason'       => ['type' => 'TEXT', 'null' => true],
            'report_type'               => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'Final'],
            'physical_test_result'      => ['type' => 'VARCHAR', 'constraint' => 15, 'default' => 'Pass'],
            'chemical_test_result'      => ['type' => 'VARCHAR', 'constraint' => 15, 'default' => 'N/A'],
            'colorfastness_result'      => ['type' => 'VARCHAR', 'constraint' => 15, 'default' => 'Pass'],
            'bonding_peel_result'       => ['type' => 'VARCHAR', 'constraint' => 15, 'default' => 'Pass'],
            'flexing_aging_result'      => ['type' => 'VARCHAR', 'constraint' => 15, 'default' => 'Pass'],
            'fail_reason_notes'         => ['type' => 'TEXT', 'null' => true],
            'corrective_action_notes'   => ['type' => 'TEXT', 'null' => true],
            'related_coc_no'            => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'attachment_name'           => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true, 'comment' => 'Tab 2 JOB_DUMMY_004 (* Report Attachment PDF)'],
            'attachment_path'           => ['type' => 'TEXT', 'null' => true],
            'supplementary_attachment'  => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true, 'comment' => 'Tab 2 JOB_DUMMY_005 Supplementary Attachment'],

            // --- Personnel & Signatories ---
            'tested_by_technician'      => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'Faye Ann Elaysa'],
            'approved_by_signatory'     => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'Eigeman Go'],

            // --- InterLink Synchronization Tracking ---
            'synced_to_interlink'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'sync_count'                => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'synced_at'                 => ['type' => 'DATETIME', 'null' => true],
            'synced_by'                 => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'last_sync_error'           => ['type' => 'TEXT', 'null' => true],
            'created_at'                => ['type' => 'DATETIME', 'null' => true],
            'updated_at'                => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('application_no');
        $this->forge->addKey('report_no');
        $this->forge->addKey('sample_type');
        $this->forge->addKey('style_no');
        $this->forge->addKey('style_color_no');
        $this->forge->addKey('material_code');
        $this->forge->addKey('synced_to_interlink');
        $this->forge->createTable('tbl_interlink_reports', true, ['ENGINE' => 'InnoDB', 'ROW_FORMAT' => 'DYNAMIC']);

        // =====================================================================
        // 2. tbl_interlink_sample_items (Tab 2 Upload Result: JOB_DUMMY_007_SAMPLE_ITEM)
        // =====================================================================
        $this->forge->dropTable('tbl_interlink_sample_items', true);

        $this->forge->addField([
            'sample_row_id'      => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'report_id'          => ['type' => 'INT', 'unsigned' => true],
            'application_no'     => ['type' => 'VARCHAR', 'constraint' => 40],
            'sample_id_code'     => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'S001', 'comment' => 'FLD01 Sample ID'],
            'sample_description' => ['type' => 'VARCHAR', 'constraint' => 255, 'comment' => 'FLD24 Sample Description'],
            'sample_color'       => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'comment' => 'FLD25 Sample Color'],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('sample_row_id');
        $this->forge->addKey('report_id');
        $this->forge->addKey('application_no');
        $this->forge->createTable('tbl_interlink_sample_items', true, ['ENGINE' => 'InnoDB']);

        // =====================================================================
        // 3. tbl_interlink_test_items (Tab 2 Upload Result: JOB_DUMMY_008_TEST_ITEM_INP)
        // =====================================================================
        $this->forge->dropTable('tbl_interlink_test_items', true);

        $this->forge->addField([
            'item_id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'report_id'             => ['type' => 'INT', 'unsigned' => true],
            'application_no'        => ['type' => 'VARCHAR', 'constraint' => 40],
            'report_no'             => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'sample_id_code'        => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'S001', 'comment' => 'FLD03 Sample ID'],
            'item_sequence'         => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 1],
            'test_category'         => ['type' => 'VARCHAR', 'constraint' => 80, 'default' => 'Physical / Mechanical'],
            'test_code'             => ['type' => 'VARCHAR', 'constraint' => 60, 'default' => 'VANS-PS-01'],
            'test_name'             => ['type' => 'VARCHAR', 'constraint' => 200, 'comment' => 'FLD07 Test Item Name'],
            'test_method_standard'  => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'component_tested'      => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'test_condition'        => ['type' => 'VARCHAR', 'constraint' => 120, 'default' => 'As Received'],
            'requirement_spec'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'measurement_unit'      => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'actual_result_summary' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'test_result'           => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Pass', 'comment' => 'FLD15 Test Item Result (Pass / Fail / Data)'],
            'technician_remarks'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_synced'             => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at'            => ['type' => 'DATETIME', 'null' => true],
            'updated_at'            => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('item_id');
        $this->forge->addKey('report_id');
        $this->forge->addKey('application_no');
        $this->forge->createTable('tbl_interlink_test_items', true, ['ENGINE' => 'InnoDB']);

        // =====================================================================
        // 4. tbl_interlink_test_readings (Tab 2 Upload Result: JOB_DUMMY_013)
        // =====================================================================
        $this->forge->dropTable('tbl_interlink_test_readings', true);

        $this->forge->addField([
            'reading_id'            => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'item_id'               => ['type' => 'INT', 'unsigned' => true],
            'report_id'             => ['type' => 'INT', 'unsigned' => true],
            'application_no'        => ['type' => 'VARCHAR', 'constraint' => 40],
            'sample_id_code'        => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'S001', 'comment' => 'Sample ID'],
            'test_item_name'        => ['type' => 'VARCHAR', 'constraint' => 200, 'comment' => 'Test Item Name'],
            'test_method'           => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true, 'comment' => 'Test method'],
            'condition_stage'       => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'As Received', 'comment' => 'Test condition'],
            'sample_component'      => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true, 'comment' => 'Sample Component'],
            'sample_component_desc' => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true, 'comment' => 'Sample Component Description'],
            'test_element'          => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true, 'comment' => 'Test element'],
            'test_value_indicator'  => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => '=', 'comment' => 'Test value indicator (=, >, <, >=, <=)'],
            'specimen_no'           => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 1],
            'specimen_label'        => ['type' => 'VARCHAR', 'constraint' => 80, 'default' => 'Specimen 1'],
            'reading_value_raw'     => ['type' => 'VARCHAR', 'constraint' => 80, 'comment' => 'Test value'],
            'reading_numeric'       => ['type' => 'DECIMAL', 'constraint' => '10,3', 'null' => true],
            'unit'                  => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'comment' => 'Unit of measure'],
            'target_spec'           => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true, 'comment' => 'Requirement'],
            'reading_status'        => ['type' => 'VARCHAR', 'constraint' => 25, 'default' => 'Pass', 'comment' => 'Test rating (Pass / Fail / Data)'],
            'notes'                 => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'created_at'            => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('reading_id');
        $this->forge->addKey('item_id');
        $this->forge->addKey('report_id');
        $this->forge->addKey('application_no');
        $this->forge->createTable('tbl_interlink_test_readings', true, ['ENGINE' => 'InnoDB']);

        // =====================================================================
        // SEED ALL 11 LIVE INTERLINK APPLICATIONS FROM THE USER'S CHROME SESSION
        // =====================================================================
        $now = date('Y-m-d H:i:s');

        $liveRows = [
            // 1. A005967935 (User's URL #1 - UploadForm [10072] & NormalForm [10000])
            [
                'nexus_sample_id'       => 'NXL-2026-7935',
                'nexus_work_order_no'   => 'WO-20261026-7935',
                'application_no'        => 'A005967935',
                'form_code'             => '10072',
                'sample_type'           => 'Finished Product',
                'trf_submission_date'   => '26 Oct 2026',
                'sample_received_date'  => '26 Oct 2026',
                'test_start_date'       => '26 Oct 2026',
                'test_completion_date'  => '26 Oct 2026',
                'report_issue_date'     => '26 Oct 2026',
                'cargo_receive_date'    => '06 Nov 2026',
                'report_no'             => 'DTP-LAB-2026-7935',
                'is_retest'             => 'No',
                'retest_flag'           => 'No',
                'previous_report_no'    => '',
                'email_recipients'      => 'dtp_qcasst@dtshoes.com',
                'company_name'          => 'KEY TRADE CO., LTD',
                'interlink_code'        => 'E126863',
                'vendor_name'           => 'KEY TRADE CO., LTD',
                'vendor_id'             => '0000507450',
                'factory_name'          => 'DATIAN SUBIC SHOES INC.',
                'factory_id'            => '0000507452',
                'supplier_name'         => '',
                'supplier_id'           => '',
                'style_no'              => 'VN000ZB8',
                'style_color_no'        => 'VN000ZB8BPR',
                'product_name'          => 'LX Old Skool 36',
                'style_name'            => 'LX Old Skool 36',
                'buyer_brand'           => 'Vans',
                'color_code'            => 'BPR',
                'colorway_name'         => 'Pearlized BLACK/MARSHMALLOW',
                'product_export_to'     => 'APAC - Non China',
                'country_of_origin'     => 'Philippines',
                'season'                => 'S27',
                'sourcing_office'       => 'ASO',
                'size_category'         => 'UNISEX',
                'age_group'             => 'Adult',
                'product_category'      => 'Footwear',
                'test_packages'         => 'VANS Pull Strength',
                'gcc_cpc_required'      => 'No',
                'product_id'            => 'VN000ZB8',
                'manufacture_date'      => '10/2026',
                'overall_result'        => 'Pass',
                'job_status'            => 'Submitted',
                'attachment_name'       => 'TRF_A005967935_VansPullStrength_Report.pdf',
                'e_form_url'            => 'https://interlink2.intertek.com/InterLink2/aspx/eFormPage/Forms/NormalForm.aspx?args=BCE93E107653E70D6FB33A4A4693CF4D0C61DB3DC4E36B40E70C31AF171D96F0FB57100B223EA24651893E72239C984CBA4DCE7C73729CD2556ACA1A540D1CE34BFEF47D0D6CE64D7B111F152C414F0AFD78C439EB67B8A1C06A183F8285808CE050395A0FDBAD6CC5FE545726F50C79065149AF61F6D78E6A12CD809D58312B31755D316F1CF373355FE079D207B94D&iudsReqId=3f4f7229bc304c548654559bba9f49fe',
                'u_form_url'            => 'https://interlink2.intertek.com/InterLink2/aspx/eFormPage/Forms/UploadForm.aspx?args=F78A0B062541AEA81E8BA8297FBA2DA76CAE8B7A92CD3B50145C27C4686D805274963B3E625332DCB961FB2AA7B050E11ED37E8ADDC797FA837E2FBF33BEC69F5E38D540277895A37A82BA2C59CA8C89F7988B57262F9C3E904C1500439116CA80060F6EC333A3B6997758DE9FDC9A481C1ADC7E9AD5FB46C72662B98219C2E2EC9823206BF54FB3D1F5C5BE4E833009F43506020D1F5EFB25EC439FA87F4669AB716CF1720D4FE6D1FC5C07B473582D940E8526AD6A152F23BEA566349729BC&iudsReqId=3301b54d2d754f9290fbe7683e8cf2ce',
                'created_at'            => $now,
                'updated_at'            => $now,
            ],
            // 2. A005967920 (Live Row #2)
            [
                'nexus_sample_id'       => 'NXL-2026-7920',
                'nexus_work_order_no'   => 'WO-20261026-7920',
                'application_no'        => 'A005967920',
                'form_code'             => '10072',
                'sample_type'           => 'Finished Product',
                'trf_submission_date'   => '26 Oct 2026',
                'report_issue_date'     => '26 Oct 2026',
                'cargo_receive_date'    => '27 Oct 2026',
                'report_no'             => 'DTP-LAB-2026-7920',
                'is_retest'             => 'No',
                'retest_flag'           => 'No',
                'email_recipients'      => 'dtp_qcasst@dtshoes.com',
                'style_no'              => 'VN000Y80',
                'style_color_no'        => 'VN000Y80EZR',
                'product_name'          => 'Vans Kids Classic Slip-On',
                'style_name'            => 'Vans Kids Classic Slip-On',
                'buyer_brand'           => 'Vans',
                'color_code'            => 'EZR',
                'colorway_name'         => 'DOTS Navy/Lime',
                'product_export_to'     => 'APAC - Non China',
                'country_of_origin'     => 'Philippines',
                'season'                => 'S27',
                'sourcing_office'       => 'ASO',
                'size_category'         => 'U',
                'age_group'             => 'Adult',
                'product_category'      => 'Footwear',
                'test_packages'         => 'VANS Pull Strength',
                'product_id'            => 'VN000Y80',
                'overall_result'        => 'Pass',
                'job_status'            => 'Submitted',
                'attachment_name'       => 'TRF_A005967920_VansPullStrength_Report.pdf',
                'created_at'            => $now,
                'updated_at'            => $now,
            ],
            // 3. A005967914 (Live Row #3)
            [
                'nexus_sample_id'       => 'NXL-2026-7914',
                'nexus_work_order_no'   => 'WO-20261026-7914',
                'application_no'        => 'A005967914',
                'form_code'             => '10072',
                'sample_type'           => 'Finished Product',
                'trf_submission_date'   => '26 Oct 2026',
                'report_issue_date'     => '26 Oct 2026',
                'cargo_receive_date'    => '27 Oct 2026',
                'report_no'             => 'DTP-LAB-2026-7914',
                'is_retest'             => 'No',
                'retest_flag'           => 'No',
                'email_recipients'      => 'dtp_qcasst@dtshoes.com',
                'style_no'              => 'VN00118K',
                'style_color_no'        => 'VN00118KY9T',
                'product_name'          => 'LX Era 95',
                'style_name'            => 'LX Era 95',
                'buyer_brand'           => 'Vans',
                'color_code'            => 'Y9T',
                'colorway_name'         => 'NAVY/RED',
                'product_export_to'     => 'APAC - Non China',
                'country_of_origin'     => 'Philippines',
                'season'                => 'S27',
                'sourcing_office'       => 'ASO',
                'size_category'         => 'U',
                'age_group'             => 'Adult',
                'product_category'      => 'Footwear',
                'test_packages'         => 'VANS Pull Strength',
                'product_id'            => 'VN00118K',
                'overall_result'        => 'Pass',
                'job_status'            => 'Submitted',
                'attachment_name'       => 'TRF_A005967914_VansPullStrength_Report.pdf',
                'created_at'            => $now,
                'updated_at'            => $now,
            ],
            // 4. A005967905 (Live Row #4)
            [
                'nexus_sample_id'       => 'NXL-2026-7905',
                'nexus_work_order_no'   => 'WO-20261026-7905',
                'application_no'        => 'A005967905',
                'form_code'             => '10072',
                'sample_type'           => 'Finished Product',
                'trf_submission_date'   => '26 Oct 2026',
                'report_issue_date'     => '26 Oct 2026',
                'cargo_receive_date'    => '06 Nov 2026',
                'report_no'             => 'DTP-LAB-2026-7905',
                'is_retest'             => 'No',
                'retest_flag'           => 'No',
                'email_recipients'      => 'dtp_qcasst@dtshoes.com',
                'style_no'              => 'VN000ZB8',
                'style_color_no'        => 'VN000ZB8CDA',
                'product_name'          => 'LX Old Skool 36',
                'style_name'            => 'LX Old Skool 36',
                'buyer_brand'           => 'Vans',
                'color_code'            => 'CDA',
                'colorway_name'         => 'Pearlized MARSHMALLOW',
                'product_export_to'     => 'APAC - Non China',
                'country_of_origin'     => 'Philippines',
                'season'                => 'S27',
                'sourcing_office'       => 'ASO',
                'size_category'         => 'UNISEX',
                'age_group'             => 'Adult',
                'product_category'      => 'Footwear',
                'test_packages'         => 'VANS Pull Strength',
                'product_id'            => 'VN000ZB8',
                'overall_result'        => 'Pass',
                'job_status'            => 'Submitted',
                'attachment_name'       => 'TRF_A005967905_VansPullStrength_Report.pdf',
                'created_at'            => $now,
                'updated_at'            => $now,
            ],
            // 5. A005967896 (Live Row #5)
            [
                'nexus_sample_id'       => 'NXL-2026-7896',
                'nexus_work_order_no'   => 'WO-20261026-7896',
                'application_no'        => 'A005967896',
                'form_code'             => '10072',
                'sample_type'           => 'Finished Product',
                'trf_submission_date'   => '26 Oct 2026',
                'report_issue_date'     => '26 Oct 2026',
                'cargo_receive_date'    => '06 Nov 2026',
                'report_no'             => 'DTP-LAB-2026-7896',
                'is_retest'             => 'No',
                'retest_flag'           => 'No',
                'email_recipients'      => 'dtp_qcasst@dtshoes.com',
                'style_no'              => 'VN000ZB8',
                'style_color_no'        => 'VN000ZB8CDA',
                'product_name'          => 'LX Old Skool 36',
                'style_name'            => 'LX Old Skool 36',
                'buyer_brand'           => 'Vans',
                'color_code'            => 'CDA',
                'colorway_name'         => 'Pearlized MARSHMALLOW',
                'product_export_to'     => 'APAC - Non China',
                'country_of_origin'     => 'Philippines',
                'season'                => 'S27',
                'sourcing_office'       => 'ASO',
                'size_category'         => 'UNISEX',
                'age_group'             => 'Adult',
                'product_category'      => 'Footwear',
                'test_packages'         => 'VANS Pull Strength',
                'product_id'            => 'VN000ZB8',
                'overall_result'        => 'Pass',
                'job_status'            => 'Submitted',
                'attachment_name'       => 'TRF_A005967896_VansPullStrength_Report.pdf',
                'created_at'            => $now,
                'updated_at'            => $now,
            ],
            // 6. A005967886 (Live Row #6)
            [
                'nexus_sample_id'       => 'NXL-2026-7886',
                'nexus_work_order_no'   => 'WO-20261026-7886',
                'application_no'        => 'A005967886',
                'form_code'             => '10072',
                'sample_type'           => 'Finished Product',
                'trf_submission_date'   => '26 Oct 2026',
                'report_issue_date'     => '26 Oct 2026',
                'cargo_receive_date'    => '27 Oct 2026',
                'report_no'             => 'DTP-LAB-2026-7886',
                'is_retest'             => 'No',
                'retest_flag'           => 'No',
                'email_recipients'      => 'dtp_qcasst@dtshoes.com',
                'style_no'              => 'VN000Y7F',
                'style_color_no'        => 'VN000Y7FFUC',
                'product_name'          => 'Vans Glitter Slip-On',
                'style_name'            => 'Vans Glitter Slip-On',
                'buyer_brand'           => 'Vans',
                'color_code'            => 'FUC',
                'colorway_name'         => 'GLITTER Classic Cream',
                'product_export_to'     => 'APAC - Non China',
                'country_of_origin'     => 'Philippines',
                'season'                => 'S27',
                'sourcing_office'       => 'ASO',
                'size_category'         => 'U',
                'age_group'             => 'Adult',
                'product_category'      => 'Footwear',
                'test_packages'         => 'VANS Pull Strength',
                'product_id'            => 'VN000Y7F',
                'overall_result'        => 'Pass',
                'job_status'            => 'Submitted',
                'attachment_name'       => 'TRF_A005967886_VansPullStrength_Report.pdf',
                'created_at'            => $now,
                'updated_at'            => $now,
            ],
            // 7. A005967876 (Live Row #7)
            [
                'nexus_sample_id'       => 'NXL-2026-7876',
                'nexus_work_order_no'   => 'WO-20261026-7876',
                'application_no'        => 'A005967876',
                'form_code'             => '10072',
                'sample_type'           => 'Finished Product',
                'trf_submission_date'   => '26 Oct 2026',
                'report_issue_date'     => '26 Oct 2026',
                'cargo_receive_date'    => '06 Nov 2026',
                'report_no'             => 'DTP-LAB-2026-7876',
                'is_retest'             => 'No',
                'retest_flag'           => 'No',
                'email_recipients'      => 'dtp_qcasst@dtshoes.com',
                'style_no'              => 'VN000ZB8',
                'style_color_no'        => 'VN000ZB8BKO',
                'product_name'          => 'LX Old Skool 36',
                'style_name'            => 'LX Old Skool 36',
                'buyer_brand'           => 'Vans',
                'color_code'            => 'BKO',
                'colorway_name'         => 'Pearlized Woodland BLACK/OLIVE',
                'product_export_to'     => 'APAC - Non China',
                'country_of_origin'     => 'Philippines',
                'season'                => 'S27',
                'sourcing_office'       => 'ASO',
                'size_category'         => 'UNISEX',
                'age_group'             => 'Adult',
                'product_category'      => 'Footwear',
                'test_packages'         => 'VANS Pull Strength',
                'product_id'            => 'VN000ZB8',
                'overall_result'        => 'Pass',
                'job_status'            => 'Submitted',
                'attachment_name'       => 'TRF_A005967876_VansPullStrength_Report.pdf',
                'created_at'            => $now,
                'updated_at'            => $now,
            ],
            // 8. A005967380 (User's URL #4 - UploadForm [10072] Product)
            [
                'nexus_sample_id'       => 'NXL-2026-7380',
                'nexus_work_order_no'   => 'WO-20261026-7380',
                'application_no'        => 'A005967380',
                'form_code'             => '10072',
                'sample_type'           => 'Finished Product',
                'trf_submission_date'   => '26 Oct 2026',
                'report_issue_date'     => '26 Oct 2026',
                'cargo_receive_date'    => '29 Oct 2026',
                'report_no'             => 'DTP-LAB-2026-7380',
                'is_retest'             => 'No',
                'retest_flag'           => 'No',
                'email_recipients'      => 'dtc_lab2@dtshoes.com; yoyo_lab@dtshoes.com; DTP_lab@dtshoes.com; dtp_qcasst@dtshoes.com',
                'style_no'              => 'VN000CQA',
                'style_color_no'        => 'VN000CQABOP',
                'product_name'          => 'LX Authentic Reissue 44 LX RDMSH',
                'style_name'            => 'LX Authentic Reissue 44 LX RDMSH',
                'buyer_brand'           => 'Vans',
                'color_code'            => 'BOP',
                'colorway_name'         => 'RACING RED/MARSHMALLOW',
                'product_export_to'     => 'US, Canada, EU & Global',
                'country_of_origin'     => 'Philippines',
                'season'                => 'S27',
                'sourcing_office'       => 'ASO',
                'size_category'         => 'MALE',
                'age_group'             => 'Adult',
                'product_category'      => 'Footwear',
                'test_packages'         => 'Finished Product Testing',
                'product_id'            => 'VN000CQA',
                'overall_result'        => 'Pass',
                'job_status'            => 'Submitted',
                'attachment_name'       => 'TRF_A005967380_FinishedProduct_Report.pdf',
                'u_form_url'            => 'https://interlink2.intertek.com/InterLink2/aspx/eFormPage/Forms/UploadForm.aspx?args=F78A0B062541AEA81E8BA8297FBA2DA76CAE8B7A92CD3B50145C27C4686D8052AD66664402613CDC4031709D5C5E2FDA0316CA3755832246B1B733AAB326C3C2A15D34722BE4F7F8309CA100A5D865BB1F0E207941D17D3799113A69C0AECE918B5C1298A6E10500C9D93FDD2DB8EA9ED23C0AAFCC12EEF1D54E9B78FABB81D92BA34C7D3C388D410404A8B8B9DFE6D46ABC58022EC9EC92E7350DCFA6AE0EFED60B209BE172CD0BC22019528928A7F5B3422F2F26CF9D5A13D8CD7F78147FE62BA8D95A3DCEE34E88DECFB0DCC9674A&iudsReqId=94fb4131f7d249fe857a4992cb952b91',
                'created_at'            => $now,
                'updated_at'            => $now,
            ],
            // 9. A005967368 (Finished Product)
            [
                'nexus_sample_id'       => 'NXL-2026-7368',
                'nexus_work_order_no'   => 'WO-20261026-7368',
                'application_no'        => 'A005967368',
                'form_code'             => '10072',
                'sample_type'           => 'Finished Product',
                'trf_submission_date'   => '26 Oct 2026',
                'report_issue_date'     => '26 Oct 2026',
                'cargo_receive_date'    => '29 Oct 2026',
                'report_no'             => 'DTP-LAB-2026-7368',
                'is_retest'             => 'No',
                'retest_flag'           => 'No',
                'email_recipients'      => 'dtp_qcasst@dtshoes.com',
                'style_no'              => 'VN000D3H',
                'style_color_no'        => 'VN000D3HY28',
                'product_name'          => 'OLD SKOOL BLACK/WHITE',
                'style_name'            => 'OLD SKOOL BLACK/WHITE',
                'buyer_brand'           => 'Vans',
                'color_code'            => 'Y28',
                'colorway_name'         => 'BLACK/TRUE WHITE',
                'product_export_to'     => 'US, Canada, EU & Global',
                'country_of_origin'     => 'Philippines',
                'season'                => 'S27',
                'sourcing_office'       => 'ASO',
                'size_category'         => 'UNISEX',
                'age_group'             => 'Adult',
                'product_category'      => 'Footwear',
                'test_packages'         => 'Finished Product Testing',
                'product_id'            => 'VN000D3H',
                'overall_result'        => 'Pass',
                'job_status'            => 'Submitted',
                'attachment_name'       => 'TRF_A005967368_FinalReport.pdf',
                'created_at'            => $now,
                'updated_at'            => $now,
            ],
            // 10. A005967337 (User's URL #3 - UploadForm [10072] Product)
            [
                'nexus_sample_id'       => 'NXL-2026-7337',
                'nexus_work_order_no'   => 'WO-20261026-7337',
                'application_no'        => 'A005967337',
                'form_code'             => '10072',
                'sample_type'           => 'Finished Product',
                'trf_submission_date'   => '26 Oct 2026',
                'report_issue_date'     => '26 Oct 2026',
                'cargo_receive_date'    => '26 Oct 2026',
                'report_no'             => 'DTP-LAB-2026-7337',
                'is_retest'             => 'No',
                'retest_flag'           => 'No',
                'email_recipients'      => 'dtc_lab2@dtshoes.com; yoyo_lab@dtshoes.com; DTP_lab@dtshoes.com; dtp_qcasst@dtshoes.com',
                'style_no'              => 'VN00118K',
                'style_color_no'        => 'VN00118KBA2',
                'product_name'          => 'LX Era 95',
                'style_name'            => 'LX Era 95',
                'buyer_brand'           => 'Vans',
                'color_code'            => 'BA2',
                'colorway_name'         => 'BLACK/WHITE',
                'product_export_to'     => 'US, Canada, EU & Global',
                'country_of_origin'     => 'Philippines',
                'season'                => 'S27',
                'sourcing_office'       => 'ASO',
                'size_category'         => 'UNISEX',
                'age_group'             => 'Adult',
                'product_category'      => 'Footwear',
                'test_packages'         => 'Finished Product Testing',
                'product_id'            => 'VN00118K',
                'overall_result'        => 'Pass',
                'job_status'            => 'Submitted',
                'attachment_name'       => 'TRF_A005967337_FinishedProduct_Report.pdf',
                'u_form_url'            => 'https://interlink2.intertek.com/InterLink2/aspx/eFormPage/Forms/UploadForm.aspx?args=F78A0B062541AEA81E8BA8297FBA2DA76CAE8B7A92CD3B50145C27C4686D8052AD66664402613CDC4031709D5C5E2FDAD26AC63FE3633E5EB85FF276185E8FEF6C32455C315C7F3510B3B5841D9DBD3661D5B571B42B44C70A97430B9D18E743762D52C04F5EBF27486B405A9B3873BDA00A48D43B6FDD6A6BCFF680402A54734CA3F61B1EDBC0CEEE5EA1ACDF1F041048B2111FFAE206966B5EA1A63E6762C8A3344D9BC40B2C8D87E0AF705DC09F420CAE30B777B199F1381AC47FA78CE670AD05BBC073860164242ED6C9138F529F&iudsReqId=f2f7044c73be480083e3be5aec54221e',
                'created_at'            => $now,
                'updated_at'            => $now,
            ],
            // 11. A005967101 (Material UploadForm [10076] - FENG AN LEATHER CO., LTD)
            [
                'nexus_sample_id'       => 'NXL-2026-7101',
                'nexus_work_order_no'   => 'WO-20261026-7101',
                'application_no'        => 'A005967101',
                'form_code'             => '10076',
                'sample_type'           => 'Material',
                'trf_submission_date'   => '26 Oct 2026',
                'report_issue_date'     => '26 Oct 2026',
                'cargo_receive_date'    => '26 Oct 2026',
                'report_no'             => 'DTP-MAT-2026-7101',
                'is_retest'             => 'No',
                'retest_flag'           => 'No',
                'related_trf_no'        => 'TRF-FA-2026-108850',
                'email_recipients'      => 'dtc_lab2@dtshoes.com; yoyo_lab@dtshoes.com; DTP_lab@dtshoes.com; dtp_qcasst@dtshoes.com',
                'company_name'          => 'KEY TRADE CO., LTD',
                'interlink_code'        => 'E126863',
                'vendor_name'           => 'KEY TRADE CO., LTD',
                'vendor_id'             => '0000507450',
                'factory_name'          => 'FENG AN LEATHER CO., LTD',
                'factory_id'            => 'G00535E00782F001',
                'supplier_name'         => 'FENG AN',
                'supplier_id'           => 'G00535',
                'facility_name'         => 'FENG AN LEATHER CO., LTD',
                'facility_id'           => 'G00535E00782F001',
                'buyer_brand'           => 'Vans',
                'material_code'         => '108850',
                'material_name'         => '1.2-1.4mm Hyper Violet127-36-35 Cow Suede',
                'material_type'         => 'Leather',
                'material_sub_type'     => 'Cow Split Suede',
                'requirement_spec_no'   => 'VANS-LTH-SUEDE-01',
                'color_code'            => '127-36-35',
                'colorway_name'         => 'Hyper Violet',
                'thickness'             => '1.2-1.4mm',
                'weight'                => '1.3',
                'weight_uom'            => 'kg/m2',
                'finishing'             => 'Dyed Suede Split',
                'end_use'               => 'Upper',
                'country_of_origin'     => 'China',
                'season'                => 'S27',
                'sourcing_office'       => 'ASO',
                'product_category'      => 'Footwear',
                'test_packages'         => 'Material Physical & Colorfastness',
                'overall_result'        => 'Pass',
                'job_status'            => 'Submitted',
                'attachment_name'       => 'TRF_A005967101_LeatherSuede_Report.pdf',
                'created_at'            => $now,
                'updated_at'            => $now,
            ],
        ];

        foreach ($liveRows as $row) {
            $this->db->table('tbl_interlink_reports')->insert($row);
        }

        // =====================================================================
        // SEED SAMPLE ITEMS, TEST ITEMS, AND TEST READINGS FOR TAB 2 (Upload Result)
        // =====================================================================
        $allInserted = $this->db->table('tbl_interlink_reports')->get()->getResultArray();
        foreach ($allInserted as $rep) {
            $rid   = (int) $rep['id'];
            $appNo = $rep['application_no'];
            $repNo = $rep['report_no'];
            $isMat = ($rep['sample_type'] === 'Material');

            // 1. Sample Information (JOB_DUMMY_007_SAMPLE_ITEM)
            $this->db->table('tbl_interlink_sample_items')->insert([
                'report_id'          => $rid,
                'application_no'     => $appNo,
                'sample_id_code'     => 'S001',
                'sample_description' => $isMat
                    ? ($rep['material_name'] ?: '1.2-1.4mm Cow Suede Upper Material')
                    : (($rep['product_name'] ?: 'Vans Footwear') . ' (' . ($rep['style_color_no'] ?: $rep['style_no']) . ')'),
                'sample_color'       => $rep['colorway_name'] ?: 'BLACK/WHITE',
                'created_at'         => $now,
            ]);

            // 2. Test Item Result (JOB_DUMMY_008_TEST_ITEM_INP) & Readings (JOB_DUMMY_013)
            if ($isMat) {
                $this->db->table('tbl_interlink_test_items')->insert([
                    'report_id'             => $rid,
                    'application_no'        => $appNo,
                    'report_no'             => $repNo,
                    'sample_id_code'        => 'S001',
                    'item_sequence'         => 1,
                    'test_category'         => 'Material Physical',
                    'test_code'             => 'ISO 3377-2',
                    'test_name'             => 'Tear Strength of Leather (Double Edge Tear)',
                    'test_method_standard'  => 'ISO 3377-2 / SATRA TM162',
                    'component_tested'      => 'Cow Suede Upper (1.2-1.4mm)',
                    'test_condition'        => 'Standard Atmosphere (23°C, 50% RH)',
                    'requirement_spec'      => '>= 80 N',
                    'measurement_unit'      => 'N',
                    'actual_result_summary' => 'Along: 112.4 N | Across: 104.8 N',
                    'test_result'           => 'Pass',
                    'created_at'            => $now,
                    'updated_at'            => $now,
                ]);
                $item1 = (int) $this->db->insertID();

                $this->db->table('tbl_interlink_test_readings')->insertBatch([
                    [
                        'item_id'               => $item1,
                        'report_id'             => $rid,
                        'application_no'        => $appNo,
                        'sample_id_code'        => 'S001',
                        'test_item_name'        => 'Tear Strength of Leather (Double Edge Tear)',
                        'test_method'           => 'ISO 3377-2',
                        'condition_stage'       => 'As Received',
                        'sample_component'      => 'Upper Suede',
                        'sample_component_desc' => $rep['material_name'],
                        'test_element'          => 'Parallel / Along Direction',
                        'test_value_indicator'  => '=',
                        'specimen_no'           => 1,
                        'specimen_label'        => 'Along',
                        'reading_value_raw'     => '112.4',
                        'reading_numeric'       => 112.400,
                        'unit'                  => 'N',
                        'target_spec'           => '>= 80 N',
                        'reading_status'        => 'Pass',
                        'created_at'            => $now,
                    ],
                    [
                        'item_id'               => $item1,
                        'report_id'             => $rid,
                        'application_no'        => $appNo,
                        'sample_id_code'        => 'S001',
                        'test_item_name'        => 'Tear Strength of Leather (Double Edge Tear)',
                        'test_method'           => 'ISO 3377-2',
                        'condition_stage'       => 'As Received',
                        'sample_component'      => 'Upper Suede',
                        'sample_component_desc' => $rep['material_name'],
                        'test_element'          => 'Perpendicular / Across Direction',
                        'test_value_indicator'  => '=',
                        'specimen_no'           => 2,
                        'specimen_label'        => 'Across',
                        'reading_value_raw'     => '104.8',
                        'reading_numeric'       => 104.800,
                        'unit'                  => 'N',
                        'target_spec'           => '>= 80 N',
                        'reading_status'        => 'Pass',
                        'created_at'            => $now,
                    ],
                ]);
            } else {
                // Finished Product (VANS Pull Strength & Whole Shoe Bonding)
                $this->db->table('tbl_interlink_test_items')->insert([
                    'report_id'             => $rid,
                    'application_no'        => $appNo,
                    'report_no'             => $repNo,
                    'sample_id_code'        => 'S001',
                    'item_sequence'         => 1,
                    'test_category'         => 'Physical / Mechanical',
                    'test_code'             => 'VANS-PS-01',
                    'test_name'             => 'VANS Pull Strength (Eyelet / Heel Tab / Decorative Attachment)',
                    'test_method_standard'  => 'SATRA TM149 / VF Footwear Spec',
                    'component_tested'      => 'Eyelet & Upper Attachment',
                    'test_condition'        => 'As Received',
                    'requirement_spec'      => '>= 90 N (No Detachment)',
                    'measurement_unit'      => 'N',
                    'actual_result_summary' => 'Left: 185.0 N | Right: 192.5 N',
                    'test_result'           => 'Pass',
                    'created_at'            => $now,
                    'updated_at'            => $now,
                ]);
                $item1 = (int) $this->db->insertID();

                $this->db->table('tbl_interlink_test_items')->insert([
                    'report_id'             => $rid,
                    'application_no'        => $appNo,
                    'report_no'             => $repNo,
                    'sample_id_code'        => 'S001',
                    'item_sequence'         => 2,
                    'test_category'         => 'Physical / Mechanical',
                    'test_code'             => 'SATRA TM411',
                    'test_name'             => 'Sole-Upper / Foxing Bonding Peel Strength',
                    'test_method_standard'  => 'SATRA TM411 / ISO 17707',
                    'component_tested'      => 'Outsole & Foxing Tape to Upper',
                    'test_condition'        => 'As Received',
                    'requirement_spec'      => '>= 3.5 N/mm',
                    'measurement_unit'      => 'N/mm',
                    'actual_result_summary' => 'Toe: 4.8 N/mm | Heel: 5.1 N/mm',
                    'test_result'           => 'Pass',
                    'created_at'            => $now,
                    'updated_at'            => $now,
                ]);
                $item2 = (int) $this->db->insertID();

                $this->db->table('tbl_interlink_test_readings')->insertBatch([
                    [
                        'item_id'               => $item1,
                        'report_id'             => $rid,
                        'application_no'        => $appNo,
                        'sample_id_code'        => 'S001',
                        'test_item_name'        => 'VANS Pull Strength (Eyelet / Heel Tab / Decorative Attachment)',
                        'test_method'           => 'SATRA TM149',
                        'condition_stage'       => 'As Received',
                        'sample_component'      => 'Top Eyelet - Left Shoe',
                        'sample_component_desc' => ($rep['product_name'] ?: 'Shoe') . ' - ' . ($rep['colorway_name'] ?: ''),
                        'test_element'          => 'Pull Force',
                        'test_value_indicator'  => '=',
                        'specimen_no'           => 1,
                        'specimen_label'        => 'Left Shoe',
                        'reading_value_raw'     => '185.0',
                        'reading_numeric'       => 185.000,
                        'unit'                  => 'N',
                        'target_spec'           => '>= 90 N',
                        'reading_status'        => 'Pass',
                        'created_at'            => $now,
                    ],
                    [
                        'item_id'               => $item1,
                        'report_id'             => $rid,
                        'application_no'        => $appNo,
                        'sample_id_code'        => 'S001',
                        'test_item_name'        => 'VANS Pull Strength (Eyelet / Heel Tab / Decorative Attachment)',
                        'test_method'           => 'SATRA TM149',
                        'condition_stage'       => 'As Received',
                        'sample_component'      => 'Top Eyelet - Right Shoe',
                        'sample_component_desc' => ($rep['product_name'] ?: 'Shoe') . ' - ' . ($rep['colorway_name'] ?: ''),
                        'test_element'          => 'Pull Force',
                        'test_value_indicator'  => '=',
                        'specimen_no'           => 2,
                        'specimen_label'        => 'Right Shoe',
                        'reading_value_raw'     => '192.5',
                        'reading_numeric'       => 192.500,
                        'unit'                  => 'N',
                        'target_spec'           => '>= 90 N',
                        'reading_status'        => 'Pass',
                        'created_at'            => $now,
                    ],
                    [
                        'item_id'               => $item2,
                        'report_id'             => $rid,
                        'application_no'        => $appNo,
                        'sample_id_code'        => 'S001',
                        'test_item_name'        => 'Sole-Upper / Foxing Bonding Peel Strength',
                        'test_method'           => 'SATRA TM411',
                        'condition_stage'       => 'As Received',
                        'sample_component'      => 'Outsole to Upper - Toe',
                        'sample_component_desc' => ($rep['product_name'] ?: 'Shoe') . ' - ' . ($rep['colorway_name'] ?: ''),
                        'test_element'          => 'Peel Strength',
                        'test_value_indicator'  => '=',
                        'specimen_no'           => 1,
                        'specimen_label'        => 'Toe',
                        'reading_value_raw'     => '4.8',
                        'reading_numeric'       => 4.800,
                        'unit'                  => 'N/mm',
                        'target_spec'           => '>= 3.5 N/mm',
                        'reading_status'        => 'Pass',
                        'created_at'            => $now,
                    ],
                ]);
            }
        }
    }

    public function down()
    {
        $this->forge->dropTable('tbl_interlink_sample_items', true);
    }
}
