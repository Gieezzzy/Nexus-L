                (function () {
                    var d = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
                    function paint(id) {
                        var el = document.getElementById(id);
                        var v = String((el && d.get.call(el)) || '').toUpperCase();
                        document.querySelectorAll('.vf-res-mark[data-for="' + id + '"]').forEach(function (td) {
                            var pass = td.getAttribute('data-side') === 'pass';
                            td.className = 'vf-res-mark';
                            td.textContent = '';
                            if (v === 'PASS' && pass) { td.textContent = '✓'; td.classList.add('is-pass'); }
                            else if (v.indexOf('TOL') === 0 && pass) { td.textContent = 'TOL'; td.classList.add('is-tol'); }
                            else if (v === 'FAIL' && !pass) { td.textContent = '✗'; td.classList.add('is-fail'); }
                        });
                    }
                    ['bp_UTF_Result', 'bp_FTO_Result', 'bp_FiTO_Result'].forEach(function (id) {
                        var el = document.getElementById(id);
                        if (!el) return;
                        Object.defineProperty(el, 'value', {
                            configurable: true,
                            get: function () { return d.get.call(this); },
                            set: function (v) { d.set.call(this, v); paint(id); }
                        });
                        paint(id);
                    });

                    var std = document.getElementById('bf_STD');
                    var stdRange = '';   // "FailMax - PassMin" of the style code (tbl_bonding_test_std), e.g. 1.99 - 2.50
                    function showStd() {
                        var n = parseFloat(std && std.value);
                        var txt = stdRange || (isNaN(n) ? '' : n.toFixed(2));
                        document.querySelectorAll('.vf-std-disp').forEach(function (b) { b.textContent = txt; });
                    }
                    if (std) {
                        var sd = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
                        Object.defineProperty(std, 'value', {
                            configurable: true,
                            get: function () { return sd.get.call(this); },
                            set: function (v) { sd.set.call(this, v); showStd(); }
                        });
                        std.addEventListener('change', showStd);
                        showStd();
                    }

                    // the style code of Model/Style decides the STD shown (UY CLASSIC SLIP-ON -> UY -> 1.99 - 2.50)
                    var styleEl = document.getElementById('bf_ModelStyle');
                    var lastStyle = null, stdTimer = null;
                    function loadStyleStd() {
                        var s = String((styleEl && Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').get.call(styleEl)) || '').trim();
                        if (s === lastStyle) return;
                        lastStyle = s;
                        clearTimeout(stdTimer);
                        if (s === '') { stdRange = ''; showStd(); return; }
                        stdTimer = setTimeout(function () {
                            var meta = document.querySelector('meta[name="base-url"]');
                            var base = meta ? meta.content.replace(/\/+$/, '') + '/' : location.origin + '/';
                            fetch(base + 'dtr/bonding-std?style=' + encodeURIComponent(s), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                                .then(function (r) { return r.json(); })
                                .then(function (j) { stdRange = (j && j.found) ? String(j.passMin) : ''; showStd(); })
                                .catch(function () { stdRange = ''; showStd(); });
                        }, 150);
                    }
                    if (styleEl) {
                        var vd = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
                        Object.defineProperty(styleEl, 'value', {
                            configurable: true,
                            get: function () { return vd.get.call(this); },
                            set: function (v) { vd.set.call(this, v); loadStyleStd(); }
                        });
                        styleEl.addEventListener('input', loadStyleStd);
                        styleEl.addEventListener('change', loadStyleStd);
                        loadStyleStd();
                    }
                })();
                
