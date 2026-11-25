(function () {
  'use strict';
  var grid = document.getElementById('cp-grid');
  var lb = document.getElementById('cp-lb');
  if (lb) {
    lb.onclick = function () { lb.className = 'lb'; };
  }
  if (!grid) return;

  var BASE = grid.getAttribute('data-base') || '';

  function hasCls(el, cls) {
    if (!el || !el.className) return false;
    return (' ' + el.className + ' ').indexOf(' ' + cls + ' ') !== -1;
  }
  function closestCls(el, cls) {
    while (el && el !== document) {
      if (hasCls(el, cls)) return el;
      el = el.parentNode;
    }
    return null;
  }
  function closestTag(el, tag) {
    tag = tag.toUpperCase();
    while (el && el !== document) {
      if (el.tagName && el.tagName.toUpperCase() === tag) return el;
      el = el.parentNode;
    }
    return null;
  }
  function removeNode(node) {
    if (node && node.parentNode) node.parentNode.removeChild(node);
  }
  function xhrPost(url, body, isJson, cb) {
    var x = new XMLHttpRequest();
    x.open('POST', url, true);
    if (isJson) x.setRequestHeader('Content-Type', 'application/json');
    x.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    x.onreadystatechange = function () {
      if (x.readyState !== 4) return;
      var j = {};
      try { j = JSON.parse(x.responseText || '{}'); } catch (e) { j = {}; }
      if (x.status >= 200 && x.status < 300 && j && j.ok) {
        cb(null, j);
      } else {
        cb(new Error((j && j.message) ? j.message : ('Request failed (' + x.status + ')')));
      }
    };
    x.onerror = function () { cb(new Error('Network error. Please try again.')); };
    x.send(isJson ? JSON.stringify(body) : body);
  }
  function updateProg() {
    var all = grid.querySelectorAll('.card').length;
    var done = grid.querySelectorAll('.card.locked').length;
    var el = document.getElementById('cp-prog');
    if (el) el.textContent = done + ' / ' + all + ' Submitted';
  }
  function syncPhotoState(card) {
    var n = card.querySelectorAll('.thumb').length;
    var cnt = card.querySelector('.p-count');
    if (cnt) cnt.textContent = 'Photos (' + n + ' / 3)';
    var full = n >= 3 || card.getAttribute('data-locked') === '1';
    var btns = card.querySelectorAll('.btn-cam, .btn-up');
    for (var i = 0; i < btns.length; i++) btns[i].disabled = full;
  }
  function say(card, txt, ok) {
    var m = card.querySelector('.msg');
    if (m) {
      m.textContent = txt;
      m.className = 'msg ' + (ok ? 'ok' : 'bad');
    }
  }
  function setActionBtns(card, disabled) {
    var btns = card.querySelectorAll('.btn-draft, .btn-submit');
    for (var i = 0; i < btns.length; i++) btns[i].disabled = disabled;
  }

  grid.addEventListener('click', function (e) {
    var target = e.target || e.srcElement;
    if (target && target.tagName && target.tagName.toUpperCase() === 'IMG' && closestCls(target, 'thumb')) {
      if (lb) {
        var lbImg = lb.querySelector('img');
        if (lbImg) lbImg.src = target.src;
        lb.className = 'lb on';
      }
      return;
    }
    var card = closestCls(target, 'card');
    if (!card) return;

    if (closestCls(target, 'btn-cam')) {
      var cam = card.querySelector('.f-cam');
      if (cam) cam.click();
      return;
    }
    if (closestCls(target, 'btn-up')) {
      var up = card.querySelector('.f-up');
      if (up) up.click();
      return;
    }

    var rm = closestCls(target, 'rm');
    if (rm) {
      if (!window.confirm('Remove this photo? (The removal will be recorded in history and kept for 72 hours for lab review.)')) return;
      var th = closestCls(rm, 'thumb');
      rm.disabled = true;
      var prepIn = card.querySelector('[data-f="prepared_by"]');
      xhrPost(BASE + '/photo/delete', {
        capa_id: parseInt(card.getAttribute('data-id'), 10),
        photo_id: parseInt(th.getAttribute('data-pid'), 10),
        prepared_by: prepIn ? prepIn.value : ''
      }, true, function (err, j) {
        if (err) {
          say(card, err.message, false);
          rm.disabled = false;
          return;
        }
        removeNode(th);
        syncPhotoState(card);
        say(card, j.message || 'Photo removed.', true);
      });
      return;
    }

    var isDraft = !!closestCls(target, 'btn-draft');
    var isSub = !!closestCls(target, 'btn-submit');
    if (isDraft || isSub) {
      if (isSub && !window.confirm('Submit this CAPA card?\n\nOnce submitted, this card becomes view-only and cannot be edited. Other unsubmitted cards in this link will stay editable.')) return;
      var payload = { capa_id: parseInt(card.getAttribute('data-id'), 10), submit: isSub };
      var fields = card.querySelectorAll('[data-f]');
      for (var i = 0; i < fields.length; i++) {
        payload[fields[i].getAttribute('data-f')] = fields[i].value;
      }
      setActionBtns(card, true);
      say(card, isSub ? 'Submitting...' : 'Saving draft...', true);
      xhrPost(BASE + '/save', payload, true, function (err, j) {
        if (err) {
          say(card, err.message, false);
          setActionBtns(card, false);
          return;
        }
        if (j.locked) {
          card.setAttribute('data-locked', '1');
          card.className = 'card locked';
          for (var k = 0; k < fields.length; k++) fields[k].disabled = true;
          var toRm = card.querySelectorAll('.photo-btns, .thumb .rm');
          for (var r = 0; r < toRm.length; r++) removeNode(toRm[r]);
          var st = card.querySelector('.st');
          if (st) { st.className = 'st done'; st.textContent = 'SUBMITTED · VIEW ONLY'; }
          var act = card.querySelector('.act');
          if (act) {
            act.innerHTML = '';
            var sp = document.createElement('span');
            sp.className = 'msg ok';
            sp.textContent = 'Submitted by ' + (j.submitted_by || 'Staff') + ' · Locked (view-only)';
            act.appendChild(sp);
          }
          updateProg();
        } else {
          say(card, j.message || 'Draft saved.', true);
          setActionBtns(card, false);
        }
      });
    }
  });

  grid.addEventListener('change', function (e) {
    var inp = e.target || e.srcElement;
    if (!inp || (!hasCls(inp, 'f-cam') && !hasCls(inp, 'f-up'))) return;
    if (!inp.files || !inp.files[0]) return;
    var card = closestCls(inp, 'card');
    if (!card) return;
    var fd = new FormData();
    fd.append('capa_id', card.getAttribute('data-id'));
    var prepIn = card.querySelector('[data-f="prepared_by"]');
    fd.append('prepared_by', prepIn ? prepIn.value : '');
    fd.append('photo', inp.files[0]);
    say(card, 'Uploading photo...', true);
    xhrPost(BASE + '/photo', fd, false, function (err, j) {
      inp.value = '';
      if (err) { say(card, err.message, false); return; }
      var d = document.createElement('div');
      d.className = 'thumb';
      d.setAttribute('data-pid', String(j.photo.id));
      var im = document.createElement('img');
      im.src = j.photo.url;
      im.alt = 'CAPA photo';
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'rm';
      b.title = 'Remove photo (logged in 72h audit history)';
      b.innerHTML = '&times;';
      d.appendChild(im);
      d.appendChild(b);
      var thumbs = card.querySelector('.thumbs');
      if (thumbs) thumbs.appendChild(d);
      syncPhotoState(card);
      say(card, 'Photo uploaded!', true);
    });
  });
})();
