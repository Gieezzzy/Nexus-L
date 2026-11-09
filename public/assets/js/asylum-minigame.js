/**
 * NEXUS-L: THE DARK ASYLUM (Easter Egg Survival Horror Mini-Game)
 * Trigger: Press Q 3 times near the warehouse partition corner (x: 0.4, z: -7.96)
 * Five floors of rooms with furniture to search and hide in, killers, power-ups; the building itself (rooms, doors, paths) is in asylum-world.js.
 */
(function() {
  'use strict';

  // ---------------------------------------------------------------------------
  // 1. PROCEDURAL AUDIO SYNTHESIS (Zero External Audio Files via Web Audio API)
  // ---------------------------------------------------------------------------
  const AudioEngine = (function() {
    let ctx = null;

    function getCtx() {
      if (!ctx) {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (AudioContext) ctx = new AudioContext();
      }
      if (ctx && ctx.state === 'suspended') {
        ctx.resume();
      }
      return ctx;
    }

    return {
      playHeartbeat(bpm) {
        const ac = getCtx();
        if (!ac) return;
        const t = ac.currentTime;

        // Lub (First lower pulse)
        const osc1 = ac.createOscillator();
        const gain1 = ac.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(62, t);
        osc1.frequency.exponentialRampToValueAtTime(32, t + 0.12);
        gain1.gain.setValueAtTime(0.55, t);
        gain1.gain.exponentialRampToValueAtTime(0.01, t + 0.15);
        osc1.connect(gain1);
        gain1.connect(ac.destination);
        osc1.start(t);
        osc1.stop(t + 0.16);

        // Dub (Second higher pulse)
        const osc2 = ac.createOscillator();
        const gain2 = ac.createGain();
        osc2.type = 'sine';
        osc2.frequency.setValueAtTime(74, t + 0.16);
        osc2.frequency.exponentialRampToValueAtTime(38, t + 0.30);
        gain2.gain.setValueAtTime(0.60, t + 0.16);
        gain2.gain.exponentialRampToValueAtTime(0.01, t + 0.32);
        osc2.connect(gain2);
        gain2.connect(ac.destination);
        osc2.start(t + 0.16);
        osc2.stop(t + 0.33);
      },

      playFlashlightClick() {
        const ac = getCtx();
        if (!ac) return;
        const t = ac.currentTime;
        const osc = ac.createOscillator();
        const gain = ac.createGain();
        osc.type = 'triangle';
        osc.frequency.setValueAtTime(1400, t);
        osc.frequency.exponentialRampToValueAtTime(200, t + 0.04);
        gain.gain.setValueAtTime(0.35, t);
        gain.gain.exponentialRampToValueAtTime(0.01, t + 0.05);
        osc.connect(gain);
        gain.connect(ac.destination);
        osc.start(t);
        osc.stop(t + 0.05);
      },

      playSecretChime(step) {
        const ac = getCtx();
        if (!ac) return;
        const t = ac.currentTime;
        const freq = step === 1 ? 261.63 : (step === 2 ? 392.00 : 523.25);
        const osc = ac.createOscillator();
        const gain = ac.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(freq, t);
        osc.frequency.exponentialRampToValueAtTime(freq * 1.5, t + 0.45);
        gain.gain.setValueAtTime(0.35, t);
        gain.gain.exponentialRampToValueAtTime(0.001, t + 0.65);
        osc.connect(gain);
        gain.connect(ac.destination);
        osc.start(t);
        osc.stop(t + 0.7);
      },

      playStoneRumble() {
        const ac = getCtx();
        if (!ac) return;
        const t = ac.currentTime;
        const bufferSize = Math.floor(ac.sampleRate * 2.2);
        const buffer = ac.createBuffer(1, bufferSize, ac.sampleRate);
        const data = buffer.getChannelData(0);
        for (let i = 0; i < bufferSize; i++) {
          data[i] = (Math.random() * 2 - 1) * Math.exp(-i / (ac.sampleRate * 1.1));
        }
        const noise = ac.createBufferSource();
        noise.buffer = buffer;
        const filter = ac.createBiquadFilter();
        filter.type = 'lowpass';
        filter.frequency.setValueAtTime(180, t);
        filter.frequency.linearRampToValueAtTime(60, t + 2.2);

        const gain = ac.createGain();
        gain.gain.setValueAtTime(0.7, t);
        gain.gain.exponentialRampToValueAtTime(0.01, t + 2.2);

        noise.connect(filter);
        filter.connect(gain);
        gain.connect(ac.destination);
        noise.start(t);
      },

      playKeyPickup() {
        const ac = getCtx();
        if (!ac) return;
        const t = ac.currentTime;
        [523.25, 659.25, 783.99, 1046.5].forEach((f, idx) => {
          const osc = ac.createOscillator();
          const gain = ac.createGain();
          osc.type = 'sine';
          osc.frequency.setValueAtTime(f, t + idx * 0.07);
          gain.gain.setValueAtTime(0.3, t + idx * 0.07);
          gain.gain.exponentialRampToValueAtTime(0.001, t + idx * 0.07 + 0.4);
          osc.connect(gain);
          gain.connect(ac.destination);
          osc.start(t + idx * 0.07);
          osc.stop(t + idx * 0.07 + 0.42);
        });
      },

      playDoorCreak() {
        const ac = getCtx();
        if (!ac) return;
        const t = ac.currentTime;
        const osc = ac.createOscillator();
        const gain = ac.createGain();
        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(140, t);
        osc.frequency.linearRampToValueAtTime(90, t + 0.5);
        osc.frequency.linearRampToValueAtTime(170, t + 0.9);
        gain.gain.setValueAtTime(0.0001, t);
        gain.gain.linearRampToValueAtTime(0.1, t + 0.1);
        gain.gain.exponentialRampToValueAtTime(0.01, t + 1.0);
        osc.connect(gain);
        gain.connect(ac.destination);
        osc.start(t);
        osc.stop(t + 1.05);
      },

      playHide() {
        const ac = getCtx();
        if (!ac) return;
        const t = ac.currentTime;
        const osc = ac.createOscillator();
        const gain = ac.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(110, t);
        osc.frequency.exponentialRampToValueAtTime(45, t + 0.25);
        gain.gain.setValueAtTime(0.35, t);
        gain.gain.exponentialRampToValueAtTime(0.01, t + 0.3);
        osc.connect(gain);
        gain.connect(ac.destination);
        osc.start(t);
        osc.stop(t + 0.32);
      },

      playBeam() {
        const ac = getCtx();
        if (!ac) return;
        const t = ac.currentTime;
        const osc = ac.createOscillator();
        const gain = ac.createGain();
        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(180, t);
        osc.frequency.exponentialRampToValueAtTime(1400, t + 0.35);
        osc.frequency.exponentialRampToValueAtTime(300, t + 0.8);
        gain.gain.setValueAtTime(0.0001, t);
        gain.gain.linearRampToValueAtTime(0.35, t + 0.08);
        gain.gain.exponentialRampToValueAtTime(0.01, t + 0.85);
        osc.connect(gain);
        gain.connect(ac.destination);
        osc.start(t);
        osc.stop(t + 0.9);
      },

      playDoorUnlock() {
        const ac = getCtx();
        if (!ac) return;
        const t = ac.currentTime;
        const osc = ac.createOscillator();
        const gain = ac.createGain();
        osc.type = 'square';
        osc.frequency.setValueAtTime(450, t);
        osc.frequency.setValueAtTime(900, t + 0.08);
        gain.gain.setValueAtTime(0.4, t);
        gain.gain.exponentialRampToValueAtTime(0.01, t + 0.4);
        osc.connect(gain);
        gain.connect(ac.destination);
        osc.start(t);
        osc.stop(t + 0.42);
      },

      playGhostScreech() {
        const ac = getCtx();
        if (!ac) return;
        const t = ac.currentTime;
        const osc = ac.createOscillator();
        const gain = ac.createGain();
        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(880, t);
        osc.frequency.linearRampToValueAtTime(2400, t + 0.22);
        osc.frequency.exponentialRampToValueAtTime(220, t + 0.9);

        gain.gain.setValueAtTime(0.65, t);
        gain.gain.exponentialRampToValueAtTime(0.01, t + 0.95);

        osc.connect(gain);
        gain.connect(ac.destination);
        osc.start(t);
        osc.stop(t + 1.0);
      },

      playExplosion() {
        const ac = getCtx();
        if (!ac) return;
        const t = ac.currentTime;
        // Sub punch
        const osc = ac.createOscillator();
        const oscGain = ac.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(160, t);
        osc.frequency.exponentialRampToValueAtTime(18, t + 1.3);
        oscGain.gain.setValueAtTime(1.0, t);
        oscGain.gain.exponentialRampToValueAtTime(0.001, t + 1.5);
        osc.connect(oscGain);
        oscGain.connect(ac.destination);
        osc.start(t);
        osc.stop(t + 1.6);

        // Debris blast noise
        const bufferSize = Math.floor(ac.sampleRate * 1.8);
        const buffer = ac.createBuffer(1, bufferSize, ac.sampleRate);
        const data = buffer.getChannelData(0);
        for (let i = 0; i < bufferSize; i++) {
          data[i] = (Math.random() * 2 - 1) * Math.exp(-i / (ac.sampleRate * 0.5));
        }
        const noise = ac.createBufferSource();
        noise.buffer = buffer;
        const filter = ac.createBiquadFilter();
        filter.type = 'lowpass';
        filter.frequency.setValueAtTime(700, t);
        filter.frequency.linearRampToValueAtTime(80, t + 1.6);

        const noiseGain = ac.createGain();
        noiseGain.gain.setValueAtTime(0.9, t);
        noiseGain.gain.exponentialRampToValueAtTime(0.01, t + 1.6);

        noise.connect(filter);
        filter.connect(noiseGain);
        noiseGain.connect(ac.destination);
        noise.start(t);
      },

      playVictory() {
        const ac = getCtx();
        if (!ac) return;
        const t = ac.currentTime;
        [392, 523.25, 659.25, 783.99, 1046.5].forEach((f, idx) => {
          const osc = ac.createOscillator();
          const gain = ac.createGain();
          osc.type = 'triangle';
          osc.frequency.setValueAtTime(f, t + idx * 0.12);
          gain.gain.setValueAtTime(0.4, t + idx * 0.12);
          gain.gain.exponentialRampToValueAtTime(0.001, t + idx * 0.12 + 0.85);
          osc.connect(gain);
          gain.connect(ac.destination);
          osc.start(t + idx * 0.12);
          osc.stop(t + idx * 0.12 + 0.9);
        });
      }
    };
  })();

  // ---------------------------------------------------------------------------
  // 2. ASYLUM GAME: five floors, killers, keys hidden in furniture, power-ups (the building is built by asylum-world.js)
  // ---------------------------------------------------------------------------
  const DOOR_KEY_COLORS = [0xe2e8f0, 0xf59e0b, 0x94a3b8, 0x38bdf8, 0xa3e635, 0xf472b6, 0xfb923c, 0xc084fc];
  // Everything you can pick up. Loose power-ups drift from place to place; guns lie where they are. You can carry 3 things at a time and use
  // them with the number keys (numpad 1, 2, 3 or the digits 1, 2, 3): the key is the slot you put the item in.
  const ITEMS = {
    kame:      { name: 'Kamehameha', kind: 'power', color: 0x38bdf8, uses: 3, text: 'a blue energy beam: it hurls a killer far away and puts it to sleep for 5 seconds (3 uses)' },
    transform: { name: 'Camouflage', kind: 'power', color: 0x84cc16, uses: 2, text: 'turn into a piece of furniture: the killers cannot tell it is you, even from close by (2 uses)' },
    potion:    { name: 'Health Potion', kind: 'power', color: 0xef4444, uses: 1, text: 'restores all your health' },
    clone:     { name: 'Clone Army', kind: 'power', color: 0xf472b6, uses: 1, text: 'five copies of you run off and the killers go after them while you escape' },
    speed:     { name: 'Adrenaline', kind: 'power', color: 0xfacc15, uses: 1, secs: 10, text: 'run faster for 10 seconds' },
    cloak:     { name: 'Shadow Cloak', kind: 'power', color: 0xa855f7, uses: 1, secs: 8, text: 'the killers can hardly see you for 8 seconds' },
    stun:      { name: 'Stun Pulse', kind: 'power', color: 0x22d3ee, uses: 1, text: 'every killer near you is frozen for 6 seconds' },
    radar:     { name: 'Key Radar', kind: 'power', color: 0x4ade80, uses: 1, secs: 14, text: 'the nearest hidden key shows on the HUD' },
    shield:    { name: 'Guardian Shield', kind: 'power', color: 0x60a5fa, uses: 1, text: 'the next time a killer catches you, you are not hurt' },
    battery:   { name: 'Power Cell', kind: 'power', color: 0xfb923c, uses: 1, secs: 20, text: 'a much stronger flashlight for 20 seconds' },
    laser:     { name: 'Laser Gun', kind: 'gun', color: 0xef4444, uses: 1, text: 'one shot in a straight line: the killer it hits sleeps for 8 seconds' },
    missile:   { name: 'Missile Launcher', kind: 'gun', color: 0xf97316, uses: 1, text: 'one missile that blasts every killer near the impact away and puts them to sleep for 6 seconds' },
    freeze:    { name: 'Freeze Grenade', kind: 'gun', color: 0x7dd3fc, uses: 1, text: 'one grenade: every killer in the cold blast sleeps for 10 seconds' }
  };
  const POWER_BAG = { kame: 2, transform: 2, potion: 2, clone: 1, speed: 2, cloak: 2, stun: 2, radar: 2, shield: 2, battery: 2 };
  const GUN_TYPES = ['laser', 'missile', 'freeze'];
  const MAX_ITEMS = 3;
  // how hard each floor is: killers get faster, see farther and give up later the higher you go
  const LEVEL = [
    { killers: 1, patrol: 1.5, chase: 2.6, see: 8,  flash: 12, lose: 2.0, giveUp: 12 },
    { killers: 2, patrol: 1.7, chase: 2.9, see: 9,  flash: 13, lose: 2.5, giveUp: 13 },
    { killers: 2, patrol: 1.8, chase: 3.1, see: 10, flash: 14, lose: 3.0, giveUp: 14 },
    { killers: 2, patrol: 2.0, chase: 3.3, see: 10.5, flash: 15, lose: 3.5, giveUp: 15 },
    { killers: 2, patrol: 2.2, chase: 3.5, see: 11, flash: 16, lose: 4.0, giveUp: 16 }
  ];
  const BOSS = { patrol: 2.2, chase: 3.6, see: 12, flash: 17, lose: 5.0, giveUp: 18 };
  const LOCKS_PER_FLOOR = [1, 2, 2, 3, 3];
  const pickOf = (arr) => arr[Math.floor(Math.random() * arr.length)];
  const shuffleIn = (arr) => { for (let i = arr.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1)); [arr[i], arr[j]] = [arr[j], arr[i]]; } return arr; };
  const keysFor = (players) => players <= 1 ? 7 : players === 2 ? 5 : players === 3 ? 4 : 3;     // 1 player: 7 keys, 2: 5, 4 or more: 3

  const AsylumGame = {
    active: false,
    inTransition: false,
    isDoorOpen: false,
    scene: null, camera: null, renderer: null, controls: null, lexus: null, warehouseState: null,
    onReturnCallback: null, setPilotFn: null,

    world: null,
    asylumGroup: null,
    secretDoorMesh: null, portalMesh: null,
    flashlight: null, flashlightTarget: null, flashlightOn: true,
    explosionParticles: null,

    playerCount: 1,           // set to 2, 3, 4... for several players (the keys the team must find follow it)
    floor: 0,                 // the floor the avatar is on (0 = ground floor)
    keysNeeded: 7,
    keysFound: 0,             // shared: a key found by one player counts for everybody
    doorKeys: {},             // keys for locked doors that the team holds
    visited: {},              // floors whose killers are awake
    ghosts: [],
    boss: null,
    powerups: [],
    fx: { speed: 0, cloak: 0, radar: 0, battery: 0, shield: false, disguise: 0 },
    inv: [null, null, null],     // what you carry (3 at most), used with the number keys
    hp: 100,
    clones: [], projectiles: [], effects: [], disguiseMesh: null,
    hiding: null,
    heartbeatTimer: 0, currentBPM: 70,
    qPressCount: 0, qLastTime: 0,
    HEAR_RANGE: 3,
    GHOST_MIN_DISTANCE: 14,
    FLOOR_HEAD_START: 10,

    // The secret door is in the east wall of the storage enclosure, exactly where the avatar stands when it faces that wall.
    SECRET: { x: 0.5, z: -19.08 },

    init(options) {
      this.scene = options.scene;
      this.camera = options.camera;
      this.renderer = options.renderer;
      this.controls = options.controls;
      this.lexus = null;           // always read the current avatar from the state (it changes when the look changes)
      this.warehouseState = options.state;
      this.onReturnCallback = options.onReturn;
      this.setPilotFn = options.setPilot;
      this.buildSecretWarehouseDoor();
    },

    isActive() { return this.active; },
    currentFloor() { return this.world ? this.world.floors[this.floor] : null; },
    speedMult() { return (this.fx.speed > 0 ? 1.5 : 1) * (this.fx.disguise > 0 ? 0.85 : 1); },

    // -------------------------------------------------------------------------
    // 3. THE SECRET DOOR IN THE WAREHOUSE
    // It is made of the SAME wall material, size and thickness as the rest of the wall, so a closed door cannot be told from the wall.
    // -------------------------------------------------------------------------
    buildSecretWarehouseDoor() {
      if (!this.scene) return;
      const wall = this.scene.getObjectByName('wh-partition-east');
      if (!wall || !wall.geometry || !wall.geometry.parameters) return;
      const mat = wall.material;
      const gp = wall.geometry.parameters;
      const t = gp.width, h = gp.height;
      const zN = wall.position.z - gp.depth / 2, zS = wall.position.z + gp.depth / 2;
      const DZ = this.SECRET.z, HW = 0.85, DH = 2.7;

      const group = new THREE.Group();
      group.name = 'wh-secret-door';
      const piece = (z0, z1, y0, y1) => {
        const m = new THREE.Mesh(new THREE.BoxGeometry(t, y1 - y0, z1 - z0), mat);
        m.position.set(wall.position.x, (y0 + y1) / 2, (z0 + z1) / 2);
        m.castShadow = wall.castShadow;
        m.receiveShadow = true;
        group.add(m);
        return m;
      };
      piece(zN, DZ - HW, 0, h);
      piece(DZ + HW, zS, 0, h);
      piece(DZ - HW, DZ + HW, DH, h);
      this.secretDoorMesh = piece(DZ - HW, DZ + HW, 0, DH);
      this.secretDoorBase = this.secretDoorMesh.position.clone();
      this.secretDoorOpening = { HW, DH, DZ };
      wall.visible = false;

      const portal = new THREE.Mesh(
        new THREE.PlaneGeometry(2 * HW - 0.08, DH - 0.08),
        new THREE.MeshBasicMaterial({ color: 0x6d28d9, transparent: true, opacity: 0.0, side: THREE.DoubleSide, depthWrite: false })
      );
      portal.position.set(wall.position.x, DH / 2, DZ);
      portal.rotation.y = Math.PI / 2;
      group.add(portal);
      this.portalMesh = portal;
      const glow = new THREE.PointLight(0xa855f7, 0, 7);
      glow.position.set(wall.position.x + 1.1, 1.4, DZ);
      group.add(glow);
      this.portalLight = glow;
      this.scene.add(group);
      this.secretGroup = group;
    },

    resetSecretDoor() {
      if (this.secretDoorMesh && this.secretDoorBase) {
        this.secretDoorMesh.position.copy(this.secretDoorBase);
        this.secretDoorMesh.scale.set(1, 1, 1);
      }
      if (this.portalMesh) this.portalMesh.material.opacity = 0.0;
      if (this.portalLight) this.portalLight.intensity = 0;
      this.isDoorOpen = false;
    },

    checkSecretCorner(lex) {
      if (this.active || this.inTransition || !lex || !lex.root) return;
      const p = lex.root.position;
      const dist = Math.hypot(p.x - this.SECRET.x, p.z - this.SECRET.z);
      if (dist < 3.2 && p.x > this.SECRET.x - 0.2) {
        const now = performance.now();
        if (now - this.qLastTime > 3000) this.qPressCount = 0;
        this.qLastTime = now;
        this.qPressCount++;
        AudioEngine.playSecretChime(this.qPressCount);
        if (this.qPressCount === 1) {
          if (window.lexusSay) window.lexusSay("Did you hear that wall whispering...? (Press Q again)", "curious");
        } else if (this.qPressCount === 2) {
          if (window.lexusSay) window.lexusSay("The stone wall is trembling! (Press Q once more!)", "surprised");
        } else if (this.qPressCount >= 3) {
          this.qPressCount = 0;
          this.triggerCinematicSecretDoor();
        }
      }
    },

    triggerCinematicSecretDoor() {
      if (this.inTransition || this.isDoorOpen || !this.secretDoorMesh) return;
      this.inTransition = true;
      const ws = this.warehouseState;
      if (ws) ws.isCameraMoving = true;
      AudioEngine.playStoneRumble();
      if (window.lexusSay) window.lexusSay("A secret doorway is opening! Step into the portal...", "surprised");
      const startTime = performance.now();
      const door = this.secretDoorMesh, base = this.secretDoorBase;
      const slide = this.secretDoorOpening.HW * 2 + 0.05;
      const camStart = this.controls ? this.controls.target.clone() : null;
      const camGoal = new THREE.Vector3(this.SECRET.x, 1.4, this.SECRET.z);
      door.scale.x = 0.86;
      const anim = () => {
        const t = Math.min(1.0, (performance.now() - startTime) / 2400);
        const e = 1 - Math.pow(1 - t, 3);
        door.position.z = base.z + e * slide;
        if (this.portalMesh) this.portalMesh.material.opacity = Math.min(0.9, Math.max(0, (t - 0.45) * 1.7));
        if (this.portalLight) this.portalLight.intensity = Math.min(2.2, Math.max(0, (t - 0.45) * 4));
        if (this.controls && camStart) this.controls.target.lerpVectors(camStart, camGoal, Math.min(1, t * 1.6));
        if (t < 1.0) requestAnimationFrame(anim);
        else { this.isDoorOpen = true; this.inTransition = false; if (ws) ws.isCameraMoving = false; }
      };
      anim();
    },

    // -------------------------------------------------------------------------
    // 4. ENTER THE ASYLUM
    // -------------------------------------------------------------------------
    enterAsylum() {
      if (this.active) return;
      this.inTransition = false;
      this.active = true;
      this.hiding = null;

      // hide the warehouse (the avatar and its parents stay)
      const keep = new Set();
      const lexNow = this.lexus || (this.warehouseState && this.warehouseState.lexus);
      if (lexNow && lexNow.root) lexNow.root.traverse(o => keep.add(o));
      let up = lexNow && lexNow.root ? lexNow.root.parent : null;
      while (up) { keep.add(up); up = up.parent; }
      this.scene.traverse((obj) => {
        if (obj !== this.scene && !keep.has(obj) && !obj.name.startsWith('lexus-')) {
          obj.userData._whPrevVisible = obj.visible;
          obj.visible = false;
        }
      });
      this.scene.background = new THREE.Color(0x05070d);
      this.scene.fog = new THREE.FogExp2(0x0a0e1c, 0.012);

      // the avatar's own shadow would swing across the view with the flashlight: off while inside
      this._castShadowOff = [];
      if (lexNow && lexNow.root) lexNow.root.traverse((o) => { if (o.isMesh && o.castShadow) { o.castShadow = false; this._castShadowOff.push(o); } });
      if (this.camera) { this._camNear = this.camera.near; this._camFar = this.camera.far; this.camera.near = 0.25; this.camera.far = 90; this.camera.updateProjectionMatrix(); }
      this.world = window.AsylumWorld.build(this.scene);
      this.asylumGroup = this.world.group;
      this.keysNeeded = keysFor(this.playerCount);
      this.keysFound = 0;
      this.doorKeys = {};
      this.visited = {};
      this.ghosts = [];
      this.boss = null;
      this.fx = { speed: 0, cloak: 0, radar: 0, battery: 0, shield: false, disguise: 0 };
      this.inv = [null, null, null];
      this.hp = 100;
      this.clones = []; this.projectiles = []; this.effects = []; this.disguiseMesh = null;
      this.elapsed = 0;
      this.hudTimer = 0;
      this.setupLights();
      this.padFloorLights();
      this.setupLocks();
      this.placeKeys();
      this.setupBoss();
      this.spawnPowerups();
      this.setupFlashlight();
      this.showFloor(0);

      // the avatar arrives at the entrance on the ground floor
      const sp = this.world.spawn;
      const lex = this.lexus || (this.warehouseState && this.warehouseState.lexus);
      if (lex && lex.root) {
        lex.root.position.set(sp.x, 0, sp.z);
        lex.currentYaw = sp.yaw;
        lex.root.rotation.y = sp.yaw;
      }
      if (this.controls) this.controls.target.set(sp.x, 1.4, sp.z);
      if (this.camera) this.camera.position.set(sp.x - Math.sin(sp.yaw) * 3.0, 2.4, sp.z - Math.cos(sp.yaw) * 3.0);
      if (this.setPilotFn) this.setPilotFn(true);

      const hud = document.getElementById('wh-asylum-hud');
      const vig = document.getElementById('wh-asylum-vignette');
      if (hud) hud.classList.remove('hidden');
      if (vig) vig.classList.remove('hidden');
      this.renderHud();
      this.setPrompt('');
      this.startFloorCountdown(0);
      AudioEngine.playFlashlightClick();
    },

    setupLights() {
      this.ambient = new THREE.AmbientLight(0x9fb2e4, 1.1);
      this.hemi = new THREE.HemisphereLight(0xb4c4ee, 0x20182a, 0.7);
      this.asylumGroup.add(this.ambient, this.hemi);
      this.floorLamps = this.world.floors.map((F) => F.lamps.map((l, i) => {
        const lamp = new THREE.PointLight(0xffe2bd, 0.9, 16, 1.6);
        lamp.position.set(l.x, 2.6, l.z);
        lamp.userData.phase = i * 1.7;
        F.group.add(lamp);
        return lamp;
      }));
    },

    // every floor ends up with exactly 8 point lights (the real lamps plus silent ones), so switching floors never changes the light count
    padFloorLights() {
      this.world.floors.forEach((F) => {
        let n = 0;
        F.group.traverse((o) => { if (o.isPointLight) n++; });
        while (n < 8) { F.group.add(new THREE.PointLight(0x000000, 0, 1)); n++; }
      });
    },

    // only one floor is shown at a time; the higher floors are darker
    showFloor(f) {
      this.floor = f;
      this.world.floors.forEach((F, i) => { F.group.visible = (i === f); });
      const dark = this.world.floors[f].dark;
      if (this.ambient) this.ambient.intensity = 1.1 * dark;
      if (this.hemi) this.hemi.intensity = 0.7 * dark;
      if (this.scene.fog) this.scene.fog.density = 0.012 + (1 - dark) * 0.02;
      this.ghosts.forEach((g) => { g.mesh.visible = (g.floor === f); });
    },

    // some doors are locked on every floor (more on the higher ones); each needs its own key, hidden somewhere
    setupLocks() {
      const w = this.world;
      this.lockedDoors = [];
      let n = 0;
      w.floors.forEach((F, fi) => {
        const ok = (d) => d.kind === 'room' && F.rooms.every(r => !r.doors.includes(d) || (!r.stair && r !== w.exit.room));
        const cands = shuffleIn(F.doors.filter(ok));
        for (let k = 0; k < LOCKS_PER_FLOOR[fi] && cands[k]; k++) {
          const color = DOOR_KEY_COLORS[n % DOOR_KEY_COLORS.length];
          F.lockDoor(cands[k], 'dk' + n, color);
          cands[k].keyColor = color;
          cands[k].keyLabel = 'key ' + (n + 1);
          this.lockedDoors.push(cands[k]);
          n++;
        }
      });
    },

    // EVERY key is hidden inside furniture (drawers, lockers, cabinets, freezers, beds, couches, toilets...), never lying around.
    // The door keys are placed so that each one can be reached with the keys found before it; the exit keys are spread over the floors.
    placeKeys() {
      const w = this.world;
      this.containers = [];
      w.floors.forEach((F) => F.searchSpots.forEach((c) => { c.contents = null; c.searched = false; this.containers.push(c); }));
      const used = new Set();
      const opened = [];
      const standOk = (R, c) => w.inSet(R, c.floor, c.anchor[0], c.anchor[1]);
      shuffleIn(this.lockedDoors.slice()).forEach((d) => {
        const R = w.reachable(opened);
        const cands = this.containers.filter(c => !used.has(c) && standOk(R, c));
        const c = cands.length ? pickOf(cands) : pickOf(this.containers.filter(x => !used.has(x)));
        used.add(c);
        c.contents = { kind: 'door', door: d };
        opened.push(d);
      });
      const R = w.reachable(opened);
      let pool = this.containers.filter(c => !used.has(c) && standOk(R, c));
      if (pool.length < this.keysNeeded) pool = this.containers.filter(c => !used.has(c));
      const perFloor = [0, 0, 0, 0, 0];
      for (let k = 0; k < this.keysNeeded && pool.length; k++) {
        const least = Math.min(...pool.map(c => perFloor[c.floor]));
        const c = pickOf(pool.filter(x => perFloor[x.floor] === least));
        pool.splice(pool.indexOf(c), 1);
        used.add(c);
        c.contents = { kind: 'key' };
        perFloor[c.floor]++;
      }
    },

    // ------------------------------------------------------------------ pick-ups: power-ups drift, guns stay; more of both with more floors and more players
    spawnPowerups() {
      this.powerups = [];
      const extra = Math.max(0, this.playerCount - 1);
      const nPower = 10 + 5 * extra;           // 2 per floor for one player, 3 per floor for two, ...
      const nGun = 5 + 2 * extra;              // at least one gun on every floor
      const bag = [];
      Object.keys(POWER_BAG).forEach((t) => { for (let i = 0; i < POWER_BAG[t]; i++) bag.push(t); });
      for (let i = 0; i < nPower; i++) this.addPickup(pickOf(bag), i % 5, false);
      for (let i = 0; i < nGun; i++) this.addPickup(i < 5 ? GUN_TYPES[Math.floor(Math.random() * 3)] : pickOf(GUN_TYPES), i % 5, true);
    },

    addPickup(type, floor, fixed) {
      const def = ITEMS[type];
      const g = new THREE.Group();
      if (fixed) {                                      // a gun: a small weapon shape on a glowing ring
        const body = new THREE.Mesh(new THREE.BoxGeometry(0.5, 0.16, 0.14), new THREE.MeshStandardMaterial({ color: 0x20252b, metalness: 0.7, roughness: 0.4 }));
        const barrel = new THREE.Mesh(new THREE.CylinderGeometry(0.04, 0.04, 0.4, 8), new THREE.MeshStandardMaterial({ color: def.color, emissive: def.color, emissiveIntensity: 0.9 }));
        barrel.rotation.z = Math.PI / 2; barrel.position.x = 0.4;
        const ring = new THREE.Mesh(new THREE.TorusGeometry(0.5, 0.02, 6, 24), new THREE.MeshBasicMaterial({ color: def.color }));
        ring.rotation.x = Math.PI / 2; ring.position.y = -0.3;
        g.add(body, barrel, ring);
      } else {
        const orb = new THREE.Mesh(new THREE.SphereGeometry(0.22, 16, 12), new THREE.MeshStandardMaterial({ color: def.color, emissive: def.color, emissiveIntensity: 1.1, roughness: 0.3 }));
        const halo = new THREE.Mesh(new THREE.SphereGeometry(0.42, 14, 10), new THREE.MeshBasicMaterial({ color: def.color, transparent: true, opacity: 0.22, depthWrite: false, blending: THREE.AdditiveBlending }));
        g.add(orb, halo);
      }
      const o = { type, def, mesh: g, floor, timer: Math.random() * 6, used: false, fixed };
      this.powerups.push(o);
      this.relocatePower(o, floor);
    },

    relocatePower(o, forceFloor) {
      const w = this.world;
      let f = forceFloor;
      if (f === null || f === undefined) f = (Math.random() < 0.75) ? (o.floor || 0) : Math.floor(Math.random() * 5);
      const F = w.floors[f];
      let spot = null;
      for (let i = 0; i < 20 && !spot; i++) {
        const p = pickOf(F.patrolPoints);
        const x = p[0] + (Math.random() - 0.5) * 4, z = p[1] + (Math.random() - 0.5) * 4;
        if (F.walkable(x, z)) spot = [x, z];
      }
      if (!spot) spot = pickOf(F.patrolPoints);
      if (o.mesh.parent) o.mesh.parent.remove(o.mesh);
      F.group.add(o.mesh);
      o.floor = f;
      o.mesh.position.set(spot[0], 0.9, spot[1]);
      o.timer = 0;
    },

    // walking into a pick-up puts it in the next free slot (3 slots in all)
    tryPickup(o) {
      const slot = this.inv.findIndex(s => !s);
      if (slot < 0) {
        const now = performance.now();
        if (now - (this._fullMsg || 0) > 3000) { this._fullMsg = now; if (window.lexusSay) window.lexusSay('Your hands are full: you can carry <b>3</b> things. Use one first (keys 1, 2, 3).', 'curious'); }
        return;
      }
      o.used = true;                                  // it never comes back
      if (o.mesh.parent) o.mesh.parent.remove(o.mesh);
      this.powerups = this.powerups.filter(p => p !== o);
      this.inv[slot] = { type: o.type, def: o.def, uses: o.def.uses };
      AudioEngine.playKeyPickup();
      if (window.lexusSay) window.lexusSay('<b>' + o.def.name + '</b> in slot <b>' + (slot + 1) + '</b>: ' + o.def.text + '. Press <b>' + (slot + 1) + '</b> to use it.', 'happy');
      this.renderHud();
    },

    // pressing the number of a slot (numpad 1-3 or digits 1-3)
    useSlot(i) {
      if (!this.active || !this.world || i < 0 || i >= MAX_ITEMS) return;
      const it = this.inv[i];
      if (!it) { if (window.lexusSay) window.lexusSay('Slot ' + (i + 1) + ' is empty.', 'curious'); return; }
      if (this.inTransition) return;
      if (this.hiding && it.type !== 'potion') { if (window.lexusSay) window.lexusSay('Not while hiding.', 'curious'); return; }
      const used = this.activate(it.type);
      if (used === false) return;
      it.uses--;
      if (it.uses <= 0) this.inv[i] = null;
      this.renderHud();
    },

    activate(type) {
      const lex = this.lexus || (this.warehouseState && this.warehouseState.lexus);
      const def = ITEMS[type];
      const say = (t, m) => { if (window.lexusSay) window.lexusSay(t, m || 'happy'); };
      switch (type) {
        case 'kame': this.fireKamehameha(lex); return true;
        case 'laser': return this.fireLaser(lex);
        case 'missile': this.fireMissile(lex); return true;
        case 'freeze': this.throwFreeze(lex); return true;
        case 'transform': return this.doTransform(lex);
        case 'clone': this.doClones(lex); return true;
        case 'potion':
          if (this.hp >= 100) { say('Your health is already full.', 'curious'); return false; }
          this.hp = 100; AudioEngine.playKeyPickup(); say('❤️ Health restored!'); return true;
        case 'speed': this.fx.speed = def.secs; say('⚡ <b>Adrenaline!</b> Run!'); return true;
        case 'cloak': this.fx.cloak = def.secs; say('🫥 <b>Shadow Cloak</b> on: the killers can hardly see you.'); return true;
        case 'radar': this.fx.radar = def.secs; say('📡 <b>Key Radar</b> on.'); return true;
        case 'battery': this.fx.battery = def.secs; say('🔦 <b>Power Cell</b>: the flashlight is much stronger.'); return true;
        case 'shield': this.fx.shield = true; say('🛡️ <b>Guardian Shield</b> ready.'); return true;
        case 'stun':
          this.ghosts.forEach((g) => { if (g.floor === this.floor && g.mesh.position.distanceTo(lex.root.position) < 14) g.stun = 6; });
          AudioEngine.playExplosion(); say('💥 <b>Stun Pulse!</b> The killers nearby are frozen.'); return true;
        default: return false;
      }
    },

    // how far you can shoot from p along dir before a wall
    rayLength(p, dx, dz, max) {
      const F = this.world.floors[this.floor];
      let d = 0;
      while (d < max && !F.isBlocked(p.x + dx * d, p.z + dz * d, 0.15)) d += 0.25;
      return d;
    },

    // a killer that is slid away (the beam and the missile): it flies along dir until a wall, then sleeps
    shove(g, dx, dz, far, sleep) {
      const F = this.world.floors[g.floor];
      const p = g.mesh.position;
      let d = 0;
      while (d < far && !F.isBlocked(p.x + dx * (d + 0.3), p.z + dz * (d + 0.3), 0.3)) d += 0.3;
      g.slide = { x0: p.x, z0: p.z, x1: p.x + dx * d, z1: p.z + dz * d, t: 0, dur: 0.45 };
      g.stun = sleep + 0.5;
      g.state = 'search'; g.path = null; g.dwell = 3; g.lost = 0; g.knowsHide = null;
    },

    flash(mesh, secs) { this.effects.push({ mesh, life: secs, max: secs }); this.scene.add(mesh); },

    fireKamehameha(lex) {
      AudioEngine.playBeam();
      const p = lex.root.position;
      const dx = Math.sin(lex.currentYaw || 0), dz = Math.cos(lex.currentYaw || 0);
      const range = Math.max(1.5, this.rayLength(p, dx, dz, 22));
      const g = new THREE.Group();
      const core = new THREE.Mesh(new THREE.CylinderGeometry(0.28, 0.28, range, 14), new THREE.MeshBasicMaterial({ color: 0xbae6fd, transparent: true, opacity: 0.95, blending: THREE.AdditiveBlending, depthWrite: false }));
      const aura = new THREE.Mesh(new THREE.CylinderGeometry(0.55, 0.55, range, 14), new THREE.MeshBasicMaterial({ color: 0x38bdf8, transparent: true, opacity: 0.4, blending: THREE.AdditiveBlending, depthWrite: false }));
      core.rotation.x = aura.rotation.x = Math.PI / 2;
      const ball = new THREE.Mesh(new THREE.SphereGeometry(0.7, 14, 10), new THREE.MeshBasicMaterial({ color: 0x7dd3fc, transparent: true, opacity: 0.8, blending: THREE.AdditiveBlending, depthWrite: false }));
      ball.position.z = -range / 2;
      g.add(core, aura, ball);
      g.position.set(p.x + dx * (range / 2 + 0.5), 1.25, p.z + dz * (range / 2 + 0.5));
      g.rotation.y = lex.currentYaw || 0;
      this.flash(g, 0.7);
      let hit = 0;
      this.ghosts.forEach((k) => {
        if (k.floor !== this.floor) return;
        const vx = k.mesh.position.x - p.x, vz = k.mesh.position.z - p.z;
        const t = vx * dx + vz * dz, lat = Math.abs(vx * dz - vz * dx);
        if (t > -0.5 && t < range + 1.5 && lat < 1.9) { this.shove(k, dx, dz, 10, 5); hit++; }
      });
      if (window.lexusSay) window.lexusSay(hit ? '🌊 <b>KA-ME-HA-ME-HA!</b> The killer is blasted away and falls asleep for 5 seconds!' : '🌊 <b>KA-ME-HA-ME-HA!</b> ...nothing in the way.', 'surprised');
    },

    fireLaser(lex) {
      AudioEngine.playBeam();
      const p = lex.root.position;
      const dx = Math.sin(lex.currentYaw || 0), dz = Math.cos(lex.currentYaw || 0);
      const range = this.rayLength(p, dx, dz, 30);
      let best = null, bt = 1e9;
      this.ghosts.forEach((k) => {
        if (k.floor !== this.floor) return;
        const vx = k.mesh.position.x - p.x, vz = k.mesh.position.z - p.z;
        const t = vx * dx + vz * dz, lat = Math.abs(vx * dz - vz * dx);
        if (t > 0 && t < range + 0.5 && lat < 0.9 && t < bt) { best = k; bt = t; }
      });
      const len = best ? bt : range;
      const beam = new THREE.Mesh(new THREE.CylinderGeometry(0.05, 0.05, len, 8), new THREE.MeshBasicMaterial({ color: 0xff3b30, transparent: true, opacity: 0.95, blending: THREE.AdditiveBlending, depthWrite: false }));
      beam.rotation.x = Math.PI / 2;
      const g = new THREE.Group(); g.add(beam);
      g.position.set(p.x + dx * (len / 2 + 0.5), 1.25, p.z + dz * (len / 2 + 0.5)); g.rotation.y = lex.currentYaw || 0;
      this.flash(g, 0.25);
      if (best) { best.stun = 8; best.state = 'search'; best.path = null; best.dwell = 3; if (window.lexusSay) window.lexusSay('🔴 <b>Laser hit!</b> The killer sleeps for 8 seconds.', 'happy'); }
      else if (window.lexusSay) window.lexusSay('🔴 The laser missed. That was the only shot.', 'curious');
      return true;
    },

    fireMissile(lex) {
      AudioEngine.playBeam();
      const p = lex.root.position;
      const dx = Math.sin(lex.currentYaw || 0), dz = Math.cos(lex.currentYaw || 0);
      const m = new THREE.Group();
      const body = new THREE.Mesh(new THREE.CylinderGeometry(0.07, 0.07, 0.5, 8), new THREE.MeshStandardMaterial({ color: 0x9ca3af, metalness: 0.6 }));
      const tip = new THREE.Mesh(new THREE.ConeGeometry(0.07, 0.18, 8), new THREE.MeshStandardMaterial({ color: 0xef4444 }));
      const flame = new THREE.Mesh(new THREE.SphereGeometry(0.12, 8, 8), new THREE.MeshBasicMaterial({ color: 0xfb923c, blending: THREE.AdditiveBlending }));
      body.rotation.x = Math.PI / 2; tip.rotation.x = Math.PI / 2; tip.position.z = 0.34; flame.position.z = -0.32;
      m.add(body, tip, flame);
      m.position.set(p.x + dx * 0.9, 1.2, p.z + dz * 0.9); m.rotation.y = lex.currentYaw || 0;
      this.scene.add(m);
      this.projectiles.push({ mesh: m, dx, dz, floor: this.floor, dist: 0 });
      if (window.lexusSay) window.lexusSay('🚀 <b>Missile away!</b>', 'surprised');
    },

    blast(x, z, floor, radius, push, sleep, color) {
      const ball = new THREE.Mesh(new THREE.SphereGeometry(1, 16, 12), new THREE.MeshBasicMaterial({ color, transparent: true, opacity: 0.7, blending: THREE.AdditiveBlending, depthWrite: false }));
      ball.position.set(x, 1.0, z);
      ball.userData.grow = radius;
      this.flash(ball, 0.6);
      AudioEngine.playExplosion();
      let n = 0;
      this.ghosts.forEach((k) => {
        if (k.floor !== floor) return;
        const vx = k.mesh.position.x - x, vz = k.mesh.position.z - z;
        const d = Math.hypot(vx, vz);
        if (d > radius) return;
        n++;
        if (push > 0) { const l = d || 1; this.shove(k, vx / l, vz / l, push, sleep); } else { k.stun = sleep; k.state = 'search'; k.path = null; k.dwell = 3; }
      });
      return n;
    },

    throwFreeze(lex) {
      const p = lex.root.position;
      const dx = Math.sin(lex.currentYaw || 0), dz = Math.cos(lex.currentYaw || 0);
      const d = Math.min(7, this.rayLength(p, dx, dz, 7));
      const n = this.blast(p.x + dx * d, p.z + dz * d, this.floor, 7, 0, 10, 0x7dd3fc);
      if (window.lexusSay) window.lexusSay(n ? '🧊 <b>Freeze Grenade!</b> ' + n + ' killer(s) fall asleep for 10 seconds.' : '🧊 <b>Freeze Grenade!</b> Nobody was close enough.', 'surprised');
    },

    // turn into a piece of furniture from the surroundings: the killers cannot recognise you, even right next to you
    doTransform(lex) {
      if (this.fx.disguise > 0) { this.endTransform(); return false; }        // using it again takes the disguise off (no use is spent)
      const F = this.world.floors[this.floor];
      const p = lex.root.position;
      let best = null, bd = 9;
      F.furniture.forEach((it) => { const d = Math.hypot(it.x - p.x, it.z - p.z); if (d < bd && true) { best = it; bd = d; } });
      const type = best ? best.type : pickOf(['cabinet', 'crate', 'durabox', 'shelf']);
      const made = window.AsylumWorld.makeFurniture(type);
      made.group.position.set(p.x, 0, p.z);
      made.group.rotation.y = lex.currentYaw || 0;
      this.scene.add(made.group);
      this.disguiseMesh = made.group;
      lex.root.visible = false;
      this.fx.disguise = 25;
      AudioEngine.playHide();
      if (window.lexusSay) window.lexusSay('🪑 You are now a <b>' + type + '</b>. The killers cannot recognise you, even if you move. Lasts 25 seconds (press the key again to take it off).', 'happy');
      return true;
    },

    endTransform() {
      const lex = this.lexus || (this.warehouseState && this.warehouseState.lexus);
      if (this.disguiseMesh) { this.scene.remove(this.disguiseMesh); this.disguiseMesh = null; }
      this.fx.disguise = 0;
      if (lex && lex.root && !this.hiding) lex.root.visible = true;
    },

    // five copies of you appear and run off in different directions: the killers go after them (you are the sixth)
    doClones(lex) {
      const F = this.world.floors[this.floor];
      const p = lex.root.position;
      let made = 0;
      for (let i = 0; i < 12 && made < 5; i++) {
        const a = Math.random() * Math.PI * 2, r = 1.2 + Math.random() * 3;
        const x = p.x + Math.cos(a) * r, z = p.z + Math.sin(a) * r;
        if (!F.walkable(x, z)) continue;
        const mesh = lex.root.clone(true);
        mesh.visible = true;
        mesh.position.set(x, 0, z);
        this.scene.add(mesh);
        this.clones.push({ mesh, floor: this.floor, path: null, pathFor: null, target: null, life: 16, speed: 2.8, repath: 0, bob: Math.random() * 6 });
        made++;
      }
      while (made < 5) {                                   // no room around: put the rest on the avatar's own spot
        const mesh = lex.root.clone(true); mesh.visible = true; mesh.position.set(p.x, 0, p.z); this.scene.add(mesh);
        this.clones.push({ mesh, floor: this.floor, path: null, pathFor: null, target: null, life: 16, speed: 2.8, repath: 0, bob: Math.random() * 6 });
        made++;
      }
      AudioEngine.playHide();
      if (window.lexusSay) window.lexusSay('👥 <b>Clone Army!</b> Five of me, and the real one is the sixth. They will draw the killers away: GO!', 'surprised');
    },

    killClone(c) {
      this.scene.remove(c.mesh);
      this.clones = this.clones.filter(x => x !== c);
      const puff = new THREE.Mesh(new THREE.SphereGeometry(0.5, 10, 8), new THREE.MeshBasicMaterial({ color: 0xf472b6, transparent: true, opacity: 0.7, blending: THREE.AdditiveBlending, depthWrite: false }));
      puff.position.copy(c.mesh.position); puff.position.y = 1.0; puff.userData.grow = 1.6;
      this.flash(puff, 0.5);
      AudioEngine.playExplosion();
    },

    updateClones(dt, time) {
      const F = this.world.floors[this.floor];
      this.clones.forEach((c) => {
        c.life -= dt;
        if (c.life <= 0) { this.killClone(c); return; }
        c.mesh.visible = (c.floor === this.floor);
        if (c.floor !== this.floor) return;
        const pos = c.mesh.position;
        c.repath -= dt;
        if (!c.target || Math.hypot(c.target[0] - pos.x, c.target[1] - pos.z) < 1.0) { c.target = pickOf(F.patrolPoints); c.path = null; }
        if (!c.path || !c.path.length || c.repath <= 0) { c.path = F.findPath(pos.x, pos.z, c.target[0], c.target[1]); c.repath = 3; if (c.path && c.path.length > 1) c.path.shift(); }
        if (c.path && c.path.length) {
          F.doors.forEach((d) => { if (!d.locked && d.target === 0 && Math.hypot(d.cx - pos.x, d.cz - pos.z) < 1.4) F.openDoor(d); });
          const wp = c.path[0], dx = wp[0] - pos.x, dz = wp[1] - pos.z, dist = Math.hypot(dx, dz);
          if (dist < 0.25) c.path.shift();
          else {
            const st = Math.min(dist, c.speed * dt);
            pos.x += dx / dist * st; pos.z += dz / dist * st;
            c.mesh.rotation.y = Math.atan2(dx, dz);
          }
        }
        pos.y = Math.abs(Math.sin(time * 9 + c.bob)) * 0.05;
      });
    },

    setupFlashlight() {
      const spot = new THREE.SpotLight(0xfff7ed, 4.2, 24.0, 0.48, 0.55, 1.0);
      spot.castShadow = true;
      spot.shadow.mapSize.width = 2048;
      spot.shadow.mapSize.height = 2048;
      spot.shadow.bias = -0.0004;
      spot.shadow.normalBias = 0.04;                 // no striped shadows on floors and walls
      spot.shadow.radius = 2;
      spot.shadow.camera.near = 0.6;                 // what is closer than this to the lamp does not cast a shadow (the avatar's own arms)
      const target = new THREE.Object3D();
      this.scene.add(target);
      spot.target = target;
      this.scene.add(spot);
      this.flashlight = spot;
      this.flashlightTarget = target;
      this.flashlightOn = true;
    },

    toggleFlashlight() {
      this.flashlightOn = !this.flashlightOn;
      AudioEngine.playFlashlightClick();
    },

    // -------------------------------------------------------------------------
    // 5. THE WORLD, SEEN FROM THE GAME
    // -------------------------------------------------------------------------
    // how high the ground is at x, z: 0 on a floor, and following the flight on a staircase (up to +3 m, or down to -3 m)
    groundAt(x, z) {
      const F = this.world && this.world.floors[this.floor];
      if (!F) return { y: 0, on: false };
      for (let i = 0; i < F.stairLayouts.length; i++) {
        const L = F.stairLayouts[i], r = L.rect;
        if (x >= r[0] && x <= r[1] && z >= r[2] && z <= r[3]) {
          const along = L.alongX ? x : z;
          let t = L.dir === 1 ? (along - L.s0) / L.Lf : (L.s0 - along) / L.Lf;
          t = Math.max(0, Math.min(1, t));
          return { y: (L.up ? 1 : -1) * window.AsylumWorld.RISE * t, on: true, layout: L, t };
        }
      }
      return { y: 0, on: false };
    },

    isBlocked(x, z) { return this.active && this.world ? this.world.floors[this.floor].isBlocked(x, z, 0.38) : false; },
    hasLineOfSight(ax, az, bx, bz) { return this.world ? this.world.floors[this.floor].hasLineOfSight(ax, az, bx, bz) : true; },

    // -------------------------------------------------------------------------
    // 6. THE KILLERS: each floor has its own (1, then 2 on every floor above), and one boss who walks between the floors
    // -------------------------------------------------------------------------
    makeGhostMesh(boss) {
      const gGroup = new THREE.Group();
      gGroup.name = 'asylum-ghost-phantom';
      const robe = new THREE.Mesh(new THREE.ConeGeometry(0.48, 1.6, 12), new THREE.MeshStandardMaterial({ color: boss ? 0x2a0710 : 0x09030d, roughness: 0.9, transparent: true, opacity: 0.92 }));
      robe.position.y = 0.80; gGroup.add(robe);
      const head = new THREE.Mesh(new THREE.SphereGeometry(0.24, 12, 12), new THREE.MeshStandardMaterial({ color: 0x030105 }));
      head.position.y = 1.60; gGroup.add(head);
      const eyeMat = new THREE.MeshBasicMaterial({ color: boss ? 0xff7a00 : 0xff1111 });
      [-0.08, 0.08].forEach(x => { const e = new THREE.Mesh(new THREE.SphereGeometry(0.04, 8, 8), eyeMat); e.position.set(x, 1.62, 0.22); gGroup.add(e); });
      const glow = new THREE.Mesh(new THREE.SphereGeometry(0.2, 10, 8), new THREE.MeshBasicMaterial({ color: boss ? 0xff6a00 : 0xef4444, transparent: true, opacity: 0.25, blending: THREE.AdditiveBlending, depthWrite: false }));
      glow.position.set(0, 1.62, 0.2);
      gGroup.add(glow);
      gGroup.userData.light = { intensity: 2.2 };      // (only a number the AI changes when the killer is stunned: no real light, so the scene never has to recompile)
      const handMat = new THREE.MeshStandardMaterial({ color: 0x261633 });
      [-0.35, 0.35].forEach(x => { const h = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.08, 0.24), handMat); h.position.set(x, 1.1, 0.3); gGroup.add(h); });
      if (boss) {
        [-0.16, 0.16].forEach(x => { const horn = new THREE.Mesh(new THREE.ConeGeometry(0.05, 0.38, 6), new THREE.MeshStandardMaterial({ color: 0xd1c2a3 })); horn.position.set(x, 1.95, 0); horn.rotation.z = x < 0 ? 0.35 : -0.35; gGroup.add(horn); });
        gGroup.scale.setScalar(1.35);
      }
      return gGroup;
    },

    newGhost(floor, boss) {
      const g = { mesh: this.makeGhostMesh(boss), floor, boss: !!boss, params: boss ? BOSS : LEVEL[floor], state: 'patrol', dwell: 1.5, lost: 0, lastSeen: null, path: null, pathFor: null, repath: 0, target: null, knowsHide: null, checked: null, stun: 0, stuck: 0 };
      this.scene.add(g.mesh);
      g.mesh.visible = (floor === this.floor);
      this.ghosts.push(g);
      return g;
    },

    // a random place on the floor that the avatar's side can reach, far from the avatar
    farSpot(floor, from) {
      const w = this.world, F = w.floors[floor];
      const R = w.reachable([]);
      const far = (p) => Math.hypot(p[0] - from.x, p[1] - from.z) >= this.GHOST_MIN_DISTANCE;
      let pool = F.patrolPoints.filter(p => w.inSet(R, floor, p[0], p[1]) && far(p));
      if (!pool.length) pool = F.patrolPoints.filter(p => w.inSet(R, floor, p[0], p[1]));
      if (!pool.length) pool = F.patrolPoints;
      return pickOf(pool);
    },

    startFloorCountdown(f) {
      if (this.visited[f]) return;
      this.visited[f] = true;
      this.clearGhostTimers();
      let left = this.FLOOR_HEAD_START;
      this.countdownFloor = f;
      const show = () => this.setStatus(left > 0 ? 'Killers wake in ' + left + 's' : (f === 0 ? 'Killer searching' : 'Killers searching'));
      show();
      if (window.lexusSay) window.lexusSay(f === 0 ? "It is dark and the doors are locked... Find the keys hidden in the furniture. We have " + left + " seconds before something wakes up." : "A new floor... and new killers. Be careful, we have " + left + " seconds.", "surprised");
      this.countdownTimer = setInterval(() => {
        if (!this.active) { this.clearGhostTimers(); return; }
        left--;
        show();
        if (left <= 0) {
          clearInterval(this.countdownTimer);
          this.countdownTimer = null;
          if (this.inTransition) return;
          const lex = this.lexus || (this.warehouseState && this.warehouseState.lexus);
          for (let i = 0; i < LEVEL[f].killers; i++) {
            const g = this.newGhost(f, false);
            const spot = this.farSpot(f, lex.root.position);
            g.mesh.position.set(spot[0], 0.35, spot[1]);
            this.ghostPickTarget(g);
          }
          AudioEngine.playStoneRumble();
          if (window.lexusSay) window.lexusSay("Something just woke up... keep moving, and hide if it gets close!", "surprised");
        }
      }, 1000);
    },

    clearGhostTimers() {
      if (this.countdownTimer) { clearInterval(this.countdownTimer); this.countdownTimer = null; }
    },

    // the boss sleeps for a while, then walks the stairs between the floors looking for you
    setupBoss() {
      this.boss = { floor: 2 + Math.floor(Math.random() * 3), awake: false, wake: 75, migrate: 20, ghost: null };
    },

    updateBoss(dt) {
      const b = this.boss;
      if (!b) return;
      if (!b.awake) { b.wake -= dt; if (b.wake <= 0) { b.awake = true; this.placeBoss(b.floor); if (window.lexusSay) window.lexusSay("🔥 The BOSS has woken up. He goes up and down the stairs. Do not stay in one place!", "surprised"); } return; }
      b.migrate -= dt;
      const g = b.ghost;
      if (b.migrate <= 0 && g && g.state !== 'chase') {
        // he leaves for another floor: most often the one where you are
        const target = Math.random() < 0.55 ? this.floor : Math.floor(Math.random() * 5);
        b.migrate = 22 + Math.random() * 16;
        if (target !== b.floor) this.placeBoss(target);
      }
    },

    placeBoss(floor) {
      const b = this.boss;
      const lex = this.lexus || (this.warehouseState && this.warehouseState.lexus);
      if (!b.ghost) b.ghost = this.newGhost(floor, true);
      const g = b.ghost;
      b.floor = floor;
      g.floor = floor;
      const F = this.world.floors[floor];
      const stairs = this.world.stairs.filter(s => s.floor === floor);
      let pos;
      if (stairs.length) {
        const far = stairs.filter(s => Math.hypot(s.x - lex.root.position.x, s.z - lex.root.position.z) > 8 || floor !== this.floor);
        const s = pickOf(far.length ? far : stairs);
        pos = [s.x, s.z];
      } else pos = this.farSpot(floor, lex.root.position);
      g.mesh.position.set(pos[0], 0.35, pos[1]);
      g.state = 'patrol'; g.path = null; g.dwell = 1.0; g.lost = 0; g.knowsHide = null;
      g.mesh.visible = (floor === this.floor);
      this.ghostPickTarget(g);
      if (floor === this.floor) { AudioEngine.playGhostScreech(); if (window.lexusSay) window.lexusSay("🔥 The boss came down the stairs onto this floor!", "surprised"); }
      void F;
    },

    ghostPickTarget(g) {
      const F = this.world.floors[g.floor];
      const from = g.mesh.position;
      for (let i = 0; i < 16; i++) {
        const p = pickOf(F.patrolPoints);
        if (Math.hypot(p[0] - from.x, p[1] - from.z) < 5) continue;
        const route = F.findPath(from.x, from.z, p[0], p[1]);
        if (route && route.length > 1) { g.target = { x: p[0], z: p[1] }; g.path = null; g.stuck = 0; return; }
      }
      g.target = null;
      g.dwell = 1.0;
    },

    // moves a ghost along a route around walls and furniture; it opens the unlocked doors on the way
    ghostWalk(g, dt, tx, tz, speed, replanEvery) {
      const F = this.world.floors[g.floor];
      const pos = g.mesh.position;
      g.repath -= dt;
      if (!g.path || !g.path.length || (g.pathFor && Math.hypot(g.pathFor.x - tx, g.pathFor.z - tz) > 1.0) || g.repath <= 0) {
        g.path = F.findPath(pos.x, pos.z, tx, tz);
        g.pathFor = { x: tx, z: tz };
        g.repath = replanEvery;
        if (g.path && g.path.length > 1) g.path.shift();
      }
      if (!g.path || !g.path.length) return false;
      F.doors.forEach((d) => {
        if (d.locked || d.target === 1) return;
        if (Math.hypot(d.cx - pos.x, d.cz - pos.z) < 1.5) { F.openDoor(d); AudioEngine.playDoorCreak(); }
      });
      const wp = g.path[0];
      const dx = wp[0] - pos.x, dz = wp[1] - pos.z;
      const dist = Math.hypot(dx, dz);
      if (dist < 0.2) { g.path.shift(); return true; }
      const step = Math.min(dist, speed * dt);
      const nx = pos.x + (dx / dist) * step, nz = pos.z + (dz / dist) * step;
      let wait = false;
      F.doors.forEach((d) => { if (!d.locked && d.open < 0.55 && nx > d.rect[0] - 0.2 && nx < d.rect[1] + 0.2 && nz > d.rect[2] - 0.2 && nz < d.rect[3] + 0.2) wait = true; });
      if (!wait) { pos.x = nx; pos.z = nz; }
      const want = Math.atan2(dx, dz);
      const diff = Math.atan2(Math.sin(want - g.mesh.rotation.y), Math.cos(want - g.mesh.rotation.y));
      g.mesh.rotation.y += diff * Math.min(1, dt * 6);
      return true;
    },

    // A killer does NOT know where the avatar is. It walks from room to room looking around.
    //  - it SEES the avatar in front of it (about 110 degrees) up to its see range with nothing in between; the flashlight gives you away farther
    //  - it FEELS the avatar only very close
    //  - a hidden avatar is not seen at all (unless it saw you go in, or it happens to search that very spot); the Shadow Cloak hides you too
    //  - a disguised avatar (Camouflage) is never recognised, however close it gets
    //  - the clones of the Clone Army are seen like the avatar and are chased first
    // Once it has seen something it follows; run far enough away, or break the line of sight for a few seconds, and it gives up.
    updateGhostAI(g, dt, time, playerPos) {
      const F = this.world.floors[g.floor];
      const P = g.params;
      const gp = g.mesh.position;
      gp.y = 0.35 + Math.sin(time * 3.5) * 0.08;
      const eye = g.mesh.userData.light;
      if (g.stun > 0) {                                      // asleep or frozen
        g.stun -= dt;
        if (eye) eye.intensity = 0.3;
        return;
      }
      if (eye) eye.intensity = 2.2;

      const sees = (tx, tz, hearRange, useFlash) => {
        const toX = tx - gp.x, toZ = tz - gp.z;
        const flat = Math.hypot(toX, toZ) || 1;
        const facing = Math.sin(g.mesh.rotation.y) * (toX / flat) + Math.cos(g.mesh.rotation.y) * (toZ / flat);
        const inView = facing > 0.57;
        if (!F.hasLineOfSight(gp.x, gp.z, tx, tz)) return false;
        if (flat < hearRange) return true;
        if (inView && flat < P.see) return true;
        if (useFlash && inView && this.flashlightOn && flat < P.flash) return true;
        return false;
      };
      const toX = playerPos.x - gp.x, toZ = playerPos.z - gp.z;
      const flat = Math.hypot(toX, toZ) || 1;
      const hidden = !!this.hiding;
      const disguised = this.fx.disguise > 0;
      const cloaked = this.fx.cloak > 0;
      let senses = false;
      if (!hidden && !disguised) senses = cloaked ? sees(playerPos.x, playerPos.z, 1.2, false) && flat < 1.2 : sees(playerPos.x, playerPos.z, this.HEAR_RANGE, true);

      // the clones: the nearest one in sight is the target (they are always chased before the real avatar)
      let decoy = null, dd = 1e9;
      this.clones.forEach((c) => {
        if (c.floor !== g.floor) return;
        const d = Math.hypot(c.mesh.position.x - gp.x, c.mesh.position.z - gp.z);
        if (d < dd && sees(c.mesh.position.x, c.mesh.position.z, this.HEAR_RANGE, false)) { decoy = c; dd = d; }
      });
      if (decoy) {
        if (g.state !== 'chase') { g.state = 'chase'; AudioEngine.playGhostScreech(); }
        g.lastSeen = { x: decoy.mesh.position.x, z: decoy.mesh.position.z };
        g.lost = 0; g.knowsHide = null; g.decoy = decoy;
        this.ghostWalk(g, dt, g.lastSeen.x, g.lastSeen.z, P.chase, 0.3);
        if (dd < 1.05) { this.killClone(decoy); g.stun = 1.6; g.state = 'search'; g.dwell = 2; g.path = null; g.decoy = null; }   // it tears the copy apart
        return;
      }
      g.decoy = null;

      if (senses) {
        if (g.state !== 'chase') { g.state = 'chase'; AudioEngine.playGhostScreech(); }
        g.lastSeen = { x: playerPos.x, z: playerPos.z };
        g.lost = 0;
        g.knowsHide = null;
      }

      // it saw the avatar slip into a hiding place: it goes straight there
      if (hidden && g.state === 'chase' && g.knowsHide) {
        const s = g.knowsHide;
        this.ghostWalk(g, dt, s.anchor[0], s.anchor[1], P.chase, 0.5);
        if (Math.hypot(s.x - gp.x, s.z - gp.z) < 1.5) this.caught(g, 'found');
        return;
      }

      if (g.state === 'chase') {
        if (!senses) g.lost += dt * (flat > P.giveUp ? 4 : 1);
        this.ghostWalk(g, dt, g.lastSeen.x, g.lastSeen.z, P.chase, 0.35);
        if (flat < 1.05 && !hidden && !disguised) { this.caught(g); return; }
        const atLastSeen = Math.hypot(g.lastSeen.x - gp.x, g.lastSeen.z - gp.z) < 0.6;
        if (g.lost >= P.lose || (atLastSeen && !senses)) { g.state = 'search'; g.dwell = 3.5; g.path = null; }
        return;
      }

      if (hidden) {
        const s = this.hiding;
        const d = Math.hypot(s.x - gp.x, s.z - gp.z);
        if (d < 1.9 && g.checked !== s.id) { g.checked = s.id; if (Math.random() < 0.35) { this.caught(g, 'found'); return; } }
        if (d > 4) g.checked = null;
      }
      if (g.dwell > 0) {
        g.dwell -= dt;
        g.mesh.rotation.y += Math.sin(time * 1.6) * 0.03;
        if (g.dwell <= 0 && g.state === 'search') { g.state = 'patrol'; this.ghostPickTarget(g); }
        return;
      }
      if (!g.target) { this.ghostPickTarget(g); return; }
      const before = gp.x + gp.z * 1000;
      const moving = this.ghostWalk(g, dt, g.target.x, g.target.z, P.patrol, 1.2);
      g.stuck = (Math.abs(gp.x + gp.z * 1000 - before) < 1e-6) ? g.stuck + dt : 0;
      if (g.stuck > 2.5) { g.stuck = 0; this.ghostPickTarget(g); return; }
      if (!moving || Math.hypot(g.target.x - gp.x, g.target.z - gp.z) < 0.7) {
        g.dwell = 2.0 + Math.random() * 2.0;
        g.state = 'patrol';
        this.ghostPickTarget(g);
      }
    },

    // a killer got hold of the avatar. The Guardian Shield stops the hit. Otherwise it costs half the health, the killer is thrown back and
    // stunned for a moment (you can run); with no health left comes the possession.
    caught(g, how) {
      const lex = this.lexus || (this.warehouseState && this.warehouseState.lexus);
      const away = new THREE.Vector3().subVectors(g.mesh.position, lex.root.position).setY(0);
      if (away.lengthSq() < 1e-4) away.set(1, 0, 0);
      away.normalize();
      if (this.fx.shield) {
        this.fx.shield = false;
        this.shove(g, away.x, away.z, 3, 4);
        AudioEngine.playHide();
        if (window.lexusSay) window.lexusSay("🛡️ The Guardian Shield broke, but it saved us! RUN!", "surprised");
        this.renderHud();
        return;
      }
      if (this.fx.disguise > 0) this.endTransform();
      this.hp -= 50;
      if (this.hp <= 0) { this.hp = 0; this.renderHud(); this.triggerPossessionExplosion(how); return; }
      this.shove(g, away.x, away.z, 4, 3);
      if (this.hiding) this.exitHide();
      AudioEngine.playExplosion();
      if (window.lexusSay) window.lexusSay('💔 It got us! Health at <b>' + this.hp + '</b>. RUN, or use a Health Potion (keys 1-3)!', 'surprised');
      this.renderHud();
    },

    // -------------------------------------------------------------------------
    // 7. SEARCHING FURNITURE, HIDING, DOORS, STAIRS AND THE EXIT
    // -------------------------------------------------------------------------
    handleKey(key, down) {
      if (!this.active) return;
      if (key === 'f' && down && !this.hiding) this.toggleFlashlight();
      if (key === 'e' && down) this.interactAsylum();
      if (key === 'r' && down) this.searchNearby();
      if (key === 'escape' && down) {
        if (this.hiding) this.exitHide();
        else this.returnToWarehouse('User exited');
      }
    },

    // what is within reach right now (the nearest first)
    nearestThing(p) {
      const w = this.world, F = w.floors[this.floor];
      let best = null;
      const take = (type, obj, dist, text) => { if (!best || dist < best.dist) best = { type, obj, dist, text }; };
      if (w.exit.floor === this.floor) {
        const d = Math.hypot(w.exit.x - p.x, w.exit.z - p.z);
        if (d < 2.2) take('exit', w.exit, d - 0.3, this.keysFound >= this.keysNeeded ? '[E] Open the EXIT and escape!' : 'EXIT: needs ' + this.keysNeeded + ' keys (' + this.keysFound + ' found)');
      }
      F.doors.forEach((d) => {
        if (!d.locked) return;
        const dist = Math.hypot(d.cx - p.x, d.cz - p.z);
        if (dist > 1.9) return;
        take('door', d, dist, this.doorKeys[d.keyId] ? '[E] Unlock the door with ' + d.keyLabel : 'Locked: find ' + d.keyLabel + ' (hidden in some furniture)');
      });
      const seen = new Set();
      F.furniture.forEach((it) => {
        if ((!it.hide && !it.searchLabel) || seen.has(it)) return;
        const d = Math.hypot(it.anchor[0] - p.x, it.anchor[1] - p.z);
        if (d > 1.6) return;
        const parts = [];
        if (it.hide) parts.push('[E] Hide ' + (it.type === 'bed' ? it.hide : 'in ' + it.hide));
        if (it.searchLabel) parts.push(it.searched ? '(searched)' : '[R] Search ' + it.searchLabel);
        take('furniture', it, d + 0.4, parts.join('   '));
      });
      return best;
    },

    searchNearby() {
      if (this.hiding || this.inTransition) return;
      const lex = this.lexus || (this.warehouseState && this.warehouseState.lexus);
      if (!lex || !lex.root) return;
      const p = lex.root.position;
      const F = this.world.floors[this.floor];
      let best = null, bd = 1.7;
      F.searchSpots.forEach((it) => { const d = Math.hypot(it.anchor[0] - p.x, it.anchor[1] - p.z); if (d < bd && !it.searched) { best = it; bd = d; } });
      if (!best) return;
      best.searched = true;
      const c = best.contents;
      if (c && c.kind === 'key') {
        this.keysFound++;
        AudioEngine.playKeyPickup();
        if (window.lexusSay) window.lexusSay('🗝️ A key was hidden in ' + best.searchLabel + '! <b>' + this.keysFound + ' of ' + this.keysNeeded + '</b>.', 'happy');
      } else if (c && c.kind === 'door') {
        this.doorKeys[c.door.keyId] = true;
        AudioEngine.playKeyPickup();
        if (window.lexusSay) window.lexusSay('🗝️ You found ' + c.door.keyLabel + ' in ' + best.searchLabel + '. It opens a locked door.', 'happy');
      } else {
        AudioEngine.playHide();
        if (window.lexusSay) window.lexusSay('Nothing in ' + best.searchLabel + ' but dust.', 'curious');
      }
      this.renderHud();
    },

    interactAsylum() {
      if (this.hiding) { this.exitHide(); return; }
      if (this.inTransition) return;
      const lex = this.lexus || (this.warehouseState && this.warehouseState.lexus);
      if (!lex || !lex.root) return;
      const near = this.nearestThing(lex.root.position);
      if (!near) return;
      if (near.type === 'door') {
        if (this.doorKeys[near.obj.keyId]) {
          this.world.floors[this.floor].unlockDoor(near.obj);
          this.world.floors[this.floor].openDoor(near.obj);
          AudioEngine.playDoorUnlock();
        } else AudioEngine.playFlashlightClick();
      } else if (near.type === 'furniture') {
        if (near.obj.hide) this.enterHide(near.obj);
      } else if (near.type === 'stairs') {
        this.takeStairs(near.obj);
      } else if (near.type === 'exit') {
        if (this.keysFound >= this.keysNeeded) {
          AudioEngine.playDoorUnlock();
          this.world.exit.panel.material.color.setHex(0xbbf7d0);
          this.triggerEscapeVictory();
        } else {
          AudioEngine.playFlashlightClick();
          if (window.lexusSay) window.lexusSay('The exit needs ' + this.keysNeeded + ' keys. We have ' + this.keysFound + '.', 'curious');
        }
      }
    },

    // a short fade, then the avatar is on the other floor at the paired staircase
    takeStairs(st) {
      this.inTransition = true;
      AudioEngine.playDoorCreak();
      let fade = document.getElementById('wh-asylum-fade');
      if (!fade) {
        fade = document.createElement('div');
        fade.id = 'wh-asylum-fade';
        fade.style.cssText = 'position:absolute;inset:0;background:#000;opacity:0;transition:opacity .35s;z-index:40;pointer-events:none';
        const wrap = document.getElementById('wh-canvas-wrap');
        (wrap || document.body).appendChild(fade);
      }
      fade.style.opacity = '1';
      setTimeout(() => {
        if (!this.active) return;
        const to = st.pair;
        const lex = this.lexus || (this.warehouseState && this.warehouseState.lexus);
        this.showFloor(to.floor);
        lex.root.position.set(to.x, 0, to.z);
        lex.currentYaw = Math.atan2(to.out[0], to.out[1]);          // facing away from the stairs
        lex.root.rotation.y = lex.currentYaw;
        if (this.controls) this.controls.target.set(to.x, 1.4, to.z);
        if (this.camera) this.camera.position.set(to.x - to.out[0] * 3.2, 2.4, to.z - to.out[1] * 3.2);
        this.startFloorCountdown(to.floor);
        // the boss may be waiting on the stairs
        this.inTransition = false;
        fade.style.opacity = '0';
        if (window.lexusSay) window.lexusSay('Now on the <b>' + this.world.STYLE[to.floor].name + '</b>. ' + (to.floor >= 3 ? 'It is darker and the killers are faster up here.' : ''), 'curious');
        this.renderHud();
      }, 380);
    },

    enterHide(it) {
      const lex = this.lexus || (this.warehouseState && this.warehouseState.lexus);
      if (!lex || !lex.root || this.hiding) return;
      if (this.fx.disguise > 0) this.endTransform();
      this.hiding = it;
      this.hideFlash = this.flashlightOn;
      lex.root.visible = false;
      lex.root.position.set(it.x, 0, it.z);
      AudioEngine.playHide();
      const ov = document.getElementById('wh-hide-overlay');
      if (ov) ov.classList.remove('hidden');
      // a killer that is chasing and sees the avatar go in knows where to look
      this.ghosts.forEach((g) => {
        if (g.floor !== this.floor || g.state !== 'chase') return;
        const gp = g.mesh.position;
        if (this.world.floors[this.floor].hasLineOfSight(gp.x, gp.z, it.x, it.z) && Math.hypot(gp.x - it.x, gp.z - it.z) < g.params.see + 2) g.knowsHide = it;
      });
    },

    exitHide() {
      const lex = this.lexus || (this.warehouseState && this.warehouseState.lexus);
      const it = this.hiding;
      if (!it || !lex || !lex.root) return;
      this.hiding = null;
      lex.root.visible = true;
      lex.root.position.set(it.anchor[0], 0, it.anchor[1]);
      lex.currentYaw = Math.atan2(it.front[0], it.front[1]);
      lex.root.rotation.y = lex.currentYaw;
      const ov = document.getElementById('wh-hide-overlay');
      if (ov) ov.classList.add('hidden');
      if (this.controls) this.controls.target.set(it.anchor[0], 1.4, it.anchor[1]);
      if (this.camera) this.camera.position.set(it.anchor[0] - it.front[0] * 2.2, 2.2, it.anchor[1] - it.front[1] * 2.2);
      AudioEngine.playHide();
      this.setPrompt('');
    },

    // the view from inside the hiding place: low, looking out through a slit
    hideStep() {
      const s = this.hiding;
      if (!s || !this.camera || !this.controls) return;
      const y = s.low ? 0.55 : 1.45;
      this.camera.position.set(s.x + s.front[0] * 0.1, y, s.z + s.front[1] * 0.1);
      this.controls.target.set(s.x + s.front[0] * 4, y - 0.05, s.z + s.front[1] * 4);
    },

    setPrompt(text) {
      const el = document.getElementById('wh-asylum-prompt');
      if (!el) return;
      const base = '[W A S D] Move &bull; [Shift] Run &bull; [Space] Jump &bull; [E] Use / Hide &bull; [R] Search &bull; [F] Flashlight &bull; [Esc] Leave';
      if (this._prompt === text) return;
      this._prompt = text;
      el.innerHTML = text ? '<b>' + text + '</b>' : base;
    },

    setStatus(text) {
      const tag = document.getElementById('wh-asylum-status');
      if (tag) tag.textContent = text;
    },

    // the top bar: keys, floor, keys for doors, power-ups that are running
    renderHud() {
      const bar = document.getElementById('wh-asylum-keys-bar');
      if (!bar || !this.world) return;
      const chips = [];
      chips.push('<div class="wh-asylum-key' + (this.keysFound >= this.keysNeeded ? ' found' : '') + '">🗝️ Keys: <span>' + this.keysFound + ' / ' + this.keysNeeded + '</span></div>');
      chips.push('<div class="wh-asylum-key">🏢 <span>' + this.world.STYLE[this.floor].name + ' (' + (this.floor + 1) + '/5)</span></div>');
      chips.push('<div class="wh-asylum-key' + (this.hp > 50 ? ' found' : '') + '">❤️ Health: <span>' + this.hp + '</span></div>');
      const dk = Object.keys(this.doorKeys).length;
      if (this.lockedDoors && this.lockedDoors.length) chips.push('<div class="wh-asylum-key' + (dk ? ' found' : '') + '">🚪 Door keys: <span>' + dk + '</span></div>');
      for (let i = 0; i < MAX_ITEMS; i++) {
        const it = this.inv[i];
        chips.push('<div class="wh-asylum-key' + (it ? ' found' : '') + '">[' + (i + 1) + '] ' + (it ? it.def.name + '<span> x' + it.uses + '</span>' : '<span>empty</span>') + '</div>');
      }
      if (this.fx.speed > 0) chips.push('<div class="wh-asylum-key found">⚡ <span>' + Math.ceil(this.fx.speed) + 's</span></div>');
      if (this.fx.cloak > 0) chips.push('<div class="wh-asylum-key found">🫥 <span>' + Math.ceil(this.fx.cloak) + 's</span></div>');
      if (this.fx.battery > 0) chips.push('<div class="wh-asylum-key found">🔦 <span>' + Math.ceil(this.fx.battery) + 's</span></div>');
      if (this.fx.disguise > 0) chips.push('<div class="wh-asylum-key found">🪑 <span>' + Math.ceil(this.fx.disguise) + 's</span></div>');
      if (this.fx.shield) chips.push('<div class="wh-asylum-key found">🛡️ <span>Shield</span></div>');
      if (this.fx.radar > 0) chips.push('<div class="wh-asylum-key found">📡 <span>' + this.radarText() + '</span></div>');
      bar.innerHTML = chips.join('');
    },

    // where the nearest hidden key is (the Key Radar)
    radarText() {
      const lex = this.lexus || (this.warehouseState && this.warehouseState.lexus);
      if (!lex || !lex.root || !this.containers) return '';
      let best = null, bd = 1e9;
      this.containers.forEach((c) => {
        if (c.searched || !c.contents) return;
        const d = Math.hypot(c.x - lex.root.position.x, c.z - lex.root.position.z) + Math.abs(c.floor - this.floor) * 40;
        if (d < bd) { bd = d; best = c; }
      });
      if (!best) return 'no more keys';
      const dx = best.x - lex.root.position.x, dz = best.z - lex.root.position.z;
      const dirs = ['east', 'south-east', 'south', 'south-west', 'west', 'north-west', 'north', 'north-east'];
      const a = Math.round(((Math.atan2(dz, dx) * 180 / Math.PI) + 360) % 360 / 45) % 8;
      const where = best.floor === this.floor ? Math.round(Math.hypot(dx, dz)) + ' m ' + dirs[a] : 'floor ' + (best.floor + 1);
      return where + ' (' + best.searchLabel + ')';
    },

    // -------------------------------------------------------------------------
    // 8. TICK UPDATE LOOP
    // -------------------------------------------------------------------------
    update(delta, time) {
      if (!this.active || !this.world) return;
      const lex = this.lexus || (this.warehouseState && this.warehouseState.lexus);
      if (!lex || !lex.root) return;
      const p = lex.root.position;
      const F = this.world.floors[this.floor];
      if (this.inTransition && !this.hiding) { F.update(delta); return; }
      this.elapsed += delta;
      F.update(delta);

      // walking all the way up (or down) a staircase: the other floor
      if (!this.hiding && !this.inTransition) {
        const gi = this.groundAt(p.x, p.z);
        if (gi.on && gi.t >= 0.9 && gi.layout.st && gi.layout.st.pair) this.takeStairs(gi.layout.st);
      }

      // an unlocked door opens when the avatar walks up to it
      if (!this.hiding) {
        F.doors.forEach((d) => {
          if (!d.locked && d.target === 0 && Math.hypot(d.cx - p.x, d.cz - p.z) < 1.2) { F.openDoor(d); AudioEngine.playDoorCreak(); }
        });
      }

      // timed effects running out
      ['speed', 'cloak', 'radar', 'battery'].forEach((k) => { if (this.fx[k] > 0) this.fx[k] = Math.max(0, this.fx[k] - delta); });
      if (this.fx.disguise > 0) {
        this.fx.disguise -= delta;
        if (this.disguiseMesh) { this.disguiseMesh.position.set(p.x, 0, p.z); this.disguiseMesh.rotation.y = lex.currentYaw || 0; }
        if (this.fx.disguise <= 0) { this.endTransform(); if (window.lexusSay) window.lexusSay('The disguise wore off.', 'curious'); }
      }
      // loose power-ups vanish every 10 seconds and show up somewhere else; guns stay where they are
      this.powerups.forEach((o) => {
        if (!o.fixed) { o.timer += delta; if (o.timer >= 10) this.relocatePower(o); }
        o.mesh.rotation.y += delta * 2;
        o.mesh.position.y = 0.9 + Math.sin(time * 3 + o.timer) * 0.1;
        if (o.floor === this.floor && !this.hiding && Math.hypot(o.mesh.position.x - p.x, o.mesh.position.z - p.z) < 1.1) this.tryPickup(o);
      });
      this.updateClones(delta, time);
      // missiles in flight
      this.projectiles = this.projectiles.filter((m) => {
        if (m.floor !== this.floor) { this.scene.remove(m.mesh); return false; }
        const step = 16 * delta;
        m.mesh.position.x += m.dx * step; m.mesh.position.z += m.dz * step; m.dist += step;
        const F2 = this.world.floors[this.floor];
        const hitGhost = this.ghosts.some(k => k.floor === this.floor && Math.hypot(k.mesh.position.x - m.mesh.position.x, k.mesh.position.z - m.mesh.position.z) < 1.0);
        if (F2.isBlocked(m.mesh.position.x, m.mesh.position.z, 0.15) || hitGhost || m.dist > 40) {
          const n = this.blast(m.mesh.position.x, m.mesh.position.z, this.floor, 5, 6, 6, 0xfb923c);
          if (window.lexusSay) window.lexusSay(n ? '💥 <b>BOOM!</b> ' + n + ' killer(s) blasted away and asleep for 6 seconds.' : '💥 <b>BOOM!</b> The missile hit the wall.', 'surprised');
          this.scene.remove(m.mesh);
          return false;
        }
        return true;
      });
      // beams and blasts fade away
      this.effects = this.effects.filter((e) => {
        e.life -= delta;
        const k = Math.max(0, e.life / e.max);
        if (e.mesh.userData.grow) e.mesh.scale.setScalar(0.3 + (1 - k) * e.mesh.userData.grow);
        e.mesh.traverse((o) => { if (o.material && o.material.transparent) o.material.opacity = Math.min(o.material.opacity, 0.95) * (0.2 + 0.8 * k); });
        if (e.life <= 0) { this.scene.remove(e.mesh); return false; }
        return true;
      });

      // flashlight
      if (this.flashlight && this.flashlightTarget) {
        const base = this.fx.battery > 0 ? 7.5 : 4.2;
        this.flashlight.distance = this.fx.battery > 0 ? 36 : 24;
        const fwdX = Math.sin(lex.currentYaw || 0), fwdZ = Math.cos(lex.currentYaw || 0);
        this.flashlight.position.set(p.x + fwdX * 0.4, p.y + 1.25, p.z + fwdZ * 0.4);
        this.flashlightTarget.position.set(p.x + fwdX * 5.0, p.y + 1.1, p.z + fwdZ * 5.0);
        if (!this.flashlightOn || this.hiding) this.flashlight.intensity = 0;
        else {
          const near = this.ghosts.some(g => g.floor === this.floor && g.mesh.position.distanceTo(p) < 8);
          this.flashlight.intensity = near ? base * (0.7 + 0.3 * Math.sin(time * 19 + Math.sin(time * 5) * 3)) : base;       // trembles when a killer is near
        }
      }
      (this.floorLamps[this.floor] || []).forEach((l) => {
        const ph = l.userData.phase;
        l.intensity = 0.85 + 0.07 * Math.sin(time * 1.7 + ph) + 0.03 * Math.sin(time * 5.3 + ph * 2);       // a gentle, slow flicker
      });

      this.updateBoss(delta);
      this.ghosts.forEach((g) => {
        if (g.floor !== this.floor) { g.mesh.visible = false; return; }
        g.mesh.visible = true;
        if (g.slide) {                                        // being thrown back by a beam or a blast
          g.slide.t += delta / g.slide.dur;
          const t = Math.min(1, g.slide.t), e = 1 - Math.pow(1 - t, 3);
          g.mesh.position.x = g.slide.x0 + (g.slide.x1 - g.slide.x0) * e;
          g.mesh.position.z = g.slide.z0 + (g.slide.z1 - g.slide.z0) * e;
          if (t >= 1) g.slide = null;
          return;
        }
        this.updateGhostAI(g, delta, time, p);
      });
      this.updateHeartbeat(delta, p);

      this.hudTimer -= delta;
      if (this.hudTimer <= 0) { this.hudTimer = 0.4; this.renderHud(); }
      if (this.hiding) this.setPrompt('Hidden. Stay quiet... [E] Come out');
      else { const n = this.nearestThing(p); this.setPrompt(n ? n.text : ''); }
    },

    updateHeartbeat(delta, playerPos) {
      const near = this.ghosts.filter(g => g.floor === this.floor);
      if (!near.length) { const bpmEl = document.getElementById('wh-asylum-bpm'); if (bpmEl) bpmEl.textContent = '70 BPM'; return; }
      let dist = 1e9, chasing = false;
      near.forEach((g) => { dist = Math.min(dist, playerPos.distanceTo(g.mesh.position)); if (g.state === 'chase') chasing = true; });
      const t = Math.max(0, Math.min(1, (20.0 - dist) / 18.0));
      this.currentBPM = Math.round(65 + Math.max(t, chasing ? 0.7 : 0) * 95);
      const bpmEl = document.getElementById('wh-asylum-bpm');
      if (bpmEl) bpmEl.textContent = this.currentBPM + ' BPM';
      const vig = document.getElementById('wh-asylum-vignette');
      if (vig) vig.classList.toggle('danger', t > 0.45 || chasing);
      this.heartbeatTimer += delta;
      if (this.heartbeatTimer >= 60 / this.currentBPM) { this.heartbeatTimer = 0; AudioEngine.playHeartbeat(this.currentBPM); }
    },

    // -------------------------------------------------------------------------
    // 9. DEATH: POSSESSION & EXPLOSION
    // -------------------------------------------------------------------------
    triggerPossessionExplosion(how) {
      if (this.inTransition) return;
      this.inTransition = true;
      void how;
      AudioEngine.playExplosion();
      const lex = this.lexus || (this.warehouseState && this.warehouseState.lexus);
      const pos = (lex && lex.root) ? lex.root.position.clone() : new THREE.Vector3();
      if (this.hiding) { const ov = document.getElementById('wh-hide-overlay'); if (ov) ov.classList.add('hidden'); this.hiding = null; }
      this.ghosts.forEach(g => { g.mesh.visible = false; });
      if (lex && lex.root) lex.root.visible = false;
      const count = 250;
      const geo = new THREE.BufferGeometry();
      const positions = [], velocities = [];
      for (let i = 0; i < count; i++) {
        positions.push(pos.x, pos.y + 0.9, pos.z);
        const theta = Math.random() * Math.PI * 2, phi = (Math.random() - 0.5) * Math.PI, speed = 2.5 + Math.random() * 5.5;
        velocities.push(Math.cos(phi) * Math.cos(theta) * speed, Math.sin(phi) * speed + 2.0, Math.cos(phi) * Math.sin(theta) * speed);
      }
      geo.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
      const pMat = new THREE.PointsMaterial({ color: 0xef4444, size: 0.18, transparent: true, opacity: 0.95 });
      const pSystem = new THREE.Points(geo, pMat);
      this.scene.add(pSystem);
      const startTime = performance.now();
      const animExplode = () => {
        const elapsed = (performance.now() - startTime) / 1000;
        const arr = geo.attributes.position.array;
        for (let i = 0; i < count; i++) {
          const idx = i * 3;
          arr[idx] += velocities[idx] * 0.016; arr[idx + 1] += velocities[idx + 1] * 0.016; velocities[idx + 1] -= 9.8 * 0.016; arr[idx + 2] += velocities[idx + 2] * 0.016;
        }
        geo.attributes.position.needsUpdate = true;
        pMat.opacity = Math.max(0, 1.0 - elapsed / 1.8);
        if (elapsed < 1.8) requestAnimationFrame(animExplode); else this.scene.remove(pSystem);
      };
      animExplode();
      const deathEl = document.getElementById('wh-asylum-death');
      if (deathEl) deathEl.classList.remove('hidden');
      setTimeout(() => {
        if (deathEl) deathEl.classList.add('hidden');
        this.returnToWarehouse('Possessed by Phantom');
      }, 3000);
    },

    triggerEscapeVictory() {
      if (this.inTransition) return;
      this.inTransition = true;
      AudioEngine.playVictory();
      const escapeEl = document.getElementById('wh-asylum-escape');
      if (escapeEl) escapeEl.classList.remove('hidden');
      setTimeout(() => {
        if (escapeEl) escapeEl.classList.add('hidden');
        this.returnToWarehouse('Escaped Asylum Survivor');
      }, 3500);
    },

    // -------------------------------------------------------------------------
    // 10. BACK TO THE WAREHOUSE
    // -------------------------------------------------------------------------
    returnToWarehouse(reason) {
      this.clearGhostTimers();
      this.active = false;
      this.inTransition = false;
      this.isDoorOpen = false;
      this.hiding = null;
      const ov = document.getElementById('wh-hide-overlay');
      if (ov) ov.classList.add('hidden');
      const fade = document.getElementById('wh-asylum-fade');
      if (fade) fade.style.opacity = '0';
      const hud = document.getElementById('wh-asylum-hud');
      const vig = document.getElementById('wh-asylum-vignette');
      if (hud) hud.classList.add('hidden');
      if (vig) vig.classList.add('hidden');

      if (this.flashlight) { this.scene.remove(this.flashlight); this.flashlight = null; }
      if (this.flashlightTarget) { this.scene.remove(this.flashlightTarget); this.flashlightTarget = null; }
      this.ghosts.forEach(g => this.scene.remove(g.mesh));
      this.ghosts = [];
      this.boss = null;
      this.powerups = [];
      this.clones.forEach(c => this.scene.remove(c.mesh)); this.clones = [];
      this.projectiles.forEach(m => this.scene.remove(m.mesh)); this.projectiles = [];
      this.effects.forEach(e => this.scene.remove(e.mesh)); this.effects = [];
      if (this.disguiseMesh) { this.scene.remove(this.disguiseMesh); this.disguiseMesh = null; }
      this.fx.disguise = 0;
      this.inv = [null, null, null];
      this.hp = 100;
      if (this.asylumGroup) { this.scene.remove(this.asylumGroup); this.asylumGroup = null; }
      this.world = null;
      this.floorLamps = null;

      this.scene.traverse((obj) => {
        if (obj.userData && obj.userData._whPrevVisible !== undefined) obj.visible = obj.userData._whPrevVisible;
      });
      this.scene.background = new THREE.Color(0x0f172a);
      this.scene.fog = new THREE.FogExp2(0x0f172a, 0.011);
      (this._castShadowOff || []).forEach((o) => { o.castShadow = true; });
      this._castShadowOff = [];
      if (this.camera && this._camNear) { this.camera.near = this._camNear; this.camera.far = this._camFar; this.camera.updateProjectionMatrix(); }

      this.resetSecretDoor();
      this.setStatus('Locked Down');
      const lex = this.lexus || (this.warehouseState && this.warehouseState.lexus);
      if (lex && lex.root) {
        lex.root.visible = true;
        lex.root.position.set(this.SECRET.x + 1.0, 0.0, this.SECRET.z);
        lex.currentYaw = -Math.PI / 2;
        lex.root.rotation.y = lex.currentYaw;
      }
      if (this.controls) this.controls.target.set(this.SECRET.x + 1.0, 1.4, this.SECRET.z);
      if (this.camera) this.camera.position.set(this.SECRET.x + 4.2, 2.6, this.SECRET.z + 1.5);
      if (this.onReturnCallback) this.onReturnCallback(reason);
      if (window.lexusSay) {
        if (reason.includes('Survivor')) window.lexusSay("🏆 Incredible! We survived the dark asylum and escaped the killers!", "happy");
        else window.lexusSay("Phew! We are back safely in the QC Warehouse. That asylum was terrifying...", "curious");
      }
    }
  };

  window.AsylumGame = AsylumGame;
})();
