        /* All sidebar collapsible groups start collapsed on initial session, then remember user toggles across views */
        (function () {
          document.querySelectorAll('#sidebar .side-group').forEach(function (d) {
            var k = 'nx.side.grp.' + (d.dataset.grp || '');
            var saved = null;
            try { saved = sessionStorage.getItem(k); } catch (e) {}
            d.open = saved === '1';
            d.addEventListener('toggle', function () {
              try { sessionStorage.setItem(k, d.open ? '1' : '0'); } catch (e) {}
            });
          });
        })();

        /* Lab Documents & Other Documents: 5-visible limit per group, View More (+5), and real-time Search */
        (function () {
          var searchInput = document.getElementById('side-doc-search');
          var clearBtn = document.getElementById('side-doc-search-clear');
          var groups = document.querySelectorAll('#sidebar .side-group');
          if (!groups.length) return;

          var STEP = 5;
          var visibleLimits = {};
          var wasOpenBeforeSearch = {};
          var isSearching = false;

          function saveLimit(key, val) {
            try {
              localStorage.setItem('nx.side.limit.' + key, String(val));
              document.cookie = 'nx_side_limit_' + encodeURIComponent(key) + '=' + encodeURIComponent(val) + ';path=/;max-age=31536000;SameSite=Lax';
            } catch (e) {}
          }

          // Restore saved limit per group from localStorage / cookie (persists on document clicks & page reloads)
          groups.forEach(function (grp) {
            var key = grp.dataset.grp || 'default';
            var saved = null;
            try { saved = parseInt(localStorage.getItem('nx.side.limit.' + key), 10); } catch (e) {}
            if (!saved) {
              var m = document.cookie.match(new RegExp('(?:^|; )nx_side_limit_' + key + '=([0-9]+)'));
              if (m) saved = parseInt(m[1], 10);
            }
            var limit = (saved && saved >= STEP) ? saved : STEP;

            // If this group contains the active document link, ensure it remains visible
            var activeItem = grp.querySelector('.side-doc-item.active, a.side-doc-item[class*="active"]');
            if (activeItem) {
              var allItems = Array.from(grp.querySelectorAll('.side-doc-item'));
              var activeIdx = allItems.indexOf(activeItem);
              if (activeIdx !== -1 && activeIdx >= limit) {
                limit = Math.ceil((activeIdx + 1) / STEP) * STEP;
                saveLimit(key, limit);
              }
            }

            visibleLimits[key] = limit;
          });

          function updateGroup(grp, query) {
            var key = grp.dataset.grp || 'default';
            var items = grp.querySelectorAll('.side-doc-item');
            var moreWrap = grp.querySelector('.side-view-more-wrap');
            var moreText = grp.querySelector('.side-view-more-text');
            var moreChev = grp.querySelector('.side-view-more-chev');
            var moreBtn = grp.querySelector('.side-view-more-btn');
            var noMatch = grp.querySelector('.side-doc-no-match');
            var countSpan = grp.querySelector('.side-group-count');
            var total = items.length;

            if (query) {
              var matchCount = 0;
              items.forEach(function (it) {
                var text = (it.dataset.searchText || it.textContent || '').toLowerCase();
                var match = text.indexOf(query) !== -1;
                it.style.display = match ? '' : 'none';
                if (match) matchCount++;
              });

              if (moreWrap) moreWrap.style.display = 'none';
              if (noMatch) noMatch.style.display = matchCount === 0 ? '' : 'none';
              if (countSpan) countSpan.textContent = '(' + matchCount + (matchCount === 1 ? ' match' : ' matches') + ')';

              if (matchCount > 0) {
                grp.open = true;
              }
            } else {
              if (noMatch) noMatch.style.display = 'none';
              if (countSpan) countSpan.textContent = '(' + total + ')';

              var limit = visibleLimits[key] || STEP;
              items.forEach(function (it, idx) {
                it.style.display = (idx < limit) ? '' : 'none';
              });

              if (moreWrap) {
                if (total <= STEP) {
                  moreWrap.style.display = 'none';
                } else {
                  moreWrap.style.display = '';
                  var remaining = total - limit;
                  if (remaining > 0) {
                    var nextAdd = Math.min(STEP, remaining);
                    if (moreText) moreText.textContent = 'View more (+' + nextAdd + ' of ' + remaining + ' more)';
                    if (moreBtn) moreBtn.dataset.action = 'more';
                    if (moreChev) moreChev.style.transform = 'rotate(0deg)';
                  } else {
                    if (moreText) moreText.textContent = 'Show less';
                    if (moreBtn) moreBtn.dataset.action = 'less';
                    if (moreChev) moreChev.style.transform = 'rotate(180deg)';
                  }
                }
              }
            }
          }

          function renderAll() {
            var query = searchInput ? searchInput.value.trim().toLowerCase() : '';
            if (clearBtn) clearBtn.style.display = query ? 'inline-flex' : 'none';

            if (query && !isSearching) {
              groups.forEach(function (grp) {
                var key = grp.dataset.grp || 'default';
                wasOpenBeforeSearch[key] = grp.open;
              });
              isSearching = true;
            } else if (!query && isSearching) {
              groups.forEach(function (grp) {
                var key = grp.dataset.grp || 'default';
                if (!grp.dataset.here && typeof wasOpenBeforeSearch[key] !== 'undefined') {
                  grp.open = wasOpenBeforeSearch[key];
                }
              });
              isSearching = false;
            }

            groups.forEach(function (grp) {
              updateGroup(grp, query);
            });
          }

          document.querySelectorAll('#sidebar .side-view-more-btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
              e.preventDefault();
              var grpKey = btn.dataset.grpMore;
              var grp = document.querySelector('#sidebar .side-group[data-grp="' + grpKey + '"]');
              if (!grp) return;

              if (btn.dataset.action === 'less') {
                visibleLimits[grpKey] = STEP;
                saveLimit(grpKey, STEP);
              } else {
                visibleLimits[grpKey] = (visibleLimits[grpKey] || STEP) + STEP;
                saveLimit(grpKey, visibleLimits[grpKey]);
              }
              updateGroup(grp, searchInput ? searchInput.value.trim().toLowerCase() : '');
            });
          });

          if (searchInput) {
            searchInput.addEventListener('input', renderAll);
            searchInput.addEventListener('keydown', function (e) {
              if (e.key === 'Escape') {
                searchInput.value = '';
                renderAll();
                searchInput.blur();
              }
            });
          }

          if (clearBtn) {
            clearBtn.addEventListener('click', function () {
              if (searchInput) {
                searchInput.value = '';
                renderAll();
                searchInput.focus();
              }
            });
          }

          renderAll();
        })();
        
