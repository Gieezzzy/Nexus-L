<?php
/**
 * NEXUS-L Warehouse 3D Locations Tracker
 * Interactive 3D Model matching DTP QC Laboratory Rack Locator SOP & FIFO System
 */
$u = service('auth')->user();
$isAdmin = $isAdmin ?? false;
?>
<script>window.WH_MY_LOOK = <?= json_encode($myLook ?? null, JSON_UNESCAPED_SLASHES) ?>;</script>
<div class="wh-root" id="wh-root"
     data-boxes-url="<?= esc(site_url('warehouse-tracker/boxes')) ?>"
     data-racks-url="<?= esc(site_url('warehouse-tracker/racks')) ?>"
     data-save-url="<?= esc(site_url('warehouse-tracker/save-box')) ?>"
     data-delete-url="<?= esc(site_url('warehouse-tracker/delete-box')) ?>"
     data-bot-ask-url="<?= esc(site_url('bot/ask')) ?>"
     data-voice-url="<?= esc(site_url('warehouse-tracker/voice')) ?>"
     data-rt-url="<?= esc(site_url('warehouse-tracker/rt')) ?>"
     data-avatar-url="<?= esc(site_url('warehouse-tracker/avatar')) ?>"
     data-me-name="<?= esc((string) ($currentUser['name'] ?? $currentUser['username'] ?? '')) ?>"
     data-is-admin="<?= $isAdmin ? '1' : '0' ?>"
     data-csrf-token="<?= csrf_token() ?>"
     data-csrf-hash="<?= csrf_hash() ?>">

    <!-- Ambient glowing backlight -->
    <div class="wh-bg" aria-hidden="true"><i></i><i></i><i></i></div>

    <!-- Header Glass Control Strip -->
    <header class="wh-glass wh-head">
        <div class="wh-title-block">
            <div class="wh-title-row">
                <div class="wh-badge-pill">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                    <span>DTP QC Laboratory</span>
                </div>
                <h2>Warehouse Locations Tracker</h2>
                <span class="wh-area-tag">FIFO &amp; Rack Locator SOP</span>
            </div>
            <p class="wh-sub">Interactive 3D navigation for physical specimen retains across Racks 1–4 and Aisles 01–04</p>
        </div>

        <!-- Controls: Search & Admin actions -->
        <div class="wh-actions-group">
            <div class="wh-search-bar">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" id="wh-search-input" placeholder="Search box label (e.g. Lab Document 2025, Bonding May 2026)…" autocomplete="off">
                <button type="button" class="wh-search-clear hidden" id="wh-search-clear" title="Clear search">&times;</button>
            </div>

            <!-- FIFO Color Reference Wheel Button -->
            <button type="button" class="wh-btn-glass" id="wh-btn-fifo-open" title="Open FIFO Monthly Color Reference Wheel">
                <span class="wh-fifo-icon-dot"></span>
                <span>FIFO Colors (先进先出)</span>
            </button>

            <?php if ($isAdmin): ?>
                <button type="button" class="wh-btn-admin" id="wh-btn-open-admin">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                    <span>Manage Boxes (Admin)</span>
                </button>
                <button type="button" class="wh-btn-glass" id="wh-btn-hide-view" aria-pressed="false" title="Hide the 3D warehouse and encode the boxes right here: register a box and place it in the warehouse">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.9 17.9A10.9 10.9 0 0112 20c-7 0-11-8-11-8a18.5 18.5 0 015.1-5.9M9.9 4.2A9.1 9.1 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.2 3.2M1 1l22 22"/></svg>
                    <span id="wh-hide-view-txt">Hide View</span>
                </button>
            <?php endif; ?>

            <button type="button" class="wh-btn-glass" id="wh-btn-reset-cam" title="Reset camera view">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 109-9 9.75 9.75 0 00-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                <span>Reset View</span>
            </button>
        </div>
    </header>

    <!-- KPI Metrics Ribbon (Official DTP QC Lab Categories) -->
    <section class="wh-kpis" aria-label="Warehouse statistics">
        <div class="wh-glass wh-kpi-card">
            <div class="wh-kpi-header">
                <span class="wh-kpi-icon icon-total">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                </span>
                <span class="wh-kpi-lbl">Total Boxes</span>
            </div>
            <div class="wh-kpi-num" id="wh-kpi-total">–</div>
            <div class="wh-kpi-sub">Across Racks 1 to 4</div>
        </div>

        <div class="wh-glass wh-kpi-card kpi-border-rack1">
            <div class="wh-kpi-header">
                <span class="wh-kpi-icon icon-rack1">
                    <span class="wh-dot-rack1"></span>
                </span>
                <span class="wh-kpi-lbl">Rack 1 (Blue)</span>
            </div>
            <div class="wh-kpi-num wh-num-rack1" id="wh-kpi-rack1">–</div>
            <div class="wh-kpi-sub">Daily Bonding &amp; Tech Trial</div>
        </div>

        <div class="wh-glass wh-kpi-card kpi-border-rack2">
            <div class="wh-kpi-header">
                <span class="wh-kpi-icon icon-rack2">
                    <span class="wh-dot-rack2"></span>
                </span>
                <span class="wh-kpi-lbl">Rack 2 (Brown)</span>
            </div>
            <div class="wh-kpi-num wh-num-rack2" id="wh-kpi-rack2">–</div>
            <div class="wh-kpi-sub">Whole Shoe Physical Tests</div>
        </div>

        <div class="wh-glass wh-kpi-card kpi-border-rack3">
            <div class="wh-kpi-header">
                <span class="wh-kpi-icon icon-rack3">
                    <span class="wh-dot-rack3"></span>
                </span>
                <span class="wh-kpi-lbl">Rack 3 (Green)</span>
            </div>
            <div class="wh-kpi-num wh-num-rack3" id="wh-kpi-rack3">–</div>
            <div class="wh-kpi-sub">First Article &amp; Old Reports</div>
        </div>

        <div class="wh-glass wh-kpi-card kpi-border-rack4">
            <div class="wh-kpi-header">
                <span class="wh-kpi-icon icon-rack4">
                    <span class="wh-dot-rack4"></span>
                </span>
                <span class="wh-kpi-lbl">Rack 4 (Purple)</span>
            </div>
            <div class="wh-kpi-num wh-num-rack4" id="wh-kpi-rack4">–</div>
            <div class="wh-kpi-sub">Material Specimens &amp; Scrap</div>
        </div>
    </section>

    <!-- Official SOP Category Filter Chips -->
    <div class="wh-glass wh-filter-strip">
        <span class="wh-filter-lbl">SOP Categories:</span>
        <div class="wh-filter-chips" id="wh-category-chips">
            <button type="button" class="wh-chip active" data-cat="ALL">All Categories</button>
            <button type="button" class="wh-chip chip-rack1" data-cat="Daily Bonding">Daily Bonding (R1)</button>
            <button type="button" class="wh-chip chip-rack1-fail" data-cat="Daily Failed Bonding">Failed Bonding (R1)</button>
            <button type="button" class="wh-chip chip-rack1-tt" data-cat="Tech Trial">Tech Trial (R1)</button>
            <button type="button" class="wh-chip chip-rack2" data-cat="Whole Shoe">Whole Shoe (R2)</button>
            <button type="button" class="wh-chip chip-rack3" data-cat="Old Test Reports">Old Reports (R3)</button>
            <button type="button" class="wh-chip chip-rack3-fa" data-cat="First Article">First Article (R3)</button>
            <button type="button" class="wh-chip chip-rack3-smp" data-cat="Sample Shoes">Sample Shoes (R3)</button>
            <button type="button" class="wh-chip chip-rack4" data-cat="Material Specimens">Material Specimens (R4)</button>
            <button type="button" class="wh-chip chip-rack4-scrap" data-cat="Material Scrap">Material Scrap (R4)</button>
            <button type="button" class="wh-chip chip-disposal" data-cat="Disposal">For Disposal</button>
        </div>
        <div class="wh-hint-tag">
            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            <span>Labels follow FIFO monthly colors</span>
        </div>
    </div>

    <!-- MAIN 3D VIEWPORT CONTAINER -->
    <div class="wh-main-stage">
        <div class="wh-glass wh-viewport-card" id="wh-viewport-card">
            <!-- Canvas where Three.js renders -->
            <div class="wh-canvas-wrap" id="wh-canvas-wrap">
                <canvas id="wh-canvas"></canvas>

                <!-- Floating 3D Navigation Controls -->
                <div class="wh-3d-controls">
                    <button type="button" class="wh-view-pill active" id="wh-view-door" title="View from entrance door (facing Aisle 01–04)">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V5a2 2 0 012-2h10a2 2 0 012 2v16"/></svg>
                        <span>Doorway View</span>
                    </button>
                    <button type="button" class="wh-view-pill" id="wh-view-overhead" title="Top-down architectural map">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/></svg>
                        <span>SOP Floor Plan</span>
                    </button>
                    <button type="button" class="wh-view-pill" id="wh-view-aisle" title="Center aisle inspection">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="2" x2="12" y2="22"/><line x1="6" y1="7" x2="6" y2="17"/><line x1="18" y1="7" x2="18" y2="17"/></svg>
                        <span>Aisle 04 View</span>
                    </button>
                    <button type="button" class="wh-view-pill wh-pilot-btn" id="wh-btn-pilot" aria-pressed="false" title="Drive Lexus yourself: W A S D or arrows to walk, E to interact, Esc to stop">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="10" rx="5"/><line x1="7" y1="12" x2="11" y2="12"/><line x1="9" y1="10" x2="9" y2="14"/><circle cx="15.5" cy="11" r="0.9"/><circle cx="18" cy="13" r="0.9"/></svg>
                        <span>Pilot</span>
                    </button>
                    <button type="button" class="wh-view-pill" id="wh-view-fs" title="Toggle full screen">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
                    </button>
                </div>

                <!-- Pilot mode: what the keys do, and what E would do right now -->
                <div class="wh-pilot-help hidden" id="wh-pilot-help" aria-live="polite">
                    <b>PILOT</b>
                    <span><kbd>W</kbd><kbd>S</kbd> walk &nbsp;<kbd>A</kbd><kbd>D</kbd> turn (arrows work too)</span>
                    <span><kbd>Shift</kbd> run</span>
                    <span><kbd>Space</kbd> jump</span>
                    <span><kbd>E</kbd> interact</span>
                    <span><kbd>Esc</kbd> exit</span>
                </div>
                <div class="wh-pilot-hint hidden" id="wh-pilot-hint" role="status"></div>

                <!-- 3D Legend Pill matching Rack Locator Color Tags -->
                <div class="wh-3d-legend">
                    <div class="legend-item"><span class="legend-dot dot-r1"></span><span>Rack 1 (Blue)</span></div>
                    <div class="legend-item"><span class="legend-dot dot-r2"></span><span>Rack 2 (Brown)</span></div>
                    <div class="legend-item"><span class="legend-dot dot-r3"></span><span>Rack 3 (Green)</span></div>
                    <div class="legend-item"><span class="legend-dot dot-r4"></span><span>Rack 4 (Purple)</span></div>
                    <div class="legend-item"><span class="legend-dot dot-bluepath"></span><span>Active Guide Line</span></div>
                </div>

                <!-- Navigation Path Banner (appears when guide is active) -->
                <div class="wh-path-banner hidden" id="wh-path-banner">
                    <span class="wh-pulse-ring"></span>
                    <span id="wh-path-text">Showing route from Entrance [🚪] to target box</span>
                    <button type="button" class="wh-btn-path-close" id="wh-path-close" title="Clear path">&times;</button>
                </div>

                <!-- Dynamic Floating 3D Speech Bubble on Top of Lexus -->
                <div class="wh-speech-bubble-3d" id="wh-lexus-bubble-3d">
                    <div class="wh-bubble-card wh-glass">
                        <div class="wh-bubble-head">
                            <div class="wh-bubble-agent-badge">
                                <span class="wh-bubble-pulse"></span>
                                <b class="wh-bubble-name">LEXUS (AI GUIDE)</b>
                            </div>
                            <div class="wh-bubble-audio-wave" id="wh-lexus-audio-wave" title="Speaking">
                                <span></span><span></span><span></span><span></span>
                            </div>
                            <div class="wh-bubble-quick-btns">
                                <button type="button" class="wh-bubble-mini-btn" id="wh-btn-bubble-wave" title="Make Lexus wave warmly">👋 Wave</button>
                                <button type="button" class="wh-bubble-mini-btn" id="wh-btn-bubble-door" title="Return to entrance door">🚪 Door</button>
                                <button type="button" class="wh-bubble-mini-btn active" id="wh-btn-voice" title="Lexus's male voice: click to switch it on or off" aria-pressed="true">🔊 Voice</button>
                                <button type="button" class="wh-bubble-mini-btn active" id="wh-btn-bulb" title="Lights: click to switch every lamp off or on (or click a lamp in the room)" aria-label="Switch the lamps">💡 Light</button>
                                <button type="button" class="wh-bubble-mini-btn" id="wh-btn-follow-lexus" title="Toggle camera tracking Lexus">🎥 <span id="wh-follow-lexus-txt">Follow</span></button>
                            </div>
                        </div>

                        <div class="wh-bubble-body" id="wh-lexus-speech-text">
                            👋 Welcome to DTP QC Laboratory! I'm <b>Lexus</b>, your 3D Guide. Search for any box or ask me anything, and I'll walk you right to it!
                        </div>

                        <!-- Direct interactive Chat input with LEXUS AI Bot -->
                        <form class="wh-bubble-input-row" id="wh-bubble-ask-form" onsubmit="return false;">
                            <input type="text" id="wh-bubble-input" placeholder="Ask Lexus (e.g. 'Where is Tech Trial?' or 'Take me to Rack 2')..." autocomplete="off">
                            <button type="submit" id="wh-btn-bubble-send" title="Send question to Lexus">
                                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                            </button>
                        </form>
                    </div>
                    <!-- Comic speech tail pointing down to Lexus's head -->
                    <div class="wh-bubble-tail"></div>
                </div>

                <!-- THE DARK ASYLUM: Horror Easter Egg Overlays -->
                <div class="wh-asylum-vignette hidden" id="wh-asylum-vignette"></div>
                <div class="wh-hide-overlay hidden" id="wh-hide-overlay" aria-hidden="true"><i></i><i></i></div>

                <div class="wh-glass wh-asylum-hud hidden" id="wh-asylum-hud">
                    <div class="wh-asylum-hud-top">
                        <div class="wh-asylum-hud-title">
                            <span class="wh-asylum-pulse-dot"></span>
                            <b>THE DARK ASYLUM</b>
                            <span class="wh-asylum-tag" id="wh-asylum-status">Locked Down</span>
                        </div>
                        <div class="wh-asylum-heart-wrap">
                            <span class="wh-asylum-heart" id="wh-asylum-heart">❤️</span>
                            <span class="wh-asylum-bpm" id="wh-asylum-bpm">72 BPM</span>
                        </div>
                    </div>
                    <div class="wh-asylum-keys-bar" id="wh-asylum-keys-bar">
                        <div class="wh-asylum-key" id="wh-key-silver">🗝️ Silver: <span>Missing</span></div>
                        <div class="wh-asylum-key" id="wh-key-brass">🗝️ Brass: <span>Missing</span></div>
                        <div class="wh-asylum-key" id="wh-key-iron">🗝️ Iron: <span>Missing</span></div>
                        <div class="wh-asylum-key master" id="wh-key-master">🏆 Master Exit: <span>Locked</span></div>
                    </div>
                    <div class="wh-asylum-prompt" id="wh-asylum-prompt">
                        [W A S D] Move &bull; [Shift] Sprint &bull; [Space] Jump &bull; [E] Pick up Key / Open Door &bull; [F] Flashlight &bull; [Esc] Return
                    </div>
                </div>

                <!-- Death / Jumpscare / Possession Screen -->
                <div class="wh-asylum-death hidden" id="wh-asylum-death">
                    <div class="wh-asylum-death-box">
                        <div class="wh-asylum-ghost-skull">💀</div>
                        <h2>POSSESSED BY THE PHANTOM</h2>
                        <p>The spectral killer caught your avatar. A dark surge explodes through your vessel...</p>
                        <div class="wh-asylum-respawn-msg">Respawning in Warehouse...</div>
                    </div>
                </div>

                <!-- Victory / Escape Screen -->
                <div class="wh-asylum-escape hidden" id="wh-asylum-escape">
                    <div class="wh-asylum-escape-box">
                        <div class="wh-asylum-escape-trophy">🏆</div>
                        <h2>ESCAPED THE ASYLUM!</h2>
                        <p>You unlocked the emergency lift and survived the haunting.</p>
                        <div class="wh-asylum-respawn-msg">Returning to Warehouse in victory...</div>
                    </div>
                </div>

                <!-- Loading overlay -->
                <div class="wh-canvas-loading" id="wh-canvas-loading">
                    <div class="wh-spinner"></div>
                    <p>Generating 3D Warehouse &amp; Racks…</p>
                </div>
            </div>

            <!-- Floating Target Box Details Panel -->
            <aside class="wh-glass wh-box-inspector hidden" id="wh-box-inspector">
                <header class="wh-bi-head">
                    <div>
                        <div style="display:flex;align-items:center;gap:6px">
                            <span class="wh-bi-cat-badge" id="bi-cat-badge">Daily Bonding</span>
                            <span class="wh-bi-fifo-pill" id="bi-fifo-pill" title="FIFO Monthly Label Color">May · Sky Blue</span>
                        </div>
                        <h4 id="bi-box-label">BONDING SPECIMEN MAY 26-28 2026</h4>
                    </div>
                    <button type="button" class="wh-bi-close" id="wh-bi-close">&times;</button>
                </header>

                <div class="wh-bi-body">
                    <div class="wh-bi-location-card" id="bi-loc-card">
                        <div class="wh-bi-loc-icon" id="bi-loc-icon">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        </div>
                        <div class="wh-bi-loc-text">
                            <div class="wh-bi-rack-title" id="bi-rack-name">Rack 1 (Blue)</div>
                            <div class="wh-bi-tier-info">
                                <b id="bi-tier-num">Layer 4</b> · Position <span id="bi-slot-num">Slot 1</span>
                            </div>
                        </div>
                    </div>

                    <div class="wh-bi-grid">
                        <div class="wh-bi-field">
                            <span class="bi-flab">Box Code</span>
                            <span class="bi-fval font-mono" id="bi-box-code">BX-BOND-2026-05A</span>
                        </div>
                        <div class="wh-bi-field">
                            <span class="bi-flab">Production / Date</span>
                            <span class="bi-fval" id="bi-date-range">May 26-28, 2026</span>
                        </div>
                        <div class="wh-bi-field">
                            <span class="bi-flab">Retention Status</span>
                            <span class="bi-fval" id="bi-status-badge"><span class="wh-status-active">ACTIVE RETAIN</span></span>
                        </div>
                        <div class="wh-bi-field">
                            <span class="bi-flab">Logged By</span>
                            <span class="bi-fval" id="bi-created-by">Admin</span>
                        </div>
                        <div class="wh-bi-field full-width">
                            <span class="bi-flab">Contents &amp; Description</span>
                            <p class="bi-fval bi-desc" id="bi-contents">Daily Whole Shoe Bonding (U60) test specimens, assembly line A1-A6.</p>
                        </div>
                    </div>
                </div>

                <footer class="wh-bi-foot">
                    <button type="button" class="wh-btn-guide-me" id="wh-btn-guide-me">
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        <span>Trace Blue Path</span>
                    </button>
                    <?php if ($isAdmin): ?>
                        <button type="button" class="wh-btn-edit-box" id="wh-btn-edit-this-box">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                            <span>Edit Box</span>
                        </button>
                    <?php endif; ?>
                </footer>
            </aside>
        </div>
    </div>

    <!-- ============================================================
         FIFO MONTHLY COLOR REFERENCE MODAL (先进先出)
         ============================================================ -->
    <div class="wh-modal-backdrop hidden" id="wh-fifo-modal-backdrop">
        <div class="wh-glass wh-fifo-panel">
            <header class="wh-fifo-head">
                <div>
                    <div style="display:flex;align-items:center;gap:6px">
                        <span class="wh-ad-badge">DTP QC Lab Standard</span>
                        <span class="wh-area-tag">FIFO: 先进先出</span>
                    </div>
                    <h4>FIFO: FIRST IN FIRST OUT — Monthly Label Color Reference</h4>
                    <p>Standard color coding for all monthly specimen box labels and 6-month retention cycle</p>
                </div>
                <button type="button" class="wh-mp-close" id="wh-fifo-close">&times;</button>
            </header>

            <div class="wh-fifo-body">
                <div class="wh-fifo-grid-wrap">
                    <!-- 12 Months Table matching media_1794181515940_9cbb9861.jpg -->
                    <div class="wh-fifo-table-box">
                        <table class="wh-fifo-tbl">
                            <thead>
                                <tr>
                                    <th>Month / 月份</th>
                                    <th>Color / 颜色</th>
                                    <th>Month / 月份</th>
                                    <th>Color / 颜色</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><b>January</b> <small>一月 1</small></td>
                                    <td><span class="fifo-color-swatch" style="background:#3e2723"></span> Dark Brown</td>
                                    <td><b>July</b> <small>七月 7</small></td>
                                    <td><span class="fifo-color-swatch" style="background:#c2185b"></span> Magenta Pink</td>
                                </tr>
                                <tr>
                                    <td><b>February</b> <small>二月 2</small></td>
                                    <td><span class="fifo-color-swatch" style="background:#ba68c8"></span> Lavender</td>
                                    <td><b>August</b> <small>八月 8</small></td>
                                    <td><span class="fifo-color-swatch" style="background:#2e7d32"></span> Bright Green</td>
                                </tr>
                                <tr>
                                    <td><b>March</b> <small>三月 3</small></td>
                                    <td><span class="fifo-color-swatch" style="background:#880e4f"></span> Maroon</td>
                                    <td><b>September</b> <small>九月 9</small></td>
                                    <td><span class="fifo-color-swatch" style="background:#c62828"></span> Red</td>
                                </tr>
                                <tr class="fifo-row-disposal">
                                    <td><b>April</b> <small>四月 4</small></td>
                                    <td><span class="fifo-color-swatch" style="background:#e65100"></span> Orange <b class="disposal-tag">FOR DISPOSAL</b></td>
                                    <td><b>October</b> <small>十月 10</small></td>
                                    <td><span class="fifo-color-swatch" style="background:#7986cb"></span> Periwinkle</td>
                                </tr>
                                <tr>
                                    <td><b>May</b> <small>五月 5</small></td>
                                    <td><span class="fifo-color-swatch" style="background:#0288d1"></span> Sky Blue</td>
                                    <td><b>November</b> <small>十一月 11</small></td>
                                    <td><span class="fifo-color-swatch" style="background:#00838f"></span> Teal Cyan</td>
                                </tr>
                                <tr>
                                    <td><b>June</b> <small>六月 6</small></td>
                                    <td><span class="fifo-color-swatch" style="background:#f57f17"></span> Golden Yellow</td>
                                    <td><b>December</b> <small>十二月 12</small></td>
                                    <td><span class="fifo-color-swatch" style="background:#212121"></span> Black</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Visual FIFO Cycle Explanation -->
                    <div class="wh-fifo-cycle-box">
                        <div class="wh-fifo-wheel-disc">
                            <div class="wh-wheel-center">
                                <div class="wh-vans-logo">VANS</div>
                                <small>6-MO CYCLE</small>
                            </div>
                        </div>
                        <div class="wh-fifo-note-card">
                            <b>FIFO Retention Rule:</b>
                            <p>Retention specimen boxes older than 6 months (marked by the arrow pointer) transition to <b>FOR DISPOSAL</b> and are scheduled for scrap disposal.</p>
                        </div>
                    </div>
                </div>
            </div>

            <footer class="wh-fifo-foot">
                <button type="button" class="wh-btn-glass" id="wh-fifo-dismiss">Close Reference</button>
            </footer>
        </div>
    </div>

    <!-- ============================================================
         ADMIN MANAGEMENT DRAWER / DATA TABLE
         ============================================================ -->
    <?php if ($isAdmin): ?>
    <div class="wh-drawer-backdrop" id="wh-admin-backdrop" aria-hidden="true"></div>
    <aside class="wh-glass wh-admin-drawer" id="wh-admin-drawer" role="dialog" aria-modal="true" aria-labelledby="wh-admin-drawer-title" aria-hidden="true">
        <header class="wh-ad-head">
            <div>
                <span class="wh-ad-badge">Admin Controls</span>
                <h3 id="wh-admin-drawer-title">Manage Retain Boxes &amp; Locations</h3>
                <p>Insert, edit or re-assign specimen retention boxes across Racks 1–4</p>
            </div>
            <div style="display:flex;align-items:center;gap:8px">
                <button type="button" class="wh-btn-primary" id="wh-btn-add-new-box">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <span>Add New Box</span>
                </button>
                <button type="button" class="wh-ad-close" id="wh-ad-close">&times;</button>
            </div>
        </header>

        <!-- Admin Table Toolbar -->
        <div class="wh-ad-toolbar">
            <div class="wh-ad-search">
                <input type="search" id="wh-tbl-search" placeholder="Filter boxes by label, code, rack, or date…" autocomplete="off">
            </div>
            <div class="wh-ad-selects">
                <select id="wh-tbl-rack-filter">
                    <option value="">All Racks</option>
                    <option value="RACK-1">Rack 1 (Blue - Bonding)</option>
                    <option value="RACK-2">Rack 2 (Brown - Whole Shoe)</option>
                    <option value="RACK-3">Rack 3 (Green - Reports &amp; Samples)</option>
                    <option value="RACK-4">Rack 4 (Purple - Material &amp; Scrap)</option>
                    <option value="RACK-PART">Partition Rack (Indigo - Materials)</option>
                    <option value="RACK-WINDOW">Window Rack (Cyan - Trays &amp; Retain)</option>
                </select>
                <select id="wh-tbl-tier-filter">
                    <option value="">All Layers</option>
                    <option value="5">Layer 5 (Top)</option>
                    <option value="4">Layer 4</option>
                    <option value="3">Layer 3</option>
                    <option value="2">Layer 2</option>
                    <option value="1">Layer 1 (Bottom)</option>
                </select>
            </div>
        </div>

        <!-- Table Container -->
        <div class="wh-ad-body">
            <div class="wh-tbl-scroll">
                <table class="wh-tbl" id="wh-boxes-table">
                    <thead>
                        <tr>
                            <th>Box Code</th>
                            <th>Box Label</th>
                            <th>Category</th>
                            <th>Rack</th>
                            <th>Layer (Tier)</th>
                            <th>FIFO Month</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="wh-boxes-tbody">
                        <!-- Dynamically populated via JS -->
                    </tbody>
                </table>
            </div>
        </div>

        <footer class="wh-ad-foot">
            <div class="wh-ad-foot-info">
                <span>Showing <b id="wh-tbl-count">0</b> retention boxes in laboratory warehouse</span>
            </div>
            <div class="wh-pager" id="wh-tbl-pager" aria-label="Pages"></div>
            <button type="button" class="wh-btn-glass" id="wh-ad-dismiss">Close Panel</button>
        </footer>
    </aside>

    <!-- Modal: Add / Edit Box -->
    <div class="wh-modal-backdrop hidden" id="wh-box-modal-backdrop">
        <div class="wh-glass wh-modal-panel">
            <header class="wh-mp-head">
                <h4 id="wh-modal-title">Add Warehouse Box</h4>
                <button type="button" class="wh-mp-close" id="wh-modal-close">&times;</button>
            </header>

            <form id="wh-box-form" class="wh-mp-form">
                <input type="hidden" id="box-form-id" name="id" value="0">

                <div class="wh-form-grid">
                    <div class="wh-fg-group full">
                        <label for="box-form-label">Box Label / Description <span class="req">*</span></label>
                        <input type="text" id="box-form-label" name="label" placeholder="e.g. BONDING SPECIMEN MAY 26-28 2026" required>
                    </div>

                    <div class="wh-fg-group">
                        <label for="box-form-code">Box Code</label>
                        <input type="text" id="box-form-code" name="box_code" placeholder="e.g. BX-BOND-2026-05A">
                        <small class="wh-hint">Auto-generated if left blank</small>
                    </div>

                    <div class="wh-fg-group">
                        <label for="box-form-category">SOP Shelf Category</label>
                        <select id="box-form-category" name="category">
                            <option value="Daily Bonding">Daily Bonding (Rack 1)</option>
                            <option value="Daily Failed Bonding">Daily Failed Bonding (Rack 1)</option>
                            <option value="Tech Trial">Tech Trial Bonding (Rack 1)</option>
                            <option value="Whole Shoe">Whole Shoe Specimens (Rack 2)</option>
                            <option value="Old Test Reports">Old Test Reports (Rack 3)</option>
                            <option value="First Article">First Article Shoe Specimens (Rack 3)</option>
                            <option value="Sample Shoes">Sample Shoes (Rack 3)</option>
                            <option value="Material Specimens">Material Specimens (Rack 4)</option>
                            <option value="Material Scrap">Material Scrap (Rack 4)</option>
                        </select>
                    </div>

                    <div class="wh-fg-group">
                        <label for="box-form-rack">Rack Placement <span class="req">*</span></label>
                        <select id="box-form-rack" name="rack_id" required>
                            <option value="RACK-1">Rack 1 (Blue - Daily Bonding &amp; Tech Trial)</option>
                            <option value="RACK-2">Rack 2 (Brown - Whole Shoe Specimens)</option>
                            <option value="RACK-3">Rack 3 (Green - Reports &amp; Sample Shoes)</option>
                            <option value="RACK-4">Rack 4 (Purple - Material &amp; Scrap)</option>
                            <option value="RACK-PART">Partition Wall Rack (Indigo - Material Storage)</option>
                            <option value="RACK-WINDOW">East Window Rack (Cyan - Specimen Trays &amp; Retain)</option>
                        </select>
                    </div>

                    <div class="wh-fg-group half">
                        <label for="box-form-tier">Shelf Layer / Tier (1 to 5) <span class="req">*</span></label>
                        <select id="box-form-tier" name="tier_level" required>
                            <option value="5">Layer 5 (Top Shelf)</option>
                            <option value="4">Layer 4 (Upper Shelf)</option>
                            <option value="3" selected>Layer 3 (Middle Shelf)</option>
                            <option value="2">Layer 2 (Lower Shelf)</option>
                            <option value="1">Layer 1 (Bottom Floor Shelf)</option>
                        </select>
                    </div>

                    <div class="wh-fg-group half">
                        <label for="box-form-slot">Slot Position (1 to 4)</label>
                        <select id="box-form-slot" name="slot_index">
                            <option value="1">Slot 1 (Left)</option>
                            <option value="2">Slot 2 (Center-Left)</option>
                            <option value="3">Slot 3 (Center-Right)</option>
                            <option value="4">Slot 4 (Right)</option>
                        </select>
                    </div>

                    <div class="wh-fg-group">
                        <label for="box-form-date">Date Range / Specific Dates</label>
                        <input type="text" id="box-form-date" name="date_range" placeholder="e.g. May 26-28, 2026">
                    </div>

                    <div class="wh-fg-group">
                        <label for="box-form-month">FIFO Month (YYYY-MM)</label>
                        <input type="month" id="box-form-month" name="month_year" value="<?= date('Y-m') ?>">
                    </div>

                    <div class="wh-fg-group">
                        <label for="box-form-status">Retention Status</label>
                        <select id="box-form-status" name="status">
                            <option value="ACTIVE">ACTIVE (In Retention)</option>
                            <option value="FOR_DISPOSAL">FOR DISPOSAL (FIFO Expired)</option>
                            <option value="ARCHIVED">ARCHIVED (Permanent Hold)</option>
                        </select>
                    </div>

                    <div class="wh-fg-group full">
                        <label for="box-form-desc">Detailed Contents / Notes</label>
                        <textarea id="box-form-desc" name="contents_desc" rows="3" placeholder="List styles, ETRF numbers, failure mode cutouts, or specific specimens inside this box…"></textarea>
                    </div>
                </div>

                <footer class="wh-mp-foot">
                    <button type="button" class="wh-btn-glass" id="wh-modal-cancel">Cancel</button>
                    <button type="submit" class="wh-btn-primary" id="wh-btn-save-box">
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        <span>Save Box Details</span>
                    </button>
                </footer>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
