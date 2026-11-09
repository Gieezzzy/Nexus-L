/**
 * NEXUS-L Warehouse 3D Locations Tracker Engine
 * Three.js Procedural 3D Model matching 39.61 m² Laboratory Archive Room
 */
(function () {
  'use strict';

  const root = document.getElementById('wh-root');
  if (!root) return;

  const boxesUrl = root.dataset.boxesUrl;
  const racksUrl = root.dataset.racksUrl;
  const saveUrl = root.dataset.saveUrl;
  const deleteUrl = root.dataset.deleteUrl;
  const isAdmin = root.dataset.isAdmin === '1';
  const csrfToken = root.dataset.csrfToken;
  const csrfHash = root.dataset.csrfHash;

  // State
  const state = {
    boxes: [],
    racks: [],
    activeCategory: 'ALL',
    searchQuery: '',
    selectedBox: null,
    highlightedBoxMesh: null,
    pathLine: null,
    pathHalo: null,
    pathComet: null,
    pathCurve: null,
    pathTexture: null,
    markerPin: null,
    isCameraMoving: false,
    // Lexus AI Guide Character
    lexus: null,
    followLexus: true
  };

  // DOM Elements
  const dom = {
    canvas: document.getElementById('wh-canvas'),
    canvasWrap: document.getElementById('wh-canvas-wrap'),
    loadingOverlay: document.getElementById('wh-canvas-loading'),
    searchInput: document.getElementById('wh-search-input'),
    searchClear: document.getElementById('wh-search-clear'),
    categoryChips: document.querySelectorAll('#wh-category-chips .wh-chip'),
    // Views
    btnResetCam: document.getElementById('wh-btn-reset-cam'),
    btnDoorView: document.getElementById('wh-view-door'),
    btnOverheadView: document.getElementById('wh-view-overhead'),
    btnAisleView: document.getElementById('wh-view-aisle'),
    btnFullscreen: document.getElementById('wh-view-fs'),
    // KPIs
    kpiTotal: document.getElementById('wh-kpi-total'),
    kpiBonding: document.getElementById('wh-kpi-bonding'),
    kpiMaterial: document.getElementById('wh-kpi-material'),
    kpiTechTrial: document.getElementById('wh-kpi-techtrial'),
    kpiDisposal: document.getElementById('wh-kpi-disposal'),
    // Floating Inspector Card
    inspector: document.getElementById('wh-box-inspector'),
    inspectorClose: document.getElementById('wh-bi-close'),
    biCatBadge: document.getElementById('bi-cat-badge'),
    biBoxLabel: document.getElementById('bi-box-label'),
    biRackName: document.getElementById('bi-rack-name'),
    biTierNum: document.getElementById('bi-tier-num'),
    biSlotNum: document.getElementById('bi-slot-num'),
    biBoxCode: document.getElementById('bi-box-code'),
    biDateRange: document.getElementById('bi-date-range'),
    biStatusBadge: document.getElementById('bi-status-badge'),
    biCreatedBy: document.getElementById('bi-created-by'),
    biContents: document.getElementById('bi-contents'),
    btnGuideMe: document.getElementById('wh-btn-guide-me'),
    btnEditThisBox: document.getElementById('wh-btn-edit-this-box'),
    pathBanner: document.getElementById('wh-path-banner'),
    pathText: document.getElementById('wh-path-text'),
    pathClose: document.getElementById('wh-path-close'),
    // Lexus AI Guide HUD
    lexusHud: document.getElementById('wh-lexus-hud'),
    lexusSpeech: document.getElementById('wh-lexus-speech'),
    lexusStatusTxt: document.getElementById('wh-lexus-status-txt'),
    btnFollowLexus: document.getElementById('wh-btn-follow-lexus'),
    followLexusTxt: document.getElementById('wh-follow-lexus-txt'),
    btnWaveLexus: document.getElementById('wh-btn-bubble-wave'),
    btnDoorLexus: document.getElementById('wh-btn-bubble-door'),
    // Lexus's speech bubble (it floats over his head)
    bubble3d: document.getElementById('wh-lexus-bubble-3d'),
    bubbleText: document.getElementById('wh-lexus-speech-text'),
    bubbleAudioWave: document.getElementById('wh-lexus-audio-wave'),
    bubbleAskForm: document.getElementById('wh-bubble-ask-form'),
    bubbleInput: document.getElementById('wh-bubble-input'),
    bubbleStatus: null,
    // Admin Drawer & Table
    btnOpenAdmin: document.getElementById('wh-btn-open-admin'),
    adminDrawer: document.getElementById('wh-admin-drawer'),
    adminBackdrop: document.getElementById('wh-admin-backdrop'),
    adminClose: document.getElementById('wh-ad-close'),
    adminDismiss: document.getElementById('wh-ad-dismiss'),
    boxesTableTbody: document.getElementById('wh-boxes-tbody'),
    tblSearch: document.getElementById('wh-tbl-search'),
    tblRackFilter: document.getElementById('wh-tbl-rack-filter'),
    tblTierFilter: document.getElementById('wh-tbl-tier-filter'),
    tblCount: document.getElementById('wh-tbl-count'),
    btnAddNewBox: document.getElementById('wh-btn-add-new-box'),
    // Box Form Modal
    boxModalBackdrop: document.getElementById('wh-box-modal-backdrop'),
    boxModalClose: document.getElementById('wh-modal-close'),
    boxModalCancel: document.getElementById('wh-modal-cancel'),
    boxModalTitle: document.getElementById('wh-modal-title'),
    boxForm: document.getElementById('wh-box-form'),
    formId: document.getElementById('box-form-id'),
    formLabel: document.getElementById('box-form-label'),
    formCode: document.getElementById('box-form-code'),
    formCategory: document.getElementById('box-form-category'),
    formRack: document.getElementById('box-form-rack'),
    formTier: document.getElementById('box-form-tier'),
    formSlot: document.getElementById('box-form-slot'),
    formDate: document.getElementById('box-form-date'),
    formMonth: document.getElementById('box-form-month'),
    formStatus: document.getElementById('box-form-status'),
    formDesc: document.getElementById('box-form-desc'),
    // FIFO Color Reference Modal
    btnFifoOpen: document.getElementById('wh-btn-fifo-open'),
    fifoModalBackdrop: document.getElementById('wh-fifo-modal-backdrop'),
    fifoClose: document.getElementById('wh-fifo-close'),
    fifoDismiss: document.getElementById('wh-fifo-dismiss'),
    biFifoPill: document.getElementById('bi-fifo-pill')
  };

  // Official 12-Month FIFO Color Wheel (先进先出) matching media_1794181515940_9cbb9861.jpg
  const FIFO_COLORS = {
    1:  { name: 'January', zh: '一月', hex: '#3e2723', label: 'Dark Brown' },
    2:  { name: 'February', zh: '二月', hex: '#ba68c8', label: 'Lavender' },
    3:  { name: 'March', zh: '三月', hex: '#880e4f', label: 'Maroon' },
    4:  { name: 'April', zh: '四月', hex: '#e65100', label: 'Orange (For Disposal)' },
    5:  { name: 'May', zh: '五月', hex: '#0288d1', label: 'Sky Blue' },
    6:  { name: 'June', zh: '六月', hex: '#f57f17', label: 'Golden Yellow' },
    7:  { name: 'July', zh: '七月', hex: '#c2185b', label: 'Magenta Pink' },
    8:  { name: 'August', zh: '八月', hex: '#2e7d32', label: 'Bright Green' },
    9:  { name: 'September', zh: '九月', hex: '#c62828', label: 'Red' },
    10: { name: 'October', zh: '十月', hex: '#7986cb', label: 'Periwinkle Blue' },
    11: { name: 'November', zh: '十一月', hex: '#00838f', label: 'Teal Cyan' },
    12: { name: 'December', zh: '十二月', hex: '#212121', label: 'Black' }
  };

  function getBoxFifoMonth(b) {
    if (!b) return null;
    if (b.month_year) {
      const parts = String(b.month_year).split('-');
      if (parts.length >= 2) {
        const m = parseInt(parts[1], 10);
        if (m >= 1 && m <= 12) return { ...FIFO_COLORS[m], monthNum: m };
      }
    }
    const text = ((b.label || '') + ' ' + (b.date_range || '')).toLowerCase();
    const monthNames = [
      'january', 'february', 'march', 'april', 'may', 'june',
      'july', 'august', 'september', 'october', 'november', 'december'
    ];
    for (let i = 0; i < 12; i++) {
      const full = monthNames[i];
      const short = full.slice(0, 3);
      const regex = new RegExp(`\\b(${full}|${short})\\b`, 'i');
      if (regex.test(text)) {
        return { ...FIFO_COLORS[i + 1], monthNum: i + 1 };
      }
    }
    return null;
  }

  // Three.js Globals
  let scene, camera, renderer, controls;
  const frameHooks = [];                       // functions called every frame (the other users' avatars)
  const interactiveBoxes = []; // Array of box meshes with userData = boxData
  const rackGroups = {}; // rack_id -> Group
  const raycaster = new THREE.Raycaster();
  const mouse = new THREE.Vector2();

  // The warehouse is 45 x 45 m (x and z from -22.5 to +22.5, z grows toward the door) and keeps the arrangement of the lab floor plan:
  // PLAN_SX / PLAN_SZ are what the plan (7.2 x 6.56 m) is stretched by. The entrance door is in the south wall at x = DOOR_X.
  const ROOM_W = 45.0;
  const ROOM_L = 45.0;
  const HALF_W = ROOM_W / 2;
  const HALF_L = ROOM_L / 2;
  const ROOM_H = 5.2;
  const DOOR_X = 15.7;
  const DOOR_W = 3.3;
  const GUIDE_START = { x: DOOR_X, z: 17.1 };   // where Lexus waits for visitors, just inside the door

  const CAM_PRESETS = {
    door: { pos: new THREE.Vector3(DOOR_X, 2.1, 21.8), target: new THREE.Vector3(DOOR_X, 1.35, GUIDE_START.z) },   // looking through the entrance at Lexus
    overhead: { pos: new THREE.Vector3(0.0, 56.0, 0.5), target: new THREE.Vector3(0.0, 0.0, 0.0) },               // top-down view of the whole warehouse
    aisle: { pos: new THREE.Vector3(4.5, 2.6, 20.5), target: new THREE.Vector3(4.5, 1.8, 0.0) },                  // looking down the aisles from the south lane
    overview: { pos: new THREE.Vector3(30.0, 22.0, 32.0), target: new THREE.Vector3(0.0, 2.0, -2.0) }             // balanced 3D perspective
  };

  // =========================================================================
  // 1. INITIALIZE THREE.JS SCENE
  // =========================================================================
  function init3D() {
    const width = dom.canvasWrap.clientWidth;
    const height = dom.canvasWrap.clientHeight || 560;

    scene = new THREE.Scene();
    scene.background = new THREE.Color(0x0f172a); // Deep slate-blue ambient
    scene.fog = new THREE.FogExp2(0x0f172a, 0.011);

    camera = new THREE.PerspectiveCamera(48, width / height, 0.1, 100);
    // Initial start view is on the entrance door welcoming the guest!
    camera.position.copy(CAM_PRESETS.door.pos);

    renderer = new THREE.WebGLRenderer({
      canvas: dom.canvas,
      antialias: true,
      powerPreference: 'high-performance'
    });
    renderer.setSize(width, height);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;

    // Controls
    controls = new THREE.OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.08;
    controls.maxPolarAngle = Math.PI / 2 - 0.02; // Don't clip under floor
    controls.minDistance = 1.0;
    controls.maxDistance = 60.0; // far enough to see the whole 45x45 m warehouse
    controls.target.copy(CAM_PRESETS.door.target);

    // Lighting
    setupLighting();

    // Construct Architectural Warehouse (39.61 m² exactly matching CAD Floor Plan)
    buildWarehouseRoom();

    // Construct 3D AI Guide "Lexus" (Toned Skin, Male, 170cm, Dark Brown Hair, Lab Gown)
    buildLexusCharacter();

    // Initialize Asylum Horror Easter Egg
    if (window.AsylumGame) {
      window.AsylumGame.init({
        scene: scene,
        camera: camera,
        renderer: renderer,
        controls: controls,
        state: state,
        setPilot: setPilot,
        onReturn: (reason) => {
          setPilot(false);
        }
      });
    }

    // Animation loop
    animate();

    // what the multi-user script (warehouse-multi.js) needs
    window.WH = {
      scene, state,
      get camera() { return camera; }, get controls() { return controls; },
      rebuildAvatar, animateCameraTo, setPilot: (on) => setPilot(on), isPilot: () => pilot.on,
      onFrame: (fn) => frameHooks.push(fn)
    };

    // Resize listener
    window.addEventListener('resize', onWindowResize);
    dom.canvas.addEventListener('click', onCanvasClick);
    dom.canvas.addEventListener('mousemove', onCanvasMouseMove);
  }

  function setupLighting() {
    // Soft ambient + the sun through the east windows; both dim when the lights are switched off
    state.ambient = new THREE.AmbientLight(0xffffff, 0.65);
    scene.add(state.ambient);

    state.sun = new THREE.DirectionalLight(0xfff7ed, 0.9);
    state.sun.position.set(5.5, 4.8, 1.0);
    state.sun.castShadow = true;
    state.sun.shadow.mapSize.width = 1024;
    state.sun.shadow.mapSize.height = 1024;
    state.sun.shadow.camera.near = 0.5;
    state.sun.shadow.camera.far = 18;
    state.sun.shadow.camera.left = -4.5;
    state.sun.shadow.camera.right = 4.5;
    state.sun.shadow.camera.top = 4.5;
    state.sun.shadow.camera.bottom = -4.5;
    state.sun.shadow.bias = -0.001;
    scene.add(state.sun);

    buildBulbs();
    applyLights();
  }

  // -------------------------------------------------------------------------
  // INTERACTIVE LIGHT BULBS: pendant lamps over the aisles. Click one to switch it, or use the bulb button in Lexus's bubble for all of them.
  // -------------------------------------------------------------------------
  function buildBulbs() {
    state.bulbs = [];
    state.bulbMeshes = [];
    const spots = [];
    [-4, 8].forEach(z => AISLE_X.forEach(x => spots.push([x, z])));      // two lamps in every aisle
    spots.push([DOOR_X, 19.0]);                                          // one at the entrance
    spots.forEach(([x, z]) => {
      const g = new THREE.Group();
      g.position.set(x, 0, z);
      const cord = new THREE.Mesh(new THREE.CylinderGeometry(0.012, 0.012, 0.7, 6), new THREE.MeshBasicMaterial({ color: 0x64748b }));
      cord.position.y = ROOM_H - 0.35;
      g.add(cord);
      const cap = new THREE.Mesh(new THREE.CylinderGeometry(0.07, 0.09, 0.12, 12), new THREE.MeshStandardMaterial({ color: 0x475569, metalness: 0.6, roughness: 0.4 }));
      cap.position.y = ROOM_H - 0.76;
      g.add(cap);
      const bulbMat = new THREE.MeshStandardMaterial({ color: 0xfff3c4, emissive: 0xffd36b, emissiveIntensity: 1.6, roughness: 0.3 });
      const bulb = new THREE.Mesh(new THREE.SphereGeometry(0.16, 20, 16), bulbMat);
      bulb.position.y = ROOM_H - 0.92;
      g.add(bulb);
      // soft glow shell and an invisible bigger sphere that makes the bulb easy to hit with the pointer
      const glow = new THREE.Mesh(new THREE.SphereGeometry(0.42, 16, 12), new THREE.MeshBasicMaterial({ color: 0xffe08a, transparent: true, opacity: 0.18, depthWrite: false, blending: THREE.AdditiveBlending }));
      glow.position.y = ROOM_H - 0.92;
      g.add(glow);
      const hit = new THREE.Mesh(new THREE.SphereGeometry(0.5, 8, 6), new THREE.MeshBasicMaterial({ visible: false }));
      hit.position.y = ROOM_H - 0.92;
      g.add(hit);
      const light = new THREE.PointLight(0xfff1c9, 1.05, 13, 1.7);
      light.position.y = ROOM_H - 1.1;
      g.add(light);
      scene.add(g);
      const b = { group: g, bulbMat, glow, light, on: true };
      hit.userData.bulb = b;
      state.bulbs.push(b);
      state.bulbMeshes.push(hit);
    });
  }

  function setBulb(b, on) {
    b.on = !!on;
    b.light.intensity = b.on ? 1.05 : 0;
    b.bulbMat.emissiveIntensity = b.on ? 1.6 : 0.0;
    b.bulbMat.color.setHex(b.on ? 0xfff3c4 : 0x475569);
    b.glow.visible = b.on;
  }

  // Everything that depends on how many lamps are lit: how dark the room is
  function applyLights() {
    const lit = state.bulbs.filter(b => b.on).length / Math.max(1, state.bulbs.length);
    state.lightLevel = lit;
    if (state.ambient) state.ambient.intensity = 0.16 + 0.49 * lit;
    if (state.sun) state.sun.intensity = 0.22 + 0.68 * lit;
    const bg = new THREE.Color(0x060b16).lerp(new THREE.Color(0x0f172a), lit);
    scene.background = bg;
    if (scene.fog) scene.fog.color.copy(bg);
    const btn = document.getElementById('wh-btn-bulb');
    if (btn) {
      btn.classList.toggle('active', lit > 0);
      btn.title = lit > 0 ? 'Lights on: click to switch every lamp off (or click a lamp in the room)' : 'Lights off: click to switch every lamp on';
    }
  }

  function setAllBulbs(on) {
    state.bulbs.forEach(b => setBulb(b, on));
    applyLights();
    lexusSay(on ? pick(PERSONA.lightsOn) : pick(PERSONA.lightsOff), on ? 'happy' : 'surprised');
  }

  function toggleBulb(b) {
    setBulb(b, !b.on);
    applyLights();
    if (state.lightLevel === 0) lexusSay(pick(PERSONA.lightsOff), 'surprised');
    else if (state.lightLevel === 1) lexusSay(pick(PERSONA.lightsOn), 'happy');
  }

  // =========================================================================
  // 2. BUILD ARCHITECTURAL WAREHOUSE (39.61 m² matching CAD Floor Plan)
  // =========================================================================
  function buildWarehouseRoom() {
    // The floor plan stretched to 45 x 45 m: the storage enclosure in the north-west corner, a pillar in the north-east corner,
    // the entrance door in the south wall and the window wall on the east. Positions follow the plan (see PLAN_SX / PLAN_SZ).
    const floor = new THREE.Mesh(new THREE.PlaneGeometry(ROOM_W, ROOM_L), new THREE.MeshStandardMaterial({ color: 0x0f172a, roughness: 0.82, metalness: 0.06 }));
    floor.rotation.x = -Math.PI / 2;
    floor.receiveShadow = true;
    scene.add(floor);

    const grid = new THREE.GridHelper(ROOM_W, 45, 0x0284c7, 0x1e293b);   // 1 m squares
    grid.position.y = 0.001;
    scene.add(grid);

    buildYellowFloorLines();
    buildWalls();
    buildWindows();
    buildDoorway();
    buildTopLeftPartition();
  }

  // Walkways (centre lines of the aisles between the racks). Everything that guides people uses these, so a route never crosses a rack.
  const AISLE_X = [-13.6, -3.76, 4.55, 13.1, 19.0];   // north-south aisles in front of Rack 3, Rack 2, the Partition rack, Rack 1 and the Window rack
  const LANE_S = 19.5;                                // the lane along the south wall, in front of the racks

  function buildYellowFloorLines() {
    const lineMat = new THREE.MeshBasicMaterial({ color: 0xfacc15 }); // safety yellow
    const flat = (w, l, x, z) => {
      const m = new THREE.Mesh(new THREE.PlaneGeometry(w, l), lineMat);
      m.rotation.x = -Math.PI / 2;
      m.position.set(x, 0.005, z);
      scene.add(m);
    };
    const aisle = (x, zNorth) => flat(0.16, LANE_S - zNorth, x, (LANE_S + zNorth) / 2);
    aisle(AISLE_X[0], -6);      // Rack 3
    aisle(AISLE_X[1], -6);      // Rack 2
    aisle(AISLE_X[2], -13);     // Partition rack
    aisle(AISLE_X[3], -18.8);   // Rack 1, up to Rack 4
    aisle(AISLE_X[4], -16);     // Window rack
    flat(AISLE_X[4] - AISLE_X[0], 0.16, (AISLE_X[0] + AISLE_X[4]) / 2, LANE_S);   // the south lane
    flat(0.16, HALF_L - 0.3 - LANE_S, DOOR_X, (HALF_L - 0.3 + LANE_S) / 2);        // from the door to the lane
  }

  function buildWalls() {
    const wallMat = new THREE.MeshStandardMaterial({ color: 0x1e293b, roughness: 0.88, metalness: 0.05 });
    const T = 0.25;
    const add = (w, h, d, x, y, z) => {
      const m = new THREE.Mesh(new THREE.BoxGeometry(w, h, d), wallMat);
      m.position.set(x, y, z);
      m.receiveShadow = true;
      scene.add(m);
      return m;
    };
    const W = ROOM_W + 2 * T;

    add(W, ROOM_H, T, 0, ROOM_H / 2, -HALF_L - T / 2);                 // north wall
    add(T, ROOM_H, ROOM_L, -HALF_W - T / 2, ROOM_H / 2, 0);            // west wall

    // south wall in two pieces with the door opening at DOOR_X, and a header over it
    const dl = DOOR_X - DOOR_W / 2, dr = DOOR_X + DOOR_W / 2;
    add(dl + HALF_W + T, ROOM_H, T, (-HALF_W - T + dl) / 2, ROOM_H / 2, HALF_L + T / 2);
    add(HALF_W + T - dr, ROOM_H, T, (dr + HALF_W + T) / 2, ROOM_H / 2, HALF_L + T / 2);
    add(DOOR_W, ROOM_H - 3.3, T, DOOR_X, 3.3 + (ROOM_H - 3.3) / 2, HALF_L + T / 2);

    // pillar in the north-east corner (the small square of the plan)
    add(4.75, ROOM_H, 5.0, HALF_W - 2.375, ROOM_H / 2, -HALF_L + 2.5);

    // framed FIFO colour poster on the north wall, above Rack 4
    const poster = new THREE.Mesh(new THREE.PlaneGeometry(3.0, 2.0), new THREE.MeshBasicMaterial({ color: 0xffffff }));
    poster.position.set(8.8, 3.0, -HALF_L + 0.02);
    scene.add(poster);
  }

  function buildWindows() {
    // the east wall: solid at both ends and between the two window bays; glass in the bays
    const wallMat = new THREE.MeshStandardMaterial({ color: 0x1e293b, roughness: 0.88, metalness: 0.05 });
    const glassMat = new THREE.MeshPhysicalMaterial({ color: 0x93c5fd, transparent: true, opacity: 0.32, roughness: 0.1, transmission: 0.8 });
    const T = 0.25, cx = HALF_W + T / 2;
    const box = (h, z0, z1, y0) => {
      const m = new THREE.Mesh(new THREE.BoxGeometry(T, h, z1 - z0), wallMat);
      m.position.set(cx, y0 + h / 2, (z0 + z1) / 2);
      scene.add(m);
    };
    const bays = [[-16.2, -3.9], [-1.2, 14.8]];
    box(ROOM_H, -HALF_L - T, bays[0][0], 0);                // north end (behind the pillar)
    box(ROOM_H, bays[0][1], bays[1][0], 0);                 // post between the two bays
    box(ROOM_H, bays[1][1], HALF_L + T, 0);                 // south end
    bays.forEach(([a, b]) => {
      box(1.25, a, b, 0);                                   // below the glass
      box(ROOM_H - 4.25, a, b, 4.25);                       // above the glass
      const g = new THREE.Mesh(new THREE.BoxGeometry(0.1, 3.0, b - a), glassMat);
      g.position.set(cx, 2.75, (a + b) / 2);
      scene.add(g);
    });
  }

  function buildDoorway() {
    // the entrance in the south wall at DOOR_X
    const frameMat = new THREE.MeshStandardMaterial({ color: 0x0284c7, roughness: 0.4 });
    const leafMat = new THREE.MeshStandardMaterial({ color: 0x1e293b, roughness: 0.5 });
    const dz = HALF_L + 0.125;
    const l = new THREE.Mesh(new THREE.BoxGeometry(0.14, 3.3, 0.3), frameMat);
    l.position.set(DOOR_X - DOOR_W / 2, 1.65, dz);
    scene.add(l);
    const r = l.clone();
    r.position.x = DOOR_X + DOOR_W / 2;
    scene.add(r);
    const top = new THREE.Mesh(new THREE.BoxGeometry(DOOR_W + 0.14, 0.16, 0.3), frameMat);
    top.position.set(DOOR_X, 3.3, dz);
    scene.add(top);
    const leaf = new THREE.Mesh(new THREE.BoxGeometry(1.55, 3.2, 0.08), leafMat);   // door standing open, turned into the room
    leaf.position.set(DOOR_X - 0.8, 1.6, HALF_L - 0.95);
    leaf.rotation.y = 0.55;
    scene.add(leaf);

    // sign above the entrance, facing outwards
    const canvas = document.createElement('canvas');
    canvas.width = 512;
    canvas.height = 128;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#0f172a';
    ctx.fillRect(0, 0, 512, 128);
    ctx.strokeStyle = '#0284c7';
    ctx.lineWidth = 6;
    ctx.strokeRect(4, 4, 504, 120);
    ctx.font = 'bold 36px sans-serif';
    ctx.fillStyle = '#38bdf8';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText('DTP QC LABORATORY', 256, 45);
    ctx.font = 'bold 24px sans-serif';
    ctx.fillStyle = '#f8fafc';
    ctx.fillText('WAREHOUSE LOCATIONS TRACKER', 256, 88);
    const sign = new THREE.Mesh(new THREE.PlaneGeometry(3.3, 0.6), new THREE.MeshBasicMaterial({ map: new THREE.CanvasTexture(canvas) }));
    sign.position.set(DOOR_X, 3.9, HALF_L + 0.26);
    scene.add(sign);
  }

  function buildTopLeftPartition() {
    // the storage enclosure of the plan: the north-west block, closed by a wall on its east and south sides
    const partMat = new THREE.MeshStandardMaterial({ color: 0x334155, roughness: 0.85 });
    const wx = 0.4, wz = -7.96, h = 4.0, t = 0.2;
    const east = new THREE.Mesh(new THREE.BoxGeometry(t, h, wz + HALF_L), partMat);
    east.position.set(wx, h / 2, (-HALF_L + wz) / 2);
    east.name = 'wh-partition-east';   // the asylum game cuts its secret door into this wall
    scene.add(east);
    const south = new THREE.Mesh(new THREE.BoxGeometry(wx + HALF_W, h, t), partMat);
    south.position.set((-HALF_W + wx) / 2, h / 2, wz);
    scene.add(south);
  }

  function renderRacks(racksData) {
    const blueRackMat = new THREE.MeshStandardMaterial({
      color: 0x1d4ed8, // Industrial steel blue from photos
      roughness: 0.45,
      metalness: 0.6
    });

    const shelfBeamMat = new THREE.MeshStandardMaterial({
      color: 0x2563eb,
      roughness: 0.5,
      metalness: 0.5
    });

    const galvanizedShelfMat = new THREE.MeshStandardMaterial({
      color: 0x64748b, // Galvanized metal shelf deck
      roughness: 0.6,
      metalness: 0.7
    });

    racksData.forEach(r => {
      const g = new THREE.Group();
      g.name = r.id;
      g.userData = r;

      const rw = r.size.w;
      const rh = r.size.h;
      const rl = r.size.l;
      const tiers = r.tiers || 5;
      const isHorizontal = rw > rl; // Rack 4 is horizontal across north wall!

      const halfW = rw / 2 - 0.025;
      const halfL = rl / 2 - 0.025;
      const colW = 0.055;

      // Heavy Upright Steel Columns
      const colGeo = new THREE.BoxGeometry(colW, rh, colW);

      if (!isHorizontal) {
        // Vertical Racks (Rack 1, Rack 2, Rack 3, Partition Rack, Window Rack)
        const colPositions = [
          [-halfW, rh / 2, -halfL],
          [halfW, rh / 2, -halfL],
          [-halfW, rh / 2, halfL],
          [halfW, rh / 2, halfL]
        ];

        // If rack is long (like Rack 1 or Window Rack), add intermediate support columns
        if (rl > 3.0) {
          colPositions.push([-halfW, rh / 2, 0]);
          colPositions.push([halfW, rh / 2, 0]);
        }

        colPositions.forEach(cp => {
          const col = new THREE.Mesh(colGeo, blueRackMat);
          col.position.set(cp[0], cp[1], cp[2]);
          col.castShadow = true;
          col.receiveShadow = true;
          g.add(col);
        });

        // Diagonal side bracing
        const braceGeo = new THREE.CylinderGeometry(0.012, 0.012, Math.hypot(rw, rh / 3));
        for (let s of [-halfL, halfL]) {
          const brace = new THREE.Mesh(braceGeo, blueRackMat);
          brace.position.set(0, rh / 2, s);
          brace.rotation.z = 0.45;
          g.add(brace);
        }

        // 5 Horizontal Shelf Tiers along Z
        const tierHeightStep = (rh - 0.2) / tiers;
        for (let t = 1; t <= tiers; t++) {
          const tierY = 0.15 + (t - 1) * tierHeightStep;

          // Front and back horizontal support beams along Z
          const beamGeo = new THREE.BoxGeometry(0.035, 0.08, rl);
          const frontBeam = new THREE.Mesh(beamGeo, shelfBeamMat);
          frontBeam.position.set(halfW, tierY, 0);
          g.add(frontBeam);

          const backBeam = new THREE.Mesh(beamGeo, shelfBeamMat);
          backBeam.position.set(-halfW, tierY, 0);
          g.add(backBeam);

          // Galvanized shelf plate
          const deckGeo = new THREE.BoxGeometry(rw - 0.02, 0.015, rl - 0.02);
          const deck = new THREE.Mesh(deckGeo, galvanizedShelfMat);
          deck.position.set(0, tierY + 0.035, 0);
          deck.receiveShadow = true;
          g.add(deck);

          // Shelf tier label strip on front beam
          createTierSign(g, r.id, t, halfW + 0.025, tierY, rl / 2 - 0.2, true);
        }

        // Top Banner Sign facing +X walkway (or -X for Window Rack)
        if (r.labels && r.labels.length > 0) {
          createRackBanner(g, r.labels[0], rw, rh, rl, r.theme_color || '#1e40af', false);
        }
      } else {
        // Horizontal Rack along North Wall (Rack 4)
        const bays = 3;
        const bayStep = rw / bays;
        for (let b = 0; b <= bays; b++) {
          const colX = -rw / 2 + b * bayStep;
          [-halfL, halfL].forEach(colZ => {
            const col = new THREE.Mesh(colGeo, blueRackMat);
            col.position.set(colX, rh / 2, colZ);
            col.castShadow = true;
            col.receiveShadow = true;
            g.add(col);
          });
        }

        // 5 Horizontal Shelf Tiers along X
        const tierHeightStep = (rh - 0.2) / tiers;
        for (let t = 1; t <= tiers; t++) {
          const tierY = 0.15 + (t - 1) * tierHeightStep;

          // Front and back horizontal support beams along X
          const beamGeo = new THREE.BoxGeometry(rw, 0.08, 0.035);
          const frontBeam = new THREE.Mesh(beamGeo, shelfBeamMat);
          frontBeam.position.set(0, tierY, halfL); // Faces south into the room
          g.add(frontBeam);

          const backBeam = new THREE.Mesh(beamGeo, shelfBeamMat);
          backBeam.position.set(0, tierY, -halfL);
          g.add(backBeam);

          // Galvanized shelf plate
          const deckGeo = new THREE.BoxGeometry(rw - 0.02, 0.015, rl - 0.02);
          const deck = new THREE.Mesh(deckGeo, galvanizedShelfMat);
          deck.position.set(0, tierY + 0.035, 0);
          deck.receiveShadow = true;
          g.add(deck);

          // Shelf tier label strip on front beam
          createTierSign(g, r.id, t, 0, tierY, halfL + 0.025, false);
        }

        // Top Banner Sign facing South (+Z)
        if (r.labels && r.labels.length > 0) {
          createRackBanner(g, r.labels[0], rw, rh, rl, r.theme_color || '#6366f1', true);
        }
      }

      // Position group in warehouse
      g.position.set(r.pos.x, r.pos.y, r.pos.z);
      scene.add(g);
      rackGroups[r.id] = g;
    });
  }

  function createTierSign(group, rackId, tierNum, x, y, z, isVertical) {
    const canvas = document.createElement('canvas');
    canvas.width = 128;
    canvas.height = 32;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#1e3a8a';
    ctx.fillRect(0, 0, 128, 32);
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 18px sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(`LAYER ${tierNum}`, 64, 16);

    const tex = new THREE.CanvasTexture(canvas);
    const mat = new THREE.MeshBasicMaterial({ map: tex });
    const sign = new THREE.Mesh(new THREE.PlaneGeometry(0.28, 0.06), mat);

    if (isVertical) {
      sign.position.set(x, y, z);
      sign.rotation.y = Math.PI / 2;
    } else {
      sign.position.set(x, y, z);
      // Facing South (+Z)
    }
    group.add(sign);
  }

  function createRackBanner(group, text, rw, rh, rl, themeHex, isHorizontal) {
    const canvas = document.createElement('canvas');
    canvas.width = 512;
    canvas.height = 80;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = themeHex || '#1e40af';
    ctx.fillRect(0, 0, 512, 80);
    ctx.strokeStyle = '#ffffff';
    ctx.lineWidth = 4;
    ctx.strokeRect(4, 4, 504, 72);
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 30px sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(text.toUpperCase(), 256, 40);

    const tex = new THREE.CanvasTexture(canvas);
    const mat = new THREE.MeshBasicMaterial({ map: tex });

    if (isHorizontal) {
      const banner = new THREE.Mesh(new THREE.PlaneGeometry(1.6, 0.25), mat);
      banner.position.set(0, rh - 0.15, rl / 2 + 0.02);
      group.add(banner);
    } else {
      const banner = new THREE.Mesh(new THREE.PlaneGeometry(Math.min(rl * 0.45, 1.8), 0.25), mat);
      banner.position.set(rw / 2 + 0.02, rh - 0.15, 0);
      banner.rotation.y = Math.PI / 2;
      group.add(banner);
    }
  }

  // =========================================================================
  // 4. RENDER 3D CARDBOARD BOXES WITH FIFO LABELS
  // =========================================================================
  function renderBoxes(boxesData) {
    interactiveBoxes.forEach(m => {
      if (m.parent) m.parent.remove(m);
    });
    interactiveBoxes.length = 0;

    boxesData.forEach(b => {
      const rack = state.racks.find(rk => rk.id === b.rack_id);
      if (!rack) return;

      const rackGroup = rackGroups[b.rack_id];
      if (!rackGroup) return;

      const tier = parseInt(b.tier_level, 10) || 1;
      const slot = parseInt(b.slot_index, 10) || 1;
      const totalSlots = rack.slots || 3;
      const isHorizontal = rack.size.w > rack.size.l;

      // Box dimensions scaled appropriately for 5-layer racks
      const boxW = Math.min(rack.size.w * 0.72, 0.44); // Depth
      const boxH = 0.38; // Height
      const boxL = Math.min((isHorizontal ? rack.size.w : rack.size.l) / (totalSlots + 0.8), 0.58); // Length

      const tierHeightStep = (rack.size.h - 0.2) / (rack.tiers || 5);
      const tierY = 0.15 + (tier - 1) * tierHeightStep + boxH / 2 + 0.035;

      let boxColor = 0xb48956; // Standard brown cardboard
      if (b.status === 'FOR_DISPOSAL') boxColor = 0xa87148;
      if (b.status === 'ARCHIVED') boxColor = 0x93785b;

      const boxMat = new THREE.MeshStandardMaterial({
        color: boxColor,
        roughness: 0.8,
        metalness: 0.05
      });

      const labelMat = createBoxLabelMaterial(b);

      if (isHorizontal) {
        // Rack 4: Slots along X, front face facing South (+Z)
        const slotSpacing = (rack.size.w - 0.3) / totalSlots;
        const slotX = -rack.size.w / 2 + 0.15 + (slot - 0.5) * slotSpacing;

        // In BoxGeometry, faces: [+X, -X, +Y, -Y, +Z, -Z]
        // +Z is face index 4!
        const materials = [boxMat, boxMat, boxMat, boxMat, labelMat, boxMat];
        const boxGeo = new THREE.BoxGeometry(boxL, boxH, boxW);
        const boxMesh = new THREE.Mesh(boxGeo, materials);
        boxMesh.position.set(slotX, tierY, 0.03);
        boxMesh.castShadow = true;
        boxMesh.receiveShadow = true;
        boxMesh.userData = b;
        rackGroup.add(boxMesh);
        interactiveBoxes.push(boxMesh);
      } else {
        // Vertical Racks (Rack 1, 2, 3, Partition, Window): Slots along Z
        const slotSpacing = (rack.size.l - 0.3) / totalSlots;
        const slotZ = -rack.size.l / 2 + 0.15 + (slot - 0.5) * slotSpacing;

        // Front face facing +X (towards aisle)
        // For Rack Window, face -X (towards interior walkway)
        const isWindowRack = rack.id === 'RACK-WINDOW';
        const materials = isWindowRack
          ? [boxMat, labelMat, boxMat, boxMat, boxMat, boxMat] // -X
          : [labelMat, boxMat, boxMat, boxMat, boxMat, boxMat]; // +X

        const boxGeo = new THREE.BoxGeometry(boxW, boxH, boxL);
        const boxMesh = new THREE.Mesh(boxGeo, materials);
        boxMesh.position.set(isWindowRack ? -0.03 : 0.03, tierY, slotZ);
        boxMesh.castShadow = true;
        boxMesh.receiveShadow = true;
        boxMesh.userData = b;
        rackGroup.add(boxMesh);
        interactiveBoxes.push(boxMesh);
      }
    });
  }

  function createBoxLabelMaterial(boxData) {
    const canvas = document.createElement('canvas');
    canvas.width = 256;
    canvas.height = 256;
    const ctx = canvas.getContext('2d');

    // White paper label sheet taped onto brown cardboard
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(8, 8, 240, 240);
    ctx.strokeStyle = '#94a3b8';
    ctx.lineWidth = 3;
    ctx.strokeRect(8, 8, 240, 240);

    // FIFO Color Reference
    const fifo = getBoxFifoMonth(boxData);
    const fifoColor = fifo ? fifo.hex : '#2563eb';

    // Category Header Strip
    ctx.fillStyle = fifoColor;
    ctx.fillRect(12, 12, 232, 28);
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 15px sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(((boxData && boxData.category) || 'SPECIMENS').toUpperCase(), 128, 26);

    // Draw circular FIFO monthly color swatch in top-right
    if (fifo) {
      ctx.beginPath();
      ctx.arc(220, 58, 14, 0, Math.PI * 2);
      ctx.fillStyle = fifo.hex;
      ctx.fill();
      ctx.strokeStyle = '#ffffff';
      ctx.lineWidth = 2.5;
      ctx.stroke();

      ctx.fillStyle = '#ffffff';
      ctx.font = 'bold 11px sans-serif';
      ctx.fillText(String(fifo.monthNum || ''), 220, 58);
    }

    // Main box label text
    ctx.fillStyle = '#0f172a';
    ctx.font = 'bold 20px sans-serif';
    ctx.textAlign = 'center';
    wrapText(ctx, (boxData && boxData.label) || '', 128, 88, 216, 26);

    // FOR DISPOSAL stamp (matching official FIFO disposal cycle)
    if (boxData && boxData.status === 'FOR_DISPOSAL') {
      ctx.save();
      ctx.translate(128, 172);
      ctx.rotate(-0.06);
      ctx.fillStyle = '#e65100'; // Orange disposal color
      ctx.fillRect(-85, -16, 170, 32);
      ctx.strokeStyle = '#ffffff';
      ctx.lineWidth = 2;
      ctx.strokeRect(-83, -14, 166, 28);
      ctx.fillStyle = '#ffffff';
      ctx.font = 'bold 16px sans-serif';
      ctx.fillText('FOR DISPOSAL', 0, 0);
      ctx.restore();
    }

    // Box Code at bottom
    ctx.fillStyle = '#64748b';
    ctx.font = 'bold 13px monospace';
    ctx.fillText((boxData && boxData.box_code) || '', 128, 230);

    const tex = new THREE.CanvasTexture(canvas);
    return new THREE.MeshStandardMaterial({ map: tex, roughness: 0.6 });
  }

  function wrapText(ctx, text, x, y, maxWidth, lineHeight) {
    const words = (text || '').split(' ');
    let line = '';
    let curY = y;
    for (let n = 0; n < words.length; n++) {
      const testLine = line + words[n] + ' ';
      const metrics = ctx.measureText(testLine);
      if (metrics.width > maxWidth && n > 0) {
        ctx.fillText(line.trim(), x, curY);
        line = words[n] + ' ';
        curY += lineHeight;
        if (curY > y + lineHeight * 3) {
          ctx.fillText('...', x, curY);
          return;
        }
      } else {
        line = testLine;
      }
    }
    ctx.fillText(line.trim(), x, curY);
  }

  // =========================================================================
  // 5. 3D AI GUIDE "LEXUS" (CHARACTER RIGGING & ANIMATIONS)
  // =========================================================================
  // The avatar of the signed-in user: built from their look by the avatar factory (a man, a woman, a dog, a cat, a panda or a snake).
  function buildLexusCharacter() {
    if (!window.AvatarFactory) return buildLexusOriginal();
    const rig = window.AvatarFactory.build(window.WH_MY_LOOK);
    rig.root.position.set(GUIDE_START.x, 0.0, GUIDE_START.z);
    rig.root.rotation.y = 0;
    scene.add(rig.root);
    state.lexus = rig;
  }

  // a new look while playing (the Avatar Studio): the new body takes over the place, direction and state of the old one
  function rebuildAvatar(look) {
    if (!window.AvatarFactory) return;
    const old = state.lexus;
    const rig = window.AvatarFactory.build(look);
    if (old) {
      rig.root.position.copy(old.root.position);
      rig.root.rotation.y = old.root.rotation.y;
      rig.root.visible = old.root.visible;
      ['state', 'walkCycle', 'waveCycle', 'pointTarget', 'path', 'pathIndex', 'currentYaw', 'targetYaw', 'mood', 'moodUntil'].forEach((k) => { if (old[k] !== undefined) rig[k] = old[k]; });
      scene.remove(old.root);
    }
    scene.add(rig.root);
    state.lexus = rig;
    window.WH_MY_LOOK = look;
  }

  function buildLexusOriginal() {
    // 170cm Tall Humanoid AI Guide: Cool Guy Low Poly Model, Toned Skin, Male, Brown-Black Hair, Lab Gown
    const group = new THREE.Group();
    // Start standing in the open entrance lobby facing south toward the entrance door
    group.position.set(GUIDE_START.x, 0.0, GUIDE_START.z);
    group.rotation.y = 0; // Facing +Z (looking straight at visitor / entrance doorway)

    // Soft circular contact shadow under feet on the concrete floor
    const shadowGeo = new THREE.CircleGeometry(0.38, 24);
    const shadowMat = new THREE.MeshBasicMaterial({ color: 0x030712, transparent: true, opacity: 0.38 });
    const shadow = new THREE.Mesh(shadowGeo, shadowMat);
    shadow.rotation.x = -Math.PI / 2;
    shadow.position.y = 0.003;
    group.add(shadow);

    // Helper to build BufferGeometry from Cool Guy low poly mesh
    function createBufferMesh(meshData, material) {
      if (!meshData || !meshData.verts || !meshData.faces) return null;
      const geo = new THREE.BufferGeometry();
      const positions = [];
      for (let i = 0; i < meshData.faces.length; i++) {
        const vIdx = meshData.faces[i];
        const v = meshData.verts[vIdx];
        if (v) positions.push(v[0], v[1], v[2]);
      }
      geo.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
      geo.computeVertexNormals();
      const m = new THREE.Mesh(geo, material);
      m.castShadow = true;
      return m;
    }

    // Anatomical Materials
    const skinMat = new THREE.MeshStandardMaterial({
      color: 0xc68a5c, // Toned warm tan skin
      roughness: 0.60,
      metalness: 0.04
    });
    const hairMat = new THREE.MeshStandardMaterial({
      color: 0x18110b, // Rich natural brown-black hair (per user request)
      roughness: 0.85,
      metalness: 0.06
    });
    const coatMat = new THREE.MeshStandardMaterial({
      color: 0xf8fafc, // Crisp white laboratory gown
      roughness: 0.65,
      metalness: 0.02
    });
    const shirtMat = new THREE.MeshStandardMaterial({
      color: 0x1e293b, // Dark slate navy inner shirt
      roughness: 0.75
    });
    const pantsMat = new THREE.MeshStandardMaterial({
      color: 0x334155, // Tailored dark slate trousers
      roughness: 0.82
    });
    const shoesMat = new THREE.MeshStandardMaterial({
      color: 0x0f172a, // Clean lab sneakers
      roughness: 0.45
    });
    const soleMat = new THREE.MeshStandardMaterial({
      color: 0xe2e8f0, // White rubber soles
      roughness: 0.35
    });
    const badgeLanyardMat = new THREE.MeshBasicMaterial({ color: 0x0284c7 });
    const badgeCardMat = new THREE.MeshBasicMaterial({ color: 0xffffff });
    const eyeMat = new THREE.MeshBasicMaterial({ color: 0x0f172a });

    // Kinematic Hierarchical Rig
    const rig = {
      root: group,
      pelvis: new THREE.Group(),
      torso: new THREE.Group(),
      neck: new THREE.Group(),
      head: new THREE.Group(),
      leftShoulder: new THREE.Group(),
      leftUpperArm: new THREE.Group(),
      leftForearm: new THREE.Group(),
      leftHand: new THREE.Group(),
      rightShoulder: new THREE.Group(),
      rightUpperArm: new THREE.Group(),
      rightForearm: new THREE.Group(),
      rightHand: new THREE.Group(),
      leftHip: new THREE.Group(),
      leftThigh: new THREE.Group(),
      leftCalf: new THREE.Group(),
      leftFoot: new THREE.Group(),
      rightHip: new THREE.Group(),
      rightThigh: new THREE.Group(),
      rightCalf: new THREE.Group(),
      rightFoot: new THREE.Group(),
      coatSkirtLeft: new THREE.Group(),
      coatSkirtRight: new THREE.Group(),
      state: 'welcoming',
      walkCycle: 0,
      waveCycle: 0,
      pointTarget: null,
      path: [],
      pathIndex: 0,
      currentYaw: 0,
      targetYaw: 0,
      baseSpeed: 2.2
    };

    // 1. Pelvis & Hips (y = 0.88m)
    rig.pelvis.position.set(0, 0.88, 0);
    const pelvisMesh = new THREE.Mesh(new THREE.BoxGeometry(0.26, 0.12, 0.18), pantsMat);
    rig.pelvis.add(pelvisMesh);

    // 2. Torso (Spine & Lab Coat Gown)
    rig.torso.position.set(0, 0.08, 0);
    const torsoMesh = new THREE.Mesh(new THREE.BoxGeometry(0.30, 0.44, 0.20), coatMat);
    torsoMesh.position.set(0, 0.22, 0);
    torsoMesh.castShadow = true;
    rig.torso.add(torsoMesh);

    // Inner navy collared shirt visible in open V-neck
    const shirtMesh = new THREE.Mesh(new THREE.BoxGeometry(0.16, 0.26, 0.02), shirtMat);
    shirtMesh.position.set(0, 0.26, 0.102);
    rig.torso.add(shirtMesh);

    // Lapel collars of lab gown
    const lapelL = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.24, 0.025), coatMat);
    lapelL.position.set(-0.08, 0.26, 0.105);
    lapelL.rotation.z = -0.15;
    rig.torso.add(lapelL);

    const lapelR = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.24, 0.025), coatMat);
    lapelR.position.set(0.08, 0.26, 0.105);
    lapelR.rotation.z = 0.15;
    rig.torso.add(lapelR);

    // Blue Lanyard & ID Card ("LEXUS - AI GUIDE")
    const lanyard = new THREE.Mesh(new THREE.BoxGeometry(0.14, 0.18, 0.01), badgeLanyardMat);
    lanyard.position.set(0, 0.27, 0.107);
    rig.torso.add(lanyard);

    const badge = new THREE.Mesh(new THREE.BoxGeometry(0.065, 0.085, 0.015), badgeCardMat);
    badge.position.set(0, 0.16, 0.11);
    rig.torso.add(badge);

    // Chest pocket with pen
    const pocket = new THREE.Mesh(new THREE.BoxGeometry(0.055, 0.065, 0.01), coatMat);
    pocket.position.set(0.09, 0.28, 0.108);
    rig.torso.add(pocket);
    const pen = new THREE.Mesh(new THREE.CylinderGeometry(0.004, 0.004, 0.045, 6), badgeLanyardMat);
    pen.position.set(0.09, 0.32, 0.112);
    rig.torso.add(pen);

    // 3. Lower Lab Gown Skirts (swing with legs)
    rig.coatSkirtLeft.position.set(-0.08, 0.0, 0);
    const skirtL = new THREE.Mesh(new THREE.BoxGeometry(0.14, 0.28, 0.19), coatMat);
    skirtL.position.set(0, -0.14, 0);
    rig.coatSkirtLeft.add(skirtL);
    rig.pelvis.add(rig.coatSkirtLeft);

    rig.coatSkirtRight.position.set(0.08, 0.0, 0);
    const skirtR = new THREE.Mesh(new THREE.BoxGeometry(0.14, 0.28, 0.19), coatMat);
    skirtR.position.set(0, -0.14, 0);
    rig.coatSkirtRight.add(skirtR);
    rig.pelvis.add(rig.coatSkirtRight);

    // 4. Neck & Head
    rig.neck.position.set(0, 0.44, 0);
    const neckMesh = new THREE.Mesh(new THREE.CylinderGeometry(0.045, 0.05, 0.08, 12), skinMat);
    neckMesh.position.set(0, 0.04, 0);
    rig.neck.add(neckMesh);
    rig.torso.add(rig.neck);

    rig.head.position.set(0, 0.08, 0);

    // Incorporate Cool Guy Low Poly 3D Model with brown-black hair
    if (window.COOL_GUY_MESH) {
      const coolHead = createBufferMesh(window.COOL_GUY_MESH.OBHead, skinMat);
      if (coolHead) rig.head.add(coolHead);

      const coolHair = createBufferMesh(window.COOL_GUY_MESH.OBHair, hairMat);
      if (coolHair) rig.head.add(coolHair);

      const coolNose = createBufferMesh(window.COOL_GUY_MESH.OBNose, skinMat);
      if (coolNose) rig.head.add(coolNose);

      const coolEars = createBufferMesh(window.COOL_GUY_MESH.OBEars, skinMat);
      if (coolEars) rig.head.add(coolEars);

      // Stylized eyes
      const eyeL = new THREE.Mesh(new THREE.BoxGeometry(0.025, 0.015, 0.01), eyeMat);
      eyeL.position.set(-0.042, 0.0, 0.125);
      rig.head.add(eyeL);

      const eyeR = new THREE.Mesh(new THREE.BoxGeometry(0.025, 0.015, 0.01), eyeMat);
      eyeR.position.set(0.042, 0.0, 0.125);
      rig.head.add(eyeR);
    } else {
      // Sculpted fallback head
      const headMesh = new THREE.Mesh(new THREE.BoxGeometry(0.18, 0.20, 0.18), skinMat);
      headMesh.position.set(0, 0.095, 0);
      headMesh.castShadow = true;
      rig.head.add(headMesh);

      // Brown-black hair
      const hairTop = new THREE.Mesh(new THREE.BoxGeometry(0.19, 0.075, 0.19), hairMat);
      hairTop.position.set(0, 0.17, -0.005);
      rig.head.add(hairTop);

      const hairBack = new THREE.Mesh(new THREE.BoxGeometry(0.19, 0.13, 0.08), hairMat);
      hairBack.position.set(0, 0.10, -0.06);
      rig.head.add(hairBack);

      const eyeL = new THREE.Mesh(new THREE.BoxGeometry(0.025, 0.015, 0.01), eyeMat);
      eyeL.position.set(-0.042, 0.095, 0.092);
      rig.head.add(eyeL);

      const eyeR = new THREE.Mesh(new THREE.BoxGeometry(0.025, 0.015, 0.01), eyeMat);
      eyeR.position.set(0.042, 0.095, 0.092);
      rig.head.add(eyeR);
    }

    rig.neck.add(rig.head);

    // 5. Left Arm (Shoulder -> UpperArm -> Forearm -> Hand)
    rig.leftShoulder.position.set(-0.19, 0.38, 0);
    rig.leftUpperArm.position.set(0, 0, 0);
    const lUpperArmMesh = new THREE.Mesh(new THREE.CylinderGeometry(0.045, 0.04, 0.24, 12), coatMat);
    lUpperArmMesh.position.set(0, -0.12, 0);
    rig.leftUpperArm.add(lUpperArmMesh);
    rig.leftShoulder.add(rig.leftUpperArm);

    rig.leftForearm.position.set(0, -0.24, 0);
    const lForearmMesh = new THREE.Mesh(new THREE.CylinderGeometry(0.04, 0.038, 0.20, 12), coatMat);
    lForearmMesh.position.set(0, -0.10, 0);
    rig.leftForearm.add(lForearmMesh);
    rig.leftUpperArm.add(rig.leftForearm);

    rig.leftHand.position.set(0, -0.20, 0);
    const lHandMesh = new THREE.Mesh(new THREE.BoxGeometry(0.05, 0.07, 0.035), skinMat);
    lHandMesh.position.set(0, -0.035, 0);
    rig.leftHand.add(lHandMesh);
    rig.leftForearm.add(rig.leftHand);
    rig.torso.add(rig.leftShoulder);

    // 6. Right Arm (PROPER HAND PLACEMENT - articulated for waving & pointing forward)
    rig.rightShoulder.position.set(0.19, 0.38, 0);
    rig.rightUpperArm.position.set(0, 0, 0);
    const rUpperArmMesh = new THREE.Mesh(new THREE.CylinderGeometry(0.045, 0.04, 0.24, 12), coatMat);
    rUpperArmMesh.position.set(0, -0.12, 0);
    rig.rightUpperArm.add(rUpperArmMesh);
    rig.rightShoulder.add(rig.rightUpperArm);

    rig.rightForearm.position.set(0, -0.24, 0);
    const rForearmMesh = new THREE.Mesh(new THREE.CylinderGeometry(0.04, 0.038, 0.20, 12), coatMat);
    rForearmMesh.position.set(0, -0.10, 0);
    rig.rightForearm.add(rForearmMesh);
    rig.rightUpperArm.add(rig.rightForearm);

    rig.rightHand.position.set(0, -0.20, 0);
    const rHandMesh = new THREE.Mesh(new THREE.BoxGeometry(0.05, 0.07, 0.035), skinMat);
    rHandMesh.position.set(0, -0.035, 0);
    rig.rightHand.add(rHandMesh);

    // Pointing index finger
    const rFinger = new THREE.Mesh(new THREE.CylinderGeometry(0.008, 0.007, 0.045, 8), skinMat);
    rFinger.position.set(0.015, -0.07, 0.01);
    rig.rightHand.add(rFinger);

    rig.rightForearm.add(rig.rightHand);
    rig.torso.add(rig.rightShoulder);

    // 7. Left Leg
    rig.leftHip.position.set(-0.08, -0.06, 0);
    rig.leftThigh.position.set(0, 0, 0);
    const lThighMesh = new THREE.Mesh(new THREE.CylinderGeometry(0.052, 0.046, 0.38, 12), pantsMat);
    lThighMesh.position.set(0, -0.19, 0);
    rig.leftThigh.add(lThighMesh);
    rig.leftHip.add(rig.leftThigh);

    rig.leftCalf.position.set(0, -0.38, 0);
    const lCalfMesh = new THREE.Mesh(new THREE.CylinderGeometry(0.046, 0.040, 0.36, 12), pantsMat);
    lCalfMesh.position.set(0, -0.18, 0);
    rig.leftCalf.add(lCalfMesh);
    rig.leftThigh.add(rig.leftCalf);

    rig.leftFoot.position.set(0, -0.36, 0);
    const lShoe = new THREE.Mesh(new THREE.BoxGeometry(0.085, 0.065, 0.19), shoesMat);
    lShoe.position.set(0, -0.032, 0.04);
    rig.leftFoot.add(lShoe);
    const lSole = new THREE.Mesh(new THREE.BoxGeometry(0.088, 0.018, 0.195), soleMat);
    lSole.position.set(0, -0.068, 0.04);
    rig.leftFoot.add(lSole);
    rig.leftCalf.add(rig.leftFoot);

    // 8. Right Leg
    rig.rightHip.position.set(0.08, -0.06, 0);
    rig.rightThigh.position.set(0, 0, 0);
    const rThighMesh = new THREE.Mesh(new THREE.CylinderGeometry(0.052, 0.046, 0.38, 12), pantsMat);
    rThighMesh.position.set(0, -0.19, 0);
    rig.rightThigh.add(rThighMesh);
    rig.rightHip.add(rig.rightThigh);

    rig.rightCalf.position.set(0, -0.38, 0);
    const rCalfMesh = new THREE.Mesh(new THREE.CylinderGeometry(0.046, 0.040, 0.36, 12), pantsMat);
    rCalfMesh.position.set(0, -0.18, 0);
    rig.rightCalf.add(rCalfMesh);
    rig.rightThigh.add(rig.rightCalf);

    rig.rightFoot.position.set(0, -0.36, 0);
    const rShoe = new THREE.Mesh(new THREE.BoxGeometry(0.085, 0.065, 0.19), shoesMat);
    rShoe.position.set(0, -0.032, 0.04);
    rig.rightFoot.add(rShoe);
    const rSole = new THREE.Mesh(new THREE.BoxGeometry(0.088, 0.018, 0.195), soleMat);
    rSole.position.set(0, -0.068, 0.04);
    rig.rightFoot.add(rSole);
    rig.rightCalf.add(rig.rightFoot);

    // Assemble Hierarchy
    rig.pelvis.add(rig.torso);
    rig.pelvis.add(rig.leftHip);
    rig.pelvis.add(rig.rightHip);
    group.add(rig.pelvis);

    scene.add(group);
    state.lexus = rig;
  }

  function updateLexus(delta, time) {
    const lex = state.lexus;
    if (!lex) return;

    // Helper: smoothly lerp a value
    function numLerp(a, b, t) {
      return a + (b - a) * Math.min(1.0, Math.max(0.0, t));
    }

    // -------------------------------------------------------------------------
    // HEAD TRACKING: Makes Lexus turn his head to look directly at the user (camera)
    // -------------------------------------------------------------------------
    function trackUserHead(maxYawAngle = 1.30, bias = 1.0, trackingSpeed = 7.0) {
      if (!camera || !lex.head) return;

      const headWorld = new THREE.Vector3();
      lex.head.getWorldPosition(headWorld);

      const dx = camera.position.x - headWorld.x;
      const dy = camera.position.y - headWorld.y;
      const dz = camera.position.z - headWorld.z;
      const distHoriz = Math.hypot(dx, dz);

      // Horizontal angle relative to character body orientation
      const camAngle = Math.atan2(dx, dz);
      let diffYaw = Math.atan2(Math.sin(camAngle - lex.currentYaw), Math.cos(camAngle - lex.currentYaw));

      // Ergonomic clamp so neck never twists unnaturally
      diffYaw = Math.max(-maxYawAngle, Math.min(maxYawAngle, diffYaw * bias));

      // Vertical elevation angle (pitch)
      const pitchAngle = Math.atan2(dy, Math.max(0.5, distHoriz));
      const targetPitch = Math.max(-0.55, Math.min(0.45, -pitchAngle * bias));

      // Gentle natural breathing / eye-contact micro-motion
      const microSwayY = Math.sin(time * 1.8) * 0.02;
      const microSwayX = Math.sin(time * 2.2) * 0.02;

      // Smooth interpolation so head glides naturally to maintain eye contact with user
      const t = delta * trackingSpeed;
      lex.head.rotation.y = numLerp(lex.head.rotation.y, diffYaw + microSwayY, t);
      lex.head.rotation.x = numLerp(lex.head.rotation.x, targetPitch + microSwayX, t);
      lex.head.rotation.z = numLerp(lex.head.rotation.z, diffYaw * 0.07, t * 0.8); // subtle natural head tilt
    }

    // -------------------------------------------------------------------------
    // BODY FOLLOWS THE HEAD: once the head has turned past a comfortable angle, the whole body turns after it,
    // so Lexus keeps facing the user instead of twisting his neck. The head only keeps the small remainder.
    // -------------------------------------------------------------------------
    function turnBodyToUser(headComfort = 0.30, bodySpeed = 3.2) {
      if (!camera || !lex.root) return;
      const dx = camera.position.x - lex.root.position.x;
      const dz = camera.position.z - lex.root.position.z;
      if (Math.hypot(dx, dz) < 0.05) return;
      const camAngle = Math.atan2(dx, dz);
      const diff = Math.atan2(Math.sin(camAngle - lex.currentYaw), Math.cos(camAngle - lex.currentYaw));
      const excess = diff - Math.max(-headComfort, Math.min(headComfort, diff));   // what the head cannot take comfortably
      lex.currentYaw += excess * Math.min(1.0, delta * bodySpeed);
      lex.root.rotation.y = lex.currentYaw;
    }

    if (lex.state === 'welcoming') {
      // Natural idle breathing
      lex.pelvis.position.y = 0.88 + Math.sin(time * 2.2) * 0.008;

      // BODY + HEAD FOLLOW THE USER as they orbit / view the scene
      turnBodyToUser();
      trackUserHead(1.35, 1.0, 7.5);

      // Left arm relaxed at side
      lex.leftUpperArm.rotation.set(0.05, 0, 0.08);
      lex.leftForearm.rotation.set(0.12, 0, 0);

      // PROPER HAND PLACEMENT: Waving warmly in front of right shoulder/chest
      lex.waveCycle += delta * 4.5;
      const w = lex.waveCycle;
      // Right arm: out to the side, elbow bent so the hand is up beside the head, forearm swinging (rotation.z > 0 swings the right arm outward)
      lex.rightUpperArm.rotation.set(-0.30, 0, 1.30);
      lex.rightForearm.rotation.set(-0.15, 0, 1.45 + Math.sin(w) * 0.30);
      lex.rightHand.rotation.set(0, 0, Math.sin(w * 1.1) * 0.18);   // palm toward the visitor

      // Stand in place
      lex.leftThigh.rotation.set(0, 0, 0);
      lex.leftCalf.rotation.set(0, 0, 0);
      lex.rightThigh.rotation.set(0, 0, 0);
      lex.rightCalf.rotation.set(0, 0, 0);
      lex.coatSkirtLeft.rotation.set(0, 0, 0);
      lex.coatSkirtRight.rotation.set(0, 0, 0);

    } else if (lex.state === 'walking') {
      // Follow path waypoints smoothly across 50x50 space
      if (lex.path && lex.path.length > 0 && lex.pathIndex < lex.path.length) {
        const targetPt = lex.path[lex.pathIndex];
        const curPos = lex.root.position;
        const dx = targetPt.x - curPos.x;
        const dz = targetPt.z - curPos.z;
        const dist = Math.hypot(dx, dz);

        if (dist > 0.18) {
          // Rotate smoothly towards walking target
          lex.targetYaw = Math.atan2(dx, dz);
          const diffYaw = Math.atan2(Math.sin(lex.targetYaw - lex.currentYaw), Math.cos(lex.targetYaw - lex.currentYaw));
          lex.currentYaw += diffYaw * Math.min(1.0, delta * 8.5);
          lex.root.rotation.y = lex.currentYaw;

          // Move forward along target vector
          const moveStep = Math.min(dist, lex.baseSpeed * delta);
          const vx = (dx / dist) * moveStep;
          const vz = (dz / dist) * moveStep;
          curPos.x += vx;
          curPos.z += vz;

          // Walk cycle animation (cadence ~ 7.5 rad/sec)
          lex.walkCycle += delta * 7.5;
          const c = lex.walkCycle;

          // Pelvis bobbing & subtle sway
          lex.pelvis.position.y = 0.88 + Math.abs(Math.sin(c)) * 0.025;
          lex.pelvis.rotation.y = Math.sin(c) * 0.05;

          // Leg kinematics: alternate thigh and calf swing
          const lThighAngle = Math.sin(c) * 0.55;
          lex.leftThigh.rotation.x = lThighAngle;
          lex.leftCalf.rotation.x = (lThighAngle < 0) ? -lThighAngle * 0.95 : 0.08;

          const rThighAngle = -Math.sin(c) * 0.55;
          lex.rightThigh.rotation.x = rThighAngle;
          lex.rightCalf.rotation.x = (rThighAngle < 0) ? -rThighAngle * 0.95 : 0.08;

          // Coat skirts flare with leg strides
          lex.coatSkirtLeft.rotation.x = lThighAngle * 0.70;
          lex.coatSkirtRight.rotation.x = rThighAngle * 0.70;

          // Arms alternate swing in opposition to legs
          lex.leftUpperArm.rotation.set(-Math.sin(c) * 0.40, 0, 0.08);
          lex.leftForearm.rotation.set(0.20 + Math.max(0, -Math.sin(c)) * 0.25, 0, 0);

          lex.rightUpperArm.rotation.set(Math.sin(c) * 0.40, 0, -0.08);
          lex.rightForearm.rotation.set(0.20 + Math.max(0, Math.sin(c)) * 0.25, 0, 0);
          lex.rightHand.rotation.set(0, 0, 0);

          // Head looks forward with gentle glance towards user
          trackUserHead(0.40, 0.35, 4.0);

          // Camera follow mode in 50x50 space
          if (state.followLexus && !state.isCameraMoving) {
            const camTarget = curPos.clone().add(new THREE.Vector3(0, 1.35, 0));
            controls.target.lerp(camTarget, delta * 3.5);

            const backward = new THREE.Vector3(-Math.sin(lex.currentYaw), 0, -Math.cos(lex.currentYaw));
            const desiredCamPos = curPos.clone().add(backward.multiplyScalar(4.0)).add(new THREE.Vector3(0, 2.6, 0));
            desiredCamPos.y = Math.max(1.4, Math.min(5.0, desiredCamPos.y));
            desiredCamPos.x = Math.max(-HALF_W + 0.5, Math.min(HALF_W - 0.5, desiredCamPos.x));
            desiredCamPos.z = Math.max(-HALF_L + 0.5, Math.min(HALF_L - 0.5, desiredCamPos.z));
            camera.position.lerp(desiredCamPos, delta * 3.0);
          }
        } else {
          // Reached this waypoint, go to next
          lex.pathIndex++;
          if (lex.pathIndex >= lex.path.length) {
            setLexusState('pointing');
          }
        }
      } else {
        setLexusState('pointing');
      }

    } else if (lex.state === 'piloting') {
      pilotStep(lex, delta, time);

    } else if (lex.state === 'pointing') {
      // Stand firmly facing rack
      lex.pelvis.position.y = 0.88 + Math.sin(time * 2.0) * 0.005;
      lex.leftThigh.rotation.set(0, 0, 0);
      lex.leftCalf.rotation.set(0, 0, 0);
      lex.rightThigh.rotation.set(0, 0, 0);
      lex.rightCalf.rotation.set(0, 0, 0);
      lex.coatSkirtLeft.rotation.set(0, 0, 0);
      lex.coatSkirtRight.rotation.set(0, 0, 0);

      if (lex.pointTarget) {
        const curPos = lex.root.position;
        const dx = lex.pointTarget.x - curPos.x;
        const dz = lex.pointTarget.z - curPos.z;
        const dy = (lex.pointTarget.y || 1.8) - 1.25;
        const distHoriz = Math.hypot(dx, dz);

        // Turn body towards rack
        lex.targetYaw = Math.atan2(dx, dz);
        const diffYaw = Math.atan2(Math.sin(lex.targetYaw - lex.currentYaw), Math.cos(lex.targetYaw - lex.currentYaw));
        lex.currentYaw += diffYaw * Math.min(1.0, delta * 6.0);
        lex.root.rotation.y = lex.currentYaw;

        // Raise right arm and point finger directly at shelf tier height
        const elev = Math.atan2(dy, Math.max(0.4, distHoriz));
        lex.rightUpperArm.rotation.x = -1.35 + elev * 0.70;
        lex.rightUpperArm.rotation.y = 0.10;
        lex.rightUpperArm.rotation.z = -0.20;
        lex.rightForearm.rotation.set(-0.10, 0, 0);
        lex.rightHand.rotation.set(-0.05, 0, 0);

        // Turn head to look at user with periodic glance at the shelf
        const glanceCycle = Math.sin(time * 1.5);
        if (glanceCycle > 0.5) {
          // Glance at shelf tier
          lex.head.rotation.x = numLerp(lex.head.rotation.x, -elev * 0.45, delta * 5.0);
          lex.head.rotation.y = numLerp(lex.head.rotation.y, 0.05, delta * 5.0);
        } else {
          // Look directly back at the user!
          trackUserHead(1.35, 1.0, 7.0);
        }
      }

      // Left hand on hip or relaxed
      lex.leftUpperArm.rotation.set(-0.2, 0, 0.4);
      lex.leftForearm.rotation.set(0.6, 0, 0);
    }
    applyMood(lex, time);
    if (lex.tick) lex.tick(delta, time);        // tails wag, snakes slither
  }

  function setLexusState(newState, meta) {
    if (!state.lexus) return;
    state.lexus.state = newState;

    if (dom.bubbleStatus) dom.bubbleStatus.textContent = newState;

    if (newState === 'welcoming') {
      lexusSay(pick(PERSONA.greet), 'happy');
    } else if (newState === 'walking') {
      const rackName = (meta && meta.rack && meta.rack.name) ? meta.rack.name : 'the designated rack';
      lexusSay(`🚶 <b>Follow me!</b> Walking along the aisle to <b>${escapeHtml(rackName)}</b>...`);
    } else if (newState === 'pointing') {
      const b = (meta && meta.box) ? meta.box : state.selectedBox;
      const r = (meta && meta.rack) ? meta.rack : (b ? state.racks.find(x => x.id === b.rack_id) : null);
      const label = b ? b.label : 'Target Box';
      const rName = r ? r.name.split(' - ')[0] : (b ? b.rack_name : 'Rack');
      const tier = b ? b.tier_level : '3';
      const slot = b ? b.slot_index : '1';

      lexusSay(`🎯 <b>Here it is!</b> <b>${escapeHtml(label)}</b> is stored on <b>${escapeHtml(rName)}</b>, <b>Layer ${tier}</b> (Slot ${slot}).`, 'happy');
    }
  }

  function startLexusGuide(waypoints, boxWorldPos, boxData, rack) {
    if (!state.lexus) return;
    if (pilot.on) setPilot(false);
    state.lexus.path = waypoints.slice();
    state.lexus.pathIndex = 0;
    state.lexus.pointTarget = boxWorldPos.clone();
    setLexusState('walking', { box: boxData, rack: rack });
  }

  function resetLexusToDoor() {
    if (!state.lexus) return;
    state.lexus.root.position.set(GUIDE_START.x, 0.0, GUIDE_START.z);
    state.lexus.currentYaw = 0;
    state.lexus.root.rotation.y = 0;
    state.lexus.path = [];
    state.lexus.pathIndex = 0;
    state.lexus.pointTarget = null;
    clearActivePath();
    setLexusState('welcoming');
    animateCameraTo(CAM_PRESETS.door.pos, CAM_PRESETS.door.target, 800);
    [dom.btnDoorView, dom.btnOverheadView, dom.btnAisleView].forEach(b => {
      if (b) b.classList.remove('active');
    });
    if (dom.btnDoorView) dom.btnDoorView.classList.add('active');
  }

  function createPathTexture() {
    const canvas = document.createElement('canvas');
    canvas.width = 512;
    canvas.height = 64;
    const ctx = canvas.getContext('2d');

    // Translucent deep cyan-blue channel
    ctx.fillStyle = 'rgba(2, 60, 120, 0.45)';
    ctx.fillRect(0, 0, 512, 64);

    // Repeated glowing laser chevron arrows >> >> >>
    for (let i = 0; i < 8; i++) {
      const x = i * 64;
      const grad = ctx.createLinearGradient(x, 0, x + 64, 0);
      grad.addColorStop(0, 'rgba(0, 229, 255, 0.15)');
      grad.addColorStop(0.5, 'rgba(0, 229, 255, 0.85)');
      grad.addColorStop(1, 'rgba(255, 255, 255, 1.0)');

      ctx.fillStyle = grad;
      ctx.beginPath();
      ctx.moveTo(x + 12, 10);
      ctx.lineTo(x + 50, 32);
      ctx.lineTo(x + 12, 54);
      ctx.lineTo(x + 28, 32);
      ctx.closePath();
      ctx.fill();

      // Center laser core beam
      ctx.strokeStyle = 'rgba(255, 255, 255, 0.95)';
      ctx.lineWidth = 4;
      ctx.beginPath();
      ctx.moveTo(x, 32);
      ctx.lineTo(x + 64, 32);
      ctx.stroke();
    }

    const tex = new THREE.CanvasTexture(canvas);
    tex.wrapS = THREE.RepeatWrapping;
    tex.wrapT = THREE.RepeatWrapping;
    tex.repeat.set(16, 1);
    return tex;
  }

  // The aisle a visitor walks in to reach a rack, and the route from where Lexus stands to the box
  function guideWaypoints(rack, boxPos, start) {
    const y = 0.05;
    const V = (x, z) => new THREE.Vector3(x, y, z);
    const nearestAisle = (x) => AISLE_X.reduce((a, b) => (Math.abs(b - x) < Math.abs(a - x) ? b : a));
    const horizontal = rack.size.w > rack.size.l;           // Rack 4 runs along the north wall
    const isWindow = rack.id === 'RACK-WINDOW';             // its boxes face west; every other rack faces east
    const pts = [V(start.x, start.z)];
    const inRacks = start.z < LANE_S - 1.0;
    const toLane = () => {
      if (inRacks) { const sx = nearestAisle(start.x); if (Math.abs(sx - start.x) > 0.3) pts.push(V(sx, start.z)); pts.push(V(sx, LANE_S)); }
      else pts.push(V(start.x, LANE_S));
    };

    if (horizontal) {
      const standZ = rack.pos.z + rack.size.l / 2 + 1.0;    // in front of the boxes (south side)
      const ax = AISLE_X[AISLE_X.length - 1];                // the east aisle leads up to Rack 4
      // the Partition rack stands against the west end of Rack 4: boxes there are reached from the nearest free spot beside it
      const part = (typeof state !== 'undefined' && state.racks) ? state.racks.find(r => r.id === 'RACK-PART') : null;
      const standX = part ? Math.max(boxPos.x, part.pos.x + part.size.w / 2 + 0.8) : boxPos.x;
      if (inRacks && Math.abs(start.z - standZ) < 0.6) {
        pts.push(V(standX, standZ));
      } else {
        toLane();
        pts.push(V(ax, LANE_S), V(ax, standZ), V(standX, standZ));
      }
    } else {
      const standX = rack.pos.x + (isWindow ? -1 : 1) * (rack.size.w / 2 + 1.0);   // the aisle in front of the boxes
      if (inRacks && Math.abs(start.x - standX) < 0.5) {
        pts.push(V(standX, boxPos.z));
      } else {
        toLane();
        pts.push(V(standX, LANE_S), V(standX, boxPos.z));
      }
    }
    return pts.filter((p, i) => i === 0 || p.distanceTo(pts[i - 1]) > 0.1);   // drop repeated points
  }

  // A route with its corners rounded, so the glowing line and Lexus's walk bend smoothly instead of cutting across a rack
  function roundedPath(points, radius) {
    const path = new THREE.CurvePath();
    if (points.length < 2) { path.add(new THREE.LineCurve3(points[0], points[0].clone().add(new THREE.Vector3(0, 0, 0.01)))); return path; }
    let prev = points[0].clone();
    for (let i = 1; i < points.length; i++) {
      const p = points[i];
      if (i < points.length - 1) {
        const n = points[i + 1];
        const d1 = p.distanceTo(prev), d2 = p.distanceTo(n);
        const r = Math.min(radius, d1 / 2, d2 / 2);
        const a = p.clone().lerp(prev, r / d1);
        const b = p.clone().lerp(n, r / d2);
        path.add(new THREE.LineCurve3(prev, a));
        path.add(new THREE.QuadraticBezierCurve3(a, p.clone(), b));
        prev = b;
      } else {
        path.add(new THREE.LineCurve3(prev, p.clone()));
      }
    }
    return path;
  }

  function showNavigationPathToBox(boxData) {
    const rack = state.racks.find(rk => rk.id === boxData.rack_id);
    if (!rack) return;

    // Clean up any existing path artifacts
    if (state.pathLine) scene.remove(state.pathLine);
    if (state.pathHalo) scene.remove(state.pathHalo);
    if (state.pathComet) scene.remove(state.pathComet);
    if (state.markerPin) scene.remove(state.markerPin);

    // Find box mesh in 3D
    const mesh = interactiveBoxes.find(m => m.userData && m.userData.id === boxData.id);
    const boxWorldPos = new THREE.Vector3();
    if (mesh) {
      mesh.getWorldPosition(boxWorldPos);
    } else {
      boxWorldPos.set(rack.pos.x, 1.2, rack.pos.z);
    }

    // Waypoints that follow the aisles of the room (never across a rack), from where Lexus stands now
    const startNow = (state.lexus && state.lexus.root) ? state.lexus.root.position : new THREE.Vector3(GUIDE_START.x, 0, GUIDE_START.z);
    const points = guideWaypoints(rack, boxWorldPos, startNow);
    const curve = roundedPath(points, 0.5);
    state.pathCurve = curve;
    const pathLen = curve.getLength();
    const segs = Math.max(48, Math.ceil(pathLen * 10));

    // 1. Moving Inner Glowing Tube (Animated flowing arrows & laser beam)
    if (!state.pathTexture) {
      state.pathTexture = createPathTexture();
    }
    state.pathTexture.repeat.set(Math.max(4, pathLen / 1.1), 1);   // chevrons keep their size whatever the length
    const coreGeo = new THREE.TubeGeometry(curve, segs, 0.040, 12, false);
    const coreMat = new THREE.MeshBasicMaterial({
      map: state.pathTexture,
      transparent: true,
      opacity: 0.95
    });
    state.pathLine = new THREE.Mesh(coreGeo, coreMat);
    scene.add(state.pathLine);

    // 2. Outer Additive Neon Halo Tube (Pulsing glowing atmosphere)
    const haloGeo = new THREE.TubeGeometry(curve, segs, 0.075, 12, false);
    const haloMat = new THREE.MeshBasicMaterial({
      color: 0x00e5ff,
      transparent: true,
      opacity: 0.40,
      blending: THREE.AdditiveBlending
    });
    state.pathHalo = new THREE.Mesh(haloGeo, haloMat);
    scene.add(state.pathHalo);

    // 3. Glowing Moving Energy Comet Orb traveling along the path
    const cometGeo = new THREE.SphereGeometry(0.08, 16, 16);
    const cometMat = new THREE.MeshBasicMaterial({ color: 0xffffff });
    state.pathComet = new THREE.Mesh(cometGeo, cometMat);
    const cometLight = new THREE.PointLight(0x00e5ff, 1.2, 2.0);
    state.pathComet.add(cometLight);
    scene.add(state.pathComet);

    // 4. Command Lexus to Walk & Guide Visitor along the path
    startLexusGuide(points, boxWorldPos, boxData, rack);

    // 5. Floating 3D Target Marker Pin above box
    if (mesh) {
      highlightBox(mesh);

      const pinGeo = new THREE.ConeGeometry(0.12, 0.28, 16);
      pinGeo.rotateX(Math.PI);
      const pinMat = new THREE.MeshBasicMaterial({ color: 0x00e5ff });
      state.markerPin = new THREE.Mesh(pinGeo, pinMat);
      state.markerPin.position.set(boxWorldPos.x, boxWorldPos.y + 0.42, boxWorldPos.z);
      scene.add(state.markerPin);

      // Smooth camera transition if user disabled follow guide
      if (!state.followLexus) {
        const camTargetOffset = rack.id === 'RACK-4'
          ? new THREE.Vector3(boxWorldPos.x, boxWorldPos.y + 0.5, boxWorldPos.z + 1.4)
          : (rack.id === 'RACK-WINDOW'
            ? new THREE.Vector3(boxWorldPos.x - 1.4, boxWorldPos.y + 0.4, boxWorldPos.z + 0.8)
            : new THREE.Vector3(boxWorldPos.x + 1.4, boxWorldPos.y + 0.4, boxWorldPos.z + 0.8));
        animateCameraTo(camTargetOffset, boxWorldPos, 900);
      }
    }

    // Show path banner with FIFO indicator
    const fifo = getBoxFifoMonth(boxData);
    const fifoStr = fifo ? ` · ${fifo.name} (${fifo.label})` : '';
    dom.pathText.textContent = `Lexus is guiding you to ${rack.name} · Layer ${boxData.tier_level} (Slot ${boxData.slot_index})${fifoStr}`;
    dom.pathBanner.classList.remove('hidden');
  }

  function highlightBox(mesh) {
    if (state.highlightedBoxMesh && state.highlightedBoxMesh !== mesh) {
      resetBoxHighlight(state.highlightedBoxMesh);
    }

    state.highlightedBoxMesh = mesh;
    if (Array.isArray(mesh.material)) {
      mesh.material.forEach(m => {
        if (m.emissive) {
          m.emissive.setHex(0x0284c7);
          m.emissiveIntensity = 0.6;
        }
      });
    }
  }

  function resetBoxHighlight(mesh) {
    if (!mesh) return;
    if (Array.isArray(mesh.material)) {
      mesh.material.forEach(m => {
        if (m.emissive) {
          m.emissive.setHex(0x000000);
          m.emissiveIntensity = 0;
        }
      });
    }
  }

  function clearActivePath() {
    if (state.pathLine) {
      scene.remove(state.pathLine);
      state.pathLine = null;
    }
    if (state.pathHalo) {
      scene.remove(state.pathHalo);
      state.pathHalo = null;
    }
    if (state.pathComet) {
      scene.remove(state.pathComet);
      state.pathComet = null;
    }
    state.pathCurve = null;
    if (state.markerPin) {
      scene.remove(state.markerPin);
      state.markerPin = null;
    }
    if (state.highlightedBoxMesh) {
      resetBoxHighlight(state.highlightedBoxMesh);
      state.highlightedBoxMesh = null;
    }
    dom.pathBanner.classList.add('hidden');
  }

  // Smooth Camera Animation
  function animateCameraTo(targetPos, lookAtPos, duration) {
    const startPos = camera.position.clone();
    const startTarget = controls.target.clone();
    const startTime = performance.now();
    state.isCameraMoving = true;

    function step() {
      const elapsed = performance.now() - startTime;
      const t = Math.min(1, elapsed / duration);
      // Ease out cubic
      const ease = 1 - Math.pow(1 - t, 3);

      camera.position.lerpVectors(startPos, targetPos, ease);
      controls.target.lerpVectors(startTarget, lookAtPos, ease);

      if (t < 1) {
        requestAnimationFrame(step);
      } else {
        state.isCameraMoving = false;
      }
    }
    step();
  }

  // =========================================================================
  // 6. RAYCASTING & INTERACTION
  // =========================================================================
  function onCanvasClick(event) {
    if (window.AsylumGame && window.AsylumGame.isActive()) return;       // no boxes, lamps or racks to click in the asylum
    const rect = dom.canvas.getBoundingClientRect();
    mouse.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
    mouse.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;

    raycaster.setFromCamera(mouse, camera);
    const lampHit = raycaster.intersectObjects(state.bulbMeshes || []);
    const intersects = raycaster.intersectObjects(interactiveBoxes);
    if (lampHit.length > 0 && (!intersects.length || lampHit[0].distance < intersects[0].distance)) {
      toggleBulb(lampHit[0].object.userData.bulb);
      return;
    }

    if (intersects.length > 0) {
      const clickedMesh = intersects[0].object;
      const boxData = clickedMesh.userData;
      if (boxData) {
        selectBox(boxData);
        if (pilot.on) { highlightBox(clickedMesh); return; }   // piloting: just look at the box, no automatic walk
        showNavigationPathToBox(boxData);
      }
    }
  }

  function onCanvasMouseMove(event) {
    if (window.AsylumGame && window.AsylumGame.isActive()) return;
    const rect = dom.canvas.getBoundingClientRect();
    mouse.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
    mouse.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;

    raycaster.setFromCamera(mouse, camera);
    const intersects = raycaster.intersectObjects(interactiveBoxes);
    const lampHit = raycaster.intersectObjects(state.bulbMeshes || []);
    const overLamp = lampHit.length > 0 && (!intersects.length || lampHit[0].distance < intersects[0].distance);
    (state.bulbs || []).forEach(b => { b.hover = false; });
    if (overLamp) lampHit[0].object.userData.bulb.hover = true;
    dom.canvas.style.cursor = (intersects.length > 0 || overLamp) ? 'pointer' : 'grab';
  }

  function selectBox(boxData) {
    state.selectedBox = boxData;

    dom.biCatBadge.textContent = boxData.category || 'Specimens';
    dom.biBoxLabel.textContent = boxData.label;
    dom.biRackName.textContent = boxData.rack_name || boxData.rack_id;
    dom.biTierNum.textContent = `Layer ${boxData.tier_level} (Tier ${boxData.tier_level})`;
    dom.biSlotNum.textContent = `Slot ${boxData.slot_index}`;
    dom.biBoxCode.textContent = boxData.box_code;
    dom.biDateRange.textContent = boxData.date_range || boxData.month_year || '–';
    dom.biCreatedBy.textContent = boxData.created_by || 'Admin';
    dom.biContents.textContent = boxData.contents_desc || 'No additional contents listed.';

    // Update FIFO pill
    const fifo = getBoxFifoMonth(boxData);
    if (dom.biFifoPill) {
      if (fifo) {
        dom.biFifoPill.innerHTML = `<span class="fifo-color-swatch-sm" style="background:${fifo.hex}"></span> ${fifo.name} · ${fifo.label}`;
        dom.biFifoPill.classList.remove('hidden');
      } else {
        dom.biFifoPill.classList.add('hidden');
      }
    }

    let stHtml = '<span class="wh-status-active">ACTIVE RETAIN</span>';
    if (boxData.status === 'FOR_DISPOSAL') stHtml = '<span class="wh-status-disposal">FOR DISPOSAL</span>';
    if (boxData.status === 'ARCHIVED') stHtml = '<span class="wh-status-archived">ARCHIVED HOLD</span>';
    dom.biStatusBadge.innerHTML = stHtml;

    dom.inspector.classList.remove('hidden');
  }

  // =========================================================================
  // 7. API FETCH & DATA HANDLING
  // =========================================================================
  async function loadData() {
    try {
      dom.loadingOverlay.classList.remove('hidden');

      // 1. Fetch Racks
      const rRes = await fetch(racksUrl, { headers: { 'Accept': 'application/json' } });
      const rData = await rRes.json();
      if (rData && rData.ok) {
        state.racks = rData.racks || [];
        renderRacks(state.racks);
      }

      // 2. Fetch Boxes
      const bRes = await fetch(boxesUrl, { headers: { 'Accept': 'application/json' } });
      const bData = await bRes.json();
      if (bData && bData.ok) {
        state.boxes = bData.boxes || [];
        renderBoxes(state.boxes);
        updateKPIs(bData.stats);
        if (isAdmin) renderAdminTable(state.boxes);
      }
    } catch (err) {
      console.error('Failed to load warehouse data:', err);
    } finally {
      dom.loadingOverlay.classList.add('hidden');
    }
  }

  function updateKPIs(stats) {
    // the cards of the page are Total and Racks 1-4: count the boxes of each rack
    const perRack = {};
    state.boxes.forEach(b => { perRack[b.rack_id] = (perRack[b.rack_id] || 0) + 1; });
    const put = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
    put('wh-kpi-total', (stats && stats.total) || state.boxes.length);
    put('wh-kpi-rack1', (perRack['RACK-1'] || 0) + (perRack['RACK-WINDOW'] || 0));
    put('wh-kpi-rack2', perRack['RACK-2'] || 0);
    put('wh-kpi-rack3', perRack['RACK-3'] || 0);
    put('wh-kpi-rack4', (perRack['RACK-4'] || 0) + (perRack['RACK-PART'] || 0));
  }

  // Search box filtering
  function performSearch(query) {
    state.searchQuery = query.toLowerCase().trim();
    dom.searchClear.classList.toggle('hidden', state.searchQuery === '');

    if (!state.searchQuery) {
      clearActivePath();
      interactiveBoxes.forEach(m => (m.visible = true));
      return;
    }

    let firstMatch = null;
    interactiveBoxes.forEach(m => {
      const b = m.userData;
      const match = (b.label && b.label.toLowerCase().includes(state.searchQuery)) ||
                    (b.box_code && b.box_code.toLowerCase().includes(state.searchQuery)) ||
                    (b.category && b.category.toLowerCase().includes(state.searchQuery)) ||
                    (b.date_range && b.date_range.toLowerCase().includes(state.searchQuery)) ||
                    (b.contents_desc && b.contents_desc.toLowerCase().includes(state.searchQuery));
      m.visible = match;
      if (match && !firstMatch) firstMatch = b;
    });

    if (firstMatch) {
      selectBox(firstMatch);
      showNavigationPathToBox(firstMatch);
    }
  }

  // Filter category chips
  function filterCategory(cat) {
    state.activeCategory = cat;
    dom.categoryChips.forEach(chip => {
      chip.classList.toggle('active', chip.dataset.cat === cat);
    });

    interactiveBoxes.forEach(m => {
      const b = m.userData;
      if (cat === 'ALL') {
        m.visible = true;
      } else if (cat === 'Disposal') {
        m.visible = b.status === 'FOR_DISPOSAL';
      } else {
        m.visible = b.category === cat;
      }
    });
  }

  // =========================================================================
  // 8. ADMIN TABLE & MODAL MANAGEMENT
  // =========================================================================
  function renderAdminTable(boxes) {
    if (!dom.boxesTableTbody) return;
    const q = (dom.tblSearch ? dom.tblSearch.value : '').toLowerCase().trim();
    const rackFilter = dom.tblRackFilter ? dom.tblRackFilter.value : '';
    const tierFilter = dom.tblTierFilter ? dom.tblTierFilter.value : '';

    const filtered = boxes.filter(b => {
      if (rackFilter && b.rack_id !== rackFilter) return false;
      if (tierFilter && String(b.tier_level) !== String(tierFilter)) return false;
      if (q) {
        const match = (b.label && b.label.toLowerCase().includes(q)) ||
                      (b.box_code && b.box_code.toLowerCase().includes(q)) ||
                      (b.date_range && b.date_range.toLowerCase().includes(q)) ||
                      (b.contents_desc && b.contents_desc.toLowerCase().includes(q));
        if (!match) return false;
      }
      return true;
    });

    if (dom.tblCount) dom.tblCount.textContent = filtered.length;

    let html = '';
    if (filtered.length === 0) {
      html = '<tr><td colspan="8" style="text-align:center;color:#94a3b8;padding:24px;">No retain boxes match the filter.</td></tr>';
    } else {
      filtered.forEach(b => {
        let statusBadge = '<span class="wh-status-active">ACTIVE</span>';
        if (b.status === 'FOR_DISPOSAL') statusBadge = '<span class="wh-status-disposal">DISPOSAL</span>';
        if (b.status === 'ARCHIVED') statusBadge = '<span class="wh-status-archived">ARCHIVED</span>';

        const fifo = getBoxFifoMonth(b);
        const fifoCell = fifo
          ? `<span class="wh-tbl-fifo-badge"><span class="fifo-color-swatch-sm" style="background:${fifo.hex}"></span> ${escapeHtml(b.month_year || fifo.name)}</span>`
          : `<span style="color:#94a3b8">${escapeHtml(b.month_year || b.date_range || '–')}</span>`;

        html += `
          <tr>
            <td><code class="font-mono">${escapeHtml(b.box_code)}</code></td>
            <td><b>${escapeHtml(b.label)}</b></td>
            <td><span class="wh-bi-cat-badge">${escapeHtml(b.category)}</span></td>
            <td>${escapeHtml(b.rack_name || b.rack_id)}</td>
            <td>Layer ${b.tier_level} (Slot ${b.slot_index})</td>
            <td>${fifoCell}</td>
            <td>${statusBadge}</td>
            <td style="white-space:nowrap;">
              <button type="button" class="wh-tbl-btn btn-locate" data-id="${b.id}" title="Locate & guide in 3D">Locate 3D</button>
              <button type="button" class="wh-tbl-btn btn-edit" data-id="${b.id}">Edit</button>
              <button type="button" class="wh-tbl-btn btn-delete" data-id="${b.id}">Delete</button>
            </td>
          </tr>
        `;
      });
    }

    dom.boxesTableTbody.innerHTML = html;

    // Table action buttons
    dom.boxesTableTbody.querySelectorAll('.btn-locate').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = parseInt(btn.dataset.id, 10);
        const b = state.boxes.find(item => item.id === id);
        if (b) {
          closeAdminDrawer();
          selectBox(b);
          showNavigationPathToBox(b);
        }
      });
    });

    dom.boxesTableTbody.querySelectorAll('.btn-edit').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = parseInt(btn.dataset.id, 10);
        const b = state.boxes.find(item => item.id === id);
        if (b) openBoxModal(b);
      });
    });

    dom.boxesTableTbody.querySelectorAll('.btn-delete').forEach(btn => {
      btn.addEventListener('click', async () => {
        const id = parseInt(btn.dataset.id, 10);
        if (confirm('Are you sure you want to delete or dispose this retention box?')) {
          await deleteBox(id);
        }
      });
    });
  }

  function openBoxModal(boxData) {
    if (!dom.boxModalBackdrop) return;
    dom.boxModalTitle.textContent = boxData ? 'Edit Retention Box' : 'Add New Retention Box';

    dom.formId.value = boxData ? boxData.id : '0';
    dom.formLabel.value = boxData ? boxData.label : '';
    dom.formCode.value = boxData ? boxData.box_code : '';
    dom.formCategory.value = boxData ? boxData.category : 'Daily Bonding';
    dom.formRack.value = boxData ? boxData.rack_id : 'RACK-1';
    dom.formTier.value = boxData ? boxData.tier_level : '3';
    dom.formSlot.value = boxData ? boxData.slot_index : '1';
    dom.formDate.value = boxData ? (boxData.date_range || '') : '';
    if (dom.formMonth) {
      dom.formMonth.value = boxData ? (boxData.month_year || '') : new Date().toISOString().slice(0, 7);
    }
    dom.formStatus.value = boxData ? boxData.status : 'ACTIVE';
    dom.formDesc.value = boxData ? (boxData.contents_desc || '') : '';

    dom.boxModalBackdrop.classList.remove('hidden');
  }

  function closeBoxModal() {
    if (dom.boxModalBackdrop) dom.boxModalBackdrop.classList.add('hidden');
  }

  async function saveBoxForm(e) {
    e.preventDefault();
    const formData = new FormData(dom.boxForm);

    try {
      const res = await fetch(saveUrl, {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfHash || ''
        },
        body: formData
      });
      const data = await res.json();

      if (data && data.ok) {
        closeBoxModal();
        await loadData();
        if (data.box) {
          selectBox(data.box);
          showNavigationPathToBox(data.box);
        }
      } else {
        alert(data.error || 'Failed to save box details.');
      }
    } catch (err) {
      console.error('Save box error:', err);
      alert('Error communicating with server.');
    }
  }

  async function deleteBox(id) {
    const fd = new FormData();
    fd.append('id', id);
    fd.append('hard_delete', '1');

    try {
      const res = await fetch(deleteUrl, {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfHash || ''
        },
        body: fd
      });
      const data = await res.json();
      if (data && data.ok) {
        await loadData();
        clearActivePath();
        if (dom.inspector) dom.inspector.classList.add('hidden');
      } else {
        alert(data.error || 'Failed to delete box.');
      }
    } catch (err) {
      console.error('Delete error:', err);
    }
  }

  function openAdminDrawer() {
    if (dom.adminBackdrop) dom.adminBackdrop.classList.add('active');
    if (dom.adminDrawer) {
      dom.adminDrawer.classList.add('active');
      dom.adminDrawer.setAttribute('aria-hidden', 'false');
    }
    renderAdminTable(state.boxes);
  }

  function closeAdminDrawer() {
    if (dom.adminBackdrop) dom.adminBackdrop.classList.remove('active');
    if (dom.adminDrawer) {
      dom.adminDrawer.classList.remove('active');
      dom.adminDrawer.setAttribute('aria-hidden', 'true');
    }
  }

  // =========================================================================
  // 9. ANIMATION LOOP & EVENT LISTENERS
  // =========================================================================
  let lastTime = performance.now();

  function animate() {
    requestAnimationFrame(animate);

    const now = performance.now();
    const delta = Math.min(0.1, (now - lastTime) / 1000);
    lastTime = now;
    const time = now * 0.001;

    if (controls) controls.update();

    // 1. Update 3D Lexus AI Guide (kinematics, walk cycle, wave, pointing)
    updateLexus(delta, time);

    frameHooks.forEach(fn => fn(delta, time));

    // 1b. Update Asylum Easter Egg Game (Ghost AI, Flashlight, Heartbeat, Collisions)
    if (window.AsylumGame && window.AsylumGame.isActive()) {
      window.AsylumGame.update(delta, time);
    }

    // 2. Project Dynamic 3D Speech Bubble on top of Lexus's head
    if (state.lexus && state.lexus.head && dom.bubble3d) {
      if (window.AsylumGame && window.AsylumGame.isActive()) {
        dom.bubble3d.style.display = 'none';
      } else {
        const headWorld = new THREE.Vector3();
        state.lexus.head.getWorldPosition(headWorld);
        headWorld.y += 0.52; // Hover directly above head

        headWorld.project(camera);

        // Check if behind camera
        if (headWorld.z > 1.0) {
          dom.bubble3d.style.display = 'none';
        } else {
          dom.bubble3d.style.display = 'block';
          const rect = dom.canvas.getBoundingClientRect();
          const x = ((headWorld.x + 1) / 2) * rect.width;
          const y = ((-headWorld.y + 1) / 2) * rect.height;

          // Keep the whole bubble inside the canvas, whatever its size
          const bw = dom.bubble3d.offsetWidth || 300, bh = dom.bubble3d.offsetHeight || 160;
          const clampedX = Math.max(bw / 2 + 8, Math.min(rect.width - bw / 2 - 8, x));
          const bannerOn = dom.pathBanner && !dom.pathBanner.classList.contains('hidden');
          const clampedY = Math.max(bh + (bannerOn ? 62 : 24), Math.min(rect.height - 12, y));

          dom.bubble3d.style.left = `${clampedX}px`;
          dom.bubble3d.style.top = `${clampedY}px`;
        }
      }
    }

    // lamps: a gentle pulse on the glow, brighter under the pointer
    if (state.bulbs) {
      state.bulbs.forEach(b => {
        if (!b.on) return;
        b.glow.material.opacity = (b.hover ? 0.34 : 0.17) + 0.02 * Math.sin(time * 3 + b.group.position.x);
        b.glow.scale.setScalar(b.hover ? 1.25 : 1.0);
      });
    }

    // 3. Animate Glowing and Moving Blue Line
    if (state.pathTexture) {
      state.pathTexture.offset.x -= delta * 1.6;
    }
    if (state.pathHalo) {
      state.pathHalo.material.opacity = 0.35 + 0.18 * Math.sin(time * 5.0);
    }
    if (state.pathComet && state.pathCurve) {
      const cometT = (time * 0.35) % 1.0;
      const pt = state.pathCurve.getPointAt(cometT);
      state.pathComet.position.copy(pt);
    }

    // 4. Subtle pulsing animation on marker pin if active
    if (state.markerPin) {
      state.markerPin.rotation.y += 0.03;
      state.markerPin.position.y += Math.sin(time * 4) * 0.0015;
    }

    if (renderer && scene && camera) {
      renderer.render(scene, camera);
    }
  }

  function onWindowResize() {
    if (!renderer || !camera || !dom.canvasWrap) return;
    const width = dom.canvasWrap.clientWidth;
    const height = dom.canvasWrap.clientHeight || 560;
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
    renderer.setSize(width, height);
  }

  function escapeHtml(str) {
    if (str == null) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }


  // =========================================================================
  // PILOT: the visitor drives Lexus. W/A/S/D or the arrow keys walk (relative to the camera), Shift runs, E interacts with the box or lamp in
  // front of him, Esc (or the Pilot button) gives control back. The mouse still works on boxes and lamps.
  // =========================================================================
  const pilot = { on: false, keys: {}, rects: [], near: null, hint: '', vy: 0, omega: 0 };
  const PILOT_R = 0.38;      // how close to a wall or rack he can get

  function pilotObstacles() {
    const r = (state.racks || []).map(rk => [rk.pos.x - rk.size.w / 2, rk.pos.x + rk.size.w / 2, rk.pos.z - rk.size.l / 2, rk.pos.z + rk.size.l / 2]);
    // the storage enclosure: its east wall has the secret door (closed it is solid like the wall, open you can walk through)
    const dz = (window.AsylumGame && window.AsylumGame.SECRET) ? window.AsylumGame.SECRET.z : -19.08;
    r.push([0.3, 0.5, -HALF_L, dz - 0.85], [0.3, 0.5, dz + 0.85, -7.96], [-HALF_W, 0.5, -8.06, -7.86]);
    pilot.doorRect = [0.3, 0.5, dz - 0.85, dz + 0.85];
    r.push([HALF_W - 4.75, HALF_W, -HALF_L, -HALF_L + 5.0]);                // the pillar
    return r;
  }

  function pilotBlocked(x, z) {
    if (window.AsylumGame && window.AsylumGame.isActive()) {
      return window.AsylumGame.isBlocked(x, z);
    }
    if (x < -HALF_W + PILOT_R || x > HALF_W - PILOT_R || z < -HALF_L + PILOT_R || z > HALF_L - PILOT_R) return true;
    if (pilot.doorRect && !(window.AsylumGame && window.AsylumGame.isDoorOpen)) {      // the closed secret door is solid
      const [dx0, dx1, dz0, dz1] = pilot.doorRect;
      if (Math.hypot(x - Math.max(dx0, Math.min(dx1, x)), z - Math.max(dz0, Math.min(dz1, z))) < PILOT_R) return true;
    }
    for (const [x0, x1, z0, z1] of pilot.rects) {
      const nx = Math.max(x0, Math.min(x1, x)), nz = Math.max(z0, Math.min(z1, z));
      if (Math.hypot(x - nx, z - nz) < PILOT_R) return true;
    }
    return false;
  }

  function setPilot(on) {
    on = !!on;
    if (!on && window.AsylumGame && window.AsylumGame.isActive()) return;       // in the asylum the pilot stays on
    if (on === pilot.on || !state.lexus) return;
    pilot.on = on;
    pilot.keys = {};
    controls.enabled = !on;                    // the follow camera is in charge while driving
    const btn = document.getElementById('wh-btn-pilot');
    const help = document.getElementById('wh-pilot-help');
    if (btn) { btn.classList.toggle('active', on); btn.setAttribute('aria-pressed', on ? 'true' : 'false'); }
    if (help) help.classList.toggle('hidden', !on);
    setPilotHint('');
    if (on) {
      pilot.rects = pilotObstacles();
      clearActivePath();
      state.lexus.path = [];
      state.lexus.pointTarget = null;
      setLexusState('piloting');
      // the camera goes behind him; from here it follows him and the mouse still turns it
      const p = state.lexus.root.position;
      const back = new THREE.Vector3(-Math.sin(state.lexus.currentYaw), 0, -Math.cos(state.lexus.currentYaw));
      animateCameraTo(new THREE.Vector3(p.x + back.x * 4.2, 2.6, p.z + back.z * 4.2), new THREE.Vector3(p.x, 1.4, p.z), 700);
      lexusSay('🎮 You are at the controls! <b>W</b> / <b>S</b> walk, <b>A</b> / <b>D</b> turn (or the arrow keys), hold <b>Shift</b> to run, <b>Space</b> to jump and <b>E</b> to interact.', 'happy');
    } else {
      setLexusState('welcoming');
      lexusSay('🙌 Thanks for the ride! I have the controls back.', 'happy');
    }
  }

  function setPilotHint(text) {
    if (text === pilot.hint) return;
    pilot.hint = text;
    const el = document.getElementById('wh-pilot-hint');
    if (!el) return;
    el.textContent = text;
    el.classList.toggle('hidden', !text);
  }

  // the nearest thing he can interact with: a box in reach, or a lamp overhead
  function pilotScan(lex) {
    const p = lex.root.position;
    const tmp = new THREE.Vector3();
    let best = null;
    interactiveBoxes.forEach(m => {
      m.getWorldPosition(tmp);
      const d = Math.hypot(tmp.x - p.x, tmp.z - p.z);
      if (d < 2.4 && (!best || d < best.d)) best = { type: 'box', obj: m, d, label: (m.userData && (m.userData.label || m.userData.box_label)) || 'box' };
    });
    if (!best) {
      (state.bulbs || []).forEach(b => {
        const d = Math.hypot(b.group.position.x - p.x, b.group.position.z - p.z);
        if (d < 3.2 && (!best || d < best.d)) best = { type: 'lamp', obj: b, d, label: b.on ? 'Switch the lamp off' : 'Switch the lamp on' };
      });
    }
    pilot.near = best;
    setPilotHint(best ? (best.type === 'box' ? 'E  Open ' + best.label : 'E  ' + best.label) : '');
  }

  function pilotInteract() {
    const n = pilot.near;
    if (!n) { lexusSay('🤔 Nothing here to interact with. Walk up to a box or under a lamp.', 'curious'); return; }
    if (n.type === 'lamp') { toggleBulb(n.obj); return; }
    const b = n.obj.userData;
    selectBox(b);
    highlightBox(n.obj);
    const rk = state.racks.find(r => r.id === b.rack_id);
    lexusSay(`📦 <b>${escapeHtml(b.label || b.box_label || 'Box')}</b> is on <b>${escapeHtml(rk ? rk.name.split(' - ')[0] : b.rack_id)}</b>, layer <b>${b.tier_level}</b>. Details are on the right.`, 'curious');
  }

  function pilotStep(lex, delta, time) {
    if (window.WHMulti && window.WHMulti.studioOpen) return;          // choosing a look: stand still
    // hidden in a cabinet, a locker, under a bed...: no walking, the camera looks out from inside
    if (window.AsylumGame && window.AsylumGame.isActive() && window.AsylumGame.hiding) { window.AsylumGame.hideStep(delta); return; }
    const k = pilot.keys;
    const turn = ((k.a || k.arrowleft) ? 1 : 0) - ((k.d || k.arrowright) ? 1 : 0);          // A / left arrow turns left, D / right arrow turns right
    const iz = ((k.w || k.arrowup) ? 1 : 0) - ((k.s || k.arrowdown) ? 1 : 0);                // W / up arrow walks forward, S / down arrow backward
    const p = lex.root.position;
    // the turn eases in and out (angular velocity follows the key with a short delay), so it never jerks
    pilot.omega += (turn * 2.6 - pilot.omega) * (1 - Math.exp(-11 * delta));
    if (!turn && Math.abs(pilot.omega) < 0.02) pilot.omega = 0;
    if (pilot.omega) {
      lex.currentYaw += pilot.omega * delta;
      lex.root.rotation.y = lex.currentYaw;
    }
    const dir = new THREE.Vector3(Math.sin(lex.currentYaw) * iz, 0, Math.cos(lex.currentYaw) * iz);
    const moving = iz !== 0;

    if (moving) {
      dir.normalize();
      const speed = (k.shift ? 4.2 : 2.5) * (iz < 0 ? 0.6 : 1) * ((window.AsylumGame && window.AsylumGame.isActive()) ? window.AsylumGame.speedMult() : 1);
      const nx = p.x + dir.x * speed * delta;
      if (!pilotBlocked(nx, p.z)) p.x = nx;
      const nz = p.z + dir.z * speed * delta;
      if (!pilotBlocked(p.x, nz)) p.z = nz;

      lex.walkCycle += delta * speed * 3.4;
      const c = lex.walkCycle;
      lex.pelvis.position.y = 0.88 + Math.abs(Math.sin(c)) * 0.025;
      lex.pelvis.rotation.y = Math.sin(c) * 0.05;
      const lt = Math.sin(c) * 0.55, rt = -Math.sin(c) * 0.55;
      lex.leftThigh.rotation.x = lt;
      lex.leftCalf.rotation.x = lt < 0 ? -lt * 0.95 : 0.08;
      lex.rightThigh.rotation.x = rt;
      lex.rightCalf.rotation.x = rt < 0 ? -rt * 0.95 : 0.08;
      lex.coatSkirtLeft.rotation.x = lt * 0.7;
      lex.coatSkirtRight.rotation.x = rt * 0.7;
      lex.leftUpperArm.rotation.set(-Math.sin(c) * 0.4, 0, 0.08);
      lex.leftForearm.rotation.set(0.2 + Math.max(0, -Math.sin(c)) * 0.25, 0, 0);
      lex.rightUpperArm.rotation.set(Math.sin(c) * 0.4, 0, -0.08);
      lex.rightForearm.rotation.set(0.2 + Math.max(0, Math.sin(c)) * 0.25, 0, 0);
      lex.rightHand.rotation.set(0, 0, 0);
    } else {
      lex.pelvis.position.y = 0.88 + Math.sin(time * 2.2) * 0.008;
      lex.pelvis.rotation.y = 0;
      [lex.leftThigh, lex.leftCalf, lex.rightThigh, lex.rightCalf, lex.coatSkirtLeft, lex.coatSkirtRight].forEach(g => g.rotation.set(0, 0, 0));
      lex.leftUpperArm.rotation.set(0.05, 0, 0.08);
      lex.leftForearm.rotation.set(0.12, 0, 0);
      lex.rightUpperArm.rotation.set(0.05, 0, -0.08);
      lex.rightForearm.rotation.set(0.12, 0, 0);
      lex.rightHand.rotation.set(0, 0, 0);
      if (Math.abs(pilot.omega) > 0.4) {                 // turning on the spot: little steps instead of sliding
        lex.walkCycle += delta * 4.5;
        const c = lex.walkCycle;
        lex.leftThigh.rotation.x = Math.sin(c) * 0.28; lex.rightThigh.rotation.x = -Math.sin(c) * 0.28;
        lex.leftCalf.rotation.x = Math.max(0, -Math.sin(c)) * 0.3; lex.rightCalf.rotation.x = Math.max(0, Math.sin(c)) * 0.3;
      }
    }
    // jump (Space): up with 4.4 m/s against 12 m/s2 of gravity, about 0.8 m high; legs tuck while in the air
    // the ground: flat (0) in the warehouse; in the asylum it follows a staircase you are on
    const gInfo = (window.AsylumGame && window.AsylumGame.isActive()) ? window.AsylumGame.groundAt(p.x, p.z) : { y: 0, on: false };
    const ground = gInfo.y;
    if (k[' '] && p.y <= ground + 0.02 && pilot.vy === 0) pilot.vy = 4.4;
    if (pilot.vy !== 0 || p.y > ground + 0.03) {
      pilot.vy -= 12 * delta;
      p.y += pilot.vy * delta;
      if (p.y <= ground) { p.y = ground; pilot.vy = 0; }
    } else {
      p.y = ground;                                        // walking: the feet follow the steps
    }
    if (p.y > ground + 0.02) {
      lex.leftThigh.rotation.x = -0.55; lex.leftCalf.rotation.x = 0.9;
      lex.rightThigh.rotation.x = -0.25; lex.rightCalf.rotation.x = 0.5;
      lex.leftUpperArm.rotation.set(-0.3, 0, 0.7); lex.rightUpperArm.rotation.set(-0.3, 0, -0.7);
    }

    // head looks ahead
    const ease = Math.min(1, delta * 6);
    lex.head.rotation.y += (0 - lex.head.rotation.y) * ease;
    lex.head.rotation.x += (0 - lex.head.rotation.x) * ease;
    lex.head.rotation.z += (0 - lex.head.rotation.z) * ease;

    // the camera follows by itself: it stays behind the avatar and swings round when the avatar turns (no mouse needed; dragging the mouse
    // still turns it for a moment, then it settles behind again)
    if (!state.isCameraMoving) {
      const tgt = new THREE.Vector3(p.x, p.y + 1.4, p.z);
      const inAsylum = !!(window.AsylumGame && window.AsylumGame.isActive());
      let ox = camera.position.x - controls.target.x, oz = camera.position.z - controls.target.z;
      let dist = Math.hypot(ox, oz) || 4;
      let ang = Math.atan2(ox, oz);
      const behind = Math.atan2(-Math.sin(lex.currentYaw), -Math.cos(lex.currentYaw));
      const diff = Math.atan2(Math.sin(behind - ang), Math.cos(behind - ang));
      ang += diff * (1 - Math.exp(-5.5 * delta)) + pilot.omega * delta * 0.25;      // a little lead into the turn
      const wantDist = inAsylum ? 3.4 : 4.2;
      dist += (wantDist - dist) * (1 - Math.exp(-2 * delta));
      controls.target.copy(tgt);
      camera.position.x = tgt.x + Math.sin(ang) * dist;
      camera.position.z = tgt.z + Math.cos(ang) * dist;
      camera.position.y += ((inAsylum ? 2.3 : 2.5) + p.y - camera.position.y) * (1 - Math.exp(-3 * delta));
      if (!inAsylum) {
        camera.position.x = Math.max(-HALF_W + 0.3, Math.min(HALF_W - 0.3, camera.position.x));
        camera.position.z = Math.max(-HALF_L + 0.3, Math.min(HALF_L - 0.3, camera.position.z));
      } else if (window.AsylumGame.hasLineOfSight) {
        // in the asylum the camera must not end up behind a wall or a gate: pull it toward the avatar while something is in between
        const cx = camera.position.x - tgt.x, cz = camera.position.z - tgt.z;
        const len = Math.hypot(cx, cz) || 0.01;
        let want = len;
        for (let i = 0; i < 8 && !window.AsylumGame.hasLineOfSight(tgt.x, tgt.z, tgt.x + cx / len * want, tgt.z + cz / len * want); i++) want = Math.max(0.9, want * 0.8);
        camera.position.x = tgt.x + cx / len * want;
        camera.position.z = tgt.z + cz / len * want;
        camera.position.y = Math.min(camera.position.y, gInfo.on ? p.y + 2.6 : 2.75);        // under the ceiling (the stairs have an opening in it)
      }
      camera.lookAt(controls.target);          // aim now, not one frame later (that was a small shake while moving)
    }
    // Check if secret door is open in warehouse and player steps into it
    if (window.AsylumGame && window.AsylumGame.isDoorOpen && !window.AsylumGame.isActive()) {
      if (p.x < window.AsylumGame.SECRET.x + 0.35 && Math.abs(p.z - window.AsylumGame.SECRET.z) < 0.9) {   // he has stepped into the doorway
        window.AsylumGame.enterAsylum();
      }
    }
    if (window.AsylumGame && window.AsylumGame.isActive()) setPilotHint('');       // in the asylum the asylum game shows what E does
    else pilotScan(lex);
  }

  function onPilotKey(e, down) {
    const t = e.target;
    if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable)) return;   // typing in a box: leave the keys alone
    if (e.ctrlKey || e.metaKey || e.altKey) return;
    const key = (e.key || '').toLowerCase();
    if (window.WHMulti && window.WHMulti.studioOpen) return;           // the studio has its own keys

    // Secret corner Q-press trigger (Press Q 3 times at x: 0.4, z: -7.96)
    if (key === 'q' && down && !e.repeat) {
      if (window.AsylumGame && state.lexus) {
        window.AsylumGame.checkSecretCorner(state.lexus);
      }
      if (window.WHMulti && state.lexus) window.WHMulti.onQ(state.lexus);        // the Avatar Studio ring
    }

    // the slots of the asylum: numpad 1-3 or the digits 1-3 (any digit up to 9 is passed on)
    if (window.AsylumGame && window.AsylumGame.isActive() && down && !e.repeat) {
      const m = /^(?:Numpad|Digit)([1-9])$/.exec(e.code || '');
      if (m) { e.preventDefault(); window.AsylumGame.useSlot(parseInt(m[1], 10) - 1); return; }
    }

    if (window.AsylumGame && window.AsylumGame.isActive()) {
      if (['f', 'e', 'r', 'escape'].includes(key) && down && !e.repeat) {
        window.AsylumGame.handleKey(key, down);
        if (key === 'escape' || key === 'f') return;
      }
    }

    if (!pilot.on) return;
    if (['w', 'a', 's', 'd', 'arrowup', 'arrowdown', 'arrowleft', 'arrowright', 'shift', 'e', 'escape', ' '].includes(key)) {
      e.preventDefault();      // arrows must not scroll the page while driving
      if (key === 'escape') { if (down) setPilot(false); return; }
      if (key === 'e') {
        if (window.AsylumGame && window.AsylumGame.isActive()) {
          // Handled by AsylumGame
        } else {
          if (down && !e.repeat) pilotInteract();
        }
        return;
      }
      pilot.keys[key] = down;
    }
  }

  // =========================================================================
  // 12. LEXUS: PERSONALITY + THE BRAIN
  // Lexus is warm, a little witty and proud of his warehouse. What he says comes from three places: his own
  // quick reactions (lights, door, wave), the box and rack search of this page, and the NEXUS-L Brain (/bot/ask)
  // for everything else. His mood moves his head and arms (see applyMood).
  // =========================================================================
  const PERSONA = {
    greet: ["👋 Welcome to the DTP QC Laboratory! I'm <b>Lexus</b>, your guide. Tell me what you are looking for and I'll walk you right to it.",
      "👋 Hi there! <b>Lexus</b> at your service. Search a box, ask me anything, or tap a lamp if it is too dark."],
    lightsOn: ["💡 Much better! I can see all the boxes now.", "💡 Let there be light! Thanks for that.", "💡 Lights on. Now nothing can hide from us."],
    lightsOff: ["🌙 Whoa, who turned the lights off? Tap a lamp or the bulb button and I'll be happy again.", "🌑 It's pretty dark in here... I'm not scared. Okay, a little. Switch a lamp on?"],
    thinking: ["🤔 Let me think about that...", "🧠 Checking my brain for \"{q}\"...", "🔎 One moment, looking into it..."],
    found: ["🔍 Found it! <b>{label}</b> is on <b>{rack}</b>, layer <b>{tier}</b>. Follow me, I'll walk you there.",
      "🎯 Got it: <b>{label}</b> sits on <b>{rack}</b>, layer <b>{tier}</b>. This way!"],
    rack: ["📍 Heading to <b>{rack}</b>. Keep up with me!", "📍 <b>{rack}</b> it is. Follow me down the aisle."],
    notFound: ["🤷 I couldn't find that on the shelves, but let me ask my brain..."],
    offline: ["😅 My brain is not answering right now. You can still search the boxes by label, or try again in a moment.",
      "🔌 I lost the connection to my brain for a second. Ask me again?"],
    idle: ["💡 Tip: tap any lamp in the room to switch it on or off.", "🗂️ Try asking me <i>\"Where is Tech Trial?\"</i> or <i>\"Take me to Rack 2\"</i>.",
      "🎥 Press Follow and the camera walks along with me.", "👀 I'm still here if you need a box. Just ask!"],
    wave: ["👋 Hello again!"],
    door: ["🚪 Back at the door. Ready when you are!"]
  };
  const pick = (arr) => arr[Math.floor(Math.random() * arr.length)];
  const fill = (tpl, o) => tpl.replace(/\{(\w+)\}/g, (_, k) => (o && o[k] != null ? o[k] : ''));

  // -------------------------------------------------------------------------
  // LEXUS'S VOICE: Lexus is a man, so he speaks with a male voice (the browser's own speech engine, no server and no key).
  // The first male voice found is used; the 🔊 button in the bubble switches the voice on and off (remembered).
  // -------------------------------------------------------------------------
  const MALE_VOICE_HINTS = /\b(david|mark|guy|james|george|ryan|christopher|eric|roger|steffan|daniel|alex|fred|thomas|google uk english male|male)\b/i;
  const FEMALE_VOICE_HINTS = /\b(zira|hazel|susan|aria|jenny|samantha|victoria|karen|moira|tessa|female|woman)\b/i;
  const voice = { on: true, picked: null };
  try { voice.on = localStorage.getItem('wh.lexusVoice') !== '0'; } catch (e) { /* private window: keep the default */ }

  function pickMaleVoice() {
    if (!('speechSynthesis' in window)) return null;
    const all = window.speechSynthesis.getVoices() || [];
    const en = all.filter(v => /^en/i.test(v.lang));
    const pool = en.length ? en : all;
    return pool.find(v => MALE_VOICE_HINTS.test(v.name) && !FEMALE_VOICE_HINTS.test(v.name))
      || pool.find(v => !FEMALE_VOICE_HINTS.test(v.name))
      || pool[0] || null;
  }

  // plain words of a message: no markup, emoji or symbols, and short enough to say in a few seconds
  function plainSpeech(html) {
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    let t = (tmp.textContent || '').replace(/[\u{1F000}-\u{1FAFF}\u{2600}-\u{27BF}\u{FE0F}]/gu, '').replace(/[#*_`|>]+/g, ' ').replace(/\s+/g, ' ').trim();
    if (t.length > 260) { t = t.slice(0, 260); const cut = Math.max(t.lastIndexOf('. '), t.lastIndexOf('? '), t.lastIndexOf('! ')); t = cut > 80 ? t.slice(0, cut + 1) : t.replace(/\s+\S*$/, '') + '.'; }
    return t;
  }

  // The real voice comes from the server (a natural male voice, made once per sentence and kept). The browser's own voice is only the fallback.
  const voiceEl = new Audio();
  voiceEl.preload = 'auto';
  voiceEl.addEventListener('playing', () => { if (dom.bubbleAudioWave) dom.bubbleAudioWave.classList.add('speaking'); });
  ['ended', 'pause', 'error'].forEach(ev => voiceEl.addEventListener(ev, () => { if (dom.bubbleAudioWave) dom.bubbleAudioWave.classList.remove('speaking'); }));
  let voiceSeq = 0;             // a newer message makes an older answer stale (no request is aborted: the page's error watcher would report an abort as a network failure)
  let voiceBlocked = null;      // an answer the browser would not play yet (no click on the page so far)
  let voiceUnlocked = false;

  // Browsers allow sound only after the visitor has touched the page once. The first click, tap or key anywhere unlocks it for good.
  function unlockVoice() {
    if (voiceUnlocked) return;
    voiceUnlocked = true;
    ['pointerdown', 'keydown', 'touchstart'].forEach(ev => document.removeEventListener(ev, unlockVoice, true));
    try { voiceEl.muted = true; voiceEl.src = 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA='; voiceEl.play().catch(() => {}); } catch (e) { /* ignore */ }
    voiceEl.muted = false;
    if (voiceBlocked) { const t = voiceBlocked; voiceBlocked = null; speakLexus(t, true); }
  }
  ['pointerdown', 'keydown', 'touchstart'].forEach(ev => document.addEventListener(ev, unlockVoice, true));

  function speakBrowser(text) {
    if (!('speechSynthesis' in window)) return;
    window.speechSynthesis.cancel();
    const u = new SpeechSynthesisUtterance(text);
    voice.picked = voice.picked || pickMaleVoice();
    if (voice.picked) { u.voice = voice.picked; u.lang = voice.picked.lang; }
    u.pitch = 0.8;
    u.onstart = () => { if (dom.bubbleAudioWave) dom.bubbleAudioWave.classList.add('speaking'); };
    u.onend = u.onerror = () => { if (dom.bubbleAudioWave) dom.bubbleAudioWave.classList.remove('speaking'); };
    window.speechSynthesis.speak(u);
  }

  async function speakLexus(htmlOrText, isPlain) {
    if (!voice.on) return;
    const text = isPlain ? htmlOrText : plainSpeech(htmlOrText);
    if (text.length < 3) return;
    const seq = ++voiceSeq;
    voiceEl.pause();
    if ('speechSynthesis' in window) window.speechSynthesis.cancel();
    if (!voiceUnlocked) { voiceBlocked = text; return; }      // plays at the first click
    try {
      const res = await fetch((root.dataset.voiceUrl || '/NEXUS-L/warehouse-tracker/voice') + '?t=' + encodeURIComponent(text), { credentials: 'same-origin' });
      if (!res.ok) throw new Error('voice ' + res.status);
      const blob = await res.blob();
      if (seq !== voiceSeq) return;      // a newer message came while this one was being made
      const old = voiceEl.src;
      voiceEl.src = URL.createObjectURL(blob);
      if (old && old.startsWith('blob:')) URL.revokeObjectURL(old);
      await voiceEl.play();
    } catch (err) {
      if (err && err.name === 'NotAllowedError') { voiceBlocked = text; return; }
      speakBrowser(text);   // the voice service is down: the browser voice still answers
    }
  }

  function setVoice(on) {
    voice.on = !!on;
    try { localStorage.setItem('wh.lexusVoice', voice.on ? '1' : '0'); } catch (e) { /* ignore */ }
    if (!voice.on) { voiceSeq++; voiceEl.pause(); if ('speechSynthesis' in window) window.speechSynthesis.cancel(); }
    const b = document.getElementById('wh-btn-voice');
    if (b) {
      b.classList.toggle('active', voice.on);
      b.textContent = voice.on ? '🔊 Voice' : '🔇 Voice';
      b.setAttribute('aria-pressed', voice.on ? 'true' : 'false');
    }
  }

  if ('speechSynthesis' in window) {
    window.speechSynthesis.onvoiceschanged = () => { voice.picked = pickMaleVoice(); };
  }

  let sayTimer = null;
  // Puts a message in Lexus's speech bubble with a short typing effect and gives him a mood (happy, surprised, thinking, curious)
  function lexusSay(html, mood) {
    // Expose globally for Asylum minigame commentary
    window.lexusSay = lexusSay;
    if (!dom.bubbleText) return;
    state.lastSpoke = performance.now();
    if (mood !== 'thinking') speakLexus(html);
    if (mood && state.lexus) { state.lexus.mood = mood; state.lexus.moodUntil = performance.now() + 3200; }
    if (sayTimer) clearInterval(sayTimer);
    if (dom.bubbleAudioWave) dom.bubbleAudioWave.classList.add('speaking');
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    const total = (tmp.textContent || '').length;
    if (total < 4 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      dom.bubbleText.innerHTML = html;
      if (dom.bubbleAudioWave) setTimeout(() => dom.bubbleAudioWave.classList.remove('speaking'), 1500);
      return;
    }
    // reveal the real markup progressively: clone the nodes and cut the text, so bold/italic stay intact
    let shown = 0;
    const step = Math.max(2, Math.ceil(total / 60));
    sayTimer = setInterval(() => {
      shown = Math.min(total, shown + step);
      dom.bubbleText.innerHTML = cutHtml(html, shown);
      dom.bubbleText.scrollTop = dom.bubbleText.scrollHeight;
      if (shown >= total) {
        clearInterval(sayTimer);
        sayTimer = null;
        setTimeout(() => dom.bubbleAudioWave && dom.bubbleAudioWave.classList.remove('speaking'), 900);
      }
    }, 22);
  }

  // the first n characters of the visible text of an HTML string, tags kept and closed
  function cutHtml(html, n) {
    const root = document.createElement('div');
    root.innerHTML = html;
    let left = n;
    (function walk(node) {
      for (const c of Array.from(node.childNodes)) {
        if (left <= 0) { c.remove(); continue; }
        if (c.nodeType === 3) {
          if (c.nodeValue.length > left) { c.nodeValue = c.nodeValue.slice(0, left); left = 0; } else left -= c.nodeValue.length;
        } else walk(c);
      }
    })(root);
    return root.innerHTML;
  }

  // Mood: small body language on top of the normal pose (called at the end of updateLexus)
  function applyMood(lex, time) {
    if (!lex.mood || performance.now() > lex.moodUntil) { lex.mood = null; return; }
    if (lex.state !== 'welcoming') return;
    const t = (lex.moodUntil - performance.now()) / 3200;      // 1 -> 0
    const ease = Math.sin(Math.min(1, t * 3) * Math.PI / 2);   // fades in fast, stays
    if (lex.mood === 'happy') {
      lex.pelvis.position.y += Math.abs(Math.sin(time * 9)) * 0.03 * ease;
      lex.head.rotation.z += Math.sin(time * 6) * 0.06 * ease;
    } else if (lex.mood === 'surprised') {
      lex.head.rotation.x -= 0.22 * ease;
      lex.leftUpperArm.rotation.set(-0.55 * ease, 0, 0.08 + 0.55 * ease);
      lex.leftForearm.rotation.set(0.12 + 0.5 * ease, 0, 0);
    } else if (lex.mood === 'thinking') {
      lex.head.rotation.z += 0.14 * ease;
      lex.head.rotation.x += 0.08 * ease;
      lex.leftUpperArm.rotation.set(-0.9 * ease, 0, 0.08);
      lex.leftForearm.rotation.set(0.12 - 1.5 * ease, 0, 0);
    } else if (lex.mood === 'curious') {
      lex.head.rotation.z -= 0.16 * ease;
      lex.head.rotation.x -= 0.06 * ease;
    }
  }

  // What a brain answer looks like in the bubble: title, text and the first steps
  function brainHtml(reply) {
    const esc = escapeHtml;
    // the bubble is small: drop markdown marks and page markers, keep the first few sentences
    let text = String(reply.text || '').replace(/-{2,}[^-\n]*-{2,}/g, ' ').replace(/#{1,6}\s*/g, '').replace(/\s+/g, ' ').trim();
    if (text.length > 280) text = text.slice(0, 280).replace(/\s+\S*$/, '') + '…';
    let h = '';
    if (reply.title && !text.startsWith(reply.title)) h += `<b>${esc(String(reply.title).replace(/#{1,6}\s*/g, '').slice(0, 80))}</b><br>`;
    h += esc(text).replace(/\*\*(.+?)\*\*/g, '<b>$1</b>');
    const steps = Array.isArray(reply.steps) ? reply.steps.slice(0, 3) : [];
    if (steps.length) h += '<ul class="wh-bubble-steps">' + steps.map(s => `<li>${esc(String(s).slice(0, 140))}</li>`).join('') + '</ul>';
    return h;
  }

  async function askLexusAI(query) {
    if (!query || !query.trim()) return;
    const q = query.trim();
    const lower = q.toLowerCase();

    // 0. Things Lexus does by himself
    if (/\b(turn|switch)\s*(on|off)\b.*\b(light|lamp|bulb)s?\b|\b(lights?|lamps?|bulbs?)\s*(on|off)\b/.test(lower)) {
      setAllBulbs(!/\boff\b/.test(lower));
      return;
    }
    if (/^(wave|say hi|hello|hi)\b/.test(lower) && lower.split(/\s+/).length <= 3) {
      setLexusState('welcoming');
      lexusSay(pick(PERSONA.wave), 'happy');
      return;
    }
    if (/\b(go|back|return)\b.*\bdoor\b/.test(lower)) {
      resetLexusToDoor();
      lexusSay(pick(PERSONA.door), 'happy');
      return;
    }

    lexusSay(fill(pick(PERSONA.thinking), { q: escapeHtml(q.length > 40 ? q.slice(0, 40) + '…' : q) }), 'thinking');

    // 1. A box or a rack of this page: Lexus walks to it
    let matchedBox = state.boxes.find(b => {
      const str = `${b.box_label || ''} ${b.label || ''} ${b.specimen_type || ''} ${b.category || ''} ${b.storage_code || ''}`.toLowerCase();
      return str.includes(lower) || lower.split(/\s+/).some(w => w.length > 3 && str.includes(w));
    });
    let matchedRack = null;
    if (!matchedBox) {
      matchedRack = state.racks.find(rk => {
        const rkStr = `${rk.id} ${rk.name} ${(rk.labels || []).join(' ')} ${(rk.shelf_types || []).join(' ')}`.toLowerCase();
        return rkStr.includes(lower) ||
          (lower.includes('rack 1') && rk.id === 'RACK-1') || (lower.includes('rack 2') && rk.id === 'RACK-2') ||
          (lower.includes('rack 3') && rk.id === 'RACK-3') || (lower.includes('rack 4') && rk.id === 'RACK-4') ||
          (lower.includes('window') && rk.id === 'RACK-WINDOW') || (lower.includes('partition') && rk.id === 'RACK-PART');
      });
    }
    if (matchedBox) {
      const rk = state.racks.find(r => r.id === matchedBox.rack_id);
      const label = matchedBox.label || matchedBox.box_label || 'the box';
      lexusSay(fill(pick(PERSONA.found), { label: escapeHtml(label), rack: escapeHtml(rk ? rk.name.split(' - ')[0] : matchedBox.rack_id), tier: matchedBox.tier_level }), 'happy');
      selectBox(matchedBox);
      showNavigationPathToBox(matchedBox);
      return;
    }
    if (matchedRack) {
      const boxInRack = state.boxes.find(b => b.rack_id === matchedRack.id);
      lexusSay(fill(pick(PERSONA.rack), { rack: escapeHtml(matchedRack.name.split(' - ')[0]) }), 'happy');
      if (boxInRack) {
        selectBox(boxInRack);
        showNavigationPathToBox(boxInRack);
      }
      return;
    }

    // 2. Anything else: the NEXUS-L Brain answers, Lexus delivers it
    try {
      const botUrl = root.dataset.botAskUrl || '/NEXUS-L/bot/ask';
      const payload = { message: q, page: 'warehouse-tracker' };
      if (csrfToken && csrfHash) payload[csrfToken] = csrfHash;
      const res = await fetch(botUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfHash || '' },
        body: JSON.stringify(payload)
      });
      const data = res.ok ? await res.json() : null;
      if (data && data.reply && data.reply.text) {
        lexusSay('🤖 ' + brainHtml(data.reply), 'curious');
      } else if (data && data.escalated) {
        lexusSay('🧑‍💼 An administrator is chatting with you now. Look in the Brain chat (bottom right).', 'curious');
      } else {
        lexusSay(pick(PERSONA.notFound) + '<br>' + pick(PERSONA.offline), 'surprised');
      }
    } catch (err) {
      console.warn('Lexus brain notice:', err);
      lexusSay(pick(PERSONA.offline), 'surprised');
    }
  }

  // Now and then Lexus says something when nobody has touched anything for a while
  function lexusIdleChatter() {
    if (!state.lexus || state.lexus.state !== 'welcoming') return;
    if (performance.now() - (state.lastSpoke || 0) < 25000) return;
    lexusSay(pick(PERSONA.idle), 'curious');
  }

  function bindEvents() {
    // 3D Speech Bubble Interactive Input
    if (dom.bubbleAskForm && dom.bubbleInput) {
      dom.bubbleAskForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const msg = dom.bubbleInput.value;
        if (msg && msg.trim()) {
          askLexusAI(msg);
          dom.bubbleInput.value = '';
        }
      });
    }

    const btnVoice = document.getElementById('wh-btn-voice');
    if (btnVoice) {
      btnVoice.addEventListener('click', () => {
        setVoice(!voice.on);
        if (voice.on) speakLexus('Voice on. I am Lexus, and this is how I sound.', true);
      });
      setVoice(voice.on);
    }
    const btnPilot = document.getElementById('wh-btn-pilot');
    if (btnPilot) btnPilot.addEventListener('click', () => setPilot(!pilot.on));
    window.addEventListener('keydown', (e) => onPilotKey(e, true));
    window.addEventListener('keyup', (e) => onPilotKey(e, false));
    window.addEventListener('blur', () => { pilot.keys = {}; });
    const btnBulb = document.getElementById('wh-btn-bulb');
    if (btnBulb) {
      btnBulb.addEventListener('click', () => setAllBulbs(!(state.lightLevel > 0)));
    }
    setInterval(lexusIdleChatter, 8000);

    if (dom.btnWaveLexus) {
      dom.btnWaveLexus.addEventListener('click', () => {
        setLexusState('welcoming');
      });
    }

    if (dom.btnDoorLexus) {
      dom.btnDoorLexus.addEventListener('click', () => {
        resetLexusToDoor();
      });
    }

    if (dom.btnFollowLexus) {
      dom.btnFollowLexus.addEventListener('click', () => {
        state.followLexus = !state.followLexus;
        dom.btnFollowLexus.classList.toggle('active', state.followLexus);
        if (dom.followLexusTxt) {
          dom.followLexusTxt.textContent = state.followLexus ? 'Following' : 'Follow';
        }
      });
    }

    // Search input Enter triggers AI query as well
    dom.searchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        const q = dom.searchInput.value;
        if (q && q.trim()) {
          askLexusAI(q);
        }
      }
    });
    // Search input
    let searchDebounce;
    dom.searchInput.addEventListener('input', (e) => {
      clearTimeout(searchDebounce);
      searchDebounce = setTimeout(() => {
        performSearch(e.target.value);
      }, 150);
    });

    dom.searchClear.addEventListener('click', () => {
      dom.searchInput.value = '';
      performSearch('');
    });

    // Category chips
    dom.categoryChips.forEach(chip => {
      chip.addEventListener('click', () => {
        filterCategory(chip.dataset.cat);
      });
    });

    // FIFO Color Reference Modal
    if (dom.btnFifoOpen) {
      dom.btnFifoOpen.addEventListener('click', () => {
        if (dom.fifoModalBackdrop) dom.fifoModalBackdrop.classList.remove('hidden');
      });
    }
    if (dom.fifoClose) {
      dom.fifoClose.addEventListener('click', () => {
        if (dom.fifoModalBackdrop) dom.fifoModalBackdrop.classList.add('hidden');
      });
    }
    if (dom.fifoDismiss) {
      dom.fifoDismiss.addEventListener('click', () => {
        if (dom.fifoModalBackdrop) dom.fifoModalBackdrop.classList.add('hidden');
      });
    }
    if (dom.fifoModalBackdrop) {
      dom.fifoModalBackdrop.addEventListener('click', (e) => {
        if (e.target === dom.fifoModalBackdrop) {
          dom.fifoModalBackdrop.classList.add('hidden');
        }
      });
    }

    // Camera preset buttons
    dom.btnDoorView.addEventListener('click', () => {
      animateCameraTo(CAM_PRESETS.door.pos, CAM_PRESETS.door.target, 800);
      setActiveViewPill(dom.btnDoorView);
    });

    dom.btnOverheadView.addEventListener('click', () => {
      animateCameraTo(CAM_PRESETS.overhead.pos, CAM_PRESETS.overhead.target, 800);
      setActiveViewPill(dom.btnOverheadView);
    });

    dom.btnAisleView.addEventListener('click', () => {
      animateCameraTo(CAM_PRESETS.aisle.pos, CAM_PRESETS.aisle.target, 800);
      setActiveViewPill(dom.btnAisleView);
    });

    dom.btnResetCam.addEventListener('click', () => {
      animateCameraTo(CAM_PRESETS.overview.pos, CAM_PRESETS.overview.target, 800);
      clearActivePath();
      dom.inspector.classList.add('hidden');
    });

    // Lexus Guide HUD Controls
    if (dom.btnFollowLexus) {
      dom.btnFollowLexus.addEventListener('click', () => {
        state.followLexus = !state.followLexus;
        dom.btnFollowLexus.classList.toggle('active', state.followLexus);
        if (dom.followLexusTxt) {
          dom.followLexusTxt.textContent = state.followLexus ? 'Following Guide' : 'Free Camera';
        }
      });
      dom.btnFollowLexus.classList.add('active');
    }

    if (dom.btnWaveLexus) {
      dom.btnWaveLexus.addEventListener('click', () => {
        if (state.lexus) {
          setLexusState('welcoming');
          if (dom.lexusSpeech) {
            dom.lexusSpeech.innerHTML = "👋 Hello again! Ready whenever you are. Search any box or item above!";
          }
        }
      });
    }

    if (dom.btnDoorLexus) {
      dom.btnDoorLexus.addEventListener('click', () => {
        resetLexusToDoor();
      });
    }

    // Fullscreen toggle
    dom.btnFullscreen.addEventListener('click', () => {
      if (!document.fullscreenElement) {
        dom.canvasWrap.requestFullscreen().catch(err => alert(err.message));
      } else {
        document.exitFullscreen();
      }
    });

    // Inspector card actions
    dom.inspectorClose.addEventListener('click', () => {
      dom.inspector.classList.add('hidden');
      clearActivePath();
    });

    dom.pathClose.addEventListener('click', clearActivePath);

    dom.btnGuideMe.addEventListener('click', () => {
      if (state.selectedBox) showNavigationPathToBox(state.selectedBox);
    });

    if (dom.btnEditThisBox) {
      dom.btnEditThisBox.addEventListener('click', () => {
        if (state.selectedBox) openBoxModal(state.selectedBox);
      });
    }

    // Admin drawer & modal
    if (isAdmin) {
      if (dom.btnOpenAdmin) dom.btnOpenAdmin.addEventListener('click', openAdminDrawer);
      if (dom.adminClose) dom.adminClose.addEventListener('click', closeAdminDrawer);
      if (dom.adminDismiss) dom.adminDismiss.addEventListener('click', closeAdminDrawer);
      if (dom.adminBackdrop) dom.adminBackdrop.addEventListener('click', closeAdminDrawer);

      if (dom.tblSearch) dom.tblSearch.addEventListener('input', () => renderAdminTable(state.boxes));
      if (dom.tblRackFilter) dom.tblRackFilter.addEventListener('change', () => renderAdminTable(state.boxes));
      if (dom.tblTierFilter) dom.tblTierFilter.addEventListener('change', () => renderAdminTable(state.boxes));

      if (dom.btnAddNewBox) dom.btnAddNewBox.addEventListener('click', () => openBoxModal(null));
      if (dom.boxModalClose) dom.boxModalClose.addEventListener('click', closeBoxModal);
      if (dom.boxModalCancel) dom.boxModalCancel.addEventListener('click', closeBoxModal);
      if (dom.boxForm) dom.boxForm.addEventListener('submit', saveBoxForm);
    }
  }

  function setActiveViewPill(btn) {
    document.querySelectorAll('.wh-view-pill').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
  }

  // Start initialization
  init3D();
  bindEvents();
  loadData();
})();
