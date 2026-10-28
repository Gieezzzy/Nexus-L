<?php
/**
 * NEXUS-L InterLink 2.0 Automation & 1-Click Report Sync Workspace
 *
 * Covers all live Intertek InterLink 2.0 forms inspected from Chrome:
 * 1. SearchFormWithHeader.aspx [10102] (Top Search Criteria + 53-Column Grid with E & U buttons)
 * 2. NormalForm.aspx           [10000] (Online eApplication Form opened via 'E' button)
 * 3. UploadForm.aspx           [10072] (Finished Product Upload Form - Tab 1 + Tab 2 Upload Result)
 * 4. UploadForm.aspx           [10076] (Material Upload Form - Tab 1 + Tab 2 Upload Result)
 * 5. Pre-Sync Verification Dialog & 1-Click Send Report
 */
$portalUser = $currentUser['name'] ?? 'Faye Ann Elaysa';
?>

<!-- Top NEXUS-L Status & Quick Actions Bar -->
<div class="il-top-cards">
    <div class="card glass il-stat">
        <div>
            <small>Total Lab Applications</small>
            <b id="stat-total"><?= count($reports) ?></b>
        </div>
        <span class="ico"><svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></span>
    </div>

    <div class="card glass il-stat">
        <div>
            <small>Pending Upload</small>
            <b id="stat-pending" class="il-warn-txt"><?= (int) $pendingCount ?></b>
        </div>
        <span class="ico il-ico-warn"><svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
    </div>

    <div class="card glass il-stat">
        <div>
            <small>Synced to InterLink</small>
            <b id="stat-synced" class="il-ok-txt"><?= (int) $syncedCount ?></b>
        </div>
        <span class="ico il-ico-ok"><svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg></span>
    </div>

    <div class="card glass il-stat il-stat-actions">
        <div>
            <small>NEXUS-L Lab Sync &amp; 1-Click Controls</small>
            <div class="il-quick-btns">
                <button type="button" id="btn-manage-reports" class="il-btn il-btn-outline">
                    <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                    Company Reports
                </button>
                <button type="button" id="btn-sync-logs" class="il-btn il-btn-outline">
                    <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Sync History
                </button>
            </div>
        </div>
    </div>
</div>

<?= view('interlink/center', ['reports' => $reports ?? [], 'openUrl' => $openUrl ?? 'https://interlink2.intertek.com/']) ?>

<!-- Main Embedded InterLink 2.0 Browser Shell -->
<div class="card glass il-browser-shell mt">
    <!-- Browser Chrome / Navigation Bar -->
    <div class="il-browser-bar">
        <div class="il-nav-btns">
            <button type="button" id="il-btn-back" class="il-icon-btn" title="Back" disabled>
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"/></svg>
            </button>
            <button type="button" id="il-btn-forward" class="il-icon-btn" title="Forward" disabled>
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
            </button>
            <button type="button" id="il-btn-reload" class="il-icon-btn" title="Reload InterLink View">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            </button>
            <button type="button" id="il-btn-home" class="il-icon-btn" title="Go to SearchFormWithHeader.aspx [10102]">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10"/></svg>
            </button>
        </div>

        <div class="il-address-bar">
            <span class="il-lock" title="HTTPS Authenticated Session (interlink2.intertek.com)">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            </span>
            <input type="text" id="il-url-input" class="il-url-input" readonly
                   value="<?= esc($searchUrl) ?>"
                   data-search-url="<?= esc($searchUrl) ?>"
                   data-upload-url="<?= esc($uploadUrl) ?>"
                   data-normal-url="<?= esc($normalUrl ?? '') ?>">
            <span id="il-page-badge" class="il-page-badge il-badge-search">SearchFormWithHeader.aspx [10102]</span>
        </div>

        <!-- Dynamic Context Action Area: Shows Hint on Search Page, Shows SYNC & 1-CLICK SEND when on UploadForm.aspx -->
        <div class="il-sync-slot">
            <div id="il-step-hint" class="il-step-hint">
                <span class="il-step-dot"></span>
                <span>Click <b>U</b> (Upload Form) or <b>E</b> (Application Form) on any report</span>
            </div>

            <div id="il-upload-actions" class="il-upload-actions hidden">
                <button type="button" id="btn-open-sync-dialog" class="il-sync-cta">
                    <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>Sync NEXUS-L Data</span>
                    <small id="sync-btn-appno"></small>
                </button>
                <button type="button" id="btn-oneclick-send-top" class="il-send-cta" title="Review & 1-Click Send Report to InterLink">
                    <svg class="ic" aria-hidden="true"><use href="#i-bolt"/></svg> 1-Click Send Report
                </button>
            </div>
        </div>
    </div>

    <!-- Loading Overlay (Simulates PortalMainLoadingPanel.Show()) -->
    <div id="il-loading-overlay" class="il-loading-overlay hidden">
        <div class="il-loading-box">
            <div class="il-spinner"></div>
            <div>
                <b>Loading InterLink 2.0 eForm...</b>
                <small id="il-loading-sub">PortalMainLoadingPanel · Connecting to interlink2.intertek.com</small>
            </div>
        </div>
    </div>

    <!-- ===================================================================
         VIEWPORT 1: InterLink 2.0 SearchFormWithHeader.aspx [10102]
         (VF - Test Request/Report Search (Inhouse & 3rd party Lab Upload))
         =================================================================== -->
    <div id="il-view-search" class="il-portal-viewport">
        <!-- Intertek Yellow Top Header -->
        <div class="itk-header">
            <div class="itk-header-left">
                <div class="itk-logo">
                    <span class="itk-logo-top">intertek</span>
                    <span class="itk-logo-main">interlink <b>2.0</b></span>
                </div>
                <div class="itk-vf-badge">VF</div>
                <div class="itk-portal-select">
                    <span>VF Corporation Portal (Vendor)</span>
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"/></svg>
                </div>
            </div>
            <div class="itk-header-right">
                <span>? Help</span>
                <span><svg class="ic" aria-hidden="true"><use href="#i-mail"/></svg> Contact Us</span>
                <span><svg class="ic" aria-hidden="true"><use href="#i-user"/></svg> <?= esc($portalUser) ?></span>
                <span class="itk-grid-ico">⋮⋮⋮</span>
            </div>
        </div>

        <!-- Intertek Black Navigation Bar -->
        <div class="itk-navbar">
            <a href="javascript:void(0)" class="itk-nav-item">Submit a Request</a>
            <span class="itk-nav-sep">·</span>
            <div class="itk-nav-dropdown">
                <a href="javascript:void(0)" class="itk-nav-item active">Search Requests / Reports</a>
                <div class="itk-dropdown-menu">
                    <a href="javascript:void(0)">VF - Disclaim Declaration Search (Applicant)</a>
                    <a href="javascript:void(0)">VF - Test Request/Report Search (Applicant)</a>
                    <a href="javascript:void(0)" class="active">VF - Test Request/Report Search (Inhouse &amp; 3rd party Lab Upload)</a>
                </div>
            </div>
            <span class="itk-nav-sep">·</span>
            <a href="javascript:void(0)" class="itk-nav-item">Documentation</a>
            <span class="itk-nav-sep">·</span>
            <a href="javascript:void(0)" class="itk-nav-item">More</a>
        </div>

        <!-- Search Page Title & Toolbar -->
        <div class="itk-content">
            <div class="itk-ownhead">
                <h2 class="itk-page-title">InterLink Applications</h2>
                <label class="itk-gsearch"><span class="sr-only">Search all applications</span>
                    <svg class="ic" aria-hidden="true"><use href="#i-search"/></svg>
                    <input type="search" id="itk-general-search" placeholder="Search every application, in any column…" autocomplete="off">
                </label>
            </div>

            <div class="itk-toolbar">
                <div class="itk-toolbar-left">
                    <button type="button" class="itk-circle-btn" id="itk-btn-toggle-criteria" title="Toggle Content Search Criteria Panel (PH1_SF1_EGVNF)"><svg class="ic" aria-hidden="true"><use href="#i-search"/></svg></button>
                    <span class="itk-pill-btn">Testing Lab: <b>DATIAN</b></span>
                </div>
                <div class="itk-toolbar-right">
                    <button type="button" class="itk-circle-btn" id="itk-btn-refresh" title="Refresh Grid">↻</button>
                    <button type="button" class="itk-circle-btn" title="Analytics"><svg class="ic" aria-hidden="true"><use href="#i-chart"/></svg></button>
                    <button type="button" class="itk-circle-btn" title="Column Settings"><svg class="ic" aria-hidden="true"><use href="#i-settings"/></svg></button>
                    <button type="button" class="itk-circle-btn" title="Clear All Filters" id="itk-btn-clear-filter">⏳</button>
                </div>
            </div>

            <!-- Collapsible Top Search Criteria Panel (PH1_SF1_EGVNF_ef0 from live HTML) -->
            <div id="itk-search-criteria-panel" class="itk-criteria-panel hidden">
                <div class="itk-criteria-grid">
                    <div class="uf-field">
                        <label>Application No.</label>
                        <input type="text" data-top-filter="application_no" placeholder="e.g. A005967935">
                    </div>
                    <div class="uf-field">
                        <label>Report No.</label>
                        <input type="text" data-top-filter="report_no" placeholder="e.g. DTP-LAB-2026-7935">
                    </div>
                    <div class="uf-field">
                        <label>Style No.</label>
                        <input type="text" data-top-filter="style_no" placeholder="e.g. VN000ZB8">
                    </div>
                    <div class="uf-field">
                        <label>Style Color / SKU No.</label>
                        <input type="text" data-top-filter="style_color_no" placeholder="e.g. VN000ZB8BPR">
                    </div>
                    <div class="uf-field">
                        <label>Sample Type</label>
                        <select data-top-filter="sample_type">
                            <option value="">- All -</option>
                            <option value="Finished Product">Finished Product</option>
                            <option value="Material">Material</option>
                        </select>
                    </div>
                    <div class="uf-field">
                        <label>Brand Name</label>
                        <input type="text" data-top-filter="buyer_brand" value="Vans">
                    </div>
                    <div class="uf-field">
                        <label>Season</label>
                        <input type="text" data-top-filter="season" placeholder="e.g. S27">
                    </div>
                    <div class="uf-field">
                        <label>Test Package(s)</label>
                        <input type="text" data-top-filter="test_packages" placeholder="e.g. VANS Pull Strength">
                    </div>
                    <div class="uf-field">
                        <label>Testing Lab</label>
                        <input type="text" value="DATIAN" readonly>
                    </div>
                </div>
            </div>

            <!-- Real-Time Live Pagination & Multi-User Sync Bar (517 Pages / 7,749 Items) -->
            <div class="itk-pager" id="itk-pager-bar">
                <div class="itk-pager-left">
                    <span class="itk-pager-summary">
                        Page <b id="itk-curr-page">1</b> of <b id="itk-total-pages">517</b>
                        (<span id="itk-total-items">7749</span> items)
                    </span>
                    <span class="itk-pager-nums" id="itk-pager-nums">
                        <!-- Rendered dynamically for pages 1..517 -->
                    </span>
                    <span class="itk-pager-jump">
                        Page #
                        <input type="number" id="itk-jump-input" min="1" max="517" value="1">
                        <button type="button" id="itk-jump-btn">Go</button>
                    </span>
                </div>
                <div class="itk-pager-right">
                    <span class="itk-live-status" id="itk-live-status" title="Real-time connection to interlink2.intertek.com">
                        <span class="itk-live-dot" id="itk-live-dot"></span>
                        <span id="itk-live-text">Live InterLink Ready</span>
                    </span>
                    <button type="button" class="itk-live-sync-btn" id="itk-btn-live-refresh" title="Fetch latest rows & statuses from InterLink 2.0 right now">
                        ↻ Live Refresh Page
                    </button>
                    <label class="itk-autosync-label" title="Automatically poll InterLink 2.0 every 15 seconds so updates by other users reflect immediately">
                        <input type="checkbox" id="itk-autosync-toggle" checked>
                        <span>Auto-Sync (15s)</span>
                    </label>
                    <span class="itk-pagesize-wrap">
                        Page size:
                        <select class="itk-pagesize" id="itk-pagesize-select">
                            <option value="10">10</option>
                            <option value="15" selected>15</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </span>
                </div>
            </div>

            <!-- Main InterLink Yellow Data Grid (All 38 Live Columns from BuilderGridView) -->
            <div class="itk-grid-wrap">
                <table class="itk-grid" id="itk-search-table">
                    <thead>
                        <tr class="itk-th-row">
                            <th style="width:44px">Report Detail Page</th>
                            <th style="width:46px">Report Attach</th>
                            <th style="width:66px">Online Appli Form Upload</th>
                            <th style="width:44px">Relat COC</th>
                            <th>Sample Type</th>
                            <th>Application No.</th>
                            <th>Online TRF Submission Date ↓</th>
                            <th>Report No.</th>
                            <th>Report Issue Date</th>
                            <th>Job Status</th>
                            <th>Overall Result</th>
                            <th>Company Name</th>
                            <th>Vendor ID</th>
                            <th>Vendor Name</th>
                            <th>Factory ID</th>
                            <th>Factory Name</th>
                            <th>Supplier ID</th>
                            <th>Supplier Name</th>
                            <th>Brand Name</th>
                            <th>Test Stage</th>
                            <th>Product Export To</th>
                            <th>Service Test Type</th>
                            <th>Service Provider</th>
                            <th>GCC/CPC Required?</th>
                            <th>Style No.</th>
                            <th>Material Code</th>
                            <th>SKU No.</th>
                            <th>Season</th>
                            <th>Sourcing Office</th>
                            <th>Test Package(s)</th>
                            <th>Color Code</th>
                            <th>Color Description</th>
                            <th>Lab Location</th>
                            <th>Review Result</th>
                            <th>Review Status</th>
                            <th>Related TRF Number</th>
                            <th>Latest Review Comment</th>
                            <th>Cancellation Reason</th>
                        </tr>
                        <tr class="itk-filter-row">
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td><input type="text" data-col="sample_type" placeholder="- Search -"></td>
                            <td><input type="text" data-col="application_no" placeholder="- Search -"></td>
                            <td><input type="text" data-col="trf_submission_date" placeholder="- Search -"></td>
                            <td><input type="text" data-col="report_no" placeholder="- Search -"></td>
                            <td><input type="text" data-col="report_issue_date" placeholder="- Search -"></td>
                            <td><input type="text" data-col="job_status" placeholder="- Search -"></td>
                            <td><input type="text" data-col="overall_result" placeholder="- Search -"></td>
                            <td><input type="text" data-col="company_name" placeholder="- Search -"></td>
                            <td><input type="text" data-col="vendor_id" placeholder="- Search -"></td>
                            <td><input type="text" data-col="vendor_name" placeholder="- Search -"></td>
                            <td><input type="text" data-col="factory_id" placeholder="- Search -"></td>
                            <td><input type="text" data-col="factory_name" placeholder="- Search -"></td>
                            <td><input type="text" data-col="supplier_id" placeholder="- Search -"></td>
                            <td><input type="text" data-col="supplier_name" placeholder="- Search -"></td>
                            <td><input type="text" data-col="buyer_brand" placeholder="- Search -"></td>
                            <td><input type="text" data-col="test_stage" placeholder="- Search -"></td>
                            <td><input type="text" data-col="product_export_to" placeholder="- Search -"></td>
                            <td><input type="text" data-col="service_test_type" placeholder="- Search -"></td>
                            <td><input type="text" data-col="service_provider" placeholder="- Search -"></td>
                            <td><input type="text" data-col="gcc_cpc_required" placeholder="- Search -"></td>
                            <td><input type="text" data-col="style_no" placeholder="- Search -"></td>
                            <td><input type="text" data-col="material_code" placeholder="- Search -"></td>
                            <td><input type="text" data-col="style_color_no" placeholder="- Search -"></td>
                            <td><input type="text" data-col="season" placeholder="- Search -"></td>
                            <td><input type="text" data-col="sourcing_office" placeholder="- Search -"></td>
                            <td><input type="text" data-col="test_packages" placeholder="- Search -"></td>
                            <td><input type="text" data-col="color_code" placeholder="- Search -"></td>
                            <td><input type="text" data-col="colorway_name" placeholder="- Search -"></td>
                            <td><input type="text" data-col="lab_location" placeholder="- Search -"></td>
                            <td><input type="text" data-col="review_result" placeholder="- Search -"></td>
                            <td><input type="text" data-col="review_status" placeholder="- Search -"></td>
                            <td><input type="text" data-col="related_trf_no" placeholder="- Search -"></td>
                            <td><input type="text" data-col="latest_review_comment" placeholder="- Search -"></td>
                            <td><input type="text" data-col="cancellation_reason" placeholder="- Search -"></td>
                        </tr>
                    </thead>
                    <tbody id="itk-grid-tbody">
                        <!-- Populated dynamically by interlink.js from $reports -->
                    </tbody>
                </table>
            </div>

            <!-- Export Buttons Footer -->
            <div class="itk-export-bar">
                <button type="button" class="itk-export-btn">Export to Excel</button>
                <button type="button" class="itk-export-btn">Export to Excel Test Item</button>
                <button type="button" class="itk-export-btn">Export to Excel Reading</button>
            </div>
        </div>

        <div class="itk-footer">
            <span><b>(in)</b> &copy; Intertek - All rights reserved</span>
            <span>Terms and Conditions | Privacy Policy | Cookie Policy</span>
        </div>
    </div>

    <!-- ===================================================================
         VIEWPORT 2: InterLink 2.0 UploadForm.aspx ([10072] Product & [10076] Material)
         (Tab 1: VF Corporation Report Online Upload Form | Tab 2: Upload Result)
         =================================================================== -->
    <div id="il-view-upload" class="il-portal-viewport hidden">
        <!-- Context Detection Banner inside UploadForm.aspx -->
        <div class="uf-sync-banner">
            <div class="uf-sync-banner-left">
                <span class="uf-detect-pill" id="uf-detect-form-pill">✓ UploadForm.aspx [10072] Reached</span>
                <span>Application No. <b id="uf-banner-appno">A005967935</b> (<span id="uf-banner-model">LX Old Skool 36</span>) is ready for NEXUS-L sync &amp; 1-click report send.</span>
            </div>
            <div class="uf-sync-banner-right">
                <button type="button" id="btn-back-to-search" class="uf-btn-secondary">
                    ← Back to Search Grid
                </button>
                <button type="button" id="btn-view-eform" class="uf-btn-secondary" title="Open NormalForm.aspx [10000] (E)">
                    View eApplication (E)
                </button>
                <button type="button" id="btn-banner-sync" class="uf-btn-sync-now">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Review &amp; Sync NEXUS-L Data
                </button>
                <button type="button" id="btn-banner-oneclick" class="il-send-cta">
                    <svg class="ic" aria-hidden="true"><use href="#i-bolt"/></svg> 1-Click Sync &amp; Send Report
                </button>
            </div>
        </div>

        <!-- Exact InterLink UploadForm.aspx Window Container -->
        <div class="uf-page">
            <!-- Tabs Header (UF1$NewTabControl$aTabPage_0 & aTabPage_1) -->
            <div class="uf-tabs">
                <button type="button" class="uf-tab active" data-uf-tab="product" id="uf-tab-btn-main">
                    VF Corporation Report Online Upload Form - Product
                </button>
                <button type="button" class="uf-tab" data-uf-tab="result">
                    Upload Result
                </button>
            </div>

            <!-- ===============================================================
                 TAB 1: VF Corporation Report Online Upload Form (Product [10072] / Material [10076])
                 =============================================================== -->
            <div id="uf-tab-product" class="uf-tab-panel">
                <!-- SECTION 1: Top Service & Report Header -->
                <div class="uf-grid-3">
                    <div class="uf-field" data-field-wrap="service_provider">
                        <label><span class="uf-req">*</span> Service Provider <span class="uf-sync-tag hidden">✓ Synced</span></label>
                        <input type="text" id="uf_service_provider" data-uf-field="service_provider" value="DATIAN SUBIC SHOES INC." readonly>
                    </div>
                    <div class="uf-field" data-field-wrap="lab_location">
                        <label><span class="uf-req">*</span> Lab Location <span class="uf-sync-tag hidden">✓ Synced</span></label>
                        <input type="text" id="uf_lab_location" data-uf-field="lab_location" value="PHILIPPINES" readonly>
                    </div>
                    <div class="uf-field" data-field-wrap="application_no">
                        <label>Application No. <span class="uf-sync-tag hidden">✓ Synced</span></label>
                        <input type="text" id="uf_application_no" data-uf-field="application_no" value="A005967935" readonly>
                    </div>

                    <div class="uf-field" data-field-wrap="service_test_type">
                        <label><span class="uf-req">*</span> Service Test Type <span class="uf-sync-tag hidden">✓ Synced</span></label>
                        <textarea id="uf_service_test_type" data-uf-field="service_test_type" rows="2" readonly>In-House Lab Testing</textarea>
                    </div>
                    <div class="uf-field" data-field-wrap="test_stage">
                        <label><span class="uf-req">*</span> Test Stage <span class="uf-sync-tag hidden">✓ Synced</span></label>
                        <input type="text" id="uf_test_stage" data-uf-field="test_stage" value="Production" readonly>
                    </div>
                    <div class="uf-field" data-field-wrap="service_required">
                        <label><span class="uf-req">*</span> Service Required <span class="uf-sync-tag hidden">✓ Synced</span></label>
                        <input type="text" id="uf_service_required" data-uf-field="service_required" value="Regular" readonly>
                    </div>

                    <div class="uf-field" data-field-wrap="report_no">
                        <label><span class="uf-req">*</span> Report No. <small class="muted">(DXEditor17)</small> <span class="uf-sync-tag hidden">✓ Synced</span></label>
                        <input type="text" id="uf_report_no" data-uf-field="report_no" value="" placeholder="Click 'Sync NEXUS-L Data' to fill Report No.">
                    </div>
                    <div class="uf-field" data-field-wrap="is_retest">
                        <label><span class="uf-req">*</span> Is this a Retest? <span class="uf-sync-tag hidden">✓ Synced</span></label>
                        <div class="uf-radio-group">
                            <label class="uf-radio">
                                <input type="radio" name="uf_is_retest" value="Yes">
                                <span>Yes</span>
                            </label>
                            <label class="uf-radio">
                                <input type="radio" name="uf_is_retest" value="No" checked>
                                <span>No</span>
                            </label>
                        </div>
                    </div>
                    <div class="uf-field" data-field-wrap="previous_report_no">
                        <label>Previous Test Report No <small class="muted">(DXEditor19)</small> <span class="uf-sync-tag hidden">✓ Synced</span></label>
                        <input type="text" id="uf_previous_report_no" data-uf-field="previous_report_no" value="">
                    </div>
                </div>

                <!-- Row 4: Related TRF Number (Material [10076]) & Email Notification Recipient(s) -->
                <div class="uf-grid-2 uf-mt">
                    <div class="uf-field" data-field-wrap="email_recipients">
                        <label>Email Notification Recipient(s) <span class="uf-sync-tag hidden">✓ Synced</span></label>
                        <div class="uf-email-wrap">
                            <input type="text" id="uf_email_recipients" data-uf-field="email_recipients" value="dtp_qcasst@dtshoes.com">
                            <button type="button" class="uf-email-add" id="uf-btn-add-email" title="Add Recipient">+</button>
                        </div>
                    </div>
                    <div class="uf-field" data-field-wrap="related_trf_no">
                        <label>Related TRF Number <span class="uf-sync-tag hidden">✓ Synced</span></label>
                        <input type="text" id="uf_related_trf_no" data-uf-field="related_trf_no" value="">
                    </div>
                </div>

                <!-- SECTION 2: Vendor/Factory/Supplier Information -->
                <div class="uf-accordion uf-mt">
                    <button type="button" class="uf-acc-head" data-acc-target="uf-acc-vendor">
                        <span class="uf-acc-arrow">⌄</span>
                        <b>Vendor/Factory/Supplier Information</b>
                    </button>

                    <div class="uf-acc-body" id="uf-acc-vendor">
                        <div class="uf-grid-2">
                            <div class="uf-field" data-field-wrap="company_name">
                                <label><span class="uf-req">*</span> Company Name <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_company_name" data-uf-field="company_name" value="KEY TRADE CO., LTD">
                            </div>
                            <div class="uf-field" data-field-wrap="interlink_code">
                                <label><span class="uf-req">*</span> Interlink Code <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_interlink_code" data-uf-field="interlink_code" value="E126863">
                            </div>

                            <div class="uf-field" data-field-wrap="vendor_name">
                                <label><span class="uf-req">*</span> Vendor Name <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_vendor_name" data-uf-field="vendor_name" value="KEY TRADE CO., LTD">
                            </div>
                            <div class="uf-field" data-field-wrap="vendor_id">
                                <label><span class="uf-req">*</span> Vendor ID <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_vendor_id" data-uf-field="vendor_id" value="0000507450">
                            </div>

                            <div class="uf-field" data-field-wrap="factory_name">
                                <label><span class="uf-req">*</span> Factory Name <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_factory_name" data-uf-field="factory_name" value="DATIAN SUBIC SHOES INC.">
                            </div>
                            <div class="uf-field" data-field-wrap="factory_id">
                                <label><span class="uf-req">*</span> Factory ID <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_factory_id" data-uf-field="factory_id" value="0000507452">
                            </div>

                            <div class="uf-field" data-field-wrap="supplier_name">
                                <label>Supplier Name <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_supplier_name" data-uf-field="supplier_name" value="">
                            </div>
                            <div class="uf-field" data-field-wrap="supplier_id">
                                <label>Supplier ID <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_supplier_id" data-uf-field="supplier_id" value="">
                            </div>

                            <div class="uf-field" data-field-wrap="facility_name">
                                <label>Facility Name <small class="muted">([10076] Material)</small> <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_facility_name" data-uf-field="facility_name" value="">
                            </div>
                            <div class="uf-field" data-field-wrap="facility_id">
                                <label>Facility ID <small class="muted">([10076] Material)</small> <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_facility_id" data-uf-field="facility_id" value="">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 3A: Product Information (ALL) (Discovered from lower half of UploadForm [10072]) -->
                <div class="uf-accordion uf-mt" id="uf-sec-product-info">
                    <button type="button" class="uf-acc-head" data-acc-target="uf-acc-product">
                        <span class="uf-acc-arrow">⌄</span>
                        <b>Product Information (ALL)</b>
                    </button>

                    <div class="uf-acc-body" id="uf-acc-product">
                        <div class="uf-grid-3">
                            <div class="uf-field" data-field-wrap="sample_type">
                                <label><span class="uf-req">*</span> Sample Type <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_sample_type" data-uf-field="sample_type" value="Finished Product" readonly>
                            </div>
                            <div class="uf-field" data-field-wrap="style_no">
                                <label><span class="uf-req">*</span> Style No. <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_style_no" data-uf-field="style_no" value="VN000ZB8">
                            </div>
                            <div class="uf-field" data-field-wrap="style_color_no">
                                <label><span class="uf-req">*</span> Style Color No. (SKU No.) <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_style_color_no" data-uf-field="style_color_no" value="VN000ZB8BPR">
                            </div>

                            <div class="uf-field" data-field-wrap="product_name">
                                <label><span class="uf-req">*</span> Product Name <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_product_name" data-uf-field="product_name" value="LX Old Skool 36">
                            </div>
                            <div class="uf-field" data-field-wrap="buyer_brand">
                                <label><span class="uf-req">*</span> Brand Name <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_buyer_brand" data-uf-field="buyer_brand" value="Vans">
                            </div>
                            <div class="uf-field" data-field-wrap="color_code">
                                <label>Color Code <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_color_code" data-uf-field="color_code" value="BPR">
                            </div>

                            <div class="uf-field" data-field-wrap="colorway_name">
                                <label><span class="uf-req">*</span> Color Description <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_colorway_name" data-uf-field="colorway_name" value="Pearlized BLACK/MARSHMALLOW">
                            </div>
                            <div class="uf-field" data-field-wrap="product_export_to">
                                <label><span class="uf-req">*</span> Product Export To <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_product_export_to" data-uf-field="product_export_to" value="APAC - Non China">
                            </div>
                            <div class="uf-field" data-field-wrap="country_of_origin">
                                <label><span class="uf-req">*</span> Country of Origin <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_country_of_origin" data-uf-field="country_of_origin" value="Philippines">
                            </div>

                            <div class="uf-field" data-field-wrap="season">
                                <label><span class="uf-req">*</span> Season <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_season" data-uf-field="season" value="S27">
                            </div>
                            <div class="uf-field" data-field-wrap="sourcing_office">
                                <label><span class="uf-req">*</span> Sourcing Office <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_sourcing_office" data-uf-field="sourcing_office" value="ASO">
                            </div>
                            <div class="uf-field" data-field-wrap="size_category">
                                <label><span class="uf-req">*</span> Size Category <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_size_category" data-uf-field="size_category" value="UNISEX">
                            </div>

                            <div class="uf-field" data-field-wrap="age_group">
                                <label><span class="uf-req">*</span> Age Group <small class="muted">(DXEditor44)</small> <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_age_group" data-uf-field="age_group" value="Adult">
                            </div>
                            <div class="uf-field" data-field-wrap="cargo_receive_date">
                                <label><span class="uf-req">*</span> Cargo Receive Date <small class="muted">(DXEditor45)</small> <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_cargo_receive_date" data-uf-field="cargo_receive_date" value="06 Nov 2026">
                            </div>
                            <div class="uf-field" data-field-wrap="product_category">
                                <label><span class="uf-req">*</span> Product Category <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_product_category" data-uf-field="product_category" value="Footwear">
                            </div>

                            <div class="uf-field" data-field-wrap="test_packages">
                                <label><span class="uf-req">*</span> Test Package(s) <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_test_packages" data-uf-field="test_packages" value="VANS Pull Strength">
                            </div>
                            <div class="uf-field" data-field-wrap="care_instruction">
                                <label>Care Instruction <small class="muted">(DXEditor46)</small> <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_care_instruction" data-uf-field="care_instruction" value="">
                            </div>
                            <div class="uf-field" data-field-wrap="fiber_composition">
                                <label>Fiber Composition <small class="muted">(DXEditor48)</small> <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_fiber_composition" data-uf-field="fiber_composition" value="">
                            </div>
                        </div>

                        <!-- Material-specific fields ([10076] Material Form) -->
                        <div class="uf-grid-3 uf-mt" id="uf-material-extra-fields">
                            <div class="uf-field" data-field-wrap="material_code">
                                <label>Material Code <small class="muted">([10076])</small> <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_material_code" data-uf-field="material_code" value="">
                            </div>
                            <div class="uf-field" data-field-wrap="material_name">
                                <label>Material Description <small class="muted">([10076])</small> <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_material_name" data-uf-field="material_name" value="">
                            </div>
                            <div class="uf-field" data-field-wrap="material_type">
                                <label>Material Type <small class="muted">([10076])</small> <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_material_type" data-uf-field="material_type" value="">
                            </div>
                            <div class="uf-field" data-field-wrap="thickness">
                                <label>Thickness <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_thickness" data-uf-field="thickness" value="">
                            </div>
                            <div class="uf-field" data-field-wrap="finishing">
                                <label>Finishing <small class="muted">(DXEditor44)</small> <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_finishing" data-uf-field="finishing" value="">
                            </div>
                            <div class="uf-field" data-field-wrap="end_use">
                                <label>End Use <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_end_use" data-uf-field="end_use" value="">
                            </div>
                        </div>

                        <div class="uf-field uf-mt" data-field-wrap="lab_remarks">
                            <label>Remarks <small class="muted">(DXEditor50)</small> <span class="uf-sync-tag hidden">✓ Synced</span></label>
                            <input type="text" id="uf_lab_remarks" data-uf-field="lab_remarks" value="">
                        </div>
                    </div>
                </div>

                <!-- SECTION 3B: GCC/CPC & Factory Address Sub-Section (DXEditor52–62) -->
                <div class="uf-accordion uf-mt">
                    <button type="button" class="uf-acc-head" data-acc-target="uf-acc-gcc">
                        <span class="uf-acc-arrow">⌄</span>
                        <b>GCC/CPC &amp; Factory Address Information (DXEditor52–DXEditor62)</b>
                    </button>

                    <div class="uf-acc-body" id="uf-acc-gcc">
                        <div class="uf-grid-3">
                            <div class="uf-field" data-field-wrap="gcc_cpc_required">
                                <label>GCC/CPC Required? <small class="muted">(DXEditor52)</small> <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <select id="uf_gcc_cpc_required" data-uf-field="gcc_cpc_required">
                                    <option value="No">No</option>
                                    <option value="Yes">Yes</option>
                                </select>
                            </div>
                            <div class="uf-field" data-field-wrap="product_id">
                                <label>Product ID <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_product_id" data-uf-field="product_id" value="VN000ZB8">
                            </div>
                            <div class="uf-field" data-field-wrap="manufacture_date">
                                <label>Manufacture Date (MM/YYYY) <small class="muted">(DXEditor54)</small> <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_manufacture_date" data-uf-field="manufacture_date" value="10/2026">
                            </div>

                            <div class="uf-field" data-field-wrap="factory_address">
                                <label>Factory Address <small class="muted">(DXEditor55)</small> <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_factory_address" data-uf-field="factory_address" value="Lot 1P7-A,Phase 2,Subic Bay GatewayPark, Subic Bay Freeport Zone">
                            </div>
                            <div class="uf-field" data-field-wrap="factory_country">
                                <label>Factory Country <small class="muted">(DXEditor56)</small> <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_factory_country" data-uf-field="factory_country" value="Philippines">
                            </div>
                            <div class="uf-field" data-field-wrap="factory_city">
                                <label>Factory City <small class="muted">(DXEditor58)</small> <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_factory_city" data-uf-field="factory_city" value="Subic Bay">
                            </div>

                            <div class="uf-field" data-field-wrap="factory_state_province">
                                <label>Factory State/Province <small class="muted">(DXEditor59)</small> <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_factory_state_province" data-uf-field="factory_state_province" value="03">
                            </div>
                            <div class="uf-field" data-field-wrap="factory_zip_postal">
                                <label>Factory Zip/Postal Code <small class="muted">(DXEditor60)</small> <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_factory_zip_postal" data-uf-field="factory_zip_postal" value="2222">
                            </div>
                            <div class="uf-field" data-field-wrap="factory_email">
                                <label>Factory Email <small class="muted">(DXEditor61)</small> <span class="uf-sync-tag hidden">✓ Synced</span></label>
                                <input type="text" id="uf_factory_email" data-uf-field="factory_email" value="dtp_qcasst@dtshoes.com">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===============================================================
                 TAB 2: Upload Result (UF1$NewTabControl$aTabPage_1)
                 Includes Report Issue Date, Overall Result, PDF Upload,
                 Sample Information (JOB_DUMMY_007_SAMPLE_ITEM),
                 Test Item Result (JOB_DUMMY_008_TEST_ITEM_INP),
                 and Test Item Result For Reading (JOB_DUMMY_013)
                 =============================================================== -->
            <div id="uf-tab-result" class="uf-tab-panel hidden">
                <div class="uf-grid-3">
                    <div class="uf-field" data-field-wrap="report_no_tab2">
                        <label><span class="uf-req">*</span> Report No. <small class="muted">(DXEditor5)</small></label>
                        <input type="text" id="uf_tab2_report_no" readonly value="">
                    </div>
                    <div class="uf-field" data-field-wrap="report_issue_date">
                        <label><span class="uf-req">*</span> Report Issue Date <small class="muted">(DXEditor6_I)</small> <span class="uf-sync-tag hidden">✓ Synced</span></label>
                        <input type="text" id="uf_report_issue_date" data-uf-field="report_issue_date" value="">
                    </div>
                    <div class="uf-field" data-field-wrap="overall_result">
                        <label><span class="uf-req">*</span> Overall Result <small class="muted">(TRPT_RESULT_DISP_0)</small> <span class="uf-sync-tag hidden">✓ Synced</span></label>
                        <select id="uf_overall_result" data-uf-field="overall_result">
                            <option value="">- Select -</option>
                            <option value="Pass">Pass</option>
                            <option value="Fail">Fail</option>
                            <option value="Data">Data</option>
                        </select>
                    </div>
                </div>

                <div class="uf-grid-2 uf-mt">
                    <div class="uf-field" data-field-wrap="attachment_name">
                        <label><span class="uf-req">*</span> Report Attachment (PDF) <small class="muted">(JOB_DUMMY_004)</small> <span class="uf-sync-tag hidden">✓ Synced</span></label>
                        <div class="uf-email-wrap">
                            <input type="text" id="uf_attachment_name" data-uf-field="attachment_name" value="" placeholder="Generate from the Lab Reference or Browse for a PDF">
                            <button type="button" class="uf-action-btn uf-btn-gen" id="uf-btn-generate-pdf" style="margin:2px" title="Create the test report PDF from the synced Lab Reference (Digital Test Report)">Generate Report</button>
                            <button type="button" class="uf-action-btn" id="uf-btn-browse-pdf" style="margin:2px">Browse...</button>
                            <input type="file" id="uf-file-pdf" accept="application/pdf,.pdf" hidden>
                        </div>
                        <div class="uf-att-meta hidden" id="uf-att-meta">
                            <span id="uf-att-info"></span>
                            <button type="button" class="uf-att-link" id="uf-btn-view-pdf">View</button>
                            <button type="button" class="uf-att-link" id="uf-btn-save-pdf">Download</button>
                        </div>
                        <div class="uf-namefmt">
                            <span>File name format:</span> <b id="uf-namefmt-cur">{app}</b>
                            <button type="button" class="uf-att-link" id="uf-namefmt-edit">Change</button>
                            <span class="muted" id="uf-namefmt-ex"></span>
                        </div>
                        <!-- sign-off: a registered Manager / Supervisor approves the report before it can go to InterLink -->
                        <div class="uf-apv hidden" id="uf-apv">
                            <div class="uf-apv-line"><span class="uf-apv-dot" id="uf-apv-dot"></span><span id="uf-apv-status">Needs approval before it can be sent to InterLink.</span></div>
                            <button type="button" class="uf-action-btn uf-btn-gen" id="uf-apv-request">Request approval (QR)</button>
                            <div class="uf-apv-qr-wrap hidden" id="uf-apv-qr-wrap">
                                <div class="uf-apv-qr" id="uf-apv-qr" aria-label="QR code of the approval link"></div>
                                <div class="uf-apv-qr-txt">
                                    <b>Manager / Supervisor:</b> scan, sign in, check the report and sign.
                                    <a id="uf-apv-link" href="#" target="_blank" rel="noopener">Open approval link</a>
                                    <button type="button" class="uf-att-link" id="uf-apv-copy">Copy link</button>
                                    <button type="button" class="uf-att-link" id="uf-apv-new">New link</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="uf-field" data-field-wrap="supplementary_attachment">
                        <label>Supplementary Attachment <small class="muted">(JOB_DUMMY_005)</small></label>
                        <input type="text" id="uf_supplementary_attachment" data-uf-field="supplementary_attachment" placeholder="Optional supplementary file">
                    </div>
                </div>

                <!-- Sub-Grid 1: Sample Information (JOB_DUMMY_007_SAMPLE_ITEM) -->
                <div class="uf-accordion uf-mt">
                    <div class="uf-subgrid-head">
                        <b>Sample Information <small class="muted">(JOB_DUMMY_007_SAMPLE_ITEM)</small></b>
                        <span class="uf-sync-tag" id="uf-samples-badge">Synced from NEXUS-L</span>
                    </div>
                    <table class="il-sync-table uf-subtable">
                        <thead>
                            <tr>
                                <th style="width:120px">Sample ID (FLD01)</th>
                                <th>* Sample Description (FLD24)</th>
                                <th style="width:220px">Sample Color (FLD25)</th>
                            </tr>
                        </thead>
                        <tbody id="uf-sample-items-tbody">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>

                <!-- Sub-Grid 2: Test Item Result (JOB_DUMMY_008_TEST_ITEM_INP) -->
                <div class="uf-accordion uf-mt">
                    <div class="uf-subgrid-head">
                        <b>Test Item Result <small class="muted">(JOB_DUMMY_008_TEST_ITEM_INP)</small></b>
                        <span class="uf-sync-tag">Pass / Fail / Data</span>
                    </div>
                    <table class="il-sync-table uf-subtable">
                        <thead>
                            <tr>
                                <th style="width:110px">* Sample ID (FLD03)</th>
                                <th>* Test Item Name (FLD07)</th>
                                <th style="width:180px">Standard / Method</th>
                                <th style="width:210px">Actual Summary</th>
                                <th style="width:130px">* Test Item Result (FLD15)</th>
                            </tr>
                        </thead>
                        <tbody id="uf-test-items-tbody">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>

                <!-- Sub-Grid 3: Test Item Result For Reading (JOB_DUMMY_013) -->
                <div class="uf-accordion uf-mt">
                    <div class="uf-subgrid-head">
                        <b>Test Item Result For Reading <small class="muted">(JOB_DUMMY_013)</small></b>
                        <span class="uf-sync-tag">Specimen Readings</span>
                    </div>
                    <table class="il-sync-table uf-subtable">
                        <thead>
                            <tr>
                                <th style="width:90px">* Sample ID</th>
                                <th>* Test Item Name</th>
                                <th>Test method</th>
                                <th>Sample Component</th>
                                <th>Test element</th>
                                <th style="width:65px">Ind.</th>
                                <th style="width:95px">* Test value</th>
                                <th style="width:75px">UOM</th>
                                <th style="width:110px">Requirement</th>
                                <th style="width:95px">Test rating</th>
                            </tr>
                        </thead>
                        <tbody id="uf-test-readings-tbody">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Bottom Form Action Bar (Save / Submit 1-Click / New / < Previous / Next >) -->
            <div class="uf-bottom-bar">
                <div class="uf-bottom-left">
                    <button type="button" id="uf-btn-save" class="uf-action-btn">Save</button>
                    <button type="button" id="uf-btn-submit-live" class="il-send-cta"><svg class="ic" aria-hidden="true"><use href="#i-bolt"/></svg> Submit &amp; Send Report to InterLink</button>
                    <button type="button" id="uf-btn-new" class="uf-action-btn">New</button>
                </div>
                <div class="uf-bottom-right">
                    <button type="button" id="uf-btn-prev" class="uf-action-btn" disabled>&lt; Previous</button>
                    <button type="button" id="uf-btn-next" class="uf-action-btn">Next &gt;</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================================================================
         VIEWPORT 3: InterLink 2.0 NormalForm.aspx [10000]
         (Online eApplication Form opened when clicking 'E' button)
         =================================================================== -->
    <div id="il-view-normal" class="il-portal-viewport hidden">
        <div class="uf-sync-banner">
            <div class="uf-sync-banner-left">
                <span class="uf-detect-pill" style="background:#1d4ed8">NormalForm.aspx [10000] (Online Application Form)</span>
                <span>Viewing eApplication Form for <b id="nf-banner-appno">A005967935</b></span>
            </div>
            <div class="uf-sync-banner-right">
                <button type="button" id="btn-nf-back-search" class="uf-btn-secondary">← Back to Search Grid</button>
                <button type="button" id="btn-nf-open-upload" class="uf-btn-sync-now">Open UploadForm.aspx (U) →</button>
            </div>
        </div>

        <div class="uf-page" style="padding:18px 22px">
            <h3 style="margin:0 0 14px;color:#111827">VF Corporation Online Application Form [10000]</h3>
            <div class="uf-grid-3">
                <div class="uf-field"><label>Application No.</label><input type="text" id="nf_application_no" readonly></div>
                <div class="uf-field"><label>Applicant Company</label><input type="text" id="nf_company_name" readonly></div>
                <div class="uf-field"><label>Interlink Code</label><input type="text" id="nf_interlink_code" readonly></div>
                <div class="uf-field"><label>Applicant Address</label><input type="text" id="nf_applicant_address" readonly></div>
                <div class="uf-field"><label>Contact Person</label><input type="text" id="nf_contact_person" readonly></div>
                <div class="uf-field"><label>Contact Email / Phone</label><input type="text" id="nf_contact_email" readonly></div>
                <div class="uf-field"><label>Service Provider</label><input type="text" id="nf_service_provider" readonly></div>
                <div class="uf-field"><label>Service Test Type</label><input type="text" id="nf_service_test_type" readonly></div>
                <div class="uf-field"><label>Test Package(s)</label><input type="text" id="nf_test_packages" readonly></div>
                <div class="uf-field"><label>Style No. / SKU No.</label><input type="text" id="nf_style_sku" readonly></div>
                <div class="uf-field"><label>Product / Material Name</label><input type="text" id="nf_product_name" readonly></div>
                <div class="uf-field"><label>Color Description</label><input type="text" id="nf_colorway_name" readonly></div>
            </div>
        </div>
    </div>
</div>

<!-- =======================================================================
     PRE-SYNC CONFIRMATION DIALOG (Readonly Application No + LabRefNo ComboBox)
     ======================================================================= -->
<div id="sync-dialog-backdrop" class="il-modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="sync-dialog-title">
    <div class="il-modal" style="max-width:1080px">
        <div class="il-modal-head">
            <div>
                <span class="il-modal-eyebrow">NEXUS-L → InterLink 2.0 Pre-Sync Dialog</span>
                <h3 id="sync-dialog-title">Select Lab Reference &amp; Confirm Data to Sync</h3>
                <p class="muted sm" style="margin:2px 0 0">Select a <b>Lab Reference</b> from NEXUS-L (<b><?= number_format((int) ($totalLabRefs ?? 4568)) ?></b> lab reports) to automatically populate all InterLink form fields, test items, and readings.</p>
            </div>
            <button type="button" id="btn-close-sync-dialog" class="il-modal-close" aria-label="Close">&times;</button>
        </div>

        <!-- Step 1: Readonly Selected Application No. + LabRefNo ComboBox Selector -->
        <div class="il-modal-toolbar" style="display:block;padding:14px 20px;background:rgba(15,23,42,0.03);border-bottom:1px solid var(--border)">
            <div style="display:grid;grid-template-columns:220px 210px 220px 1fr;gap:12px;align-items:end">
                <div class="uf-field" style="margin:0">
                    <label style="font-weight:700;color:#1e40af;font-size:11px;text-transform:uppercase;letter-spacing:.04em">Selected Application No. (Readonly)</label>
                    <input type="text" id="dlg-readonly-appno" readonly style="background:#eff6ff;border:1.5px solid #93c5fd;color:#1e3a8a;font-weight:800;font-family:monospace;font-size:14px;padding:7px 10px;border-radius:6px" value="">
                </div>
                <div class="uf-field" style="margin:0">
                    <label style="font-weight:600;font-size:11px">Filter Lab Report Category</label>
                    <select id="dlg-labref-category" class="il-select" style="width:100%;padding:7px 10px">
                        <option value="all">All Categories (<?= number_format((int) ($totalLabRefs ?? 4568)) ?>)</option>
                        <option value="fabric">Fabric (FBU / FBL)</option>
                        <option value="leather">Leather (LEU / LEL)</option>
                        <option value="wst">Webbing / Shoelace / Thread</option>
                        <option value="fthl">Foxing Tape &amp; Heel Logo</option>
                        <option value="hardware">Hardware (EY / GR / ZP)</option>
                        <option value="label">Woven / Size / Transfer Label</option>
                        <option value="sklfm">Sockliner &amp; Foam (SKL / FM)</option>
                        <option value="rubber">Rubber Outsole (RB)</option>
                        <option value="box">Carton Box (IB / OB)</option>
                        <option value="insole">Insole Board (SL)</option>
                        <option value="bonding">Whole Shoe Bonding (U60)</option>
                    </select>
                </div>
                <div class="uf-field" style="margin:0">
                    <label style="font-weight:600;font-size:11px">Quick Search LabRef / Style / MatCode</label>
                    <input type="text" id="dlg-labref-search" placeholder="Type LabRefNo, TR, Style, Material..." style="width:100%;padding:7px 10px;border:1px solid var(--border);border-radius:6px;font-size:12.5px">
                </div>
                <div class="uf-field" style="margin:0">
                    <label for="dlg-labref-combo" style="font-weight:700;color:#047857;font-size:11px;text-transform:uppercase;letter-spacing:.04em">* Select Lab Reference from NEXUS-L</label>
                    <select id="dlg-labref-combo" class="il-select" style="width:100%;padding:7px 10px;border:1.5px solid #10b981;background:#ecfdf5;font-weight:700;color:#065f46;font-size:13px"></select>
                </div>
            </div>

            <!-- Selected Lab Reference Details Summary & Editable Overrides -->
            <div id="dlg-labref-summary" style="margin-top:12px;padding:12px 14px;background:#fff;border:1px solid #cbd5e1;border-radius:8px;box-shadow:0 1px 2px rgba(0,0,0,0.03)">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                    <div style="display:flex;align-items:center;gap:8px">
                        <span class="badge b-ok" id="dlg-lr-cat-badge">Digital Test Report</span>
                        <b id="dlg-lr-title" style="font-size:13px;color:#0f172a">Select a LabReference above to load report details</b>
                    </div>
                    <div style="display:flex;align-items:center;gap:12px">
                        <span class="muted sm" id="dlg-lr-tests-count">0 Test Items · 0 Readings</span>
                        <label class="il-checkbox-label" style="margin:0">
                            <input type="checkbox" id="sync-check-all" checked>
                            <span>Select All Fields (<b id="sync-selected-count">32</b>)</span>
                        </label>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:repeat(6, 1fr);gap:8px;font-size:12px">
                    <div class="uf-field" style="margin:0">
                        <label style="font-size:10.5px;color:#64748b">Lab Reference No.</label>
                        <input type="text" id="dlg-lr-labref" style="padding:4px 7px;font-size:12px;font-weight:700;font-family:monospace" placeholder="LabRefNo">
                    </div>
                    <div class="uf-field" style="margin:0">
                        <label style="font-size:10.5px;color:#64748b">Report / TR No.</label>
                        <input type="text" id="dlg-lr-trno" style="padding:4px 7px;font-size:12px;font-weight:700;font-family:monospace" placeholder="TR / ETRF No.">
                    </div>
                    <div class="uf-field" style="margin:0">
                        <label style="font-size:10.5px;color:#64748b">Overall Result</label>
                        <select id="dlg-lr-result" style="padding:4px 7px;font-size:12px;font-weight:700">
                            <option value="Pass">Pass</option>
                            <option value="Fail">Fail</option>
                            <option value="Data">Data (Tolerable)</option>
                        </select>
                    </div>
                    <div class="uf-field" style="margin:0">
                        <label style="font-size:10.5px;color:#64748b">Material Type</label>
                        <input type="text" id="dlg-lr-mattype" style="padding:4px 7px;font-size:12px" placeholder="Material Type">
                    </div>
                    <div class="uf-field" style="margin:0">
                        <label style="font-size:10.5px;color:#64748b">Material Code (VF)</label>
                        <input type="text" id="dlg-lr-matcode" style="padding:4px 7px;font-size:12px;font-family:monospace" placeholder="Material Code">
                    </div>
                    <div class="uf-field" style="margin:0">
                        <label style="font-size:10.5px;color:#64748b">MTL # (DSR)</label>
                        <input type="text" id="dlg-lr-mtl" style="padding:4px 7px;font-size:12px;font-family:monospace" placeholder="MTL No.">
                    </div>
                    <div class="uf-field" style="margin:0;grid-column:span 2">
                        <label style="font-size:10.5px;color:#64748b">Material / Product Description</label>
                        <input type="text" id="dlg-lr-matdesc" style="padding:4px 7px;font-size:12px" placeholder="Material Description">
                    </div>
                    <div class="uf-field" style="margin:0">
                        <label style="font-size:10.5px;color:#64748b">Supplier / Factory</label>
                        <input type="text" id="dlg-lr-supplier" style="padding:4px 7px;font-size:12px" placeholder="Supplier Name">
                    </div>
                    <div class="uf-field" style="margin:0">
                        <label style="font-size:10.5px;color:#64748b">Style / Model No.</label>
                        <input type="text" id="dlg-lr-style" style="padding:4px 7px;font-size:12px" placeholder="Style No.">
                    </div>
                    <div class="uf-field" style="margin:0">
                        <label style="font-size:10.5px;color:#64748b">Color / Colorway</label>
                        <input type="text" id="dlg-lr-color" style="padding:4px 7px;font-size:12px" placeholder="Color">
                    </div>
                    <div class="uf-field" style="margin:0">
                        <label style="font-size:10.5px;color:#64748b">PO/Lot No. &amp; Tech</label>
                        <input type="text" id="dlg-lr-polot" style="padding:4px 7px;font-size:12px" placeholder="PO / Lot No.">
                    </div>
                </div>
            </div>
            <!-- Keep hidden legacy select for backward compatibility -->
            <select id="sync-source-select" class="hidden"></select>
        </div>

        <div class="il-modal-body" style="max-height:420px;overflow-y:auto">
            <!-- Extracted Test Items from Selected LabRefNo -->
            <div id="dlg-labref-tests-wrap" style="margin-bottom:14px">
                <div style="font-weight:700;font-size:12px;color:#0f172a;margin-bottom:6px;display:flex;justify-content:space-between;align-items:center">
                    <span><svg class="ic" aria-hidden="true"><use href="#i-flask"/></svg> Test Item Results &amp; Readings from Selected LabRefNo (Auto-fills Tab 2 Upload Result)</span>
                    <a href="<?= site_url('dtr') ?>" target="_blank" style="font-size:11.5px;color:#2563eb;text-decoration:none;font-weight:600">Open in QC Lab Digital Test Reports ↗</a>
                </div>
                <table class="il-sync-table" style="margin-bottom:10px">
                    <thead>
                        <tr>
                            <th style="width:120px">Test Code</th>
                            <th>Test Item / Property</th>
                            <th style="width:130px">Test Method</th>
                            <th style="width:130px">Standard Spec</th>
                            <th style="width:140px">Actual Result</th>
                            <th style="width:90px">Rating</th>
                        </tr>
                    </thead>
                    <tbody id="dlg-labref-tests-tbody">
                        <tr><td colspan="6" class="muted" style="text-align:center;padding:10px">Select a Lab Reference above to load test item results.</td></tr>
                    </tbody>
                </table>
            </div>

            <div style="font-weight:700;font-size:12px;color:#0f172a;margin-bottom:6px"><svg class="ic" aria-hidden="true"><use href="#i-clipboard"/></svg> Complete InterLink Form Controls Mapping (Tab 1 Application/Sample + Tab 2 Upload Result)</div>
            <table class="il-sync-table">
                <thead>
                    <tr>
                        <th style="width:38px">Sync</th>
                        <th style="width:230px">InterLink Form Field &amp; Control</th>
                        <th style="width:170px">Current Form Value</th>
                        <th>NEXUS-L / LabRef Value to Fill (Editable)</th>
                    </tr>
                </thead>
                <tbody id="sync-fields-tbody">
                    <!-- Populated dynamically when opening dialog -->
                </tbody>
            </table>
        </div>

        <div class="il-modal-foot">
            <label class="il-checkbox-label">
                <input type="checkbox" id="sync-run-playwright" checked>
                <span>Update <b>NEXUS-L</b> &amp; Queue Live InterLink Session Sync</span>
            </label>
            <div class="il-modal-actions">
                <button type="button" id="btn-cancel-sync" class="il-btn il-btn-outline">Cancel</button>
                <button type="button" id="btn-confirm-sync" class="il-btn il-btn-primary">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                    Confirm &amp; Fill Form
                </button>
                <button type="button" id="btn-confirm-and-send-live" class="il-send-cta">
                    <svg class="ic" aria-hidden="true"><use href="#i-bolt"/></svg> Confirm, Fill &amp; 1-Click Send Report
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =======================================================================
     COMPANY REPORTS MANAGER MODAL (Add / Edit NEXUS-L Lab Report Values)
     ======================================================================= -->
<div id="manage-reports-backdrop" class="il-modal-backdrop hidden" role="dialog" aria-modal="true">
    <div class="il-modal" style="max-width:940px">
        <div class="il-modal-head">
            <div>
                <span class="il-modal-eyebrow">NEXUS-L Internal Lab Records</span>
                <h3>Manage Company Report Data</h3>
                <p class="muted sm" style="margin:2px 0 0">Add or edit company side test report values ready to sync with InterLink 2.0.</p>
            </div>
            <button type="button" id="btn-close-manage-modal" class="il-modal-close">&times;</button>
        </div>

        <div class="il-modal-body">
            <form id="company-report-form" class="il-company-form">
                <div class="uf-grid-3">
                    <div class="uf-field">
                        <label>* Application No.</label>
                        <input type="text" name="application_no" id="cr_application_no" required placeholder="e.g. A005967935">
                    </div>
                    <div class="uf-field">
                        <label>* NEXUS-L Report No.</label>
                        <input type="text" name="report_no" id="cr_report_no" required placeholder="e.g. DTP-LAB-2026-7935">
                    </div>
                    <div class="uf-field">
                        <label>Sample Type</label>
                        <select name="sample_type" id="cr_sample_type">
                            <option value="Finished Product">Finished Product [10072]</option>
                            <option value="Material">Material [10076]</option>
                        </select>
                    </div>
                    <div class="uf-field">
                        <label>Style No.</label>
                        <input type="text" name="style_no" id="cr_style_no" placeholder="e.g. VN000ZB8">
                    </div>
                    <div class="uf-field">
                        <label>Style Color No. (SKU No.)</label>
                        <input type="text" name="style_color_no" id="cr_style_color_no" placeholder="e.g. VN000ZB8BPR">
                    </div>
                    <div class="uf-field">
                        <label>Product Name</label>
                        <input type="text" name="product_name" id="cr_product_name" placeholder="e.g. LX Old Skool 36">
                    </div>
                    <div class="uf-field">
                        <label>Color Code</label>
                        <input type="text" name="color_code" id="cr_color_code" placeholder="e.g. BPR">
                    </div>
                    <div class="uf-field">
                        <label>Color Description</label>
                        <input type="text" name="colorway_name" id="cr_colorway_name" placeholder="e.g. Pearlized BLACK/MARSHMALLOW">
                    </div>
                    <div class="uf-field">
                        <label>Test Package(s)</label>
                        <input type="text" name="test_packages" id="cr_test_packages" value="VANS Pull Strength">
                    </div>
                    <div class="uf-field">
                        <label>Season</label>
                        <input type="text" name="season" id="cr_season" value="S27">
                    </div>
                    <div class="uf-field">
                        <label>Sourcing Office</label>
                        <input type="text" name="sourcing_office" id="cr_sourcing_office" value="ASO">
                    </div>
                    <div class="uf-field">
                        <label>Cargo Receive Date</label>
                        <input type="text" name="cargo_receive_date" id="cr_cargo_receive_date" value="06 Nov 2026">
                    </div>
                    <div class="uf-field">
                        <label>Material Code ([10076])</label>
                        <input type="text" name="material_code" id="cr_material_code" placeholder="e.g. 108850">
                    </div>
                    <div class="uf-field">
                        <label>Material Description ([10076])</label>
                        <input type="text" name="material_name" id="cr_material_name" placeholder="Optional">
                    </div>
                    <div class="uf-field">
                        <label>Email Notification Recipient(s)</label>
                        <input type="text" name="email_recipients" id="cr_email_recipients" value="dtp_qcasst@dtshoes.com">
                    </div>
                    <div class="uf-field">
                        <label>Company Name</label>
                        <input type="text" name="company_name" id="cr_company_name" value="KEY TRADE CO., LTD">
                    </div>
                    <div class="uf-field">
                        <label>Vendor ID</label>
                        <input type="text" name="vendor_id" id="cr_vendor_id" value="0000507450">
                    </div>
                    <div class="uf-field">
                        <label>Factory Name</label>
                        <input type="text" name="factory_name" id="cr_factory_name" value="DATIAN SUBIC SHOES INC.">
                    </div>
                    <div class="uf-field">
                        <label>Factory ID</label>
                        <input type="text" name="factory_id" id="cr_factory_id" value="0000507452">
                    </div>
                    <div class="uf-field">
                        <label>Overall Result (Tab 2)</label>
                        <select name="overall_result" id="cr_overall_result">
                            <option value="Pass">Pass</option>
                            <option value="Fail">Fail</option>
                            <option value="Data">Data</option>
                        </select>
                    </div>
                    <div class="uf-field">
                        <label>Report Issue Date (Tab 2)</label>
                        <input type="text" name="report_issue_date" id="cr_report_issue_date" value="<?= date('d M Y') ?>">
                    </div>
                </div>
                <div class="il-form-save-row">
                    <button type="submit" class="il-btn il-btn-primary">Save Company Report Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =======================================================================
     SYNC HISTORY LOG MODAL
     ======================================================================= -->
<div id="sync-logs-backdrop" class="il-modal-backdrop hidden" role="dialog" aria-modal="true">
    <div class="il-modal">
        <div class="il-modal-head">
            <div>
                <span class="il-modal-eyebrow">Audit Trail</span>
                <h3>InterLink 2.0 Sync &amp; 1-Click Send History</h3>
            </div>
            <button type="button" id="btn-close-logs-modal" class="il-modal-close">&times;</button>
        </div>
        <div class="il-modal-body">
            <table class="il-sync-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Application No.</th>
                        <th>Report No.</th>
                        <th>Technician</th>
                        <th>Mode</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="sync-logs-tbody">
                    <?php foreach ($logs as $lg): ?>
                        <tr>
                            <td><?= esc($lg['created_at'] ?? '') ?></td>
                            <td><b><?= esc($lg['application_no'] ?? '') ?></b></td>
                            <td class="mono"><?= esc($lg['report_no'] ?? '') ?></td>
                            <td><?= esc($lg['user_name'] ?? '') ?></td>
                            <td><span class="badge b-ok"><?= esc($lg['sync_mode'] ?? 'portal_sync') ?></span></td>
                            <td><span class="badge b-ok"><?= esc($lg['status'] ?? 'success') ?></span></td>
                        </tr>
                    <?php endforeach ?>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="6" class="muted" style="text-align:center;padding:18px">No sync history yet. Open a report and click "Sync NEXUS-L Data".</td></tr>
                    <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Toast Notification Container -->
<div id="il-toast" class="il-toast hidden"></div>

<script id="il-initial-data" type="application/json">
<?= json_encode([
    'reports'      => $reports,
    'logs'         => $logs,
    'labrefs'      => $initialLabRefs ?? [],
    'totalLabRefs' => $totalLabRefs ?? 0,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
