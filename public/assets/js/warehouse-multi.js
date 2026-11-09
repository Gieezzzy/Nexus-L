/**
 * WAREHOUSE: several users in the same room.
 *  - every user is in the warehouse with the avatar of their own account; avatars of the others walk around and you can meet them
 *  - a Chat button: the messages are seen by everybody in the warehouse at that moment and are NOT saved anywhere (they live a couple of
 *    minutes in a temporary file on the server and then they are gone)
 *  - the Avatar Studio: stand on the glowing ring near the entrance and press Q three times to change how your avatar looks
 * The look of the avatar is saved for the account; positions and chat are only passed along.
 * The page talks to the server with a short poll (4 times a second): warehouse-tracker/rt.
 */
(function () {
  'use strict';
  const root = document.getElementById('wh-root');
  if (!root || !window.AvatarFactory) return;

  const rtUrl = root.dataset.rtUrl;
  const avatarUrl = root.dataset.avatarUrl;
  const csrf = root.dataset.csrfHash || '';
  const myName = root.dataset.meName || 'Me';
  const STUDIO = { x: -21.4, z: 21.4, r: 1.8, ringR: 1.25 };   // the ring where Q three times opens the Avatar Studio (the south-west corner)

  const Multi = { studioOpen: false, remotes: new Map(), me: null, chat: [], unread: 0, online: 1, myLook: AvatarFactory.normalize(window.WH_MY_LOOK) };
  window.WHMulti = Multi;

  // ------------------------------------------------------------------ small canvas labels
  function textSprite(text, opts) {
    opts = opts || {};
    const c = document.createElement('canvas');
    const g = c.getContext('2d');
    const font = (opts.size || 28) + 'px sans-serif';
    g.font = font;
    // wrap
    const maxW = opts.maxW || 360;
    const words = String(text).split(/\s+/);
    const lines = [];
    let line = '';
    words.forEach((w) => { const t = line ? line + ' ' + w : w; if (g.measureText(t).width > maxW && line) { lines.push(line); line = w; } else line = t; });
    if (line) lines.push(line);
    const lh = (opts.size || 28) + 6;
    const w = Math.min(maxW, Math.max(...lines.map(l => g.measureText(l).width))) + 28;
    c.width = Math.ceil(w); c.height = lines.length * lh + 20;
    const g2 = c.getContext('2d');
    g2.font = font;
    g2.fillStyle = opts.bg || 'rgba(15,23,42,0.82)';
    const r = 12;
    g2.beginPath(); g2.moveTo(r, 0); g2.lineTo(c.width - r, 0); g2.quadraticCurveTo(c.width, 0, c.width, r); g2.lineTo(c.width, c.height - r); g2.quadraticCurveTo(c.width, c.height, c.width - r, c.height); g2.lineTo(r, c.height); g2.quadraticCurveTo(0, c.height, 0, c.height - r); g2.lineTo(0, r); g2.quadraticCurveTo(0, 0, r, 0); g2.fill();
    g2.fillStyle = opts.color || '#f8fafc';
    g2.textBaseline = 'top';
    lines.forEach((l, i) => g2.fillText(l, 14, 10 + i * lh));
    const tex = new THREE.CanvasTexture(c);
    const sp = new THREE.Sprite(new THREE.SpriteMaterial({ map: tex, transparent: true, depthTest: false }));
    const mpp = (opts.worldH || 0.3) / ((opts.size || 28) + 26);       // metres per pixel: a one-line label is about worldH high
    sp.scale.set(c.width * mpp, c.height * mpp, 1);
    sp.renderOrder = 10;
    return sp;
  }

  const labelHeight = (look) => look.kind === 'human' ? 2.05 * look.height : look.kind === 'dog' ? 1.05 : look.kind === 'cat' ? 0.9 : look.kind === 'panda' ? 1.2 : 0.95;

  // ------------------------------------------------------------------ the other users' avatars
  function createRemote(u) {
    const look = AvatarFactory.normalize(u.look);
    const rig = AvatarFactory.build(look);
    rig.root.position.set(u.x || 0, 0, u.z || 0);
    rig.root.rotation.y = u.yaw || 0;
    WH.scene.add(rig.root);
    const holder = new THREE.Group();
    const name = textSprite(u.name || 'Guest', { size: 26, maxW: 260, worldH: 0.22 });
    name.position.y = 0;
    holder.add(name);
    WH.scene.add(holder);
    return { id: u.id, name: u.name, rig, look, lookKey: JSON.stringify(look), holder, nameSprite: name, bubble: null, bubbleUntil: 0, tx: u.x || 0, tz: u.z || 0, tyaw: u.yaw || 0, speed: 0, zone: u.zone || 'warehouse', last: performance.now() };
  }
  function removeRemote(r) {
    WH.scene.remove(r.rig.root);
    WH.scene.remove(r.holder);
    Multi.remotes.delete(r.id);
  }
  function applyUsers(list) {
    const seen = new Set();
    list.forEach((u) => {
      seen.add(String(u.id));
      let r = Multi.remotes.get(String(u.id));
      const key = JSON.stringify(AvatarFactory.normalize(u.look));
      if (r && r.lookKey !== key) { removeRemote(r); r = null; }            // they changed their look: build it again
      if (!r) { r = createRemote(u); Multi.remotes.set(String(u.id), r); }
      r.tx = u.x; r.tz = u.z; r.tyaw = u.yaw; r.zone = u.zone || 'warehouse'; r.floor = u.floor || 0; r.ty = u.y || 0; r.last = performance.now();
      if (u.name && u.name !== r.name) { r.name = u.name; }
    });
    Array.from(Multi.remotes.values()).forEach((r) => { if (!seen.has(String(r.id))) removeRemote(r); });
  }
  function frameRemotes(dt, time) {
    const AG = window.AsylumGame, inAsy = !!(AG && AG.isActive());
    const here = !inAsy;
    lockAsylumUi(inAsy);
    Multi.remotes.forEach((r) => {
      const p = r.rig.root.position;
      const dx = r.tx - p.x, dz = r.tz - p.z;
      const k = 1 - Math.exp(-dt * 14);
      if (Math.hypot(dx, dz) > 6) { p.x = r.tx; p.z = r.tz; } else { p.x += dx * k; p.z += dz * k; }
      p.y += ((r.ty || 0) - p.y) * Math.min(1, dt * 12);
      const dy = Math.atan2(Math.sin(r.tyaw - r.rig.root.rotation.y), Math.cos(r.tyaw - r.rig.root.rotation.y));
      r.rig.root.rotation.y += dy * Math.min(1, dt * 10);
      const v = Math.hypot(dx, dz) * 8;                       // metres per second, from how far behind the avatar is
      r.speed += ((v > 0.2 ? Math.min(v, 4.2) : 0) - r.speed) * Math.min(1, dt * 8);
      AvatarFactory.animate(r.rig, r.speed, dt, time);
      const visible = inAsy ? (r.zone === 'asylum' && (r.floor || 0) === AG.floor) : r.zone === 'warehouse';
      r.rig.root.visible = visible; r.holder.visible = visible;
      r.holder.position.set(p.x, labelHeight(r.look) + 0.3, p.z);
      if (r.bubble && performance.now() > r.bubbleUntil) { r.holder.remove(r.bubble); r.bubble = null; }
    });
    // a message floating over the local avatar too
    if (localBubble) {
      const lex = WH.state.lexus;
      localHolder.position.set(lex.root.position.x, labelHeight(Multi.myLook) + 0.3, lex.root.position.z);
      localHolder.visible = true;
      if (performance.now() > localBubbleUntil) { localHolder.remove(localBubble); localBubble = null; }
    }
  }
  // in the asylum only Chat, Full screen and Pilot stay; Pilot cannot be switched off and the boxes cannot be clicked
  let asyLocked = false;
  function lockAsylumUi(on) {
    if (on) { if (WH.state.lexus && !WH.isPilot()) WH.setPilot(true); }
    if (on === asyLocked) return;
    asyLocked = on;
    document.body.classList.toggle('wh-asylum-lock', on);
    ['wh-view-door', 'wh-view-overhead', 'wh-view-aisle', 'wh-btn-pilot'].forEach((id) => { const b = document.getElementById(id); if (b) b.disabled = on; });
  }
  function say(id, text) {
    if (String(id) === String(Multi.me)) {
      if (localBubble) localHolder.remove(localBubble);
      localBubble = textSprite(text, { size: 28, maxW: 380, bg: 'rgba(37,99,235,0.9)', worldH: 0.26 });
      localBubble.position.y = 0.45;
      localHolder.add(localBubble);
      localBubbleUntil = performance.now() + 7000;
      return;
    }
    const r = Multi.remotes.get(String(id));
    if (!r) return;
    if (r.bubble) r.holder.remove(r.bubble);
    r.bubble = textSprite(text, { size: 28, maxW: 380, bg: 'rgba(15,23,42,0.92)', worldH: 0.26 });
    r.bubble.position.y = 0.45;
    r.holder.add(r.bubble);
    r.bubbleUntil = performance.now() + 7000;
  }
  let localBubble = null, localBubbleUntil = 0;
  const localHolder = new THREE.Group();

  // ------------------------------------------------------------------ the chat (nothing is stored)
  const ui = {};
  function buildChat() {
    const wrap = document.getElementById('wh-canvas-wrap');
    if (!wrap) return;
    const controls = wrap.querySelector('.wh-3d-controls');
    const btn = document.createElement('button');
    btn.type = 'button'; btn.className = 'wh-view-pill wh-chat-btn'; btn.id = 'wh-chat-btn';
    btn.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg><span>Chat</span><b class="wh-chat-badge" id="wh-chat-badge" hidden>0</b>';
    if (controls) controls.insertBefore(btn, controls.querySelector('#wh-view-fs') || null); else wrap.appendChild(btn);
    const panel = document.createElement('div');
    panel.className = 'wh-chat-panel hidden'; panel.id = 'wh-chat-panel';
    panel.innerHTML = '<div class="wh-chat-head"><b>Warehouse chat</b><span id="wh-chat-online">1 online</span><button type="button" id="wh-chat-x" aria-label="Close chat">&times;</button></div>' +
      '<div class="wh-chat-note">Messages are seen by everyone here right now and are not saved.</div>' +
      '<div class="wh-chat-list" id="wh-chat-list" aria-live="polite"></div>' +
      '<form class="wh-chat-form" id="wh-chat-form" autocomplete="off"><input id="wh-chat-input" maxlength="240" placeholder="Say something..."><button type="submit">Send</button></form>';
    wrap.appendChild(panel);
    ui.btn = btn; ui.panel = panel; ui.list = panel.querySelector('#wh-chat-list'); ui.input = panel.querySelector('#wh-chat-input'); ui.badge = btn.querySelector('#wh-chat-badge'); ui.online = panel.querySelector('#wh-chat-online');
    // a button that hides the guide's speech bubble (the bar with Wave / Door / Voice / ...) so the chat can be seen completely
    const gbtn = document.createElement('button');
    gbtn.type = 'button'; gbtn.className = 'wh-view-pill wh-guide-btn'; gbtn.id = 'wh-guide-btn'; gbtn.title = 'Hide or show the guide bar';
    gbtn.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg><span>Guide bar</span>';
    if (controls) controls.insertBefore(gbtn, btn.nextSibling); else wrap.appendChild(gbtn);
    const bubble = document.getElementById('wh-lexus-bubble-3d');
    const setGuide = (show) => {
      root.classList.toggle('wh-guide-off', !show);
      gbtn.classList.toggle('active', !show);
      gbtn.querySelector('span').textContent = show ? 'Hide bar' : 'Show bar';
      try { localStorage.setItem('wh.guideBar', show ? '1' : '0'); } catch (e) { /* private window */ }
    };
    let showGuide = true;
    try { showGuide = localStorage.getItem('wh.guideBar') !== '0'; } catch (e) { /* default */ }
    setGuide(showGuide);
    gbtn.addEventListener('click', () => { showGuide = !showGuide; setGuide(showGuide); });
    void bubble;
    btn.addEventListener('click', () => toggleChat());
    panel.querySelector('#wh-chat-x').addEventListener('click', () => toggleChat(false));
    panel.querySelector('#wh-chat-form').addEventListener('submit', (e) => {
      e.preventDefault();
      const t = ui.input.value.trim();
      if (t) { outbox.push(t); ui.input.value = ''; sendNow(); }
    });
    ui.input.addEventListener('keydown', (e) => { e.stopPropagation(); if (e.key === 'Escape') { toggleChat(false); } });
  }
  function toggleChat(force) {
    const open = force === undefined ? ui.panel.classList.contains('hidden') : !!force;
    ui.panel.classList.toggle('hidden', !open);
    ui.btn.classList.toggle('active', open);
    if (open) { Multi.unread = 0; renderBadge(); ui.list.scrollTop = ui.list.scrollHeight; setTimeout(() => ui.input.focus(), 50); } else ui.input.blur();
  }
  function renderBadge() { if (!ui.badge) return; ui.badge.hidden = Multi.unread <= 0; ui.badge.textContent = Multi.unread > 9 ? '9+' : Multi.unread; }
  function addChat(m) {
    const mine = String(m.uid) === String(Multi.me);
    const row = document.createElement('div');
    row.className = 'wh-chat-row' + (mine ? ' mine' : '');
    const t = new Date((m.t || Date.now() / 1000) * 1000);
    const nm = document.createElement('b'); nm.textContent = mine ? 'You' : m.name;
    const tx = document.createElement('span'); tx.textContent = m.text;
    const tm = document.createElement('i'); tm.textContent = t.getHours().toString().padStart(2, '0') + ':' + t.getMinutes().toString().padStart(2, '0');
    row.append(nm, tx, tm);
    ui.list.appendChild(row);
    while (ui.list.children.length > 80) ui.list.removeChild(ui.list.firstChild);
    ui.list.scrollTop = ui.list.scrollHeight;
    say(m.uid, m.text);
    if (ui.panel.classList.contains('hidden') && !mine) { Multi.unread++; renderBadge(); }
  }

  // ------------------------------------------------------------------ the poll
  const outbox = [];
  let busy = false, since = 0, joined = false, lastLook = '', lastPos = null, lastSent = 0, lastSpeed = 0;
  function payload() {
    const lex = WH.state.lexus;
    const p = lex.root.position;
    const now = performance.now();
    let mv = 0;
    if (lastPos) { const d = Math.hypot(p.x - lastPos.x, p.z - lastPos.z); mv = d / Math.max(0.05, (now - lastSent) / 1000); }
    lastPos = { x: p.x, z: p.z }; lastSent = now;
    const body = { x: +p.x.toFixed(2), z: +p.z.toFixed(2), yaw: +(lex.root.rotation.y).toFixed(2), mv: +mv.toFixed(2), zone: (window.AsylumGame && window.AsylumGame.isActive()) ? 'asylum' : 'warehouse', floor: window.AsylumGame ? (window.AsylumGame.floor || 0) : 0, y: +p.y.toFixed(2), since };
    const lk = JSON.stringify(Multi.myLook);
    if (lk !== lastLook) body.look = Multi.myLook;
    if (!joined) body.join = true;
    if (outbox.length) body.chat = outbox.shift();
    body._lk = lk;
    return body;
  }
  async function sendNow() {
    if (busy || !WH.state.lexus) return;
    busy = true;
    const body = payload();
    const lk = body._lk; delete body._lk;
    try {
      const res = await fetch(rtUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(body) });
      if (!res.ok) throw new Error('rt ' + res.status);
      const j = await res.json();
      if (j && j.ok) {
        Multi.me = j.me;
        if (body.look) lastLook = lk;
        if (!joined) { joined = true; since = j.seq; }
        else { (j.chat || []).forEach((m) => { addChat(m); since = Math.max(since, m.id); }); if (j.seq > since && !(j.chat || []).length) since = j.seq; }
        applyUsers(j.users || []);
        Multi.online = (j.users || []).length + 1;
        if (ui.online) ui.online.textContent = Multi.online + ' online';
      }
    } catch (e) { /* offline for a moment: the next poll tries again */ } finally { busy = false; }
  }

  // ------------------------------------------------------------------ the Avatar Studio
  const FIELDS = {
    kinds: [['human-male', 'Man', { kind: 'human', gender: 'male' }], ['human-female', 'Woman', { kind: 'human', gender: 'female' }], ['dog', 'Dog', { kind: 'dog' }], ['cat', 'Cat', { kind: 'cat' }], ['panda', 'Panda', { kind: 'panda' }], ['snake', 'Snake', { kind: 'snake' }]],
    builds: [['thin', 'Thin'], ['normal', 'Normal'], ['fat', 'Fat']],
    hair: [['none', 'Bald'], ['short', 'Short'], ['long', 'Long'], ['spiky', 'Spiky'], ['bun', 'Bun'], ['ponytail', 'Ponytail'], ['afro', 'Afro']],
    outfit: [['gown', 'Lab gown'], ['casual', 'Casual'], ['suit', 'Suit'], ['dress', 'Dress']],
    skins: ['#f6d7c3', '#f1c9a5', '#e0ac82', '#c68a5c', '#8d5a3a', '#5c3a24'],
    hairs: ['#18110b', '#3b2a1a', '#7a4a21', '#c9a24a', '#a31515', '#e5e7eb', '#1d4ed8', '#7c3aed'],
    furs: ['#b9783d', '#d8a05a', '#e5e7eb', '#2b2b2b', '#7a7f87', '#a05a2c', '#caa57a', '#6b8e23']
  };
  let cur = null, saved = null, rebuildTimer = null;
  function buildStudio() {
    const wrap = document.getElementById('wh-canvas-wrap');
    const el = document.createElement('div');
    el.className = 'wh-studio hidden'; el.id = 'wh-studio'; el.setAttribute('role', 'dialog'); el.setAttribute('aria-label', 'Avatar Studio');
    el.innerHTML = '<div class="wh-studio-head"><b>Avatar Studio</b><span>Change how you look. Everybody sees it.</span></div><div class="wh-studio-body" id="wh-studio-body"></div>' +
      '<div class="wh-studio-foot"><button type="button" id="wh-st-random">Surprise me</button><span></span><button type="button" id="wh-st-cancel">Cancel</button><button type="button" id="wh-st-save" class="primary">Save</button></div>';
    wrap.appendChild(el);
    ui.studio = el; ui.body = el.querySelector('#wh-studio-body');
    el.querySelector('#wh-st-cancel').addEventListener('click', () => closeStudio(false));
    el.querySelector('#wh-st-save').addEventListener('click', () => closeStudio(true));
    el.querySelector('#wh-st-random').addEventListener('click', () => { cur = AvatarFactory.random(); applyLook(); renderStudio(); });
    el.addEventListener('keydown', (e) => { e.stopPropagation(); if (e.key === 'Escape') closeStudio(false); });
  }
  const sec = (title, inner) => '<section><h4>' + title + '</h4>' + inner + '</section>';
  const chips = (name, list, value) => '<div class="wh-chips">' + list.map(([v, l]) => '<button type="button" data-set="' + name + '" data-v="' + v + '" class="' + (String(v) === String(value) ? 'on' : '') + '">' + l + '</button>').join('') + '</div>';
  const swatches = (name, list, value) => '<div class="wh-swatches">' + list.map(c => '<button type="button" data-set="' + name + '" data-v="' + c + '" class="' + (c === value ? 'on' : '') + '" style="background:' + c + '" aria-label="' + c + '"></button>').join('') + '<input type="color" data-color="' + name + '" value="' + value + '" aria-label="Pick a colour"></div>';
  function renderStudio() {
    const c = cur, human = c.kind === 'human', animal = !human;
    const kindKey = human ? 'human-' + c.gender : c.kind;
    let h = '';
    h += sec('Who are you?', '<div class="wh-chips">' + FIELDS.kinds.map(([k, l]) => '<button type="button" data-kind="' + k + '" class="' + (k === kindKey ? 'on' : '') + '">' + l + '</button>').join('') + '</div>');
    h += sec('Body', chips('build', FIELDS.builds, c.build) + '<label class="wh-range">Height <input type="range" min="0.8" max="1.2" step="0.01" value="' + c.height + '" data-range="height"></label>');
    if (human) {
      h += sec('Skin', swatches('skin', FIELDS.skins, c.skin));
      h += sec('Hair', chips('hair', FIELDS.hair, c.hair) + swatches('hairColor', FIELDS.hairs, c.hairColor));
      h += sec('Clothes', chips('outfit', FIELDS.outfit, c.outfit) + '<label class="wh-colorrow">Clothes colour <input type="color" data-color="outfitColor" value="' + c.outfitColor + '"></label><label class="wh-colorrow">Trousers <input type="color" data-color="pants" value="' + c.pants + '"></label>');
    } else if (c.kind !== 'panda') {
      h += sec(c.kind === 'snake' ? 'Skin pattern' : 'Fur', swatches('fur', FIELDS.furs, c.fur) + '<label class="wh-colorrow">Patches <input type="color" data-color="accent" value="' + c.accent + '"></label>');
    } else {
      h += sec('Panda', '<label class="wh-colorrow">Patches <input type="color" data-color="accent" value="' + c.accent + '"></label>');
    }
    h += sec('Details', '<label class="wh-colorrow">Eyes <input type="color" data-color="eyes" value="' + c.eyes + '"></label>' + (human ? '<div class="wh-chips"><button type="button" data-toggle="glasses" class="' + (c.glasses ? 'on' : '') + '">Glasses</button><button type="button" data-toggle="cap" class="' + (c.cap ? 'on' : '') + '">Cap</button></div>' : ''));
    void animal;
    ui.body.innerHTML = h;
    ui.body.querySelectorAll('[data-kind]').forEach((b) => b.addEventListener('click', () => { const f = FIELDS.kinds.find(k => k[0] === b.dataset.kind)[2]; Object.assign(cur, f); applyLook(); renderStudio(); }));
    ui.body.querySelectorAll('[data-set]').forEach((b) => b.addEventListener('click', () => { cur[b.dataset.set] = b.dataset.v; applyLook(); renderStudio(); }));
    ui.body.querySelectorAll('[data-toggle]').forEach((b) => b.addEventListener('click', () => { cur[b.dataset.toggle] = !cur[b.dataset.toggle]; applyLook(); renderStudio(); }));
    ui.body.querySelectorAll('[data-color]').forEach((i) => i.addEventListener('input', () => { cur[i.dataset.color] = i.value; applyLook(); }));
    ui.body.querySelectorAll('[data-range]').forEach((i) => i.addEventListener('input', () => { cur[i.dataset.range] = parseFloat(i.value); applyLook(); }));
  }
  // the avatar in the room changes while you choose
  function applyLook() {
    cur = AvatarFactory.normalize(cur);
    clearTimeout(rebuildTimer);
    rebuildTimer = setTimeout(() => WH.rebuildAvatar(cur), 40);
  }
  function openStudio() {
    if (Multi.studioOpen) return;
    Multi.studioOpen = true;
    if (WH.setPilot) WH.setPilot(false);
    saved = AvatarFactory.normalize(Multi.myLook);
    cur = Object.assign({}, saved);
    renderStudio();
    ui.studio.classList.remove('hidden');
    if (window.AsylumGame) window.AsylumGame.qPressCount = 0;
    // look at the avatar from the front
    const lex = WH.state.lexus, p = lex.root.position, yaw = lex.root.rotation.y;
    WH.animateCameraTo(new THREE.Vector3(p.x + Math.sin(yaw) * 3.0, 1.5, p.z + Math.cos(yaw) * 3.0), new THREE.Vector3(p.x, 1.0, p.z), 700);
    if (window.lexusSay) window.lexusSay('✨ <b>Avatar Studio</b>: choose how you look. Save when you like it.', 'happy');
  }
  async function closeStudio(save) {
    if (!Multi.studioOpen) return;
    if (save) {
      try {
        const res = await fetch(avatarUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ look: cur }) });
        const j = await res.json();
        if (j && j.ok) { Multi.myLook = AvatarFactory.normalize(j.look); WH.rebuildAvatar(Multi.myLook); if (window.lexusSay) window.lexusSay('Looking good! Everybody can see your new look.', 'happy'); }
        else throw new Error('save');
      } catch (e) { if (window.lexusSay) window.lexusSay('I could not save your look right now. It stays for this visit only.', 'surprised'); Multi.myLook = AvatarFactory.normalize(cur); }
    } else {
      WH.rebuildAvatar(saved);
      Multi.myLook = saved;
    }
    ui.studio.classList.add('hidden');
    Multi.studioOpen = false;
  }

  // Q three times on the ring
  let qCount = 0, qLast = 0;
  Multi.onQ = function (lex) {
    if (!lex || !lex.root || Multi.studioOpen) return;
    const p = lex.root.position;
    if (Math.hypot(p.x - STUDIO.x, p.z - STUDIO.z) > STUDIO.r) return;
    const now = performance.now();
    if (now - qLast > 3000) qCount = 0;
    qLast = now;
    qCount++;
    if (qCount === 1 && window.lexusSay) window.lexusSay('The mirror on the floor shimmers... (Q again)', 'curious');
    else if (qCount === 2 && window.lexusSay) window.lexusSay('It is humming! (once more)', 'surprised');
    else if (qCount >= 3) { qCount = 0; openStudio(); }
  };

  // ------------------------------------------------------------------ start when the 3D warehouse is ready
  function start() {
    buildChat();
    buildStudio();
    WH.scene.add(localHolder);
    // the glowing ring on the floor
    const ring = new THREE.Mesh(new THREE.RingGeometry(STUDIO.ringR - 0.15, STUDIO.ringR, 48), new THREE.MeshBasicMaterial({ color: 0x38bdf8, transparent: true, opacity: 0.55, side: THREE.DoubleSide }));
    ring.rotation.x = -Math.PI / 2; ring.position.set(STUDIO.x, 0.03, STUDIO.z);
    const disc = new THREE.Mesh(new THREE.CircleGeometry(STUDIO.ringR - 0.1, 40), new THREE.MeshBasicMaterial({ color: 0x0ea5e9, transparent: true, opacity: 0.12, side: THREE.DoubleSide }));
    disc.rotation.x = -Math.PI / 2; disc.position.set(STUDIO.x, 0.028, STUDIO.z);
    ring.name = disc.name = 'wh-studio-ring';
    WH.scene.add(ring, disc);
    // the avatar of this account
    WH.rebuildAvatar(Multi.myLook);
    WH.onFrame((dt, time) => frameRemotes(dt, time));
    sendNow();
    setInterval(() => { if (!document.hidden || Math.random() < 0.25) sendNow(); }, 110);
  }
  const WHref = () => window.WH;
  let WH = null;
  (function wait() { if (WHref() && WHref().state && WHref().state.lexus) { WH = WHref(); start(); } else setTimeout(wait, 200); })();
})();
