/**
 * Minifies every script and stylesheet of the web app into public/assets/min/{js,css}.
 * The layout serves the minified copy automatically when it exists and is newer than the source
 * (see app/Helpers/nx_helper.php), so a forgotten build never serves stale files.
 *
 *   cd tools/build && npm install && node build.js
 */
const fs = require('fs');
const path = require('path');
const esbuild = require('esbuild');

const ROOT = path.resolve(__dirname, '..', '..', 'public', 'assets');
let before = 0;
let after = 0;

(async () => {
  for (const kind of ['js', 'css']) {
    const src = path.join(ROOT, kind);
    const out = path.join(ROOT, 'min', kind);
    fs.mkdirSync(out, { recursive: true });
    for (const f of fs.readdirSync(src)) {
      if (!f.endsWith('.' + kind) || f.endsWith('.min.' + kind)) continue;
      const from = path.join(src, f);
      const to = path.join(out, f);
      try {
        const res = await esbuild.build({ entryPoints: [from], outfile: to, minify: true, bundle: false, legalComments: 'none', charset: 'utf8', target: ['chrome90', 'edge90', 'firefox90', 'safari14'], logLevel: 'silent' });
        void res;
        const a = fs.statSync(from).size;
        const b = fs.statSync(to).size;
        before += a; after += b;
        console.log(`${kind}/${f}: ${a} -> ${b} (${Math.round((1 - b / a) * 100)}% smaller)`);
      } catch (e) {
        console.error(`${kind}/${f}: skipped (${String(e.message).split('\n')[0]})`);
        try { fs.unlinkSync(to); } catch (x) { /* none */ }
      }
    }
  }
  console.log(`total ${before} -> ${after} bytes`);
})();
