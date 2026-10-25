<div class="msgr" id="msgr" data-base="<?= esc(site_url('inbox'), 'attr') ?>" data-admin="<?= ! empty($isAdmin) ? '1' : '0' ?>">
    <aside class="msgr-list glass" id="msgr-list">
        <div class="msgr-head">
            <h2>Chats</h2>
            <button type="button" id="msgr-new" class="msgr-new" aria-label="New message" title="New message">
                <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            </button>
        </div>
        <div id="msgr-notif-banner" class="msgr-notif-banner hidden" role="region" aria-label="Desktop notifications">
            <div class="notif-banner-icon">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            </div>
            <div class="notif-banner-body">
                <b>Enable desktop notifications</b>
                <p>Get instant alerts on your computer when you receive messages.</p>
                <div class="notif-banner-actions">
                    <button type="button" id="notif-btn-allow" class="notif-btn allow">Allow</button>
                    <button type="button" id="notif-btn-dismiss" class="notif-btn dismiss">Later</button>
                </div>
            </div>
        </div>
        <p id="msgr-banner" class="msgr-banner hidden" role="alert"></p>
        <div class="msgr-search">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            <input id="msgr-q" type="search" placeholder="Search people" autocomplete="off" maxlength="50" aria-label="Search people">
        </div>
        <ul id="msgr-items" class="msgr-items"></ul>
        <p id="msgr-none" class="msgr-empty hidden">No conversations yet. Search for a person to start one.</p>
    </aside>

    <section class="msgr-chat glass" id="msgr-chat">
        <div id="msgr-blank" class="msgr-blank">
            <svg fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path d="M21 12a8 8 0 01-11.6 7.1L3 21l1.9-5.4A8 8 0 1121 12z"/></svg>
            <p>Select a chat to start messaging</p>
        </div>

        <div id="msgr-pane" class="msgr-pane hidden">
            <header class="msgr-bar">
                <button type="button" id="msgr-back" class="msgr-back" aria-label="Back to chats">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 5l-7 7 7 7"/></svg>
                </button>
                <span class="av" id="msgr-av"></span>
                <div class="msgr-who"><b id="msgr-name"></b><small id="msgr-sub"></small></div>
                <button type="button" id="msgr-info-btn" class="msgr-icon" aria-label="Chat details" aria-pressed="false" title="Chat details">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
                </button>
            </header>

            <div id="msgr-msgs" class="msgr-msgs" aria-live="polite"></div>

            <div id="msgr-reply" class="msgr-reply hidden" aria-live="polite"></div>

            <div id="msgr-atts" class="msgr-atts hidden" aria-label="Attachments"></div>

            <form id="msgr-form" class="msgr-form" autocomplete="off">
                <div class="msgr-tools">
                    <button type="button" id="msgr-attach" class="msgr-icon" aria-label="Attach" title="Attach" aria-haspopup="menu">
                        <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg>
                    </button>
                    <button type="button" id="msgr-emoji" class="msgr-icon" aria-label="Emoji" title="Emoji" aria-haspopup="dialog">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 14s1.5 2 4 2 4-2 4-2M9 9.5h.01M15 9.5h.01"/></svg>
                    </button>
                </div>
                <textarea id="msgr-input" rows="1" maxlength="2000" placeholder="Aa" aria-label="Message"></textarea>
                <button type="submit" class="msgr-send" id="msgr-send" aria-label="Send">
                    <span id="msgr-send-ico"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M3.4 20.4l17.4-7.5a1 1 0 000-1.8L3.4 3.6a.8.8 0 00-1.1.9L3.5 10l9 2-9 2-1.2 5.5a.8.8 0 001.1.9z"/></svg></span>
                    <span id="msgr-send-quick" class="hidden"></span>
                </button>
            </form>
            <input type="file" id="msgr-file-img" accept="image/jpeg,image/png,image/gif,image/webp" multiple hidden>
            <input type="file" id="msgr-file-any" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar,.7z,image/*" multiple hidden>
            <p id="msgr-err" class="msgr-err hidden" role="alert"></p>
        </div>
    </section>

    <aside class="msgr-info glass hidden" id="msgr-info" aria-label="Chat details">
        <div id="inf-main" class="inf-view">
            <div class="inf-head">
                <span class="av big" id="inf-av"></span>
                <b id="inf-name"></b>
                <small id="inf-user"></small>
                <span id="inf-status" class="inf-status"></span>
            </div>

            <h4>Chat info</h4>
            <button type="button" class="inf-row" data-go="pinned"><span>View pinned messages</span><em id="inf-pinned-n"></em></button>
            <button type="button" class="inf-row" data-go="search"><span>Search in conversation</span></button>

            <h4>Customize chat</h4>
            <button type="button" class="inf-row" id="inf-emoji-btn"><span>Change emoji</span><em id="inf-emoji"></em></button>
            <button type="button" class="inf-row" id="inf-nick-btn"><span>Edit nicknames</span></button>

            <h4>Media &amp; files</h4>
            <button type="button" class="inf-row" data-go="media"><span>Media (attachments)</span></button>
            <button type="button" class="inf-row" data-go="files" id="inf-files-btn"><span>Files</span></button>

            <h4>Privacy &amp; support</h4>
            <div class="inf-row static"><span>Disappearing messages</span><em id="inf-dis"></em></div>
            <label class="inf-row toggle"><span>Read receipts</span><input type="checkbox" id="inf-rr"></label>
            <label class="inf-row toggle"><span>Secure messaging (E2EE)</span><input type="checkbox" id="inf-sec"></label>
            <p id="inf-err" class="msgr-err hidden" role="alert"></p>
        </div>

        <div id="inf-sub" class="inf-view hidden">
            <div class="inf-subbar"><button type="button" id="inf-back" class="msgr-icon" aria-label="Back"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 5l-7 7 7 7"/></svg></button><b id="inf-subtitle"></b></div>
            <div id="inf-search-box" class="msgr-search hidden"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg><input id="inf-q" type="search" placeholder="Search in conversation" maxlength="60" autocomplete="off"></div>
            <ul id="inf-list" class="inf-list"></ul>
            <div id="inf-grid" class="inf-grid"></div>
            <button type="button" id="inf-more" class="inf-save hidden">Load more</button>
            <p id="inf-empty" class="msgr-empty hidden"></p>
        </div>
    </aside>

    <!-- right-click / long-press menu -->
    <div id="msgr-ctx" class="ctx hidden" role="menu" aria-label="Message options">
        <div class="ctx-react" id="ctx-react"></div>
        <button type="button" role="menuitem" data-act="reply">Reply</button>
        <button type="button" role="menuitem" data-act="pin">Pin</button>
        <button type="button" role="menuitem" data-act="bump">Bump</button>
        <button type="button" role="menuitem" data-act="copy">Copy</button>
        <button type="button" role="menuitem" data-act="translate">Translate</button>
        <button type="button" role="menuitem" data-act="forward">Forward</button>
        <button type="button" role="menuitem" data-act="edit" id="ctx-edit">Edit</button>
    </div>

    <!-- attach menu -->
    <div id="msgr-attach-menu" class="ctx attach hidden" role="menu" aria-label="Attach">
        <button type="button" role="menuitem" data-att="image">📷 Send image(s) / gallery</button>
        <button type="button" role="menuitem" data-att="file" id="att-file-btn">📎 Send a file</button>
        <button type="button" role="menuitem" data-att="report" disabled title="Report sending is not available on the web yet">📊 Attach report <em>(not available on web yet)</em></button>
    </div>

    <!-- composer emoji popover -->
    <div id="msgr-emoji-pop" class="emoji-pop hidden" role="dialog" aria-label="Emoji"></div>

    <!-- modals -->
    <div id="dlg" class="dlg hidden" role="dialog" aria-modal="true">
        <div class="dlg-card" id="dlg-card"></div>
    </div>
</div>
