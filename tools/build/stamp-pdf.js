/**
 * Puts the approver's signature and date stamp on page 1 of a report PDF.
 *   node stamp-pdf.js <in.pdf> <out.pdf> <stamp.json>
 * stamp.json: { "png": "<base64 PNG>", "name": "...", "title": "...", "date": "2026-10-28 10:15", "kind": "access" | "web" }
 * "access" = official Access report (signature beside "Approved by 核准", bottom right);
 * "web"    = NEXUS-L bonding sheet (signature box above the "Approved by" line, bottom right).
 */
const fs = require('fs');
const { PDFDocument, StandardFonts, rgb } = require('pdf-lib');

(async () => {
  const [, , inPdf, outPdf, stampFile] = process.argv;
  const s = JSON.parse(fs.readFileSync(stampFile, 'utf8'));
  const doc = await PDFDocument.load(fs.readFileSync(inPdf));
  const page = doc.getPage(0);
  const { width, height } = page.getSize();
  const font = await doc.embedFont(StandardFonts.HelveticaBold);
  const small = await doc.embedFont(StandardFonts.Helvetica);
  const blue = rgb(0.11, 0.25, 0.69);

  // signature area, in page fractions measured on the A4 reports (origin bottom-left)
  const box = s.kind === 'web'
    ? { x: 0.80, y: 0.105, w: 0.17, h: 0.045 }
    : { x: 0.50, y: 0.012, w: 0.15, h: 0.036 };
  const bx = box.x * width;
  const by = box.y * height;
  const bw = box.w * width;
  const bh = box.h * height;

  if (s.png) {
    const img = await doc.embedPng(Buffer.from(s.png, 'base64'));
    const k = Math.min(bw / img.width, bh / img.height);
    page.drawImage(img, { x: bx + (bw - img.width * k) / 2, y: by + (bh - img.height * k) / 2, width: img.width * k, height: img.height * k });
  }
  // date stamp under / beside the signature
  const line1 = `Approved: ${s.name}${s.title ? ' (' + s.title + ')' : ''}`;
  const line2 = `Signed ${s.date}`;
  const w1 = font.widthOfTextAtSize(line1, 5.6);
  const fx = s.kind === 'web' ? bx - 0.02 * width : bx - Math.max(w1, small.widthOfTextAtSize(line2, 5.6)) - 4;
  const fy = s.kind === 'web' ? by - 9 : by + bh / 2;
  page.drawText(line1, { x: fx, y: fy, size: 5.6, font, color: blue });
  page.drawText(line2, { x: fx, y: fy - 7, size: 5.6, font: small, color: blue });

  doc.setSubject(`Approved by ${s.name} on ${s.date}`);
  fs.writeFileSync(outPdf, await doc.save());
  console.log('OK');
})().catch((e) => { console.error(e.message); process.exit(1); });
