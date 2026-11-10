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
  document.addEventListener('submit', function (e) { if (!e.defaultPrevented) bar.className = 'navbar-progress go'; });
  window.addEventListener('pageshow', function () { bar.className = 'navbar-progress'; });
})();
