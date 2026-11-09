/**
 * AVATAR FACTORY: builds the avatar of a user from a "look" (a small object), for the warehouse and the asylum.
 * Kinds: human (male or female), dog, cat, panda, snake. A human can be thin, normal or fat, short or tall, with a hair style and colour,
 * skin, outfit and accessories; the animals have a fur colour and an accent colour.
 *
 * Every avatar has the same joints as the original guide (pelvis, torso, head, arms, legs ...), so the walking, waving and jumping code
 * works on all of them. An animal uses its front legs as "arms" and its hind legs as "legs". A snake has no limbs; its `tick` makes it slither.
 * The rig is built at the size of a person (pelvis 0.88 m up) and animals are scaled down with the root's scale.
 */
(function () {
  'use strict';

  const KINDS = ['human', 'dog', 'cat', 'panda', 'snake'];
  const HAIR = ['none', 'short', 'long', 'spiky', 'bun', 'ponytail', 'afro'];
  const OUTFIT = ['gown', 'casual', 'suit', 'dress'];
  const BUILD = ['thin', 'normal', 'fat'];
  const GENDER = ['male', 'female'];
  const DEFAULT = {
    kind: 'human', gender: 'male', build: 'normal', height: 1.0,
    skin: '#c68a5c', hair: 'short', hairColor: '#18110b', outfit: 'gown', outfitColor: '#f8fafc', pants: '#334155',
    eyes: '#0f172a', fur: '#b9783d', accent: '#3a2412', glasses: false, cap: false
  };

  const colorOk = (v, d) => (typeof v === 'string' && /^#[0-9a-fA-F]{6}$/.test(v)) ? v.toLowerCase() : d;
  const oneOf = (v, list, d) => list.includes(v) ? v : d;

  // makes any input into a valid look (unknown things fall back to the defaults)
  function normalize(c) {
    c = c || {};
    const h = parseFloat(c.height);
    return {
      kind: oneOf(c.kind, KINDS, DEFAULT.kind),
      gender: oneOf(c.gender, GENDER, DEFAULT.gender),
      build: oneOf(c.build, BUILD, DEFAULT.build),
      height: isFinite(h) ? Math.max(0.8, Math.min(1.2, h)) : 1.0,
      skin: colorOk(c.skin, DEFAULT.skin),
      hair: oneOf(c.hair, HAIR, DEFAULT.hair),
      hairColor: colorOk(c.hairColor, DEFAULT.hairColor),
      outfit: oneOf(c.outfit, OUTFIT, DEFAULT.outfit),
      outfitColor: colorOk(c.outfitColor, DEFAULT.outfitColor),
      pants: colorOk(c.pants, DEFAULT.pants),
      eyes: colorOk(c.eyes, DEFAULT.eyes),
      fur: colorOk(c.fur, DEFAULT.fur),
      accent: colorOk(c.accent, DEFAULT.accent),
      glasses: !!c.glasses,
      cap: !!c.cap
    };
  }

  const pickR = (a) => a[Math.floor(Math.random() * a.length)];
  const hex = (n) => '#' + n.toString(16).padStart(6, '0');
  function random() {
    const kind = pickR(KINDS);
    const skins = ['#f1c9a5', '#e0ac82', '#c68a5c', '#8d5a3a', '#5c3a24', '#f6d7c3'];
    const hairs = ['#18110b', '#3b2a1a', '#7a4a21', '#c9a24a', '#a31515', '#e5e7eb', '#1d4ed8'];
    const outfits = ['#f8fafc', '#1d4ed8', '#16a34a', '#dc2626', '#7c3aed', '#0f172a', '#f59e0b'];
    return normalize({
      kind, gender: pickR(GENDER), build: pickR(BUILD), height: 0.9 + Math.random() * 0.25, skin: pickR(skins), hair: pickR(HAIR), hairColor: pickR(hairs),
      outfit: pickR(OUTFIT), outfitColor: pickR(outfits), pants: pickR(['#334155', '#1f2937', '#3b4d61', '#4b3a2a']), eyes: pickR(['#0f172a', '#1d4ed8', '#16a34a', '#7c2d12']),
      fur: pickR(['#b9783d', '#e5e7eb', '#2b2b2b', '#d8a05a', '#7a7f87']), accent: pickR(['#3a2412', '#111111', '#f5f5f5']), glasses: Math.random() < 0.3, cap: Math.random() < 0.2
    });
  }

  // ------------------------------------------------------------------ helpers
  const M = (color, rough, metal) => new THREE.MeshStandardMaterial({ color, roughness: rough === undefined ? 0.8 : rough, metalness: metal || 0.03 });
  const B = (parent, w, h, d, x, y, z, mat) => { const m = new THREE.Mesh(new THREE.BoxGeometry(w, h, d), mat); m.position.set(x, y, z); m.castShadow = true; parent.add(m); return m; };
  const C = (parent, r0, r1, h, x, y, z, mat, seg) => { const m = new THREE.Mesh(new THREE.CylinderGeometry(r0, r1, h, seg || 10), mat); m.position.set(x, y, z); m.castShadow = true; parent.add(m); return m; };
  const S = (parent, r, x, y, z, mat, sx, sy, sz) => { const m = new THREE.Mesh(new THREE.SphereGeometry(r, 12, 10), mat); m.position.set(x, y, z); m.scale.set(sx || 1, sy || 1, sz || 1); m.castShadow = true; parent.add(m); return m; };

  function newRig(root) {
    const G = () => new THREE.Group();
    return {
      root, pelvis: G(), torso: G(), neck: G(), head: G(),
      leftShoulder: G(), leftUpperArm: G(), leftForearm: G(), leftHand: G(),
      rightShoulder: G(), rightUpperArm: G(), rightForearm: G(), rightHand: G(),
      leftHip: G(), leftThigh: G(), leftCalf: G(), leftFoot: G(),
      rightHip: G(), rightThigh: G(), rightCalf: G(), rightFoot: G(),
      coatSkirtLeft: G(), coatSkirtRight: G(),
      state: 'welcoming', walkCycle: 0, waveCycle: 0, pointTarget: null, path: [], pathIndex: 0, currentYaw: 0, targetYaw: 0, baseSpeed: 2.2,
      look: null, tick: null
    };
  }
  function shadow(root, r) {
    const s = new THREE.Mesh(new THREE.CircleGeometry(r, 24), new THREE.MeshBasicMaterial({ color: 0x030712, transparent: true, opacity: 0.38 }));
    s.material.depthWrite = false; s.material.polygonOffset = true; s.material.polygonOffsetFactor = -80; s.material.polygonOffsetUnits = -80;
    s.rotation.x = -Math.PI / 2; s.position.y = 0.02; s.renderOrder = 2; root.add(s);
  }

  // ------------------------------------------------------------------ hair and accessories on a head group (the head box is 0.18 x 0.20 x 0.18, centre y = 0.095)
  function addHair(head, style, color) {
    if (style === 'none') return;
    const hm = M(color, 0.85, 0.05);
    if (style === 'short') { B(head, 0.195, 0.07, 0.195, 0, 0.19, -0.005, hm); B(head, 0.195, 0.1, 0.06, 0, 0.12, -0.07, hm); B(head, 0.02, 0.07, 0.17, -0.098, 0.15, 0, hm); B(head, 0.02, 0.07, 0.17, 0.098, 0.15, 0, hm); }
    else if (style === 'long') { B(head, 0.2, 0.08, 0.2, 0, 0.19, -0.005, hm); B(head, 0.2, 0.34, 0.06, 0, 0.0, -0.09, hm); B(head, 0.02, 0.3, 0.14, -0.1, 0.02, -0.02, hm); B(head, 0.02, 0.3, 0.14, 0.1, 0.02, -0.02, hm); }
    else if (style === 'spiky') { B(head, 0.19, 0.04, 0.19, 0, 0.185, 0, hm); for (let i = -2; i <= 2; i++) { const c = new THREE.Mesh(new THREE.ConeGeometry(0.03, 0.12, 6), hm); c.position.set(i * 0.04, 0.26, (i % 2) * 0.03); head.add(c); } }
    else if (style === 'bun') { B(head, 0.195, 0.07, 0.195, 0, 0.19, 0, hm); B(head, 0.195, 0.1, 0.05, 0, 0.12, -0.075, hm); S(head, 0.06, 0, 0.27, -0.03, hm); }
    else if (style === 'ponytail') { B(head, 0.195, 0.07, 0.195, 0, 0.19, 0, hm); B(head, 0.195, 0.1, 0.05, 0, 0.12, -0.075, hm); C(head, 0.025, 0.035, 0.26, 0, 0.02, -0.12, hm, 8); }
    else if (style === 'afro') { S(head, 0.17, 0, 0.18, -0.01, hm, 1, 0.9, 1); }
  }
  function addAccessories(head, look, headTop) {
    if (look.glasses) {
      const gm = M(0x111827, 0.4, 0.6);
      [-0.045, 0.045].forEach((x) => { const t = new THREE.Mesh(new THREE.TorusGeometry(0.03, 0.006, 6, 14), gm); t.position.set(x, 0.095, 0.095); head.add(t); });
      B(head, 0.03, 0.006, 0.006, 0, 0.1, 0.098, gm);
    }
    if (look.cap) {
      const cm = M(look.outfitColor === '#f8fafc' ? 0x1d4ed8 : look.outfitColor, 0.7);
      B(head, 0.2, 0.06, 0.2, 0, headTop || 0.2, 0, cm);
      B(head, 0.2, 0.015, 0.1, 0, (headTop || 0.2) - 0.02, 0.14, cm);
    }
  }

  // ==================================================================== HUMAN
  function buildHuman(look) {
    const root = new THREE.Group();
    shadow(root, 0.38);
    const rig = newRig(root);
    rig.look = look;
    const female = look.gender === 'female';
    const bw = look.build === 'thin' ? 0.82 : look.build === 'fat' ? 1.38 : 1.0;           // how wide
    const bd = look.build === 'fat' ? 1.3 : look.build === 'thin' ? 0.9 : 1.0;             // how deep (belly)
    const sh = female ? 0.9 : 1.0, hp = female ? 1.1 : 1.0;                                // shoulders, hips

    const skin = M(look.skin, 0.6), hairM = M(look.hairColor, 0.85);
    const outfit = M(look.outfitColor, 0.65), pants = M(look.pants, 0.82), shoes = M(0x0f172a, 0.45), sole = M(0xe2e8f0, 0.35), eye = new THREE.MeshBasicMaterial({ color: look.eyes });
    const dress = look.outfit === 'dress', casual = look.outfit === 'casual', suit = look.outfit === 'suit', gown = look.outfit === 'gown';
    const sleeve = dress ? skin : outfit, foreArm = (casual || dress) ? skin : outfit, legM = dress ? skin : pants;

    rig.pelvis.position.set(0, 0.88, 0);
    B(rig.pelvis, 0.26 * bw * hp, 0.12, 0.18 * bd, 0, 0, 0, dress ? outfit : pants);
    rig.torso.position.set(0, 0.08, 0);
    B(rig.torso, 0.30 * bw * sh, 0.44, 0.20 * bd, 0, 0.22, 0, outfit);
    if (gown) { B(rig.torso, 0.16 * bw, 0.26, 0.02, 0, 0.26, 0.102 * bd, M(0x1e293b, 0.75)); B(rig.torso, 0.06, 0.24, 0.025, -0.08 * bw, 0.26, 0.105 * bd, outfit).rotation.z = -0.15; B(rig.torso, 0.06, 0.24, 0.025, 0.08 * bw, 0.26, 0.105 * bd, outfit).rotation.z = 0.15; }
    if (suit) { B(rig.torso, 0.05, 0.3, 0.02, 0, 0.24, 0.103 * bd, M(0xdc2626, 0.6)); B(rig.torso, 0.12 * bw, 0.28, 0.015, 0, 0.26, 0.102 * bd, M(0xf8fafc, 0.6)); }
    if (female && !dress) { S(rig.torso, 0.055 * bw, -0.065 * bw, 0.31, 0.09 * bd, outfit); S(rig.torso, 0.055 * bw, 0.065 * bw, 0.31, 0.09 * bd, outfit); }

    // lower part of the coat or dress, swings with the legs
    const skirtLen = dress ? 0.5 : gown ? 0.28 : 0.0;
    [['coatSkirtLeft', -1], ['coatSkirtRight', 1]].forEach(([k, s]) => {
      rig[k].position.set(s * 0.08 * bw * hp, 0, 0);
      if (skirtLen > 0) B(rig[k], (dress ? 0.2 : 0.14) * bw * hp, skirtLen, (dress ? 0.24 : 0.19) * bd, 0, -skirtLen / 2, 0, outfit);
      rig.pelvis.add(rig[k]);
    });

    // neck and head
    rig.neck.position.set(0, 0.44, 0);
    C(rig.neck, 0.045, 0.05, 0.08, 0, 0.04, 0, skin, 12);
    rig.torso.add(rig.neck);
    rig.head.position.set(0, 0.08, 0);
    B(rig.head, 0.18, 0.20, 0.18, 0, 0.095, 0, skin);
    B(rig.head, 0.026, 0.018, 0.01, -0.042, 0.095, 0.092, eye); B(rig.head, 0.026, 0.018, 0.01, 0.042, 0.095, 0.092, eye);
    B(rig.head, 0.03, 0.04, 0.03, 0, 0.065, 0.1, skin);                                  // nose
    B(rig.head, 0.05, 0.008, 0.008, 0, 0.035, 0.092, M(female ? 0xb4363a : 0x5a2d1a, 0.7)); // mouth
    B(rig.head, 0.02, 0.05, 0.03, -0.1, 0.09, 0, skin); B(rig.head, 0.02, 0.05, 0.03, 0.1, 0.09, 0, skin);   // ears
    addHair(rig.head, look.hair, look.hairColor);
    addAccessories(rig.head, look, 0.205);
    void hairM;
    rig.neck.add(rig.head);

    // arms
    const arm = (side) => {
      const sg = side === 'left' ? -1 : 1;
      const sho = rig[side + 'Shoulder'], up = rig[side + 'UpperArm'], fo = rig[side + 'Forearm'], hand = rig[side + 'Hand'];
      sho.position.set(sg * 0.19 * bw * sh, 0.38, 0);
      C(up, 0.045 * bw, 0.04 * bw, 0.24, 0, -0.12, 0, sleeve, 12);
      sho.add(up);
      fo.position.set(0, -0.24, 0);
      C(fo, 0.04 * bw, 0.038 * bw, 0.2, 0, -0.1, 0, foreArm, 12);
      up.add(fo);
      hand.position.set(0, -0.2, 0);
      B(hand, 0.05, 0.07, 0.035, 0, -0.035, 0, skin);
      fo.add(hand);
      rig.torso.add(sho);
    };
    arm('left'); arm('right');
    const finger = new THREE.Mesh(new THREE.CylinderGeometry(0.008, 0.007, 0.045, 8), skin); finger.position.set(0.015, -0.07, 0.01); rig.rightHand.add(finger);

    // legs
    const leg = (side) => {
      const sg = side === 'left' ? -1 : 1;
      const hip = rig[side + 'Hip'], th = rig[side + 'Thigh'], ca = rig[side + 'Calf'], ft = rig[side + 'Foot'];
      hip.position.set(sg * 0.08 * bw * hp, -0.06, 0);
      C(th, 0.052 * bw, 0.046 * bw, 0.38, 0, -0.19, 0, legM, 12);
      hip.add(th);
      ca.position.set(0, -0.38, 0);
      C(ca, 0.046 * bw, 0.04 * bw, 0.36, 0, -0.18, 0, legM, 12);
      th.add(ca);
      ft.position.set(0, -0.36, 0);
      B(ft, 0.085 * bw, 0.065, 0.19, 0, -0.032, 0.04, shoes);
      B(ft, 0.088 * bw, 0.018, 0.195, 0, -0.068, 0.04, sole);
      ca.add(ft);
    };
    leg('left'); leg('right');
    rig.pelvis.add(rig.torso, rig.leftHip, rig.rightHip);
    root.add(rig.pelvis);
    root.scale.setScalar(look.height);
    return rig;
  }

  // ==================================================================== DOG, CAT, PANDA (four legs)
  function buildQuad(look) {
    const root = new THREE.Group();
    shadow(root, 0.5);
    const rig = newRig(root);
    rig.look = look;
    const kind = look.kind;
    const k = kind === 'dog' ? 0.62 : kind === 'cat' ? 0.5 : 0.7;               // the whole animal is scaled down from person size
    const bw = look.build === 'thin' ? 0.8 : look.build === 'fat' ? 1.35 : 1.0;
    const panda = kind === 'panda';
    const fur = M(panda ? 0xf5f5f5 : look.fur, 0.9), acc = M(panda ? 0x161616 : look.accent, 0.9), eyeM = new THREE.MeshBasicMaterial({ color: panda ? 0x111111 : look.eyes });
    const nose = M(0x111111, 0.4), tongue = M(0xe11d48, 0.6);

    rig.pelvis.position.set(0, 0.88, 0);
    rig.torso.position.set(0, 0, 0);
    // body: long and low, with a belly
    S(rig.torso, 0.3, 0, 0.0, 0.0, fur, 1.0 * bw, 0.95, 2.35);
    if (kind === 'dog') S(rig.torso, 0.22, 0.0, 0.1, 0.05, acc, 0.95 * bw, 0.5, 1.5);                  // a saddle patch
    if (panda) { S(rig.torso, 0.31, 0, 0.0, -0.45, acc, 1.01 * bw, 0.97, 0.7); S(rig.torso, 0.31, 0, 0.0, 0.5, acc, 1.01 * bw, 0.97, 0.7); }   // dark shoulders and hips
    rig.neck.position.set(0, 0.1, 0.62);
    rig.torso.add(rig.neck);
    rig.head.position.set(0, 0.12, 0.1);
    S(rig.head, 0.22, 0, 0, 0, fur, 1, 0.95, 1);
    if (kind === 'dog') {
      S(rig.head, 0.12, 0, -0.06, 0.2, fur, 1, 0.8, 1.3); S(rig.head, 0.04, 0, -0.03, 0.34, nose);
      [-1, 1].forEach(s => { const e = S(rig.head, 0.09, s * 0.19, 0.0, -0.02, acc, 0.5, 1.5, 0.9); e.rotation.z = s * 0.35; });   // floppy ears
      B(rig.head, 0.05, 0.03, 0.12, 0, -0.14, 0.24, tongue);
    } else if (kind === 'cat') {
      S(rig.head, 0.07, 0, -0.06, 0.2, fur, 1, 0.8, 1); S(rig.head, 0.025, 0, -0.04, 0.26, M(0xf9a8d4, 0.5));
      [-1, 1].forEach(s => { const e = new THREE.Mesh(new THREE.ConeGeometry(0.08, 0.16, 4), fur); e.position.set(s * 0.13, 0.2, 0); e.rotation.z = -s * 0.2; rig.head.add(e); });   // pointed ears
      [-1, 1].forEach(s => [-0.02, 0.02].forEach(dy => B(rig.head, 0.18, 0.004, 0.004, s * 0.14, -0.05 + dy * 2, 0.2, M(0xf3f4f6)).rotation.z = s * dy * 4));   // whiskers
    } else {
      S(rig.head, 0.1, 0, -0.06, 0.2, M(0xf5f5f5, 0.9), 1, 0.8, 1); S(rig.head, 0.035, 0, -0.03, 0.29, nose);
      [-1, 1].forEach(s => { S(rig.head, 0.075, s * 0.16, 0.18, -0.02, acc); S(rig.head, 0.07, s * 0.09, 0.04, 0.17, acc, 1, 1.3, 0.5); });   // round black ears and eye patches
    }
    [-1, 1].forEach(s => S(rig.head, 0.03, s * 0.09, 0.06, 0.2, eyeM));
    rig.neck.add(rig.head);
    // a tail
    rig.tail = new THREE.Group();
    rig.tail.position.set(0, 0.05, -0.68);
    if (kind === 'dog') { const t = C(rig.tail, 0.04, 0.07, 0.4, 0, 0.12, -0.08, fur, 8); t.rotation.x = -0.7; }
    else if (kind === 'cat') { const t = C(rig.tail, 0.035, 0.05, 0.75, 0, 0.3, -0.1, fur, 8); t.rotation.x = -0.35; }
    else S(rig.tail, 0.09, 0, 0.0, -0.05, acc, 1, 1, 1);
    rig.torso.add(rig.tail);

    // four legs: the front ones are the "arms", the hind ones the "legs"
    const legPair = (hipOrSho, up, fo, hand, x, z, mat, isFront) => {
      hipOrSho.position.set(x * bw, -0.05, z);
      C(up, 0.075, 0.062, 0.4, 0, -0.2, 0, mat, 10);
      hipOrSho.add(up);
      fo.position.set(0, -0.4, 0);
      C(fo, 0.062, 0.05, 0.38, 0, -0.19, 0, mat, 10);
      up.add(fo);
      hand.position.set(0, -0.38, 0);
      B(hand, 0.13, 0.06, 0.2, 0, -0.03, 0.04, panda ? acc : (isFront ? fur : fur));
      fo.add(hand);
    };
    const legMat = panda ? acc : fur;
    legPair(rig.leftShoulder, rig.leftUpperArm, rig.leftForearm, rig.leftHand, -0.17, 0.55, legMat, true);
    legPair(rig.rightShoulder, rig.rightUpperArm, rig.rightForearm, rig.rightHand, 0.17, 0.55, legMat, true);
    legPair(rig.leftHip, rig.leftThigh, rig.leftCalf, rig.leftFoot, -0.17, -0.55, legMat, false);
    legPair(rig.rightHip, rig.rightThigh, rig.rightCalf, rig.rightFoot, 0.17, -0.55, legMat, false);
    rig.torso.add(rig.leftShoulder, rig.rightShoulder);
    rig.pelvis.add(rig.torso, rig.leftHip, rig.rightHip);
    root.add(rig.pelvis);
    root.scale.setScalar(k * look.height);
    // the tail wags
    rig.tick = (dt, time) => { rig.tail.rotation.y = Math.sin(time * (kind === 'dog' ? 9 : 3)) * (kind === 'dog' ? 0.45 : 0.25); };
    return rig;
  }

  // ==================================================================== SNAKE
  function buildSnake(look) {
    const root = new THREE.Group();
    shadow(root, 0.5);
    const rig = newRig(root);
    rig.look = look;
    const fur = M(look.fur, 0.55, 0.1), acc = M(look.accent, 0.55, 0.1);
    const bw = look.build === 'thin' ? 0.75 : look.build === 'fat' ? 1.45 : 1.0;
    const n = 13;
    const segs = [];
    const body = new THREE.Group();
    root.add(body);
    for (let i = 0; i < n; i++) {
      const r = (0.13 * (1 - i * 0.045) + (i === 0 ? 0.02 : 0)) * bw;
      const m = new THREE.Mesh(new THREE.SphereGeometry(Math.max(0.035, r), 12, 10), i % 2 ? acc : fur);
      m.castShadow = true;
      body.add(m);
      segs.push(m);
    }
    // the head is a rig joint (so names, bubbles and the camera find it): it sits on the first segment
    rig.head.position.set(0, 0.3, 0.7);
    const hm = new THREE.Mesh(new THREE.SphereGeometry(0.15 * bw, 12, 10), fur); hm.scale.set(1, 0.75, 1.3); rig.head.add(hm);
    [-1, 1].forEach(s => { const e = new THREE.Mesh(new THREE.SphereGeometry(0.03, 8, 8), new THREE.MeshBasicMaterial({ color: look.eyes === '#0f172a' ? 0xfacc15 : look.eyes })); e.position.set(s * 0.09, 0.07, 0.12); rig.head.add(e); });
    const tongue = new THREE.Mesh(new THREE.BoxGeometry(0.02, 0.006, 0.14), M(0xe11d48, 0.6)); tongue.position.set(0, -0.03, 0.26); rig.head.add(tongue);
    rig.tongue = tongue;
    root.add(rig.head);
    rig.pelvis.position.set(0, 0.88, 0);       // unused joints: the animation code writes to them, they carry nothing
    rig.neck.position.set(0, 0, 0);
    root.add(rig.pelvis);
    root.scale.setScalar(0.9 * look.height);
    let phase = 0, lastWalk = 0, moving = 0;
    rig.tick = (dt, time) => {
      const moved = Math.abs(rig.walkCycle - lastWalk) > 1e-4;
      lastWalk = rig.walkCycle;
      moving += ((moved ? 1 : 0) - moving) * Math.min(1, dt * 6);
      phase += dt * (2 + moving * 7);
      const amp = 0.1 + moving * 0.22;
      for (let i = 0; i < n; i++) {
        const t = i * 0.55;
        segs[i].position.set(Math.sin(phase - t) * amp * (0.4 + i / n), (segs[i].geometry.parameters.radius || 0.05), 0.7 - i * 0.17);
      }
      // the head rides on the first segment, raised, looking where the body goes
      rig.head.position.set(segs[0].position.x, 0.32 + Math.sin(time * 1.5) * 0.02, segs[0].position.z + 0.12);
      rig.tongue.scale.z = 0.6 + Math.abs(Math.sin(time * 6)) * 0.5;
    };
    rig.tick(0.016, 0);
    return rig;
  }

  function build(look) {
    look = normalize(look);
    const rig = look.kind === 'human' ? buildHuman(look) : look.kind === 'snake' ? buildSnake(look) : buildQuad(look);
    return rig;
  }

  // the walking pose of an avatar that someone else moves (the local avatar has its own animation code): speed in m/s, 0 = standing
  function animate(rig, speed, dt, time) {
    if (speed > 0.15) {
      rig.walkCycle += dt * speed * 3.4;
      const c = rig.walkCycle;
      rig.pelvis.position.y = 0.88 + Math.abs(Math.sin(c)) * 0.025;
      const lt = Math.sin(c) * 0.55, rt = -Math.sin(c) * 0.55;
      rig.leftThigh.rotation.x = lt; rig.leftCalf.rotation.x = lt < 0 ? -lt * 0.95 : 0.08;
      rig.rightThigh.rotation.x = rt; rig.rightCalf.rotation.x = rt < 0 ? -rt * 0.95 : 0.08;
      rig.coatSkirtLeft.rotation.x = lt * 0.7; rig.coatSkirtRight.rotation.x = rt * 0.7;
      rig.leftUpperArm.rotation.set(-Math.sin(c) * 0.4, 0, 0.08); rig.leftForearm.rotation.set(0.2, 0, 0);
      rig.rightUpperArm.rotation.set(Math.sin(c) * 0.4, 0, -0.08); rig.rightForearm.rotation.set(0.2, 0, 0);
    } else {
      rig.pelvis.position.y = 0.88 + Math.sin(time * 2.2) * 0.008;
      [rig.leftThigh, rig.leftCalf, rig.rightThigh, rig.rightCalf, rig.coatSkirtLeft, rig.coatSkirtRight, rig.leftForearm, rig.rightForearm].forEach(g => g.rotation.set(0, 0, 0));
      rig.leftUpperArm.rotation.set(0.05, 0, 0.08); rig.rightUpperArm.rotation.set(0.05, 0, -0.08);
    }
    if (rig.tick) rig.tick(dt, time);
  }

  window.AvatarFactory = { build, normalize, random, animate, defaults: () => normalize(DEFAULT), KINDS, HAIR, OUTFIT, BUILD };
})();
