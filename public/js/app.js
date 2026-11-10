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
