/**
 * Prints one test report WITHOUT Access: the lab's blank report form (a PDF printed once from the Access report, see
 * tools/access-report-templates.ps1) plus the record's values written at the positions the Access report design gives
 * (tools/access-report-layout.ps1 -> JSON). Access expressions (IIf, Switch, Nz, &, Len, Trim) are evaluated here.
 *   node render-report.js <job.json>      job: { layout, template, record, out }
 */
const fs = require('fs');
const { PDFDocument, StandardFonts, rgb } = require('pdf-lib');
const fontkit = require('@pdf-lib/fontkit');
const path = require('path');

// ---------------------------------------------------------------------------------------------------- expressions
const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

const makeLookup = (record) => {
  const norm = (s) => String(s).toLowerCase().replace(/[\s_]+/g, '');
  const byNorm = {};
  for (const k of Object.keys(record)) byNorm[norm(k)] = record[k];
  return (name) => {
    let v = Object.prototype.hasOwnProperty.call(record, name) ? record[name] : byNorm[norm(name)];
    return v === undefined || v === null ? null : v;
  };
};

const tokenize = (src) => {
  const t = [];
  let i = 0;
  while (i < src.length) {
    const c = src[i];
    if (/\s/.test(c)) { i++; continue; }
    if (c === '"') {
      let j = i + 1, s = '';
      while (j < src.length) { if (src[j] === '"') { if (src[j + 1] === '"') { s += '"'; j += 2; continue; } break; } s += src[j++]; }
      t.push({ k: 'str', v: s }); i = j + 1; continue;
    }
    if (c === '[') { const j = src.indexOf(']', i); t.push({ k: 'field', v: src.slice(i + 1, j) }); i = j + 1; continue; }
    if (/[0-9.]/.test(c)) { let j = i; while (j < src.length && /[0-9.]/.test(src[j])) j++; t.push({ k: 'num', v: parseFloat(src.slice(i, j)) }); i = j; continue; }
    if (/[A-Za-z_]/.test(c)) { let j = i; while (j < src.length && /[A-Za-z0-9_.]/.test(src[j])) j++; t.push({ k: 'id', v: src.slice(i, j) }); i = j; continue; }
    const two = src.slice(i, i + 2);
    if (['<>', '<=', '>='].includes(two)) { t.push({ k: 'op', v: two }); i += 2; continue; }
    t.push({ k: 'op', v: c }); i++;
  }
  return t;
};

const evaluate = (src, lookup) => {
  const toks = tokenize(String(src).replace(/^=/, ''));
  let p = 0;
  const peek = () => toks[p];
  const isOp = (v) => peek() && peek().k === 'op' && peek().v === v;
  const isId = (v) => peek() && peek().k === 'id' && peek().v.toLowerCase() === v;
  const str = (v) => (v === null || v === undefined ? '' : String(v));
  const num = (v) => { const n = parseFloat(v); return Number.isNaN(n) ? 0 : n; };
  const truthy = (v) => v === true || (typeof v === 'number' && v !== 0) || (typeof v === 'string' && v.toLowerCase() === 'true');

  const parseOr = () => { let l = parseAnd(); while (isId('or')) { p++; const r = parseAnd(); l = truthy(l) || truthy(r); } return l; };
  const parseAnd = () => { let l = parseNot(); while (isId('and')) { p++; const r = parseNot(); l = truthy(l) && truthy(r); } return l; };
  const parseNot = () => { if (isId('not')) { p++; return !truthy(parseNot()); } return parseCmp(); };
  const parseCmp = () => {
    const l = parseConcat();
    const t = peek();
    if (t && t.k === 'op' && ['=', '<>', '<', '>', '<=', '>='].includes(t.v)) {
      p++;
      const r = parseConcat();
      const bothNum = l !== null && r !== null && l !== '' && r !== '' && !Number.isNaN(Number(l)) && !Number.isNaN(Number(r));
      const a = bothNum ? Number(l) : str(l).toLowerCase();
      const b = bothNum ? Number(r) : str(r).toLowerCase();
      switch (t.v) { case '=': return a === b; case '<>': return a !== b; case '<': return a < b; case '>': return a > b; case '<=': return a <= b; default: return a >= b; }
    }
    return l;
  };
  const parseConcat = () => { let l = parseAdd(); while (isOp('&')) { p++; l = str(l) + str(parseAdd()); } return l; };
  const parseAdd = () => {
    let l = parsePrimary();
    while (isOp('+') || isOp('-')) { const o = peek().v; p++; const r = parsePrimary(); l = o === '+' ? (typeof l === 'string' || typeof r === 'string' ? str(l) + str(r) : num(l) + num(r)) : num(l) - num(r); }
    return l;
  };
  const parseArgs = () => {
    const args = [];
    if (!isOp(')')) { do { if (isOp(',')) p++; args.push(parseOr()); } while (isOp(',')); }
    if (isOp(')')) p++;
    return args;
  };
  const parsePrimary = () => {
    const t = toks[p++];
    if (!t) return '';
    if (t.k === 'str') return t.v;
    if (t.k === 'num') return t.v;
    if (t.k === 'field') return lookup(t.v);
    if (t.k === 'op' && t.v === '(') { const v = parseOr(); if (isOp(')')) p++; return v; }
    if (t.k === 'op' && t.v === '-') return -num(parsePrimary());
    if (t.k === 'id') {
      const name = t.v.toLowerCase();
      if (name === 'true') return true;
      if (name === 'false') return false;
      if (name === 'null') return null;
      if (isOp('(')) {
        p++;
        // IIf and Switch evaluate lazily in Access, but every argument here is a pure expression, so eager is the same
        const a = parseArgs();
        switch (name) {
          case 'iif': return truthy(a[0]) ? a[1] : a[2];
          case 'switch': for (let i = 0; i + 1 < a.length; i += 2) if (truthy(a[i])) return a[i + 1]; return null;
          case 'nz': return a[0] === null || a[0] === undefined ? (a.length > 1 ? a[1] : '') : a[0];
          case 'len': return str(a[0]).length;
          case 'trim': return str(a[0]).trim();
          case 'ucase': return str(a[0]).toUpperCase();
          case 'lcase': return str(a[0]).toLowerCase();
          case 'left': return str(a[0]).slice(0, num(a[1]));
          case 'right': return str(a[0]).slice(-num(a[1]) || undefined);
          case 'isnull': return a[0] === null || a[0] === undefined;
          case 'round': return Math.round(num(a[0]) * 10 ** num(a[1] || 0)) / 10 ** num(a[1] || 0);
          case 'format': return a[0];
          default: return a[0];
        }
      }
      return '';
    }
    return '';
  };
  try { return parseOr(); } catch (e) { return ''; }
};

// ---------------------------------------------------------------------------------------------------- formatting
const pad2 = (n) => String(n).padStart(2, '0');
const asDate = (v) => {
  const m = /^(\d{4})-(\d{2})-(\d{2})(?: (\d{2}):(\d{2}):(\d{2}))?$/.exec(String(v));
  return m ? { y: +m[1], mo: +m[2], d: +m[3], timeless: !m[4] || (m[4] === '00' && m[5] === '00' && m[6] === '00') } : null;
};
const formatValue = (v, fmt) => {
  if (v === null || v === undefined) return '';
  if (typeof v === 'boolean') return v ? 'True' : 'False';
  const d = asDate(v);
  if (d) {
    const f = String(fmt || '').replace(/\\/g, '');
    const mn = MONTHS[d.mo - 1];
    switch (f) {
      case 'dd-mmmm-yyyy': return `${pad2(d.d)}-${mn}-${d.y}`;
      case 'dd-mmm-yy': return `${pad2(d.d)}-${mn.slice(0, 3)}-${String(d.y).slice(2)}`;
      case 'yyyy/mm/dd': return `${d.y}/${pad2(d.mo)}/${pad2(d.d)}`;
      case 'yyyy-mmm-dd': return `${d.y}-${mn.slice(0, 3)}-${pad2(d.d)}`;
      case 'yyyy-mmmm-dd': return `${d.y}-${mn}-${pad2(d.d)}`;
      default: return `${d.mo}/${d.d}/${d.y}`;                       // Access "General Date"
    }
  }
  const s = String(v);
  if (/^-?\d+\.\d+$/.test(s) && (!fmt || fmt === 'General Number')) return s.replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '');   // 5.000 -> 5
  return s;
};

// ---------------------------------------------------------------------------------------------------- drawing
const bgr = (c) => {
  const n = Number(c);
  if (!Number.isFinite(n) || n < 0) return rgb(0, 0, 0);
  return rgb((n & 255) / 255, ((n >> 8) & 255) / 255, ((n >> 16) & 255) / 255);
};

(async () => {
  const job = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
  const layout = JSON.parse(fs.readFileSync(job.layout, 'utf8').replace(/^﻿/, ''));
  const doc = await PDFDocument.load(fs.readFileSync(job.template));
  const page = doc.getPage(0);
  const H = page.getSize().height;
  const regular = await doc.embedFont(StandardFonts.Helvetica);
  const bold = await doc.embedFont(StandardFonts.HelveticaBold);
  // Chinese text (e.g. "Prod.Material 量产材料") needs a real font; without it those characters print as "?"
  let cjk = null;
  const cjkFile = path.join(__dirname, '..', '..', 'writable', 'cache', 'fonts', 'cjk.ttf');
  if (fs.existsSync(cjkFile)) { doc.registerFontkit(fontkit); cjk = await doc.embedFont(fs.readFileSync(cjkFile), { subset: true }); }
  const lookup = makeLookup(job.record);
  // The web "Refresh & Apply Smart Standards" writes 0 and a rating into tests nobody ran. A report must not print them:
  // zero is blank, and a rating (CTCDRY_R, HeatAgingRating, ...) prints only when a result field of its test holds a value.
  const normName = (n) => String(n).toLowerCase().replace(/[^a-z0-9]/g, '');
  const RATING = /(_?rating|_?rate|_r)$/i;
  const STD = /(_s|_std|std)$/i;
  const hasResult = (ratingField) => {
    const stem = normName(String(ratingField).replace(RATING, ''));
    if (stem.length < 3) return true;
    let known = false;
    for (const k of Object.keys(job.record)) {
      const nk = normName(k);
      if (!nk.startsWith(stem) || RATING.test(k) || STD.test(k)) continue;
      known = true;
      const v = job.record[k];
      if (v !== null && v !== undefined && String(v).trim() !== '' && Number(v) !== 0) return true;
    }
    return !known;                                  // no result field found for this stem: leave the rating as it is
  };
  const [ml, mt] = layout.margins || [0, 0, 0, 0];
  // Standard fonts hold Latin text only; anything else becomes "?" rather than failing the whole report
  const safe = (s) => String(s).replace(/[\r\t]+/g, ' ').replace(/[^\x20-\x7E\xA0-\xFF\n]/g, '?');
  const hasCjk = (s) => /[\u2E80-\u9FFF\uFF00-\uFFEF]/.test(s);

  for (const sec of layout.sections) {
    for (const c of sec.controls) {
      if (c.type !== 'text' || !c.visible || !c.controlSource) continue;
      const isExpr = String(c.controlSource).startsWith('=');
      let raw = isExpr ? evaluate(c.controlSource, lookup) : lookup(c.controlSource);
      const refs = isExpr ? [...String(c.controlSource).matchAll(/\[([^\]]+)\]/g)].map((m) => m[1]) : [c.controlSource];
      if (refs.length && refs.every((r) => RATING.test(r)) && !refs.some((r) => hasResult(r))) raw = '';
      if (!isExpr && /^-?0+(\.0+)?$/.test(String(raw ?? '').trim()) && !c.format) raw = '';
      if (!isExpr && String(raw).trim() === '0' && c.format === 'General Number') raw = '';
      const cjkText = cjk && hasCjk(String(raw ?? ''));
      const text = (cjkText ? String(formatValue(raw, c.format)).replace(/[\r\t]+/g, ' ') : safe(formatValue(raw, c.format))).trim();

      const x = (c.left + ml) / 20, w = c.width / 20, h = c.height / 20;
      const yTop = (c.top + mt) / 20;
      // an expression that glues fields together ("a / b") leaves its separator on the blank form: clear it first
      if (isExpr && /&\s*"/.test(c.controlSource)) {
        page.drawRectangle({ x: x + 1.2, y: H - yTop - h + 1.2, width: Math.max(0, w - 2.4), height: Math.max(0, h - 2.4), color: rgb(1, 1, 1) });
      }
      if (text === '') continue;
      let size = Number(c.fontSize) || 8;
      const verdict = size >= 18 && /^(PASS|FAIL|TOLERABLE)$/i.test(text);      // the big result word at the top of the form
      const font = cjkText ? cjk : Number(c.fontWeight) >= 600 || verdict ? bold : regular;
      let color = bgr(c.foreColor);
      if (verdict) color = /^FAIL$/i.test(text) ? rgb(0.75, 0.1, 0.1) : /^TOLERABLE$/i.test(text) ? rgb(0.85, 0.45, 0.05) : rgb(0.09, 0.64, 0.29);

      // wrap to the box like a text box does (words, then hard breaks for a word wider than the box)
      const maxW = Math.max(4, w - 3);
      const lines = [];
      const oneLine = h < size * 1.17 * 1.6;                 // a one-line box never wraps: the text is shrunk a little to fit instead
      if (oneLine) {
        const flat = text.replace(/\s*\n\s*/g, ' ');
        const wide = font.widthOfTextAtSize(flat, size);
        if (wide > maxW) size = Math.max(size * 0.72, size * maxW / wide);
        lines.push(flat);
      }
      for (const para of oneLine ? [] : text.split('\n')) {
        let cur = '';
        for (const word of para.split(/\s+/)) {
          const tryLine = cur ? cur + ' ' + word : word;
          if (font.widthOfTextAtSize(tryLine, size) <= maxW || !cur) {
            if (!cur && font.widthOfTextAtSize(word, size) > maxW) {
              let part = '';
              for (const ch of word) { if (font.widthOfTextAtSize(part + ch, size) > maxW && part) { lines.push(part); part = ch; } else part += ch; }
              cur = part;
            } else cur = tryLine;
          } else { lines.push(cur); cur = word; }
        }
        lines.push(cur);
      }
      const lineH = size * 1.17;
      const maxLines = Math.max(1, Math.floor((h + 1) / lineH));
      const shown = lines.slice(0, maxLines);
      const block = shown.length * lineH;
      const align = Number(c.textAlign) || 0;
      // Access prints a text box's text from its top edge; one line in a taller box is centred like the lab's reports show
      let y = H - yTop - 1.5 - size * 0.92;
      if (shown.length === 1 && h > lineH * 1.6) y = H - yTop - (h - block) / 2 - size * 0.92;
      for (const ln of shown) {
        const tw = font.widthOfTextAtSize(ln, size);
        const lx = align === 2 ? x + (w - tw) / 2 : align === 3 ? x + w - tw - 1.5 : x + 1.5;
        page.drawText(ln, { x: lx, y, size, font, color });
        y -= lineH;
      }
    }
  }
  if (job.title) doc.setTitle(job.title);
  fs.writeFileSync(job.out, await doc.save());
  console.log('OK');
})().catch((e) => { console.error(e.message); process.exit(1); });
