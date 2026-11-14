<?php /* "Send report" dropdown (Synology Chat / Email), shared by the Bonding, Whole Shoe and Material toolbars; dtr.js binds the classes. */ ?>
<div class="dtr-send-dropdown-wrap">
    <button type="button" class="dtr-btn dtr-btn-outline dtr-send-btn js-send-toggle" title="Send report via Synology Chat or Email">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>
        <span>Send report</span>
        <svg class="dtr-caret" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
    </button>
    <div class="dtr-send-dropdown-menu">
        <button type="button" class="dtr-send-dropdown-item js-open-synology">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#0284c7"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <div>
                <strong>Send Synology</strong>
                <small>Send to Synology Chat channel with report image</small>
            </div>
        </button>
        <button type="button" class="dtr-send-dropdown-item js-open-email">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#ea580c"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
            <div>
                <strong>Send Email</strong>
                <small>Send formal notification via email</small>
            </div>
        </button>
    </div>
</div>
