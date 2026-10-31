<?php /** Brain console (admin): chats, unanswered questions, problems, knowledge, health. */ ?>
<div class="card glass br-head">
    <div style="display:flex;align-items:center;gap:14px">
        <img src="<?= base_url('assets/images/lexis-icon.png') ?>" alt="LEXIS AI" style="width:48px;height:48px;border-radius:10px;background:#fff;border:1px solid #bae6fd;box-shadow:0 2px 8px rgba(2,132,199,0.18);padding:3px;flex-shrink:0">
        <div>
            <h3>LEXIS AI Brain <span class="dsg-badge">Admin only</span></h3>
            <p class="muted sm" style="margin-bottom:0">Laboratory Execution Intelligence System knowledge base, user interactions, problem diagnostics, and automated learning engine.</p>
        </div>
    </div>
    <div class="br-tabs" role="tablist">
        <button type="button" class="br-tab active" data-tab="overview">Overview</button>
        <button type="button" class="br-tab" data-tab="chats">Chats <span class="br-n" id="br-n-chats"></span></button>
        <button type="button" class="br-tab" data-tab="unanswered">Unanswered <span class="br-n" id="br-n-un"></span></button>
        <button type="button" class="br-tab" data-tab="learning">Learning <span class="br-n" id="br-n-learn"></span></button>
        <button type="button" class="br-tab" data-tab="issues">Problems <span class="br-n" id="br-n-iss"></span></button>
        <button type="button" class="br-tab" data-tab="knowledge">Knowledge</button>
        <button type="button" class="br-tab" data-tab="teach">Teach / Import</button>
        <button type="button" class="br-tab" data-tab="health">Health</button>
    </div>
</div>

<section id="br-overview" class="br-pane">
    <div class="br-cards" id="br-cards"></div>
    <div class="card glass"><h4>Most visited pages (7 days)</h4><div id="br-pages" class="muted sm">Loading…</div></div>
</section>

<section id="br-chats" class="br-pane" hidden>
    <div class="br-split">
        <div class="card glass"><h4>Conversations</h4>
            <div class="br-filter"><select id="br-chat-status"><option value="escalated">With an administrator</option><option value="">All</option><option value="resolved">Resolved</option></select></div>
            <ul id="br-threads" class="br-list"></ul></div>
        <div class="card glass" id="br-thread-box"><p class="muted sm">Pick a conversation.</p></div>
    </div>
</section>

<section id="br-unanswered" class="br-pane" hidden>
    <div class="card glass"><h4>Questions the assistant could not answer</h4>
        <p class="muted sm">Answer the most frequent ones first. Once you answer, the assistant knows it for everyone.</p>
        <ul id="br-un-list" class="br-list"></ul></div>
</section>

<section id="br-learning" class="br-pane" hidden>
    <div class="card glass"><h4>What the assistant learned from users</h4>
        <p class="muted sm">The assistant watches what people ask, which answers get a thumbs-down, and how they rephrase. It only <b>proposes</b>: nothing becomes an answer until you apply it. Questions are stored without names.</p>
        <div class="br-cards" id="br-learn-stats"></div>
        <div id="br-learn-loop"></div>
        <p class="muted sm">How it learns, step by step: the assistant answers (prediction), a thumbs-down or a rephrase shows it was wrong (error), only the words of that question and that answer take the blame, and their weights move a small, limited step (update). The next similar question is ranked with the new weights, and the mistake rate above shows whether it is improving. Only the <i>ranking</i> changes by itself; new answer texts always wait for you below.</p>
        <div class="br-row"><button type="button" class="dsg-btn dsg-primary" id="br-learn-refresh">Look for new suggestions</button><span id="br-learn-msg" class="muted sm"></span></div>
        <ul id="br-learn-list" class="br-list"></ul></div>
</section>

<section id="br-issues" class="br-pane" hidden>
    <div class="card glass"><h4>Repeated problems seen in users' activity</h4>
        <p class="muted sm">Errors, failed requests and long waits, grouped. Write the fix once to teach the assistant.</p>
        <ul id="br-iss-list" class="br-list"></ul></div>
</section>

<section id="br-knowledge" class="br-pane" hidden>
    <div class="card glass">
        <div class="br-filter"><input type="text" id="br-k-q" placeholder="Search the knowledge…"><select id="br-k-status"><option value="">All</option><option value="approved">Approved</option><option value="draft">Draft</option><option value="retired">Retired</option></select></div>
        <div class="br-tablewrap"><table class="br-table"><thead><tr><th>Title</th><th>Kind</th><th>Source</th><th>Uses</th><th>Helpful</th><th>Status</th><th></th></tr></thead><tbody id="br-k-rows"></tbody></table></div>
    </div>
</section>

<section id="br-teach" class="br-pane" hidden>
    <div class="br-split">
        <div class="card glass"><h4 id="br-teach-title">Teach one answer</h4>
            <input type="hidden" id="br-t-id">
            <label>Question / title<input type="text" id="br-t-title" maxlength="190" placeholder="e.g. How do I reprint an old bonding report?"></label>
            <label>Words people would use (comma separated)<input type="text" id="br-t-kw" maxlength="590" placeholder="reprint, old report, print again, previous report"></label>
            <label>Answer<textarea id="br-t-body" rows="4" placeholder="Plain, short answer."></textarea></label>
            <label>Steps (one per line)<textarea id="br-t-steps" rows="4"></textarea></label>
            <div class="br-two"><label>Page it belongs to<input type="text" id="br-t-page" placeholder="dtr, interlink, designer…"></label>
            <label>Who sees it<select id="br-t-role"><option value="user">Everyone</option><option value="admin">Administrators only</option></select></label></div>
            <label>Open-page button (optional) <input type="text" id="br-t-open" placeholder="dtr?tab=bonding"></label>
            <div class="br-row"><button type="button" class="dsg-btn dsg-primary" id="br-t-save">Save answer</button><button type="button" class="dsg-btn" id="br-t-clear">Clear</button><span id="br-t-msg" class="muted sm"></span></div>
        </div>
        <div class="card glass"><h4>Import many answers</h4>
            <p class="muted sm">Plain text: separate answers with a blank line; the first line is the question, the rest is the answer. Or paste JSON lines / CSV (title, keywords, body). Duplicates are skipped.</p>
            <select id="br-i-fmt"><option value="text">Plain text</option><option value="jsonl">JSON lines</option><option value="csv">CSV</option></select>
            <textarea id="br-i-body" rows="12" placeholder="How do I ...?&#10;First, ...&#10;Then, ...&#10;&#10;Where is ...?&#10;It is in ..."></textarea>
            <div class="br-row"><button type="button" class="dsg-btn dsg-primary" id="br-i-go">Import</button><span id="br-i-msg" class="muted sm"></span></div>
        </div>
    </div>
</section>

<section id="br-health" class="br-pane" hidden>
    <div class="card glass"><h4>System health</h4><ul id="br-h-checks" class="br-list"></ul>
        <div class="br-row"><button type="button" class="dsg-btn" id="br-h-flush">Export the Brain now</button><button type="button" class="dsg-btn dsg-danger" id="br-h-restore">Restore knowledge from knowledge.back</button><span id="br-h-msg" class="muted sm"></span></div></div>
    <div class="card glass"><h4>Brain folders</h4><div id="br-h-dirs" class="muted sm"></div></div>
    <div class="card glass"><h4>Server settings that affect speed and safety</h4><div id="br-h-env" class="muted sm"></div></div>
</section>
