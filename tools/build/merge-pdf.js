// node merge-pdf.js out.pdf in1.pdf in2.pdf ...  (joins PDFs page by page)
const fs = require('fs');
const { PDFDocument } = require('pdf-lib');
(async () => {
  const [out, ...ins] = process.argv.slice(2);
  const doc = await PDFDocument.create();
  for (const f of ins) {
    const src = await PDFDocument.load(fs.readFileSync(f));
    (await doc.copyPages(src, src.getPageIndices())).forEach((p) => doc.addPage(p));
  }
  fs.writeFileSync(out, await doc.save());
})().catch((e) => { console.error(e.message); process.exit(1); });
