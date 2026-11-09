/**
 * THE DARK ASYLUM: the building (version 2: five floors).
 * Every floor is generated on a 0.5 m grid: corridors first (a different skeleton on every floor), then rooms of different sizes packed along
 * them, then doors, then the walls. The walls are read off the grid (wherever two different areas meet) and the SAME wall pieces are used for
 * what you see and for collision, so there are no invisible walls and no gaps. Furniture goes into the rooms, and a lot of it can be searched
 * or hidden in. Only one floor is visible at a time (all floors share x and z; the game switches between them with the stairs).
 *
 * Units are metres, a floor is x and z from -30 to +30, walls are 3 m high.
 */
(function () {
  'use strict';

  const H = 3.0;            // wall and ceiling height
  const T = 0.2;            // wall thickness
  const CELL = 0.5;         // grid cell
  const N = 120;            // cells per side (60 m)
  const MIN = -30;
  const AGENT_R = 0.4;      // room a person or the ghost needs from a wall
  const DOOR_W = 1.5;       // three cells

  const rnd = (a, b) => a + Math.random() * (b - a);
  const pick = (arr) => arr[Math.floor(Math.random() * arr.length)];
  const shuffle = (arr) => { for (let i = arr.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1)); [arr[i], arr[j]] = [arr[j], arr[i]]; } return arr; };
  const cellOf = (v) => Math.floor((v - MIN) / CELL);
  const centerOf = (i) => MIN + (i + 0.5) * CELL;
  const lineOf = (i) => MIN + i * CELL;

  // how each floor looks and how hard it is: floor 1 is the friendliest, floor 5 the darkest and narrowest
  const STYLE = [
    { name: 'Ground floor', layout: 'cross', wall: 0x7a776c, corridor: 3.0, dark: 1.00 },
    { name: 'Second floor', layout: 'ring', wall: 0x6d7b73, corridor: 3.0, dark: 0.90 },
    { name: 'Third floor', layout: 'wings', wall: 0x6b7184, corridor: 2.5, dark: 0.80 },
    { name: 'Fourth floor', layout: 'comb', wall: 0x7a6b6b, corridor: 2.5, dark: 0.70 },
    { name: 'Fifth floor', layout: 'snake', wall: 0x5f6670, corridor: 2.0, dark: 0.60 }
  ];

  // the corridor skeleton of a floor, as rectangles [x0, z0, x1, z1] in metres (w = corridor width)
  function skeleton(layout, w) {
    const h = w / 2;
    switch (layout) {
      case 'cross': return [[-28, -h, 28, h], [-h, -28, h, 28], [-5, -5, 5, 5]];
      case 'ring': return [[-19, -19, 19, -19 + w], [-19, 19 - w, 19, 19], [-19, -19, -19 + w, 19], [19 - w, -19, 19, 19], [-19, -h, 19, h], [-h, 19, h, 28], [-h, -28, h, -19]];
      case 'wings': return [[-17.5 - h, -28, -17.5 + h, 28], [17.5 - h, -28, 17.5 + h, 28], [-17.5, -h, 17.5, h], [-28, 10 - h, -17.5, 10 + h], [17.5, -10 - h, 28, -10 + h]];
      case 'comb': return [[-27, -28, -27 + w, 28], [-27, -20 - h, 28, -20 + h], [-27, -h, 28, h], [-27, 20 - h, 28, 20 + h], [4 - h, -20, 4 + h, 20]];
      default: return [[-28, -22 - h, 8, -22 + h], [8 - h, -22, 8 + h, 5], [-20, 4 - h, 8 + h, 4 + h], [-20 - h, 4, -20 + h, 28], [-20, 25 - h, 28, 25 + h], [20 - h, 4, 20 + h, 25]];
    }
  }

  // ------------------------------------------------------------------ furniture (shared by all floors)
  const mat = (color, rough = 0.85, metal = 0.05) => new THREE.MeshStandardMaterial({ color, roughness: rough, metalness: metal });
  const box = (parent, w, h, d, x, y, z, material, shadow = false) => {
    const m = new THREE.Mesh(new THREE.BoxGeometry(w, h, d), material);
    m.position.set(x, y, z);
    m.castShadow = shadow;
    m.receiveShadow = true;
    parent.add(m);
    return m;
  };
  const woodDark = mat(0x4a3a2c, 0.8), woodMid = mat(0x6b533c, 0.75), metalGrey = mat(0x5c6a78, 0.5, 0.5), white = mat(0xd9dfe2, 0.55, 0.1);
  const cloth = [mat(0x8b9aa5, 0.95), mat(0x9a8b76, 0.95), mat(0x7e8c7a, 0.95), mat(0x8a6f78, 0.95)];

  // each builder fills a group around its centre; the group's +z side is the front (the side people stand on)
  const BUILD = {
    bed(g, w, d) {
      [[-1, -1], [1, -1], [-1, 1], [1, 1]].forEach(([sx, sz]) => box(g, 0.06, 0.34, 0.06, sx * (w / 2 - 0.05), 0.17, sz * (d / 2 - 0.05), woodDark));
      box(g, w, 0.1, d, 0, 0.36, 0, woodMid);
      box(g, w - 0.08, 0.2, d - 0.1, 0, 0.5, 0, pick(cloth));
      box(g, w - 0.3, 0.1, 0.4, 0, 0.66, -d / 2 + 0.3, white);
      box(g, w, 0.8, 0.07, 0, 0.55, -d / 2 + 0.03, woodDark);
    },
    cabinet(g, w, d) {
      box(g, w, 2.0, d, 0, 1.0, 0, woodMid, true);
      box(g, 0.025, 1.8, 0.03, 0, 1.0, d / 2, mat(0x1f1812));
      box(g, 0.04, 0.2, 0.05, -0.08, 1.0, d / 2 + 0.02, mat(0xc9a24a, 0.4, 0.7));
      box(g, 0.04, 0.2, 0.05, 0.08, 1.0, d / 2 + 0.02, mat(0xc9a24a, 0.4, 0.7));
      box(g, w + 0.04, 0.06, d + 0.04, 0, 2.03, 0, woodDark);
    },
    locker(g, w, d) {
      box(g, w, 1.9, d, 0, 0.95, 0, metalGrey, true);
      for (let i = 0; i < 4; i++) box(g, w - 0.18, 0.02, 0.02, 0, 1.55 + i * 0.06, d / 2 + 0.005, mat(0x20272e));
      box(g, 0.04, 0.16, 0.04, w / 2 - 0.1, 1.0, d / 2 + 0.02, mat(0xb7bcc2, 0.3, 0.8));
    },
    freezer(g, w, d) {
      box(g, w, 0.82, d, 0, 0.41, 0, white, true);
      box(g, w + 0.03, 0.07, d + 0.03, 0, 0.86, 0, mat(0xb9c2c8, 0.4, 0.3));
      box(g, 0.5, 0.04, 0.06, 0, 0.92, d / 2 - 0.05, mat(0x30363c));
    },
    durabox(g, w, d) {
      box(g, w, 0.62, d, 0, 0.31, 0, mat(0x2c4a3d, 0.6, 0.1), true);
      box(g, w + 0.04, 0.09, d + 0.04, 0, 0.67, 0, mat(0x3b6552, 0.6, 0.1));
      box(g, 0.1, 0.12, 0.05, -w / 4, 0.55, d / 2 + 0.02, mat(0xe0b020, 0.5));
      box(g, 0.1, 0.12, 0.05, w / 4, 0.55, d / 2 + 0.02, mat(0xe0b020, 0.5));
    },
    desk(g, w, d) {
      box(g, w, 0.05, d, 0, 0.75, 0, woodMid, true);
      [[-1, -1], [1, -1], [-1, 1], [1, 1]].forEach(([sx, sz]) => box(g, 0.06, 0.73, 0.06, sx * (w / 2 - 0.06), 0.365, sz * (d / 2 - 0.06), woodDark));
      box(g, 0.45, 0.5, 0.45, w / 2 - 0.3, 0.25, 0, woodDark);
      box(g, 0.3, 0.04, 0.02, w / 2 - 0.3, 0.45, 0.24, mat(0xc9a24a));
    },
    chair(g, w, d) {
      box(g, w, 0.05, d, 0, 0.45, 0, woodDark);
      box(g, w, 0.5, 0.05, 0, 0.72, -d / 2 + 0.03, woodDark);
      [[-1, -1], [1, -1], [-1, 1], [1, 1]].forEach(([sx, sz]) => box(g, 0.04, 0.43, 0.04, sx * (w / 2 - 0.04), 0.215, sz * (d / 2 - 0.04), woodDark));
    },
    table(g, w, d) {
      box(g, w, 0.06, d, 0, 0.74, 0, woodMid, true);
      [[-1, -1], [1, -1], [-1, 1], [1, 1]].forEach(([sx, sz]) => box(g, 0.07, 0.72, 0.07, sx * (w / 2 - 0.08), 0.36, sz * (d / 2 - 0.08), woodDark));
    },
    shelf(g, w, d) {
      box(g, w, 1.9, 0.04, 0, 0.95, -d / 2 + 0.02, woodDark);
      for (let i = 0; i < 5; i++) box(g, w, 0.04, d, 0, 0.15 + i * 0.42, 0, woodMid);
      box(g, 0.04, 1.9, d, -w / 2 + 0.02, 0.95, 0, woodDark); box(g, 0.04, 1.9, d, w / 2 - 0.02, 0.95, 0, woodDark);
      for (let i = 0; i < 4; i++) box(g, rnd(0.2, 0.5), 0.2, d * 0.7, rnd(-w / 3, w / 3), 0.27 + i * 0.42, 0, mat(pick([0x7a5a3a, 0x3a4a5a, 0x5a3a3a, 0x4a5a3a])));
    },
    nightstand(g, w, d) { box(g, w, 0.55, d, 0, 0.275, 0, woodMid, true); box(g, 0.2, 0.03, 0.02, 0, 0.4, d / 2 + 0.01, mat(0xc9a24a)); },
    couch(g, w, d) {
      box(g, w, 0.4, d, 0, 0.3, 0, pick(cloth), true);
      box(g, w, 0.5, 0.25, 0, 0.65, -d / 2 + 0.12, pick(cloth));
      box(g, 0.2, 0.55, d, -w / 2 + 0.1, 0.45, 0, pick(cloth)); box(g, 0.2, 0.55, d, w / 2 - 0.1, 0.45, 0, pick(cloth));
    },
    wheelchair(g, w, d) {
      box(g, w - 0.1, 0.05, d - 0.2, 0, 0.5, 0.05, mat(0x20252b));
      box(g, w - 0.1, 0.45, 0.05, 0, 0.78, -d / 2 + 0.15, mat(0x20252b));
      [-1, 1].forEach(s => { const wh = new THREE.Mesh(new THREE.CylinderGeometry(0.3, 0.3, 0.04, 14), mat(0x15181c, 0.9)); wh.rotation.z = Math.PI / 2; wh.position.set(s * (w / 2), 0.3, 0.05); g.add(wh); });
    },
    tub(g, w, d) { box(g, w, 0.55, d, 0, 0.275, 0, white, true); box(g, w - 0.2, 0.05, d - 0.2, 0, 0.56, 0, mat(0x2a3138)); },
    sink(g, w, d) { box(g, w, 0.08, d, 0, 0.85, 0, white); box(g, 0.1, 0.8, 0.1, 0, 0.4, -d / 4, white); },
    toilet(g, w, d) { box(g, w, 0.4, d, 0, 0.2, 0.05, white, true); box(g, w, 0.55, 0.2, 0, 0.62, -d / 2 + 0.1, white); },
    pew(g, w, d) {
      box(g, w, 0.08, d, 0, 0.45, 0, woodMid, true);
      box(g, w, 0.55, 0.06, 0, 0.75, -d / 2 + 0.04, woodDark);
      box(g, 0.08, 0.45, d, -w / 2 + 0.05, 0.22, 0, woodDark); box(g, 0.08, 0.45, d, w / 2 - 0.05, 0.22, 0, woodDark);
    },
    opTable(g, w, d) {
      box(g, w, 0.08, d, 0, 0.9, 0, mat(0xcfd6da, 0.4, 0.5), true);
      box(g, 0.15, 0.85, 0.15, 0, 0.42, 0, mat(0x6b7480, 0.4, 0.6));
      box(g, w + 0.3, 0.04, 0.5, 0, 0.1, 0, mat(0x6b7480, 0.4, 0.6));
    },
    crate(g, w, d) {
      box(g, w, 0.8, d, 0, 0.4, 0, mat(0x6e5636, 0.9), true);
      box(g, w + 0.03, 0.05, d + 0.03, 0, 0.82, 0, mat(0x58452b, 0.9));
      box(g, w, 0.05, 0.05, 0, 0.4, d / 2 + 0.01, mat(0x3b2d1b)); box(g, w, 0.05, 0.05, 0, 0.2, d / 2 + 0.01, mat(0x3b2d1b));
    }
  };
  // name: [w along the wall, d out from the wall, h, hide label or null, search label or null]
  const SPEC = {
    bed: [1.0, 2.0, 0.7, 'under the bed', 'under the mattress'], cabinet: [1.0, 0.55, 2.0, 'the cabinet', 'the cabinet'], locker: [0.55, 0.55, 1.9, 'the locker', 'the locker'],
    freezer: [1.5, 0.75, 0.9, 'the freezer', 'the freezer'], durabox: [1.0, 0.6, 0.7, 'the durabox', 'the durabox'], desk: [1.3, 0.65, 0.8, null, 'the drawers'], chair: [0.5, 0.5, 0.8, null, null],
    table: [1.5, 0.85, 0.8, null, null], shelf: [1.2, 0.4, 1.9, null, 'the shelves'], nightstand: [0.45, 0.45, 0.55, null, 'the drawer'], couch: [1.8, 0.8, 0.9, null, 'the cushions'],
    wheelchair: [0.65, 0.7, 0.9, null, null], tub: [1.7, 0.75, 0.6, null, null], sink: [0.55, 0.45, 0.9, null, null], toilet: [0.45, 0.65, 0.8, null, 'the cistern'],
    pew: [1.8, 0.6, 0.9, null, 'under the pew'], opTable: [0.8, 1.9, 0.95, null, null], crate: [0.9, 0.7, 0.85, null, 'the crate']
  };
  const TEMPLATE = {
    bedroom: ['bed', 'cabinet', 'nightstand', 'desk', 'chair', 'durabox'],
    cell: ['bed', 'toilet', 'durabox'],
    ward: ['bed', 'bed', 'bed', 'locker', 'locker', 'wheelchair', 'table', 'nightstand'],
    storage: ['durabox', 'durabox', 'crate', 'crate', 'shelf', 'shelf', 'cabinet'],
    kitchen: ['freezer', 'freezer', 'table', 'shelf', 'cabinet', 'chair', 'chair'],
    office: ['desk', 'desk', 'chair', 'chair', 'shelf', 'shelf', 'cabinet', 'locker'],
    bathroom: ['tub', 'tub', 'sink', 'sink', 'toilet', 'toilet', 'cabinet', 'locker'],
    dayroom: ['couch', 'couch', 'couch', 'table', 'table', 'chair', 'chair', 'chair', 'shelf', 'cabinet'],
    morgue: ['freezer', 'freezer', 'freezer', 'opTable', 'locker', 'locker', 'cabinet'],
    library: ['shelf', 'shelf', 'shelf', 'shelf', 'table', 'chair', 'chair', 'desk'],
    chapel: ['pew', 'pew', 'pew', 'pew', 'table', 'shelf', 'cabinet'],
    surgery: ['opTable', 'cabinet', 'cabinet', 'freezer', 'locker', 'table']
  };
  const TYPE_NAME = { bedroom: 'Bedroom', cell: 'Padded cell', ward: 'Ward', storage: 'Storage room', kitchen: 'Kitchen', office: 'Office', bathroom: 'Bathroom', dayroom: 'Day room', morgue: 'Morgue', library: 'Library', chapel: 'Chapel', surgery: 'Surgery' };
  const overlap = (a, b, pad) => a[0] - pad < b[1] && a[1] + pad > b[0] && a[2] - pad < b[3] && a[3] + pad > b[2];

  // textures for the floors
  const tex = {};
  const patternTex = (key, draw) => {
    if (tex[key]) return tex[key];
    const c = document.createElement('canvas'); c.width = c.height = 128;
    draw(c.getContext('2d'));
    const t = new THREE.CanvasTexture(c); t.wrapS = t.wrapT = THREE.RepeatWrapping;
    return (tex[key] = t);
  };
  const FLOOR_TEX = {
    tile: () => patternTex('tile', (g) => { for (let i = 0; i < 2; i++) for (let j = 0; j < 2; j++) { g.fillStyle = (i + j) % 2 ? '#4a5260' : '#3a4150'; g.fillRect(i * 64, j * 64, 64, 64); } g.strokeStyle = 'rgba(0,0,0,.35)'; g.lineWidth = 2; g.strokeRect(1, 1, 126, 126); }),
    wood: () => patternTex('wood', (g) => { for (let i = 0; i < 4; i++) { g.fillStyle = ['#5a4636', '#4f3d2f', '#604b3a', '#53402f'][i]; g.fillRect(0, i * 32, 128, 32); g.fillStyle = 'rgba(0,0,0,.4)'; g.fillRect(0, i * 32, 128, 2); } }),
    green: () => patternTex('green', (g) => { for (let i = 0; i < 4; i++) for (let j = 0; j < 4; j++) { g.fillStyle = (i + j) % 2 ? '#3d5247' : '#34463d'; g.fillRect(i * 32, j * 32, 32, 32); } }),
    carpet: () => patternTex('carpet', (g) => { g.fillStyle = '#4a3340'; g.fillRect(0, 0, 128, 128); g.fillStyle = 'rgba(255,255,255,.05)'; for (let i = 0; i < 90; i++) g.fillRect(Math.random() * 128, Math.random() * 128, 2, 2); }),
    concrete: () => patternTex('concrete', (g) => { g.fillStyle = '#4b4f55'; g.fillRect(0, 0, 128, 128); g.fillStyle = 'rgba(0,0,0,.12)'; for (let i = 0; i < 60; i++) g.fillRect(Math.random() * 128, Math.random() * 128, 6, 2); })
  };

  // ==================================================================== STAIRS
  // A real staircase: 16 steps up (or down) 3 m along a straight flight, 1.6 m wide, with handrails. The avatar WALKS it: the height of the
  // ground follows the flight (see stairHeight). Going up, the flight ends under an opening in the ceiling; going down, the flight goes into
  // an opening in the floor. At the top (or the bottom) the avatar arrives on the next floor, at the other end of the same staircase.
  const RISE = 3.0, NSTEPS = 16, FLIGHT_W = 1.6;

  // where the flight goes in a room: along the long side, starting at the end nearest the door, on the side away from the door
  function layoutStairs(room, up) {
    const wx = room.x1 - room.x0, wz = room.z1 - room.z0;
    const alongX = wx >= wz;
    const a0 = alongX ? room.x0 : room.z0, a1 = alongX ? room.x1 : room.z1;
    const c0 = alongX ? room.z0 : room.x0, c1 = alongX ? room.z1 : room.x1;
    const door = room.door || room.doors[0];
    const da = alongX ? door.cx : door.cz, dc = alongX ? door.cz : door.cx;
    const dir = da <= (a0 + a1) / 2 ? 1 : -1;
    const s0 = (dir === 1 ? a0 : a1) + dir * 1.5;                       // 1.5 m of floor before the first step
    const Lf = Math.min(9.5, (a1 - a0) - 1.75);
    const end = s0 + dir * Lf;
    const cc = dc <= (c0 + c1) / 2 ? c1 - 0.3 - FLIGHT_W / 2 : c0 + 0.3 + FLIGHT_W / 2;
    const lo = Math.min(s0, end), hi = Math.max(s0, end);
    const rect = alongX ? [lo, hi, cc - FLIGHT_W / 2, cc + FLIGHT_W / 2] : [cc - FLIGHT_W / 2, cc + FLIGHT_W / 2, lo, hi];
    const outA = s0 - dir * 0.9;
    return {
      room, up, alongX, dir, s0, Lf, end, cc, rect,
      outX: alongX ? outA : cc, outZ: alongX ? cc : outA, out: alongX ? [-dir, 0] : [0, -dir],
      rails: [], ceilHole: null, st: null
    };
  }

  // 3D parts of a staircase (steps, handrails, sign) and its collision (the rails keep you on the flight)
  function buildStairMesh(F, L, floorLabel) {
    const g = new THREE.Group();
    const { alongX, dir, s0, Lf, end, cc, up } = L;
    const run = Lf / NSTEPS;
    const stepMat = mat(up ? 0x6f7683 : 0x2a2f38, 0.8), nosing = mat(0x7b5e3b, 0.7), railMat = mat(0x2a2f38, 0.45, 0.5), wallM = mat(0x3a3f48, 0.9);
    const put = (a, y, c, la, hh, lc, material) => {
      const m = new THREE.Mesh(new THREE.BoxGeometry(alongX ? la : lc, hh, alongX ? lc : la), material);
      m.position.set(alongX ? a : c, y, alongX ? c : a);
      m.castShadow = true; m.receiveShadow = true;
      g.add(m);
      return m;
    };
    for (let i = 0; i < NSTEPS; i++) {
      const a = s0 + dir * (i + 0.5) * run;
      const top = (up ? 1 : -1) * RISE * (i + 0.5) / NSTEPS;
      if (up) { put(a, top / 2, cc, run, top, FLIGHT_W, stepMat); put(a, top + 0.012, cc, run * 0.96, 0.025, FLIGHT_W, nosing); }
      else { const hh = top + 3.1; put(a, (top - 3.1) / 2, cc, run, hh, FLIGHT_W, stepMat); put(a, top + 0.012, cc, run * 0.96, 0.025, FLIGHT_W, nosing); }
    }
    // handrails: posts every fourth step and a sloped rail on top of them
    const dy = up ? RISE : -RISE;
    const slope = Math.atan2(dy, Lf) * dir;
    [-1, 1].forEach((sd) => {
      const c = cc + sd * (FLIGHT_W / 2 + 0.05);
      for (let i = 0; i <= NSTEPS; i += 4) {
        const a = s0 + dir * i * run, gy = dy * i / NSTEPS;
        put(a, gy + 0.47, c, 0.06, 0.95, 0.06, railMat);
      }
      const len = Math.hypot(Lf, dy);
      const rail = new THREE.Mesh(new THREE.BoxGeometry(alongX ? len : 0.07, 0.07, alongX ? 0.07 : len), railMat);
      const mid = (s0 + end) / 2;
      rail.position.set(alongX ? mid : c, dy / 2 + 0.95, alongX ? c : mid);
      if (alongX) rail.rotation.z = slope; else rail.rotation.x = -slope;
      g.add(rail);
      // the collision of the side: nobody steps off the flight sideways (or into the pit)
      const lo = Math.min(s0, end), hi = Math.max(s0, end);
      const r = alongX ? [lo, hi, c - 0.06, c + 0.06] : [c - 0.06, c + 0.06, lo, hi];
      F.walls.push(r);
      L.rails.push(r);
    });
    if (!up) {                                                     // the pit: walls down to the lower floor
      const mid = (s0 + end) / 2;
      [-1, 1].forEach((sd) => put(mid, -1.55, cc + sd * (FLIGHT_W / 2 + 0.2), Lf + 0.2, 3.1, 0.2, wallM));
      put(end + dir * 0.15, -1.55, cc, 0.2, 3.1, FLIGHT_W + 0.6, wallM);
      const far = alongX ? [Math.min(end, end + dir * 0.4), Math.max(end, end + dir * 0.4), cc - FLIGHT_W / 2 - 0.3, cc + FLIGHT_W / 2 + 0.3] : [cc - FLIGHT_W / 2 - 0.3, cc + FLIGHT_W / 2 + 0.3, Math.min(end, end + dir * 0.4), Math.max(end, end + dir * 0.4)];
      F.walls.push(far); L.rails.push(far);
    }
    // a sign at the foot of the stairs, facing the way you come from
    const cv = document.createElement('canvas'); cv.width = 512; cv.height = 128;
    const cx = cv.getContext('2d');
    cx.fillStyle = up ? '#0369a1' : '#b45309'; cx.fillRect(0, 0, 512, 128);
    cx.strokeStyle = '#f8fafc'; cx.lineWidth = 6; cx.strokeRect(4, 4, 504, 120);
    cx.fillStyle = '#f8fafc'; cx.font = 'bold 40px sans-serif'; cx.textAlign = 'center'; cx.textBaseline = 'middle';
    cx.fillText((up ? '▲ UP' : '▼ DOWN') + ' to ' + floorLabel, 256, 66);
    const sign = new THREE.Mesh(new THREE.PlaneGeometry(1.8, 0.45), new THREE.MeshBasicMaterial({ map: new THREE.CanvasTexture(cv), side: THREE.DoubleSide }));
    const sa = s0 - dir * 0.15;
    sign.position.set(alongX ? sa : cc, 2.15, alongX ? cc : sa);
    sign.rotation.y = alongX ? -dir * Math.PI / 2 : (dir === 1 ? Math.PI : 0);
    g.add(sign);
    // up: the ceiling is open above the upper part of the flight
    if (up) {
      const a0 = s0 + dir * Lf * 0.3, a1 = end + dir * 0.1;
      const lo = Math.min(a0, a1), hi = Math.max(a0, a1);
      L.ceilHole = alongX ? [lo, hi, cc - FLIGHT_W / 2 - 0.15, cc + FLIGHT_W / 2 + 0.15] : [cc - FLIGHT_W / 2 - 0.15, cc + FLIGHT_W / 2 + 0.15, lo, hi];
    }
    F.group.add(g);
  }

  // ==================================================================== one floor
  function buildFloor(index, level, upCount, downCount) {
    const style = STYLE[index];
    const F = { stairUp: [], stairDown: [], index, name: style.name, layout: style.layout, dark: style.dark, level, group: new THREE.Group(), walls: [], tall: [], furniture: [], doors: [], rooms: [], hideSpots: [], searchSpots: [], lamps: [], patrolPoints: [], stairs: [], corridorRects: [] };
    F.group.name = 'asylum-floor-' + (index + 1);
    const group = F.group;
    const lab = new Int16Array(N * N);              // 0 solid, 1 corridor, 2.. rooms
    const at = (ix, iz) => (ix < 0 || iz < 0 || ix >= N || iz >= N) ? 0 : lab[ix + iz * N];

    // ---- corridors
    const sk = skeleton(style.layout, style.corridor);
    sk.forEach(([x0, z0, x1, z1]) => {
      const r = [Math.round(x0 * 2) / 2, Math.round(z0 * 2) / 2, Math.round(x1 * 2) / 2, Math.round(z1 * 2) / 2];
      F.corridorRects.push(r);
      for (let iz = cellOf(r[1] + 0.01); iz < cellOf(r[3] - 0.01) + 1 && iz < N; iz++) for (let ix = cellOf(r[0] + 0.01); ix < cellOf(r[2] - 0.01) + 1 && ix < N; ix++) if (ix >= 1 && iz >= 1 && ix < N - 1 && iz < N - 1) lab[ix + iz * N] = 1;
    });
    // ---- rooms packed along the corridors: different sizes and depths, whatever fits
    let nextLabel = 2;
    const cellsFree = (x0, z0, x1, z1) => {
      const ix0 = cellOf(x0 + 0.01), ix1 = cellOf(x1 - 0.01), iz0 = cellOf(z0 + 0.01), iz1 = cellOf(z1 - 0.01);
      if (ix0 < 1 || iz0 < 1 || ix1 > N - 2 || iz1 > N - 2) return false;
      for (let iz = iz0; iz <= iz1; iz++) for (let ix = ix0; ix <= ix1; ix++) if (lab[ix + iz * N] !== 0) return false;
      return true;
    };
    const claim = (x0, z0, x1, z1, id) => {
      const ix0 = cellOf(x0 + 0.01), ix1 = cellOf(x1 - 0.01), iz0 = cellOf(z0 + 0.01), iz1 = cellOf(z1 - 0.01);
      for (let iz = iz0; iz <= iz1; iz++) for (let ix = ix0; ix <= ix1; ix++) lab[ix + iz * N] = id;
    };
    const lens = [4, 5, 6, 7, 8, 9, 11];
    const depths = [4, 5, 6, 7, 8];
    F.corridorRects.forEach((c) => {
      const horizontal = (c[2] - c[0]) >= (c[3] - c[1]);
      [-1, 1].forEach((side) => {
        let pos = (horizontal ? c[0] : c[1]);
        const end = (horizontal ? c[2] : c[3]);
        let guard = 0;
        while (pos < end - 3.5 && guard++ < 60) {
          let placed = false;
          const len = Math.min(pick(lens), end - pos);
          for (const depth of shuffle(depths.slice())) {
            let x0, z0, x1, z1;
            if (horizontal) { x0 = pos; x1 = pos + len; if (side < 0) { z1 = c[1]; z0 = z1 - depth; } else { z0 = c[3]; z1 = z0 + depth; } }
            else { z0 = pos; z1 = pos + len; if (side < 0) { x1 = c[0]; x0 = x1 - depth; } else { x0 = c[2]; x1 = x0 + depth; } }
            x0 = Math.round(x0 * 2) / 2; x1 = Math.round(x1 * 2) / 2; z0 = Math.round(z0 * 2) / 2; z1 = Math.round(z1 * 2) / 2;
            if (cellsFree(x0, z0, x1, z1)) {
              const id = nextLabel++;
              claim(x0, z0, x1, z1, id);
              F.rooms.push({ id, x0, z0, x1, z1, area: (x1 - x0) * (z1 - z0), doors: [], floor: index, type: 'bedroom', name: '', side, horizontal });
              pos += len;
              placed = true;
              break;
            }
          }
          if (!placed) pos += 1.5;
        }
      });
    });

    // ---- doors: each room opens onto the corridor where they touch; some rooms also open into a neighbour (loops, shortcuts)
    const doorEdges = new Set();
    const mkDoor = (kind, orient, line, i0, roomIdx, roomIdx2) => {
      // orient 'v': a vertical wall at x = lineOf(line), the door spans cells i0..i0+2 along z;  'h': a horizontal wall at z = lineOf(line), spans cells along x
      const cx = orient === 'v' ? lineOf(line) : lineOf(i0 + 1.5), cz = orient === 'v' ? lineOf(i0 + 1.5) : lineOf(line);
      for (let k = 0; k < 3; k++) doorEdges.add(orient + ':' + line + ':' + (i0 + k));
      const axis = orient === 'v' ? 'z' : 'x';
      const g = new THREE.Group();
      const hingeSign = Math.random() < 0.5 ? 1 : -1;
      const leaf = new THREE.Mesh(new THREE.BoxGeometry(axis === 'z' ? 0.07 : DOOR_W, 2.1, axis === 'z' ? DOOR_W : 0.07), mat(0x6b4a2f, 0.7, 0.05));
      leaf.castShadow = true;
      if (axis === 'z') { g.position.set(cx, 0, cz - DOOR_W / 2); leaf.position.set(0, 1.05, DOOR_W / 2); } else { g.position.set(cx - DOOR_W / 2, 0, cz); leaf.position.set(DOOR_W / 2, 1.05, 0); }
      g.add(leaf);
      const knob = new THREE.Mesh(new THREE.SphereGeometry(0.045, 8, 8), mat(0xc9a24a, 0.4, 0.7));
      knob.position.copy(leaf.position); knob.position.y = 1.0;
      if (axis === 'z') { knob.position.z += DOOR_W * 0.38; knob.position.x += 0.06; } else { knob.position.x += DOOR_W * 0.38; knob.position.z += 0.06; }
      g.add(knob);
      group.add(g);
      // the wall above the door
      const lint = axis === 'z' ? box(group, T + 0.004, H - 2.1, DOOR_W + 0.01, cx, 2.1 + (H - 2.1) / 2, cz, wallMat) : box(group, DOOR_W + 0.01, H - 2.1, T + 0.004, cx, 2.1 + (H - 2.1) / 2, cz, wallMat);
      void lint;
      const rect = axis === 'z' ? [cx - 0.12, cx + 0.12, cz - DOOR_W / 2, cz + DOOR_W / 2] : [cx - DOOR_W / 2, cx + DOOR_W / 2, cz - 0.12, cz + 0.12];
      const d = { id: 'f' + index + 'd' + F.doors.length, floor: index, cx, cz, axis, width: DOOR_W, kind, room: roomIdx, room2: roomIdx2, locked: false, keyId: null, open: 0, target: 0, group: g, leaf, hingeSign, rect, plate: null, cells: [] };
      F.doors.push(d);
      return d;
    };
    const wallMat = mat(style.wall, 0.92, 0.02);

    // where could a door between region a and region b go? every boundary edge run between them that is at least 3 cells long
    const boundaryRuns = (roomId, otherTest) => {
      const runs = [];
      const r = F.rooms.find(q => q.id === roomId);
      const ix0 = cellOf(r.x0 + 0.01), ix1 = cellOf(r.x1 - 0.01), iz0 = cellOf(r.z0 + 0.01), iz1 = cellOf(r.z1 - 0.01);
      const scan = (cells, edge) => {            // cells: list of [ix,iz] along one side of the room, edge: function to read the neighbour cell
        let run = [];
        const flush = () => { if (run.length >= 3) runs.push(run.slice()); run = []; };
        cells.forEach((c) => { const nb = edge(c); if (otherTest(nb) && at(c[0], c[1]) === roomId) run.push(c); else flush(); });
        flush();
      };
      const west = [], east = [], north = [], south = [];
      for (let iz = iz0; iz <= iz1; iz++) { west.push([ix0, iz]); east.push([ix1, iz]); }
      for (let ix = ix0; ix <= ix1; ix++) { north.push([ix, iz0]); south.push([ix, iz1]); }
      scan(west, (c) => at(c[0] - 1, c[1]));
      scan(east, (c) => at(c[0] + 1, c[1]));
      scan(north, (c) => at(c[0], c[1] - 1));
      scan(south, (c) => at(c[0], c[1] + 1));
      // each run remembers which side it is on
      return runs.map((run) => {
        const c0 = run[0], c1 = run[run.length - 1];
        if (c0[0] === c1[0]) { const sideEast = at(c0[0] + 1, c0[1]) !== roomId; return { orient: 'v', line: sideEast ? c0[0] + 1 : c0[0], start: c0[1], len: run.length }; }
        const sideSouth = at(c0[0], c0[1] + 1) !== roomId; return { orient: 'h', line: sideSouth ? c0[1] + 1 : c0[1], start: c0[0], len: run.length };
      });
    };
    F.rooms.forEach((room, ri) => {
      const runs = boundaryRuns(room.id, (nb) => nb === 1);
      if (!runs.length) return;
      runs.sort((a, b) => b.len - a.len);
      const run = runs[0];
      const off = Math.floor(Math.random() * (run.len - 2));
      const d = mkDoor('room', run.orient, run.line, run.start + off, ri, -1);
      room.door = d;
    });
    // a second door into a neighbouring room for some rooms: more loops on the higher floors
    const loopChance = 0.16 + index * 0.04;
    F.rooms.forEach((room, ri) => {
      if (Math.random() > loopChance) return;
      const runs = boundaryRuns(room.id, (nb) => nb >= 2 && nb !== room.id);
      if (!runs.length) return;
      const run = pick(runs);
      const off = Math.floor(Math.random() * (run.len - 2));
      mkDoor('inner', run.orient, run.line, run.start + off, ri, -1);
    });
    // every room knows all the doors in its walls (a door touches the two rooms on either side of it)
    F.doors.forEach((d) => { F.rooms.forEach((r) => { if (d.cx > r.x0 - 0.3 && d.cx < r.x1 + 0.3 && d.cz > r.z0 - 0.3 && d.cz < r.z1 + 0.3 && !r.doors.includes(d)) r.doors.push(d); }); });
    // rooms without any door (they touch no corridor) are removed from the building: filled back to solid
    F.rooms = F.rooms.filter((room) => {
      if (room.doors.length) return true;
      claim(room.x0, room.z0, room.x1, room.z1, 0);
      return false;
    });

    // ---- the walls, read off the grid: wherever two different areas meet there is a wall piece, except in the door gaps
    const addWallRun = (orient, line, a0, a1, flushStart, flushEnd) => {       // a0..a1 in cells along the wall
      const ext = T / 2 - 0.004;                                                // into the wall it meets, but not to its far face
      const start = lineOf(a0) - (flushStart ? 0 : ext), end = lineOf(a1) + (flushEnd ? 0 : ext);
      const alongLen = end - start, mid = (start + end) / 2;
      const w = orient === 'v' ? T : alongLen, d = orient === 'v' ? alongLen : T;
      const cx = orient === 'v' ? lineOf(line) : mid, cz = orient === 'v' ? mid : lineOf(line);
      box(group, w, H, d, cx, H / 2, cz, wallMat, true);
      const r = [cx - w / 2, cx + w / 2, cz - d / 2, cz + d / 2];
      F.walls.push(r); F.tall.push(r);
    };
    for (let i = 0; i <= N; i++) {
      let s = -1;
      for (let j = 0; j <= N; j++) {
        let need = false;
        if (j < N) { const a = at(i - 1, j), b = at(i, j); need = a !== b && (a || b) && !doorEdges.has('v:' + i + ':' + j); }
        if (need && s < 0) s = j;
        if (!need && s >= 0) { addWallRun('v', i, s, j, doorEdges.has('v:' + i + ':' + (s - 1)), doorEdges.has('v:' + i + ':' + j)); s = -1; }
      }
    }
    for (let j = 0; j <= N; j++) {
      let s = -1;
      for (let i = 0; i <= N; i++) {
        let need = false;
        if (i < N) { const a = at(i, j - 1), b = at(i, j); need = a !== b && (a || b) && !doorEdges.has('h:' + j + ':' + i); }
        if (need && s < 0) s = i;
        if (!need && s >= 0) { addWallRun('h', j, s, i, doorEdges.has('h:' + j + ':' + (s - 1)), doorEdges.has('h:' + j + ':' + i)); s = -1; }
      }
    }

    // ---- which rooms hold the stairs of this floor: long rooms (a flight needs room), no furniture in them
    {
      const longOf = (r) => Math.max(r.x1 - r.x0, r.z1 - r.z0), shortOf = (r) => Math.min(r.x1 - r.x0, r.z1 - r.z0);
      const cands = F.rooms.filter(r => r.door && shortOf(r) >= 4 && longOf(r) >= 6);
      cands.sort((p, q) => (Math.min(longOf(q), 9) - Math.min(longOf(p), 9)) + (Math.random() - 0.5) * 2);
      for (let k = 0; k < downCount && cands.length; k++) { const r = cands.shift(); r.stairKind = 'down'; r.stairLayout = layoutStairs(r, false); F.stairDown.push(r); }
      for (let k = 0; k < upCount && cands.length; k++) { const r = cands.shift(); r.stairKind = 'up'; r.stairLayout = layoutStairs(r, true); F.stairUp.push(r); }
    }
    F.stairLayouts = F.rooms.filter(r => r.stairLayout).map(r => r.stairLayout);
    // the ceiling opening of the up stairs is known once the flights are drawn; the floor openings of the down stairs now
    const floorHoles = F.stairLayouts.filter(L => !L.up).map(L => ({ room: L.room, hole: [L.rect[0] - 0.25, L.rect[1] + 0.25, L.rect[2] - 0.25, L.rect[3] + 0.25] }));

    // ---- floors and ceiling
    const base = new THREE.Mesh(new THREE.PlaneGeometry(64, 64), mat(0x14171e, 0.95));
    base.rotation.x = -Math.PI / 2; base.position.y = -3.1; base.receiveShadow = true; group.add(base);       // the bottom of the pits under the down stairs
    let floorOrder = 0;                                    // later floors are drawn over earlier ones where they overlap (no flicker)
    const floorPlane = (x0, z0, x1, z1, texKey) => {
      const t = FLOOR_TEX[texKey]().clone(); t.needsUpdate = true; t.repeat.set((x1 - x0) / 2, (z1 - z0) / 2); t.anisotropy = 8;
      const k = Math.min(60, ++floorOrder);
      const m = new THREE.Mesh(new THREE.PlaneGeometry(x1 - x0, z1 - z0), new THREE.MeshStandardMaterial({ map: t, roughness: 0.9, metalness: 0.05, polygonOffset: true, polygonOffsetFactor: -k, polygonOffsetUnits: -k }));
      m.rotation.x = -Math.PI / 2; m.position.set((x0 + x1) / 2, 0, (z0 + z1) / 2); m.receiveShadow = true; group.add(m);
    };
    F.corridorRects.forEach((r) => floorPlane(r[0] - 0.1, r[1] - 0.1, r[2] + 0.1, r[3] + 0.1, index % 2 ? 'concrete' : 'tile'));
    F.rooms.forEach((r) => {
      r.floorTex = pick(['wood', 'wood', 'green', 'carpet', 'concrete', 'tile']);
      const fh = floorHoles.find(h => h.room === r);
      if (!fh) { floorPlane(r.x0 - 0.1, r.z0 - 0.1, r.x1 + 0.1, r.z1 + 0.1, r.floorTex); return; }
      // a room with a hole in the floor (the way down): floor all round the opening
      const [h0, h1, k0, k1] = fh.hole;
      const x0 = Math.max(r.x0, h0), x1 = Math.min(r.x1, h1), z0 = Math.max(r.z0, k0), z1 = Math.min(r.z1, k1);
      [[r.x0, r.z0, x0, r.z1], [x1, r.z0, r.x1, r.z1], [x0, r.z0, x1, z0], [x0, z1, x1, r.z1]].forEach(([a, b, c, d]) => { if (c - a > 0.05 && d - b > 0.05) floorPlane(a, b, c, d, r.floorTex); });
    });

    // ---- what each room is, and what is in it
    const typesBySize = (area) => area < 20 ? ['cell', 'cell', 'bathroom', 'storage', 'bedroom'] : area < 38 ? ['bedroom', 'bedroom', 'office', 'kitchen', 'ward', 'library', 'morgue', 'surgery', 'storage'] : ['dayroom', 'ward', 'chapel', 'library', 'dayroom', 'surgery', 'storage'];
    F.rooms.forEach((room) => { room.type = room.stairKind ? 'stairs' : pick(typesBySize(room.area)); room.name = room.stairKind ? (room.stairKind === 'up' ? 'Stairs up' : 'Stairs down') : TYPE_NAME[room.type]; });
    const itemsOf = (room) => {
      const list = TEMPLATE[room.type].slice();
      const extra = Math.floor(room.area / 14);
      const more = ['chair', 'table', 'durabox', 'cabinet', 'crate', 'nightstand', 'locker', 'couch'];
      for (let i = 0; i < extra; i++) list.push(pick(more));
      return list;
    };
    F.rooms.filter(r => !r.stairKind).forEach((room) => {
      const placed = [];
      const lanes = room.doors.map((d) => {
        const inside = (x, z) => x > room.x0 && x < room.x1 && z > room.z0 && z < room.z1;
        const nx = d.axis === 'z' ? (inside(d.cx + 0.6, d.cz) ? 1 : -1) : 0, nz = d.axis === 'x' ? (inside(d.cx, d.cz + 0.6) ? 1 : -1) : 0;
        const ex = d.cx + nx * 2.4, ez = d.cz + nz * 2.4;
        return d.axis === 'z' ? [Math.min(d.cx, ex) - 0.1, Math.max(d.cx, ex) + 0.1, d.cz - 0.95, d.cz + 0.95] : [d.cx - 0.95, d.cx + 0.95, Math.min(d.cz, ez) - 0.1, Math.max(d.cz, ez) + 0.1];
      });
      itemsOf(room).forEach((name) => {
        const [sw, sd, sh, hide, search] = SPEC[name];
        for (let attempt = 0; attempt < 60; attempt++) {
          const side = Math.floor(Math.random() * 4);
          const alongX = side === 0 || side === 2;
          const span = alongX ? room.x1 - room.x0 : room.z1 - room.z0;
          if (span < sw + 0.6) continue;
          const t = rnd(sw / 2 + 0.2, span - sw / 2 - 0.2);
          let cx, cz, w, d, rotY;
          if (side === 0) { cx = room.x0 + t; cz = room.z0 + sd / 2 + 0.12; w = sw; d = sd; rotY = 0; }
          else if (side === 2) { cx = room.x0 + t; cz = room.z1 - sd / 2 - 0.12; w = sw; d = sd; rotY = Math.PI; }
          else if (side === 3) { cx = room.x0 + sd / 2 + 0.12; cz = room.z0 + t; w = sd; d = sw; rotY = Math.PI / 2; }
          else { cx = room.x1 - sd / 2 - 0.12; cz = room.z0 + t; w = sd; d = sw; rotY = -Math.PI / 2; }
          const rect = [cx - w / 2, cx + w / 2, cz - d / 2, cz + d / 2];
          if (rect[0] < room.x0 || rect[1] > room.x1 || rect[2] < room.z0 || rect[3] > room.z1) continue;
          if (lanes.some(l => overlap(rect, l, 0.05))) continue;
          if (placed.some(p => overlap(rect, p, 0.5))) continue;
          const g = new THREE.Group();
          g.position.set(cx, 0, cz); g.rotation.y = rotY;
          BUILD[name](g, sw, sd);
          group.add(g);
          placed.push(rect);
          const front = side === 0 ? [0, 1] : side === 2 ? [0, -1] : side === 3 ? [1, 0] : [-1, 0];
          const item = { type: name, rect, h: sh, hide, searchLabel: search, room, floor: index, x: cx, z: cz, front, anchor: [cx + front[0] * (sd / 2 + 0.75), cz + front[1] * (sd / 2 + 0.75)], low: name === 'bed' || name === 'freezer' || name === 'durabox', contents: null, searched: false, group: g };
          F.furniture.push(item);
          F.walls.push(rect);
          if (sh > 1.4) F.tall.push(rect);
          if (hide) { item.id = 'hide' + index + '_' + F.hideSpots.length; item.label = hide; F.hideSpots.push(item); }
          if (search) F.searchSpots.push(item);
          break;
        }
      });
    });

    // ---- lamps (a fitting everywhere, real light only in some) and old stains
    const lampMat = new THREE.MeshBasicMaterial({ color: 0xffe9bf });
    const addLamp = (x, z, light) => { box(group, 0.5, 0.05, 0.22, x, H - 0.03, z, lampMat); if (light) F.lamps.push({ x, z }); };
    F.rooms.forEach((r) => addLamp((r.x0 + r.x1) / 2, (r.z0 + r.z1) / 2, false));
    F.corridorRects.forEach((c) => {
      const horizontal = (c[2] - c[0]) >= (c[3] - c[1]);
      const len = horizontal ? c[2] - c[0] : c[3] - c[1];
      const step = 8 + index * 1.5;
      for (let s = step / 2; s < len; s += step) addLamp(horizontal ? c[0] + s : (c[0] + c[2]) / 2, horizontal ? (c[1] + c[3]) / 2 : c[1] + s, F.lamps.length < 7 - index);
    });
    for (let i = 0; i < 10 + index * 4; i++) {
      const r = pick(F.rooms);
      const s = new THREE.Mesh(new THREE.CircleGeometry(rnd(0.25, 0.7), 14), new THREE.MeshBasicMaterial({ color: 0x3b0d0d, transparent: true, opacity: rnd(0.35, 0.6), depthWrite: false }));
      s.rotation.x = -Math.PI / 2; s.position.set(rnd(r.x0 + 0.5, r.x1 - 0.5), 0.035, rnd(r.z0 + 0.5, r.z1 - 0.5)); group.add(s);
    }

    // ---- the stairs (steps, handrails, sign) and the ceiling, with an opening above every flight that goes up
    const FLOOR_NAMES = ['the ground floor', 'the second floor', 'the third floor', 'the fourth floor', 'the fifth floor'];
    F.stairLayouts.forEach((L) => buildStairMesh(F, L, FLOOR_NAMES[L.up ? index + 1 : index - 1]));
    {
      const shape = new THREE.Shape();
      shape.moveTo(-32, -32); shape.lineTo(32, -32); shape.lineTo(32, 32); shape.lineTo(-32, 32); shape.lineTo(-32, -32);
      F.stairLayouts.forEach((L) => {
        if (!L.ceilHole) return;
        const h = L.ceilHole, p = new THREE.Path();
        p.moveTo(h[0], h[2]); p.lineTo(h[0], h[3]); p.lineTo(h[1], h[3]); p.lineTo(h[1], h[2]); p.lineTo(h[0], h[2]);
        shape.holes.push(p);
      });
      const ceiling = new THREE.Mesh(new THREE.ShapeGeometry(shape), mat(0x2d313b, 0.95));
      ceiling.position.y = H; ceiling.rotation.x = Math.PI / 2;           // faces down: seen from inside, invisible from the camera above
      group.add(ceiling);
    }

    // ---- the grid for paths
    const blocked = new Uint8Array(N * N);
    F.blocked = blocked;
    const mark = (r) => {
      const ix0 = Math.max(0, cellOf(r[0] - AGENT_R)), ix1 = Math.min(N - 1, cellOf(r[1] + AGENT_R));
      const iz0 = Math.max(0, cellOf(r[2] - AGENT_R)), iz1 = Math.min(N - 1, cellOf(r[3] + AGENT_R));
      for (let iz = iz0; iz <= iz1; iz++) for (let ix = ix0; ix <= ix1; ix++) {
        const cx = centerOf(ix), cz = centerOf(iz);
        const nx = Math.max(r[0], Math.min(r[1], cx)), nz = Math.max(r[2], Math.min(r[3], cz));
        if (Math.hypot(cx - nx, cz - nz) < AGENT_R) blocked[ix + iz * N] = 1;
      }
    };
    F.walls.forEach(mark);
    F.stairLayouts.forEach((L) => { mark([L.rect[0] - 0.05, L.rect[1] + 0.05, L.rect[2] - 0.05, L.rect[3] + 0.05]); });     // the ghosts keep off the stairs
    F.mark = mark;
    for (let i = 0; i < N; i++) { blocked[i] = 1; blocked[i + (N - 1) * N] = 1; blocked[i * N] = 1; blocked[i * N + N - 1] = 1; }
    F.doors.forEach((d) => {                              // a doorway is always open on the grid, blocked only while the door is locked
      const r = d.rect, alongX = d.axis === 'x', hw = d.width / 2 - 0.05;
      for (let iz = cellOf(r[2] - 0.8); iz <= cellOf(r[3] + 0.8); iz++) for (let ix = cellOf(r[0] - 0.8); ix <= cellOf(r[1] + 0.8); ix++) {
        const cx = centerOf(ix), cz = centerOf(iz);
        const along = alongX ? Math.abs(cx - d.cx) : Math.abs(cz - d.cz);
        const across = alongX ? Math.abs(cz - d.cz) : Math.abs(cx - d.cx);
        if (along <= hw && across <= 0.4) { blocked[ix + iz * N] = 0; d.cells.push(ix + iz * N); }
      }
    });
    let lockedCells = new Set();
    F.refreshLocks = () => { lockedCells = new Set(); F.doors.forEach(d => { if (d.locked) d.cells.forEach(c => lockedCells.add(c)); }); };
    F.refreshLocks();
    F.lockedHas = (c) => lockedCells.has(c);
    const free = (ix, iz) => ix >= 0 && iz >= 0 && ix < N && iz < N && !blocked[ix + iz * N] && !lockedCells.has(ix + iz * N);
    F.walkable = (x, z) => free(cellOf(x), cellOf(z));
    F.nearestFree = (x, z) => {
      const ix = cellOf(x), iz = cellOf(z);
      if (free(ix, iz)) return [ix, iz];
      for (let r = 1; r < 12; r++) for (let dz = -r; dz <= r; dz++) for (let dx = -r; dx <= r; dx++) { if (Math.max(Math.abs(dx), Math.abs(dz)) !== r) continue; if (free(ix + dx, iz + dz)) return [ix + dx, iz + dz]; }
      return null;
    };
    const cellLine = (ax, az, bx, bz) => {
      const n = Math.max(Math.abs(bx - ax), Math.abs(bz - az)) * 2 || 1;
      for (let i = 0; i <= n; i++) {
        const t = i / n, ix = Math.round(ax + (bx - ax) * t), iz = Math.round(az + (bz - az) * t);
        if (!free(ix, iz)) return false;
        const px = Math.round(ax + (bx - ax) * Math.max(0, t - 1 / n)), pz = Math.round(az + (bz - az) * Math.max(0, t - 1 / n));
        if (px !== ix && pz !== iz && (!free(px, iz) || !free(ix, pz))) return false;
      }
      return true;
    };
    F.findPath = (sx, sz, tx, tz) => {
      const s = F.nearestFree(sx, sz), g = F.nearestFree(tx, tz);
      if (!s || !g) return null;
      const gScore = new Float32Array(N * N).fill(Infinity), came = new Int32Array(N * N).fill(-1), closed = new Uint8Array(N * N);
      const heap = [];
      const push = (id, f) => { heap.push([f, id]); let i = heap.length - 1; while (i > 0) { const p = (i - 1) >> 1; if (heap[p][0] <= heap[i][0]) break; [heap[p], heap[i]] = [heap[i], heap[p]]; i = p; } };
      const pop = () => { const top = heap[0], last = heap.pop(); if (heap.length) { heap[0] = last; let i = 0; for (;;) { const l = i * 2 + 1, r = l + 1; let m = i; if (l < heap.length && heap[l][0] < heap[m][0]) m = l; if (r < heap.length && heap[r][0] < heap[m][0]) m = r; if (m === i) break; [heap[m], heap[i]] = [heap[i], heap[m]]; i = m; } } return top; };
      const sid = s[0] + s[1] * N, gid = g[0] + g[1] * N;
      const hOf = (ix, iz) => { const dx = Math.abs(ix - g[0]), dz = Math.abs(iz - g[1]); return Math.max(dx, dz) + 0.414 * Math.min(dx, dz); };
      gScore[sid] = 0; push(sid, hOf(s[0], s[1]));
      let found = false;
      while (heap.length) {
        const [, id] = pop();
        if (closed[id]) continue;
        closed[id] = 1;
        if (id === gid) { found = true; break; }
        const ix = id % N, iz = (id / N) | 0;
        for (let dz = -1; dz <= 1; dz++) for (let dx = -1; dx <= 1; dx++) {
          if (!dx && !dz) continue;
          const nx = ix + dx, nz = iz + dz;
          if (!free(nx, nz)) continue;
          if (dx && dz && (!free(ix + dx, iz) || !free(ix, iz + dz))) continue;
          const nid = nx + nz * N;
          if (closed[nid]) continue;
          const ng = gScore[id] + (dx && dz ? 1.414 : 1);
          if (ng < gScore[nid]) { gScore[nid] = ng; came[nid] = id; push(nid, ng + hOf(nx, nz)); }
        }
      }
      if (!found) return null;
      const cells = [];
      for (let c = gid; c !== -1; c = came[c]) cells.push([c % N, (c / N) | 0]);
      cells.reverse();
      const out = [cells[0]];
      let a = 0;
      while (a < cells.length - 1) {
        let b = cells.length - 1;
        while (b > a + 1 && !cellLine(cells[a][0], cells[a][1], cells[b][0], cells[b][1])) b--;
        out.push(cells[b]);
        a = b;
      }
      return out.map(([ix, iz]) => [centerOf(ix), centerOf(iz)]);
    };
    const circleHits = (r, x, z, rad) => { const nx = Math.max(r[0], Math.min(r[1], x)), nz = Math.max(r[2], Math.min(r[3], z)); return Math.hypot(x - nx, z - nz) < rad; };
    F.isBlocked = (x, z, rad) => {
      rad = rad || 0.38;
      if (x < -29.6 || x > 29.6 || z < -29.6 || z > 29.6) return true;
      for (let i = 0; i < F.walls.length; i++) if (circleHits(F.walls[i], x, z, rad)) return true;
      for (let j = 0; j < F.doors.length; j++) { const d = F.doors[j]; if (d.open < 0.55 && circleHits(d.rect, x, z, rad)) return true; }
      return false;
    };
    F.hasLineOfSight = (ax, az, bx, bz) => {
      const len = Math.hypot(bx - ax, bz - az);
      const steps = Math.max(2, Math.ceil(len / 0.3));
      for (let i = 1; i < steps; i++) {
        const x = ax + (bx - ax) * i / steps, z = az + (bz - az) * i / steps;
        for (let k = 0; k < F.tall.length; k++) { const r = F.tall[k]; if (x > r[0] && x < r[1] && z > r[2] && z < r[3]) return false; }
        for (let j = 0; j < F.doors.length; j++) { const d = F.doors[j]; if (d.open < 0.55 && x > d.rect[0] && x < d.rect[1] && z > d.rect[2] && z < d.rect[3]) return false; }
      }
      return true;
    };
    F.lockDoor = (d, keyId, color) => {
      d.locked = true; d.keyId = keyId;
      d.leaf.material = mat(0x4c2323, 0.6, 0.1);
      const plate = new THREE.Mesh(new THREE.BoxGeometry(d.axis === 'z' ? 0.12 : 0.22, 0.26, d.axis === 'z' ? 0.22 : 0.12), new THREE.MeshStandardMaterial({ color, emissive: color, emissiveIntensity: 0.55, roughness: 0.4, metalness: 0.6 }));
      plate.position.copy(d.leaf.position); plate.position.y = 1.25;
      if (d.axis === 'z') plate.position.z += d.width * 0.3; else plate.position.x += d.width * 0.3;
      d.group.add(plate); d.plate = plate;
      F.refreshLocks();
    };
    F.unlockDoor = (d) => { d.locked = false; d.leaf.material = mat(0x6b4a2f, 0.7, 0.05); if (d.plate) { d.group.remove(d.plate); d.plate = null; } F.refreshLocks(); };
    F.openDoor = (d) => { if (!d.locked && d.target === 0) { d.target = 1; return true; } return false; };
    F.update = (dt) => {
      F.doors.forEach((d) => {
        if (d.open !== d.target) {
          d.open += Math.sign(d.target - d.open) * dt * 1.8;
          if (Math.abs(d.open - d.target) < 0.05) d.open = d.target;
          d.group.rotation.y = d.hingeSign * (d.axis === 'z' ? -1 : 1) * d.open * (Math.PI / 2) * 0.98;
        }
      });
    };
    F.reachableFrom = (ix, iz, openedCells, seen) => {            // flood fill over free cells; the cells of the opened doors count as free
      const ok = (x, z) => x >= 0 && z >= 0 && x < N && z < N && !blocked[x + z * N] && (!lockedCells.has(x + z * N) || openedCells.has(x + z * N));
      const q = [];
      if (ok(ix, iz) && !seen[ix + iz * N]) { seen[ix + iz * N] = 1; q.push(ix + iz * N); }
      for (let h = 0; h < q.length; h++) {
        const id = q[h], x = id % N, z = (id / N) | 0;
        [[1, 0], [-1, 0], [0, 1], [0, -1]].forEach(([dx, dz]) => { const nx = x + dx, nz = z + dz; if (ok(nx, nz) && !seen[nx + nz * N]) { seen[nx + nz * N] = 1; q.push(nx + nz * N); } });
      }
    };
    // places the ghost can wander to
    F.rooms.forEach((r) => { const p = F.nearestFree((r.x0 + r.x1) / 2, (r.z0 + r.z1) / 2); if (p) F.patrolPoints.push([centerOf(p[0]), centerOf(p[1])]); });
    F.corridorRects.forEach((c) => {
      const horizontal = (c[2] - c[0]) >= (c[3] - c[1]);
      const len = horizontal ? c[2] - c[0] : c[3] - c[1];
      for (let t = 2; t < len; t += 7) { const p = F.nearestFree(horizontal ? c[0] + t : (c[0] + c[2]) / 2, horizontal ? (c[1] + c[3]) / 2 : c[1] + t); if (p) F.patrolPoints.push([centerOf(p[0]), centerOf(p[1])]); }
    });
    return F;
  }

  // ==================================================================== the whole building
  function build(scene) {
    const W = { group: new THREE.Group(), floors: [], stairs: [], exit: null, STYLE };
    W.group.name = 'asylum-environment-group';
    // stairs: 3 between floors 1 and 2, 3 between 2 and 3, 2 between 3 and 4, 1 between 4 and 5 (each one is a room on both floors)
    const COUNTS = [3, 3, 2, 1];
    for (let f = 0; f < 5; f++) {
      const F = buildFloor(f, f, f < 4 ? COUNTS[f] : 0, f > 0 ? COUNTS[f - 1] : 0);
      F.group.visible = false; W.group.add(F.group); W.floors.push(F);
    }
    const mkStair = (F, room, up, to) => {
      const L = room.stairLayout;
      const st = { id: 's' + W.stairs.length, floor: F.index, toFloor: to, up, room, layout: L, x: L.outX, z: L.outZ, out: L.out, pair: null };
      L.st = st;
      room.stair = st;
      W.stairs.push(st);
      return st;
    };
    for (let f = 0; f < 4; f++) {
      const ups = W.floors[f].stairUp, downs = W.floors[f + 1].stairDown;
      for (let k = 0; k < Math.min(ups.length, downs.length); k++) {
        const a = mkStair(W.floors[f], ups[k], true, f + 1), b = mkStair(W.floors[f + 1], downs[k], false, f);
        a.pair = b; b.pair = a;
      }
    }

    // ---- the entrance on the ground floor: the far end of a corridor, facing along it
    {
      const F0 = W.floors[0];
      let best = null, bestD = -1;
      F0.corridorRects.forEach((c) => {
        const horizontal = (c[2] - c[0]) >= (c[3] - c[1]);
        const cx = (c[0] + c[2]) / 2, cz = (c[1] + c[3]) / 2;
        const ends = horizontal ? [[c[0] + 1.5, cz, Math.PI / 2], [c[2] - 1.5, cz, -Math.PI / 2]] : [[cx, c[1] + 1.5, 0], [cx, c[3] - 1.5, Math.PI]];
        ends.forEach((e) => { const p = F0.nearestFree(e[0], e[1]); if (!p) return; const d = Math.hypot(e[0], e[1]); if (d > bestD) { bestD = d; best = { x: centerOf(p[0]), z: centerOf(p[1]), yaw: e[2] }; } });
      });
      W.spawn = { floor: 0, x: best.x, z: best.z, yaw: best.yaw };
      const arch = new THREE.Mesh(new THREE.PlaneGeometry(1.2, 2.2), new THREE.MeshBasicMaterial({ color: 0x6d28d9, transparent: true, opacity: 0.55, side: THREE.DoubleSide }));
      arch.position.set(best.x - Math.sin(best.yaw) * 1.1, 1.1, best.z - Math.cos(best.yaw) * 1.1);
      arch.rotation.y = best.yaw;
      F0.group.add(arch);
    }

    // ---- the exit: a glowing door in a random room on a random floor (it opens with all the keys)
    {
      const fi = Math.floor(Math.random() * 5);
      const F = W.floors[fi];
      const rooms = F.rooms.filter(r => !r.stair && r.area >= 14 && r.type !== 'stairs');
      const room = pick(rooms.length ? rooms : F.rooms);
      const p = F.nearestFree((room.x0 + room.x1) / 2, (room.z0 + room.z1) / 2);
      const x = centerOf(p[0]), z = centerOf(p[1]);
      const g = new THREE.Group();
      const green = new THREE.MeshStandardMaterial({ color: 0x16a34a, emissive: 0x22c55e, emissiveIntensity: 0.9, roughness: 0.4 });
      box(g, 0.14, 2.4, 0.2, -0.8, 1.2, 0, green); box(g, 0.14, 2.4, 0.2, 0.8, 1.2, 0, green); box(g, 1.74, 0.14, 0.2, 0, 2.4, 0, green);
      const panel = new THREE.Mesh(new THREE.PlaneGeometry(1.46, 2.3), new THREE.MeshBasicMaterial({ color: 0x14532d, transparent: true, opacity: 0.85, side: THREE.DoubleSide }));
      panel.position.set(0, 1.2, 0); g.add(panel);
      const sign = new THREE.Mesh(new THREE.PlaneGeometry(1.2, 0.3), new THREE.MeshBasicMaterial({ color: 0x86efac, side: THREE.DoubleSide }));
      sign.position.set(0, 2.7, 0); g.add(sign);
      const glow = new THREE.PointLight(0x22c55e, 0.9, 7); glow.position.set(0, 1.5, 0.8); g.add(glow);
      g.position.set(x, 0, z);
      F.group.add(g);
      const rect = [x - 0.9, x + 0.9, z - 0.15, z + 0.15];
      F.walls.push(rect); F.mark(rect);
      W.exit = { floor: fi, x, z: z + 1.1, doorX: x, doorZ: z, room, mesh: g, panel, open: false };
    }

    // ---- connectivity across the whole building (stairs join the floors)
    W.reachable = (opened) => {
      const open = new Set();
      (opened || []).forEach(d => d.cells.forEach(c => open.add(d.floor * N * N + c)));
      const seen = W.floors.map(() => new Uint8Array(N * N));
      const queue = [[W.spawn.floor, W.spawn.x, W.spawn.z]];
      const done = new Set();
      while (queue.length) {
        const [f, x, z] = queue.pop();
        const F = W.floors[f];
        const p = F.nearestFree(x, z);
        if (!p || seen[f][p[0] + p[1] * N]) continue;
        const openCells = new Set(); (opened || []).forEach(d => { if (d.floor === f) d.cells.forEach(c => openCells.add(c)); });
        F.reachableFrom(p[0], p[1], openCells, seen[f]);
        W.stairs.forEach((st) => {
          if (st.floor !== f || done.has(st.id)) return;
          const q = F.nearestFree(st.x, st.z);
          if (q && seen[f][q[0] + q[1] * N]) { done.add(st.id); queue.push([st.pair.floor, st.pair.x, st.pair.z]); }
        });
      }
      void open;
      return seen;
    };
    W.inSet = (seen, f, x, z) => { const ix = cellOf(x), iz = cellOf(z); return ix >= 0 && iz >= 0 && ix < N && iz < N && seen[f][ix + iz * N] === 1; };

    scene.add(W.group);
    return W;
  }

  // a piece of furniture on its own (for the disguise): { group, w, d, h }
  function makeFurniture(name) {
    const spec = SPEC[name] || SPEC.cabinet;
    const g = new THREE.Group();
    BUILD[SPEC[name] ? name : 'cabinet'](g, spec[0], spec[1]);
    return { group: g, w: spec[0], d: spec[1], h: spec[2] };
  }

  window.AsylumWorld = { build, makeFurniture, H, N, CELL, STYLE, RISE };
})();
