
<!-- =======================================================================
     POPUP MODAL: RESULT EVALUATION & FINDINGS DIALOG (AppSheet Parity)
     ======================================================================= -->
<div id="wst-eval-modal-backdrop" class="dtr-modal-backdrop hidden" style="z-index:10080">
    <div class="dtr-modal-dialog wst-theme-modal" style="max-width:720px;width:95%">
        <div class="dtr-modal-header" style="padding:16px 22px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center">
            <div style="display:flex;align-items:center;gap:10px">
                <span class="dtr-pill dtr-pill-blue" style="font-weight:700">RESULT INSPECTION</span>
                <h3 style="margin:0;font-size:16px;font-weight:700;color:#0f172a" id="wst-eval-title">Whole Shoe Test Findings &amp; Outcome</h3>
            </div>
            <button type="button" id="btn-wst-close-eval-modal" class="dtr-modal-close" style="font-size:22px">&times;</button>
        </div>

        <div class="dtr-modal-body" style="padding:22px;max-height:calc(85vh - 120px);overflow-y:auto">
            <!-- Inspection Tabs Header (Setup / Result / Other) -->
            <div style="display:flex;border-bottom:2px solid #e2e8f0;margin-bottom:20px;gap:20px">
                <span style="padding:8px 12px;font-weight:700;color:#2563eb;border-bottom:2px solid #2563eb;margin-bottom:-2px;cursor:pointer">Result Evaluation</span>
            </div>

            <!-- Testing End Time -->
            <div class="dtr-field" style="margin-bottom:18px">
                <label style="font-size:12px;font-weight:700;color:#475569;margin-bottom:6px;display:block">Testing End Time</label>
                <input type="text" id="wst-eval-end-time" class="dtr-input" readonly style="background:#f8fafc;font-weight:600;color:#0f172a">
            </div>

            <!-- 1. Delamination Options -->
            <div class="wst-eval-group" style="margin-bottom:18px">
                <label style="font-size:12px;font-weight:700;color:#475569;margin-bottom:8px;display:block">Delamination</label>
                <div class="wst-btn-pill-group" data-eval-field="delamination" style="display:flex;flex-direction:column;gap:6px">
                    <button type="button" class="wst-choice-btn active" data-val="No delamination">No delamination</button>
                    <button type="button" class="wst-choice-btn" data-val="Slight delamination">Slight delamination</button>
                    <button type="button" class="wst-choice-btn" data-val="Severe delamination">Severe delamination</button>
                </div>
            </div>

            <!-- 2. Bleeding Options -->
            <div class="wst-eval-group" style="margin-bottom:18px">
                <label style="font-size:12px;font-weight:700;color:#475569;margin-bottom:8px;display:block">Bleeding</label>
                <div class="wst-btn-pill-group" data-eval-field="bleeding" style="display:flex;flex-direction:column;gap:6px">
                    <button type="button" class="wst-choice-btn active" data-val="No bleeding">No bleeding</button>
                    <button type="button" class="wst-choice-btn" data-val="Slight bleeding">Slight bleeding</button>
                    <button type="button" class="wst-choice-btn" data-val="Severe bleeding">Severe bleeding</button>
                </div>
            </div>

            <!-- 3. Color Change Options -->
            <div class="wst-eval-group" style="margin-bottom:18px">
                <label style="font-size:12px;font-weight:700;color:#475569;margin-bottom:8px;display:block">Color Change</label>
                <div class="wst-btn-pill-group" data-eval-field="color_change" style="display:flex;flex-direction:column;gap:6px">
                    <button type="button" class="wst-choice-btn active" data-val="No color change">No color change</button>
                    <button type="button" class="wst-choice-btn" data-val="Slight color change">Slight color change</button>
                    <button type="button" class="wst-choice-btn" data-val="Severe color change">Severe color change</button>
                    <button type="button" class="wst-choice-btn" data-val="With color change">With color change</button>
                </div>
            </div>

            <!-- 4. Outsole Warping Options -->
            <div class="wst-eval-group" style="margin-bottom:18px">
                <label style="font-size:12px;font-weight:700;color:#475569;margin-bottom:8px;display:block">Outsole Warping</label>
                <div class="wst-btn-pill-group" data-eval-field="outsole_warping" style="display:flex;flex-direction:column;gap:6px">
                    <button type="button" class="wst-choice-btn active" data-val="No Outsole warping">No Outsole warping</button>
                    <button type="button" class="wst-choice-btn" data-val="With Outsole warping">With Outsole warping</button>
                </div>
            </div>

            <!-- 5. Blooming Options -->
            <div class="wst-eval-group" style="margin-bottom:18px">
                <label style="font-size:12px;font-weight:700;color:#475569;margin-bottom:8px;display:block">Blooming</label>
                <div class="wst-btn-pill-group" data-eval-field="blooming" style="display:flex;flex-direction:column;gap:6px">
                    <button type="button" class="wst-choice-btn active" data-val="No blooming">No blooming</button>
                    <button type="button" class="wst-choice-btn" data-val="Slight blooming">Slight blooming</button>
                    <button type="button" class="wst-choice-btn" data-val="Severe blooming">Severe blooming</button>
                </div>
            </div>

            <!-- 6. Other Findings Textarea -->
            <div class="dtr-field" style="margin-bottom:22px">
                <label style="font-size:12px;font-weight:700;color:#475569;margin-bottom:6px;display:block">Other Findings</label>
                <textarea class="dtr-input" id="wst-eval-other-findings" rows="3" placeholder="Enter other remarks or observations (e.g. slight sole yellowing, glue bead intact)..."></textarea>
            </div>

            <!-- 7. Result / Status (PASSED, TOLERABLE, FAILED) -->
            <div class="wst-eval-group" style="margin-bottom:10px;padding-top:16px;border-top:1px solid #e2e8f0">
                <label style="font-size:13px;font-weight:800;text-transform:uppercase;color:#0f172a;letter-spacing:0.04em;margin-bottom:8px;display:block">Final Result / Status</label>
                <div class="wst-outcome-pill-group" style="display:flex;flex-direction:column;gap:8px">
                    <button type="button" class="wst-outcome-btn active" data-outcome="PASS" style="border:2px solid #22c55e;color:#15803d;font-weight:700;font-size:14px;padding:12px;border-radius:8px;background:#f0fdf4;text-align:center;cursor:pointer">PASSED</button>
                    <button type="button" class="wst-outcome-btn" data-outcome="TOLERABLE" style="border:2px solid #f59e0b;color:#b45309;font-weight:700;font-size:14px;padding:12px;border-radius:8px;background:#fffbeb;text-align:center;cursor:pointer">TOLERABLE</button>
                    <button type="button" class="wst-outcome-btn" data-outcome="FAIL" style="border:2px solid #ef4444;color:#b91c1c;font-weight:700;font-size:14px;padding:12px;border-radius:8px;background:#fef2f2;text-align:center;cursor:pointer">FAILED</button>
                </div>
            </div>
        </div>

        <div class="dtr-modal-footer" style="padding:14px 22px;background:#f8fafc;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:12px">
            <button type="button" class="dtr-btn wst-btn-secondary" id="btn-wst-cancel-eval-modal" style="padding:8px 20px">Cancel</button>
            <button type="button" class="dtr-btn dtr-btn-primary" id="btn-wst-save-eval" style="padding:8px 24px;font-weight:700;background:#16a34a;border-color:#16a34a">
                <svg class="ic" aria-hidden="true"><use href="#i-check"/></svg> Save Evaluation &amp; Complete
            </button>
        </div>
    </div>
</div>

<div id="dtr-toast" class="dtr-toast hidden"></div>
