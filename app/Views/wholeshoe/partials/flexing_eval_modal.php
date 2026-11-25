<!-- Toast Notification -->
<!-- =======================================================================
     POPUP MODAL: FLEXING RESULT EVALUATION (AppSheet Parity for Whole Shoe Flexing)
     ======================================================================= -->
<div id="wst-flex-eval-modal-backdrop" class="dtr-modal-backdrop hidden" style="z-index:10085">
    <div class="dtr-modal-dialog wst-theme-modal" style="max-width:680px;width:94%">
        <div class="dtr-modal-header" style="padding:16px 22px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center">
            <div style="display:flex;align-items:center;gap:10px">
                <span class="dtr-pill dtr-pill-blue" style="font-weight:700">FLEXING INSPECTION</span>
                <h3 style="margin:0;font-size:16px;font-weight:700;color:#0f172a" id="wst-flex-eval-title">Whole Shoe Flexing Test Result</h3>
            </div>
            <button type="button" id="btn-wst-close-flex-eval" class="dtr-modal-close" style="font-size:22px">&times;</button>
        </div>

        <div class="dtr-modal-body" style="padding:22px;max-height:calc(85vh - 120px);overflow-y:auto">
            <!-- Inspection Tabs Header (Setup / Test Result / Misc.) -->
            <div style="display:flex;border-bottom:2px solid #e2e8f0;margin-bottom:20px;gap:20px">
                <span style="padding:8px 12px;font-weight:700;color:#2563eb;border-bottom:2px solid #2563eb;margin-bottom:-2px;cursor:pointer">Test Result</span>
            </div>

            <!-- Cycle Checkpoint Selection -->
            <div class="dtr-field" style="margin-bottom:16px">
                <label style="font-size:12px;font-weight:700;color:#475569;margin-bottom:6px;display:block">Checkpoint Cycle</label>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                    <span id="wst-flex-auto-badge" class="dtr-pill dtr-pill-blue" style="font-size:11px;font-weight:700">Auto-Detected Checkpoint</span>
                </div>
                <input type="hidden" id="wst-flex-eval-cycle" value="5000">
                <div id="wst-flex-eval-cycle-display" class="dtr-input mono" style="background:#f1f5f9;font-weight:700;color:#1e40af;cursor:not-allowed;padding:9px 12px">
                    5,000 Cycles
                </div>
            </div>

            <!-- 1. Length of Delamination: Lateral Side (L) -->
            <div class="dtr-field" style="margin-bottom:16px">
                <label style="font-size:12px;font-weight:700;color:#475569;margin-bottom:6px;display:block">Length of Delamination: Lateral Side (L)</label>
                <input type="number" id="wst-flex-lat-len" class="dtr-input" min="0" step="0.1" value="0">
            </div>

            <!-- 2. Length of Delamination: Medial Side (M) -->
            <div class="dtr-field" style="margin-bottom:16px">
                <label style="font-size:12px;font-weight:700;color:#475569;margin-bottom:6px;display:block">Length of Delamination: Medial Side (M)</label>
                <input type="number" id="wst-flex-med-len" class="dtr-input" min="0" step="0.1" value="0">
            </div>

            <!-- 3. Depth of Delamination: Lateral Side (D) -->
            <div class="dtr-field" style="margin-bottom:16px">
                <label style="font-size:12px;font-weight:700;color:#475569;margin-bottom:6px;display:block">Depth of Delamination: Lateral Side (D)</label>
                <input type="number" id="wst-flex-lat-depth" class="dtr-input" min="0" step="0.1" value="0">
            </div>

            <!-- 4. Depth of Delamination: Medial Side (D) -->
            <div class="dtr-field" style="margin-bottom:18px">
                <label style="font-size:12px;font-weight:700;color:#475569;margin-bottom:6px;display:block">Depth of Delamination: Medial Side (D)</label>
                <input type="number" id="wst-flex-med-depth" class="dtr-input" min="0" step="0.1" value="0">
            </div>

            <!-- 5. Flexing Result (Pending, PASSED, FAILED) -->
            <div class="wst-eval-group" style="margin-bottom:18px">
                <label style="font-size:12px;font-weight:700;color:#475569;margin-bottom:8px;display:block">Flexing Result</label>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
                    <button type="button" class="wst-flex-result-btn" data-flex-result="Pending" style="border:1.5px solid #cbd5e1;padding:10px;border-radius:6px;font-weight:700;background:#ffffff;color:#475569;cursor:pointer">Pending</button>
                    <button type="button" class="wst-flex-result-btn active" data-flex-result="PASSED" style="border:1.5px solid #22c55e;padding:10px;border-radius:6px;font-weight:700;background:#2563eb;color:#ffffff;cursor:pointer">PASSED</button>
                    <button type="button" class="wst-flex-result-btn" data-flex-result="FAILED" style="border:1.5px solid #cbd5e1;padding:10px;border-radius:6px;font-weight:700;background:#ffffff;color:#475569;cursor:pointer">FAILED</button>
                </div>
            </div>

            <!-- 6. Findings Formula Display -->
            <div class="dtr-field" style="margin-bottom:10px">
                <label style="font-size:12px;font-weight:700;color:#475569;margin-bottom:6px;display:block">Findings</label>
                <input type="text" id="wst-flex-findings-preview" class="dtr-input mono" value="45° degrees 125000 cycles - (L: 0 mm, M: 0 mm; D:0 mm, D:0 mm)">
            </div>
        </div>

        <div class="dtr-modal-footer" style="padding:14px 22px;background:#f8fafc;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:12px">
            <button type="button" class="dtr-btn wst-btn-secondary" id="btn-wst-cancel-flex-eval" style="padding:8px 20px">Cancel</button>
            <button type="button" class="dtr-btn dtr-btn-primary" id="btn-wst-save-flex-eval" style="padding:8px 24px;font-weight:700;background:#2563eb;border-color:#2563eb">
                <svg class="ic" aria-hidden="true"><use href="#i-check"/></svg> Save Flexing Result
            </button>
        </div>
    </div>
</div>
