<?php
/** Lab Monitoring: ERP Finish Monitoring. Data comes from lab-monitoring/data (JS). Data: $meId */
?>
<div class="lm" id="lm-root" data-me="<?= (int) $meId ?>">
    <header class="lm-top">
        <div>
            <h2>ERP Finish Monitoring</h2>
            <p class="lm-sub">Bonding lab references per production day: InterLink numbers and ERP status.</p>
        </div>
        <span class="lm-agent" id="lm-agent" role="status" title="The program on the ERP PC that enters the reports in the ERP">ERP PC: checking...</span>
        <label class="lm-field">
            <span>Month</span>
            <select id="lm-date" aria-label="Month"></select>
        </label>
    </header>

    <section class="lm-sum card glass" id="lm-sum" aria-label="Summary">
        <button type="button" class="lm-tot" data-f="" id="lm-s-total"><small>Lab references</small><b>-</b></button>
        <div class="lm-grp" id="lm-s-il">
            <small>InterLink status</small>
            <span class="lm-trio">
                <button type="button" class="lm-chip ok" data-f="ok"><b>-</b> OK</button>
                <button type="button" class="lm-chip pending" data-f="pending"><b>-</b> Pending</button>
                <button type="button" class="lm-chip bad" data-f="bad"><b>-</b> Not submitted</button>
            </span>
        </div>
        <div class="lm-grp" id="lm-s-erp">
            <small>ERP status</small>
            <span class="lm-trio">
                <button type="button" class="lm-chip ok" data-f="erp_ok"><b>-</b> OK / Submitted</button>
                <button type="button" class="lm-chip pending" data-f="erp_pending"><b>-</b> Ongoing</button>
                <button type="button" class="lm-chip bad" data-f="erp_bad"><b>-</b> Not submitted</button>
            </span>
        </div>
    </section>

    <section class="lm-panel card glass">
        <div class="lm-tools">
            <label class="lm-search"><span class="sr-only">Search</span>
                <input type="search" id="lm-q" placeholder="Search lab reference, line, VSP code, TR number or result" autocomplete="off">
            </label>
            <div class="lm-seg" role="group" aria-label="Side">
                <button type="button" class="on" data-side="">All</button>
                <button type="button" data-side="R">Right shoes</button>
                <button type="button" data-side="L">Left shoes</button>
            </div>
            <button type="button" class="lm-btn" id="lm-refresh">Refresh</button>
            <button type="button" class="lm-btn lm-btn-go" id="lm-submit">Submit</button>
            <button type="button" class="lm-btn lm-btn-go" id="lm-submit-go" hidden disabled>Submit to ERP</button>
            <button type="button" class="lm-btn" id="lm-submit-cancel" hidden>Cancel</button>
            <span class="lm-note" id="lm-note" role="status"></span>
        </div>
        <div class="lm-scroll">
            <table class="lm-table" id="lm-table">
                <thead>
                    <tr>
                        <th class="lm-sel"><input type="checkbox" id="lm-pick-all" aria-label="Select all"></th><th>Tested date</th><th>Production date</th><th>Line</th><th>Lab reference</th><th>VSP code</th>
                        <th>Bonding TR no.</th><th>Pull test TR no.</th><th>InterLink status</th><th>Result</th><th>ERP status</th>
                    </tr>
                </thead>
                <tbody id="lm-body"><tr><td colspan="11" class="lm-empty">Loading...</td></tr></tbody>
            </table>
        </div>
    </section>

    <section class="lm-panel card glass">
        <div class="lm-reqhead">
            <h3>Sent to ERP</h3>
            <span class="lm-note" id="lm-req-note">Every report sent to ERP, one by one or in bulk. Updates by itself.</span>
        </div>
        <div class="lm-scroll">
            <table class="lm-table lm-req">
                <thead><tr><th>#</th><th>Lab reference</th><th>Production date</th><th>Sent as</th><th>Batch</th><th>Status</th><th>Message</th><th>By</th><th>When</th></tr></thead>
                <tbody id="lm-req"><tr><td colspan="9" class="lm-empty">Loading...</td></tr></tbody>
            </table>
        </div>
    </section>

    <div class="lm-dlg" id="lm-dlg" hidden role="dialog" aria-modal="true" aria-labelledby="lm-dlg-title">
        <div class="lm-dlg-box card glass">
            <div class="lm-dlg-head">
                <div class="lm-dlg-ttl">
                    <small>Shoe Bonding (U60) - read only</small>
                    <h3 id="lm-dlg-title"></h3>
                </div>
                <div class="lm-dlg-meta" id="lm-dlg-meta"></div>
                <div class="lm-dlg-act">
                    <button type="button" class="lm-btn lm-btn-go" id="lm-dlg-send">Submit to ERP</button>
                    <button type="button" class="lm-btn" id="lm-dlg-close" aria-label="Close">Close</button>
                </div>
            </div>
            <div class="lm-dlg-body">
                <div class="lm-dlg-load" id="lm-dlg-load"><span class="nx-spin" aria-hidden="true"></span> Loading the report...</div>
                <iframe id="lm-frame" title="Shoe Bonding (U60) report" src="about:blank"></iframe>
            </div>
        </div>
    </div>
</div>
