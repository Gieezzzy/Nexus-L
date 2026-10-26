<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * InterlinkReportModel
 * Manages the complete Intertek InterLink 2.0 schema in dbnexus:
 * - tbl_interlink_reports       (102 columns covering SearchFormWithHeader [10102], NormalForm [10000], UploadForm [10072] Product & [10076] Material, and Tab 2 Upload Result)
 * - tbl_interlink_sample_items  (Tab 2 Upload Result: Sample Information - JOB_DUMMY_007_SAMPLE_ITEM)
 * - tbl_interlink_test_items    (Tab 2 Upload Result: Test Item Result - JOB_DUMMY_008_TEST_ITEM_INP)
 * - tbl_interlink_test_readings (Tab 2 Upload Result: Test Item Result For Reading - JOB_DUMMY_013)
 * - tbl_interlink_attachments   (Report PDFs, Supplementary Attachments, Related COCs)
 * - tbl_interlink_partners      (Vendor / Factory / Supplier / Facility Master Directory)
 * - tbl_interlink_sync_logs     (Sync Audit Trail, Live HTTP Postback & Playwright Bot Queue)
 */
class InterlinkReportModel extends Model
{
    protected $table            = 'tbl_interlink_reports';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    protected $allowedFields = [
        // Internal NEXUS-L Identifiers
        'nexus_sample_id',
        'nexus_work_order_no',

        // InterLink Form Identifiers & Live URLs
        'application_no',
        'form_code',
        'portal_name',
        'e_form_url',
        'u_form_url',
        'search_form_args',
        'upload_form_args',
        'iuds_req_id',

        // UploadForm Tab 1 Top Section: Service & Lab Information
        'service_provider',
        'lab_location',
        'testing_lab_code',
        'service_test_type',
        'test_stage',
        'service_required',
        'sample_type',
        'report_no',
        'is_retest',
        'retest_flag',
        'retest_sequence',
        'previous_report_no',
        'related_trf_no',
        'retest_reason',
        'email_recipients',
        'cc_email_recipients',

        // NormalForm [10000]: Applicant & Invoice Information
        'applicant_address',
        'applicant_country',
        'applicant_postal_code',
        'contact_person',
        'contact_email',
        'contact_phone',
        'invoice_to_applicant',
        'quotation_required',
        'return_sample',

        // UploadForm Section 2: Vendor / Factory / Supplier / Facility Information
        'company_name',
        'interlink_code',
        'vendor_name',
        'vendor_id',
        'factory_name',
        'factory_id',
        'supplier_name',
        'supplier_id',
        'facility_name',
        'facility_id',

        // UploadForm Section 3: Product Information (ALL) [10072]
        'style_no',
        'style_color_no',
        'product_name',
        'style_name',
        'buyer_brand',
        'department',
        'business_division_code',
        'color_code',
        'colorway_name',
        'product_export_to',
        'destination_market',
        'country_of_origin',
        'season',
        'season_year',
        'sourcing_office',
        'size_category',
        'age_group',
        'age_group_gender',
        'cargo_receive_date',
        'care_instruction',
        'product_category',
        'fiber_composition',
        'test_packages',
        'spec_package_code',
        'lab_remarks',

        // UploadForm Section 3B: GCC/CPC & Factory Address [10072]
        'gcc_cpc_required',
        'product_id',
        'manufacture_date',
        'factory_address',
        'factory_country',
        'factory_apt_suite',
        'factory_city',
        'factory_state_province',
        'factory_zip_postal',
        'factory_email',
        'factory_phone',

        // UploadForm [10076]: Material Information (ALL)
        'material_code',
        'material_name',
        'material_type',
        'requirement_spec_no',
        'material_sub_type',
        'claim_type',
        'thickness',
        'weight',
        'weight_uom',
        'finishing',
        'end_use',
        'washing_instruction',

        // Order & Sample Tracking
        'po_number',
        'lot_batch_no',
        'order_quantity',
        'sample_size',
        'sample_quantity',
        'construction_type',

        // Dates & Timeline Columns
        'trf_submission_date',
        'sample_received_date',
        'test_start_date',
        'test_completion_date',
        'report_issue_date',
        'turnaround_days',

        // UploadForm Tab 2: Upload Result & Search Grid Status Columns
        'job_status',
        'overall_result',
        'review_result',
        'review_status',
        'latest_review_comment',
        'cancellation_reason',
        'report_type',
        'physical_test_result',
        'chemical_test_result',
        'colorfastness_result',
        'bonding_peel_result',
        'flexing_aging_result',
        'fail_reason_notes',
        'corrective_action_notes',
        'related_coc_no',
        'attachment_name',
        'attachment_path',
        'supplementary_attachment',

        // Personnel & Signatories
        'tested_by_technician',
        'approved_by_signatory',

        // Synchronization Tracking
        'synced_to_interlink',
        'sync_count',
        'synced_at',
        'synced_by',
        'last_sync_error',
    ];

    public function findByAppNo(string $appNo): ?array
    {
        $row = $this->where('application_no', trim($appNo))->first();
        return $row ?: null;
    }

    public function getSampleItems(string $appNo): array
    {
        if (! $this->db->tableExists('tbl_interlink_sample_items')) {
            return [];
        }
        return $this->db->table('tbl_interlink_sample_items')
            ->where('application_no', trim($appNo))
            ->orderBy('sample_row_id', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function getTestItems(string $appNo): array
    {
        if (! $this->db->tableExists('tbl_interlink_test_items')) {
            return [];
        }
        return $this->db->table('tbl_interlink_test_items')
            ->where('application_no', trim($appNo))
            ->orderBy('item_sequence', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function getTestReadings(string $appNo): array
    {
        if (! $this->db->tableExists('tbl_interlink_test_readings')) {
            return [];
        }
        return $this->db->table('tbl_interlink_test_readings')
            ->where('application_no', trim($appNo))
            ->orderBy('item_id', 'ASC')
            ->orderBy('specimen_no', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function getPartners(): array
    {
        if (! $this->db->tableExists('tbl_interlink_partners')) {
            return [];
        }
        return $this->db->table('tbl_interlink_partners')
            ->where('is_active', 1)
            ->orderBy('partner_role', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function getRecentLogs(int $limit = 15): array
    {
        if (! $this->db->tableExists('tbl_interlink_sync_logs')) {
            return [];
        }
        return $this->db->table('tbl_interlink_sync_logs')
            ->orderBy('log_id', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    public function logSync(array $data): int
    {
        $fields = (array) ($data['fields'] ?? []);
        $this->db->table('tbl_interlink_sync_logs')->insert([
            'report_id'          => $data['report_id'] ?? null,
            'application_no'     => $data['application_no'] ?? '',
            'report_no'          => $data['report_no'] ?? '',
            'target_portal_url'  => $data['target_portal_url'] ?? null,
            'fields_count'       => count($fields),
            'synced_fields_json' => ! empty($fields) ? json_encode($fields, JSON_UNESCAPED_UNICODE) : null,
            'sync_mode'          => $data['sync_mode'] ?? 'portal_sync',
            'bot_job_file'       => $data['bot_job_file'] ?? null,
            'status'             => $data['status'] ?? 'success',
            'error_message'      => $data['error_message'] ?? null,
            'ip_address'         => $data['ip_address'] ?? null,
            'user_id'            => $data['user_id'] ?? null,
            'user_name'          => $data['user_name'] ?? null,
            'created_at'         => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }
}
