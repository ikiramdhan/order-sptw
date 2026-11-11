// Tampilkan / sembunyikan password
document.addEventListener('click', function (e) {
  var btn = e.target.closest('[data-toggle-password]');
  if (!btn) return;
  var input = document.getElementById(btn.getAttribute('data-toggle-password'));
  if (!input) return;
  var show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  btn.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
});

// Konfirmasi untuk aksi berisiko (nonaktifkan, reset password)
document.addEventListener('submit', function (e) {
  var msg = e.target.getAttribute('data-confirm');
  if (msg && !window.confirm(msg)) e.preventDefault();
});

// Menu "Lainnya" (lembar bawah di HP/tablet)
(function () {
  var sheet = document.getElementById('more');
  if (!sheet) return;
  var openers = document.querySelectorAll('[data-more-open]');
  function setOpen(on) {
    sheet.classList.toggle('open', on);
    document.documentElement.style.overflow = on ? 'hidden' : '';
    openers.forEach(function (b) { b.setAttribute('aria-expanded', on ? 'true' : 'false'); });
  }
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-more-open]')) { setOpen(!sheet.classList.contains('open')); return; }
    if (e.target.closest('[data-more-close]')) setOpen(false);
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
})();

// Kunci zoom (iOS mengabaikan user-scalable=no, jadi cegah gesture cubit)
['gesturestart', 'gesturechange', 'gestureend'].forEach(function (t) {
  document.addEventListener(t, function (e) { e.preventDefault(); });
});

// Service worker (PWA)
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register('/sw.js').catch(function () {});
  });
}

// Mode terang / gelap (tersimpan di perangkat; tanpa pilihan = ikut sistem)
(function () {
  var root = document.documentElement;
  function apply(t, store) {
    root.setAttribute('data-theme', t);
    if (store) { try { localStorage.setItem('ck-theme', t); } catch (e) {} }
    var m = document.querySelector('meta[name=theme-color]');
    if (m) m.setAttribute('content', t === 'dark' ? '#121110' : '#CF3F16');
    document.querySelectorAll('[data-theme-toggle]').forEach(function (b) { b.setAttribute('aria-pressed', t === 'dark' ? 'true' : 'false'); });
  }
  document.addEventListener('click', function (e) {
    if (!e.target.closest('[data-theme-toggle]')) return;
    root.classList.add('theme-anim');
    apply(root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark', true);
    setTimeout(function () { root.classList.remove('theme-anim'); }, 400);
  });
  apply(root.getAttribute('data-theme') || 'light', false);
  if (window.matchMedia) {
    var mq = matchMedia('(prefers-color-scheme: dark)');
    var onChange = function (e) {
      var s = null; try { s = localStorage.getItem('ck-theme'); } catch (_) {}
      if (s !== 'light' && s !== 'dark') apply(e.matches ? 'dark' : 'light', false);
    };
    if (mq.addEventListener) mq.addEventListener('change', onChange);
  }
})();

// Tombol kirim di halaman masuk: tampilkan animasi memuat
document.addEventListener('submit', function (e) {
  var f = e.target;
  if (!f.matches || !f.matches('[data-auth-form]')) return;
  setTimeout(function () {
    var b = f.querySelector('button[type=submit]');
    if (b) { b.classList.add('loading'); b.setAttribute('aria-busy', 'true'); }
  }, 0);
});
window.addEventListener('pageshow', function () {
  document.querySelectorAll('button.loading').forEach(function (b) { b.classList.remove('loading'); b.removeAttribute('aria-busy'); });
});

// Indikator memuat saat pindah halaman (terasa seperti aplikasi asli)
(function () {
  var bar = document.querySelector('.navbar-progress');
  if (!bar) return;
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[href]');
    if (!a || a.target === '_blank' || e.metaKey || e.ctrlKey || a.hasAttribute('download')) return;
    var u = a.getAttribute('href');
    if (!u || u.charAt(0) === '#' || u.indexOf('javascript:') === 0) return;
    bar.className = 'navbar-progress go';
  });
  document.addEventListener('submit', function (e) { if (!e.defaultPrevented && !(e.target.matches && e.target.matches('form[data-addcart]'))) bar.className = 'navbar-progress go'; });
  window.addEventListener('pageshow', function () { bar.className = 'navbar-progress'; });
})();

// Kolom uang: tampil dengan titik ribuan (1.500.000), dikirim sebagai angka biasa
(function () {
  function fmt(v) { var d = String(v).replace(/\D/g, ''); return d ? d.replace(/\B(?=(\d{3})+(?!\d))/g, '.') : ''; }
  document.addEventListener('input', function (e) {
    var el = e.target;
    if (!el.matches || !el.matches('[data-money]')) return;
    var pos = el.selectionStart, before = el.value.length;
    el.value = fmt(el.value);
    var np = Math.max(0, pos + el.value.length - before);
    try { el.setSelectionRange(np, np); } catch (_) {}
  });
  document.addEventListener('submit', function (e) {
    e.target.querySelectorAll && e.target.querySelectorAll('[data-money]').forEach(function (el) { el.value = String(el.value).replace(/\D/g, ''); });
  }, true);
  window.addEventListener('pageshow', function () {
    document.querySelectorAll('[data-money]').forEach(function (el) { el.value = fmt(el.value); });
  });
})();

// Dropdown kustom (menggantikan tampilan bawaan sistem; <select> asli tetap dipakai untuk kirim data)
(function () {
  var openDD = null, backdrop = null;
  var CHEV = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';
  var TICK = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
  function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
  function isMobile() { return window.matchMedia('(max-width:900px)').matches; }

  function build(sel) {
    if (sel.multiple || sel.hasAttribute('data-native') || sel.__dd) return;
    var wrap = document.createElement('div'); wrap.className = 'dd';
    sel.parentNode.insertBefore(wrap, sel); wrap.appendChild(sel);
    sel.classList.add('dd-native'); sel.tabIndex = -1; sel.setAttribute('aria-hidden', 'true');
    var btn = document.createElement('button'); btn.type = 'button'; btn.className = 'dd-btn';
    btn.setAttribute('aria-haspopup', 'listbox'); btn.setAttribute('aria-expanded', 'false');
    var lab = sel.getAttribute('aria-label'); if (lab) btn.setAttribute('aria-label', lab);
    wrap.appendChild(btn);
    var inst = { sel: sel, wrap: wrap, btn: btn, panel: null, idx: -1 };
    sel.__dd = inst;
    function opts() { return Array.prototype.slice.call(sel.options); }
    function label() {
      var o = sel.options[sel.selectedIndex];
      var t = o ? o.text : '';
      var empty = !o || o.value === '';
      btn.innerHTML = '<span class="dd-txt' + (empty ? ' ph' : '') + '">' + esc(t || 'Pilih…') + '</span>' + CHEV;
      btn.disabled = sel.disabled;
    }
    inst.refresh = label;
    label();
    sel.addEventListener('change', label);
    new MutationObserver(label).observe(sel, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled', 'selected'] });

    function close() {
      if (!inst.panel) return;
      if (inst.onScroll) { window.removeEventListener('scroll', inst.onScroll, true); window.removeEventListener('resize', inst.onScroll); inst.onScroll = null; }
      inst.panel.remove(); inst.panel = null; btn.setAttribute('aria-expanded', 'false'); wrap.classList.remove('open');
      if (backdrop) { backdrop.remove(); backdrop = null; }
      document.documentElement.classList.remove('dd-lock'); openDD = null;
    }
    inst.close = close;
    function choose(i) {
      var o = sel.options[i]; if (!o || o.disabled) return;
      var changed = sel.selectedIndex !== i; sel.selectedIndex = i; label(); close(); btn.focus();
      if (changed) { sel.dispatchEvent(new Event('input', { bubbles: true })); sel.dispatchEvent(new Event('change', { bubbles: true })); }
    }
    function open() {
      if (sel.disabled) return;
      if (openDD && openDD !== inst) openDD.close();
      var p = document.createElement('div'); p.className = 'dd-panel'; p.setAttribute('role', 'listbox');
      var list = opts(), html = '';
      if (list.length > 8) html += '<div class="dd-search"><input type="search" placeholder="Cari…" autocomplete="off" aria-label="Cari"></div>';
      html += '<ul class="dd-opts">';
      list.forEach(function (o, i) {
        html += '<li role="option" data-i="' + i + '" class="' + (i === sel.selectedIndex ? 'sel ' : '') + (o.disabled ? 'dis' : '') + '" aria-selected="' + (i === sel.selectedIndex) + '"><span>' + esc(o.text || '—') + '</span>' + (i === sel.selectedIndex ? TICK : '') + '</li>';
      });
      html += '</ul>';
      p.innerHTML = html;
      var mobile = isMobile();
      if (mobile) {
        backdrop = document.createElement('div'); backdrop.className = 'dd-backdrop'; document.body.appendChild(backdrop);
        backdrop.addEventListener('click', close);
        p.classList.add('sheet-m'); document.documentElement.classList.add('dd-lock');
        var h = document.createElement('div'); h.className = 'dd-handle'; p.insertBefore(h, p.firstChild);
        document.body.appendChild(p);
      } else {
        // dipasang di <body> dengan posisi fixed supaya tidak terpotong tabel/kartu yang bisa digulir
        document.body.appendChild(p);
        function place() {
            var r = btn.getBoundingClientRect(), below = window.innerHeight - r.bottom - 12, above = r.top - 12;
            var up = below < 260 && above > below, room = Math.max(160, up ? above : below);
            p.classList.add('fx'); p.classList.toggle('up', up);
            p.style.left = Math.max(8, Math.min(r.left, window.innerWidth - Math.max(r.width, 200) - 8)) + 'px';
            p.style.width = Math.max(r.width, 200) + 'px';
            if (up) { p.style.top = 'auto'; p.style.bottom = (window.innerHeight - r.top + 6) + 'px'; } else { p.style.bottom = 'auto'; p.style.top = (r.bottom + 6) + 'px'; }
            p.querySelector('.dd-opts').style.maxHeight = Math.min(280, room - (p.querySelector('.dd-search') ? 56 : 12)) + 'px';
        }
        place();
        inst.onScroll = function (ev) { if (!p.contains(ev.target)) place(); };
        window.addEventListener('scroll', inst.onScroll, true); window.addEventListener('resize', inst.onScroll);
      }
      inst.panel = p; openDD = inst; wrap.classList.add('open'); btn.setAttribute('aria-expanded', 'true');
      var ul = p.querySelector('.dd-opts'), search = p.querySelector('.dd-search input');
      var cur = ul.querySelector('li.sel'); if (cur) ul.scrollTop = Math.max(0, cur.offsetTop - ul.clientHeight / 2 + 20);
      inst.idx = sel.selectedIndex;
      function items() { return Array.prototype.slice.call(ul.querySelectorAll('li:not(.hide):not(.dis)')); }
      function mark(li) {
        ul.querySelectorAll('li.hl').forEach(function (x) { x.classList.remove('hl'); });
        if (li) { li.classList.add('hl'); li.scrollIntoView({ block: 'nearest' }); inst.idx = +li.getAttribute('data-i'); }
      }
      ul.addEventListener('click', function (e) { var li = e.target.closest('li'); if (li && !li.classList.contains('dis')) choose(+li.getAttribute('data-i')); });
      ul.addEventListener('mousemove', function (e) { var li = e.target.closest('li'); if (li && !li.classList.contains('dis')) mark(li); });
      if (search) {
        search.addEventListener('input', function () {
          var q = search.value.toLowerCase();
          ul.querySelectorAll('li').forEach(function (li) { li.classList.toggle('hide', q && li.textContent.toLowerCase().indexOf(q) === -1); });
          mark(items()[0]);
        });
        if (!mobile) search.focus();
      }
      p.addEventListener('keydown', function (e) { keys(e, items, mark, search); });
    }
    function keys(e, items, mark) {
      var it = items(); if (!it.length) return;
      var cur = it.findIndex(function (x) { return x.classList.contains('hl'); });
      if (e.key === 'ArrowDown') { e.preventDefault(); mark(it[Math.min(it.length - 1, cur + 1)]); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); mark(it[Math.max(0, cur - 1)]); }
      else if (e.key === 'Enter') { e.preventDefault(); var h = it[cur]; if (h) choose(+h.getAttribute('data-i')); }
    }
    btn.addEventListener('click', function () { inst.panel ? close() : open(); });
    btn.addEventListener('keydown', function (e) {
      if (['ArrowDown', 'ArrowUp'].indexOf(e.key) > -1 && !inst.panel) { e.preventDefault(); open(); return; }
      if (inst.panel) {
        var ul = inst.panel.querySelector('.dd-opts');
        var it = Array.prototype.slice.call(ul.querySelectorAll('li:not(.hide):not(.dis)'));
        var cur = it.findIndex(function (x) { return x.classList.contains('hl') || x.classList.contains('sel'); });
        function mk(li) { ul.querySelectorAll('li.hl').forEach(function (x) { x.classList.remove('hl'); }); if (li) { li.classList.add('hl'); li.scrollIntoView({ block: 'nearest' }); } }
        if (e.key === 'ArrowDown') { e.preventDefault(); mk(it[Math.min(it.length - 1, cur + 1)]); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); mk(it[Math.max(0, cur - 1)]); }
        else if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); var h = ul.querySelector('li.hl'); if (h) choose(+h.getAttribute('data-i')); else close(); }
      }
    });
  }
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && openDD) { var b = openDD.btn; openDD.close(); b.focus(); } });
  document.addEventListener('click', function (e) { if (openDD && !openDD.wrap.contains(e.target) && !e.target.closest('.dd-panel')) openDD.close(); });
  function scan(root) { (root || document).querySelectorAll('select').forEach(build); }
  scan();
  new MutationObserver(function (ms) {
    ms.forEach(function (m) { m.addedNodes.forEach(function (n) { if (n.nodeType === 1) { if (n.tagName === 'SELECT') build(n); else scan(n); } }); });
  }).observe(document.body, { childList: true, subtree: true });
})();

// Tabel jadi kartu di HP: beri label tiap sel dari judul kolom
(function () {
  function label(tbl) {
    if (tbl.closest('.sheet') || tbl.__lbl) return; tbl.__lbl = true;
    var ths = Array.prototype.map.call(tbl.querySelectorAll('thead th'), function (t) { return t.textContent.trim(); });
    if (!ths.length) return;
    tbl.querySelectorAll('tbody tr').forEach(function (tr) {
      var tds = tr.children, plain = Array.prototype.some.call(tds, function (td) { return td.colSpan > 1; });
      if (plain) return;
      Array.prototype.forEach.call(tds, function (td, i) {
        if (ths[i]) td.setAttribute('data-label', ths[i]);
        if (td.querySelector('input:not([type=hidden]),select,textarea')) td.classList.add('has-input');
      });
    });
    tbl.classList.add('stackable');
  }
  function scan() { document.querySelectorAll('table.tbl').forEach(label); }
  scan();
  document.addEventListener('DOMContentLoaded', scan);
})();

// Tombol salin (info transfer supplier)
document.addEventListener('click', function (e) {
  var b = e.target.closest('[data-copy]'); if (!b) return;
  var txt = b.getAttribute('data-copy'), old = b.textContent;
  function done() { b.textContent = 'Tersalin ✓'; setTimeout(function () { b.textContent = old; }, 1600); }
  if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(txt).then(done, function () {}); return; }
  var t = document.createElement('textarea'); t.value = txt; t.style.position = 'fixed'; t.style.opacity = '0'; document.body.appendChild(t); t.select();
  try { document.execCommand('copy'); done(); } catch (_) {} t.remove();
});

/* Unduh file tanpa meninggalkan aplikasi (PWA iOS tidak punya tombol kembali dari pratinjau file) */
document.addEventListener('click', function (e) {
  var a = e.target.closest('a[data-download]'); if (!a) return;
  e.preventDefault();
  if (a.dataset.busy) return; a.dataset.busy = '1';
  var old = a.textContent; a.textContent = 'Menyiapkan…';
  fetch(a.href, { credentials: 'same-origin' }).then(function (r) {
    if (!r.ok) throw new Error('gagal');
    var cd = r.headers.get('Content-Disposition') || '', m = /filename="?([^";]+)"?/i.exec(cd);
    var name = m ? m[1] : (a.getAttribute('data-download') || 'file');
    return r.blob().then(function (blob) { return { blob: blob, name: name }; });
  }).then(function (f) {
    var file = new File([f.blob], f.name, { type: f.blob.type });
    function save() {
      var url = URL.createObjectURL(f.blob), l = document.createElement('a');
      l.href = url; l.download = f.name; document.body.appendChild(l); l.click(); l.remove();
      setTimeout(function () { URL.revokeObjectURL(url); }, 4000);
    }
    if (navigator.canShare && navigator.canShare({ files: [file] })) {
      return navigator.share({ files: [file], title: f.name }).catch(function (err) { if (err && err.name !== 'AbortError') save(); });
    }
    save();
  }).catch(function () { alert('Gagal mengunduh. Coba lagi.'); })
    .then(function () { a.textContent = old; delete a.dataset.busy; });
});

/* Tambah ke keranjang tanpa reload halaman (posisi scroll tetap) */
document.addEventListener('submit', function (e) {
  var f = e.target; if (!f.matches || !f.matches('form[data-addcart]')) return;
  e.preventDefault();
  var btn = f.querySelector('button[type=submit]'); if (btn.disabled) return;
  var old = btn.textContent; btn.disabled = true; btn.textContent = '…';
  fetch(f.action, { method: 'POST', body: new FormData(f), credentials: 'same-origin',
    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
  .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
  .then(function (x) {
    if (!x.ok) throw new Error((x.j && x.j.errors && x.j.errors.qty && x.j.errors.qty[0]) || (x.j && x.j.message) || 'Gagal menambah');
    var c = document.getElementById('cartcount'); if (c) c.textContent = '(' + x.j.count + ')';
    btn.textContent = '✓ Masuk'; f.querySelector('input[name=qty]').value = 1;
    setTimeout(function () { btn.textContent = old; btn.disabled = false; }, 1200);
  }).catch(function (err) {
    btn.textContent = old; btn.disabled = false; alert(err.message);
  });
});

/* Kolom jumlah: isi otomatis terpilih saat disentuh, jadi mengetik langsung menimpa angka lama */
document.addEventListener('focusin', function (e) {
  var i = e.target; if (!i.matches || !i.matches('input[type=number]')) return;
  setTimeout(function () { try { i.select(); } catch (_) {} }, 0);
});
