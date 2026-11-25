/**
 * Puts the approver's signature and date stamp on page 1 of a report PDF.
 *   node stamp-pdf.js <in.pdf> <out.pdf> <stamp.json>
 * stamp.json: { "png": "<base64 PNG>", "name": "...", "title": "...", "date": "2026-10-28 10:15", "kind": "access" | "web" }
 * pos (optional) = where the report prints "Reviewed by" / "Approved by" (from tools/pdf_signoff.py): the signature goes beside
 * those lines and the date stamp under them; without it the fixed areas below are used.
 * "access" = official Access report (signature beside "Approved by 核准", bottom right);
 * "web"    = NEXUS-L bonding sheet (signature box above the "Approved by" line, bottom right).
 */
const fs = require('fs');
const { PDFDocument, StandardFonts, rgb } = require('pdf-lib');

(async () => {
  const [, , inPdf, outPdf, stampFile] = process.argv;
  const s = JSON.parse(fs.readFileSync(stampFile, 'utf8'));
  const doc = await PDFDocument.load(fs.readFileSync(inPdf));
  const page = doc.getPage(Math.min(doc.getPageCount() - 1, Math.max(0, (s.pos && s.pos.page) || 0)));
  const { width, height } = page.getSize();
  const font = await doc.embedFont(StandardFonts.HelveticaBold);
  const small = await doc.embedFont(StandardFonts.Helvetica);
  const blue = rgb(0.11, 0.25, 0.69);

  const stampBox = (x, y, w, h, l1, l2) => {                 // a rubber-stamp frame: outline and text only, nothing filled
    page.drawRectangle({ x, y, width: w, height: h, borderColor: blue, borderWidth: 0.7, opacity: 0, borderOpacity: 0.9 });
    page.drawText(l1, { x: x + 3, y: y + h - 8, size: 5.6, font, color: blue });
    page.drawText(l2, { x: x + 3, y: y + h - 15, size: 5.2, font: small, color: blue });
  };
  const line1 = `APPROVED: ${s.name}${s.title ? ' (' + s.title + ')' : ''}`;
  const line2 = `Signed ${s.date}`;
  const ap = s.pos && s.pos.approved && s.pos.approved.label ? s.pos.approved : null;

  if (ap) {
    // beside the printed "Reviewed by / Approved by" lines: signature to the left of them, stamp right under them
    const H = s.pos.h;
    const rv = s.pos.reviewed && s.pos.reviewed.label ? s.pos.reviewed : null;
    const top = Math.min(ap.label[1], rv ? rv.label[1] : ap.label[1]);          // top-origin coordinates
    const bottom = Math.max(ap.label[3], rv ? rv.label[3] : ap.label[3]);
    // the signature is laid over the printed name(s) on the Reviewed by / Approved by lines (transparent, so the name stays readable)
    // a SMALL copy of the signature on each printed name that is the signer's (transparent, so the name stays readable)
    // Reviewed by / Approved by must carry the signer's name: empty -> written in, different -> replaced
    const signerName = String(s.name || '').trim();
    const same = (a, b) => { const n = (x) => String(x).toLowerCase().replace(/[^a-z]+/g, ' ').trim().split(' ').filter(Boolean); const p = n(a), f = n(b); return p.length > 0 && p.every((t) => f.some((u) => u === t || (t.length === 1 && u[0] === t))) || p.join('') === f.map((u) => u[0]).join(''); };
    const fillName = (row) => {
      if (!row || !row.label || !signerName) return;
      if (row.name && same(row.name, signerName)) return;
      const lh = row.label[3] - row.label[1];
      const size = Math.max(7, Math.min(10, lh * 0.9));
      let x;
      if (row.name_rect) {                                       // a different name: whiten it, then write the signer's
        const r = row.name_rect;
        page.drawRectangle({ x: r[0] - 1, y: H - r[3] - 1.5, width: Math.max(r[2] - r[0], 60) + 2, height: r[3] - r[1] + 3, color: rgb(1, 1, 1), borderWidth: 0 });
        x = r[0];
      } else {
        x = (row.row_end || row.label[2]) + 4;
      }
      page.drawText(signerName.toUpperCase(), { x, y: H - row.label[3] + 1.5, size, font, color: rgb(0, 0, 0) });
      const w = font.widthOfTextAtSize(signerName.toUpperCase(), size);
      row.name_rect = [x, row.label[1], x + w, row.label[3]];    // the signature goes over the new name
    };
    fillName(ap);
    fillName(rv);
    if (s.png) {
      const img = await doc.embedPng(Buffer.from(s.png, 'base64'));
      const place = (row) => {
        const r = row && row.name_rect;
        if (!r) return;
        const nameW = r[2] - r[0], nameH = r[3] - r[1];
        const w = Math.min(Math.max(nameW * 0.9, 44), 84), h = Math.max(nameH * 2.2, 20);     // readable, centred on the name
        const k = Math.min(w / img.width, h / img.height);
        const iw = img.width * k, ih = img.height * k;
        const cx = (r[0] + r[2]) / 2, cy = (r[1] + r[3]) / 2;                            // centre of the name (top-origin y)
        page.drawImage(img, { x: Math.min(width - 4 - iw, Math.max(4, cx - iw / 2)), y: H - cy - ih / 2, width: iw, height: ih });
      };
      place(ap);
      if (rv && s.pos.reviewed_is_signer) place(rv);
      if (!ap.name_rect) {
        const bx = Math.max(12, ap.label[0] - 88), bw = ap.label[0] - 4 - bx, bh = (bottom - top) + 14, by = H - bottom - 5;
        const k = Math.min(bw / img.width, bh / img.height);
        page.drawImage(img, { x: bx + (bw - img.width * k) / 2, y: by + (bh - img.height * k) / 2, width: img.width * k, height: img.height * k });
      }
    }
    const sx = ap.label[0], sw = width - 6 - sx, sh = 19, sy = H - bottom - 15 - sh;
    stampBox(sx, Math.max(3, sy), sw, sh, line1, line2);
  } else {
    // no printed sign-off lines found: fixed areas measured on the A4 reports (origin bottom-left)
    const box = s.kind === 'web'
      ? { x: 0.80, y: 0.105, w: 0.17, h: 0.045 }
      : { x: 0.50, y: 0.012, w: 0.15, h: 0.036 };
    const bx = box.x * width, by = box.y * height, bw = box.w * width, bh = box.h * height;
    if (s.png) {
      const img = await doc.embedPng(Buffer.from(s.png, 'base64'));
      const k = Math.min(bw / img.width, bh / img.height);
      page.drawImage(img, { x: bx + (bw - img.width * k) / 2, y: by + (bh - img.height * k) / 2, width: img.width * k, height: img.height * k });
    }
    const sw2 = Math.max(font.widthOfTextAtSize(line1, 5.6), small.widthOfTextAtSize(line2, 5.2)) + 6;
    const fx = s.kind === 'web' ? bx - 0.02 * width : bx - sw2 - 4;
    const fy = s.kind === 'web' ? by - 18 : by + bh / 2 - 8;
    stampBox(fx, fy, sw2, 19, line1, line2);
  }

  doc.setSubject(`Approved by ${s.name} on ${s.date}`);
  fs.writeFileSync(outPdf, await doc.save());
  console.log('OK');
})().catch((e) => { console.error(e.message); process.exit(1); });
