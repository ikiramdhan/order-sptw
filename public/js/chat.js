/* Chat pengantaran: polling ringan tiap 4 detik */
(function () {
  var box = document.getElementById('chat'); if (!box) return;
  var log = document.getElementById('chatlog'), form = document.getElementById('chatform');
  var url = box.dataset.url, token = box.dataset.token, last = 0, busy = false;
  function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
  function render(msgs) {
    if (!msgs.length) return;
    var empty = log.querySelector('.chatempty'); if (empty) empty.remove();
    var atBottom = log.scrollHeight - log.scrollTop - log.clientHeight < 60;
    msgs.forEach(function (m) {
      last = Math.max(last, m.id);
      var d = document.createElement('div'); d.className = 'bub ' + (m.mine ? 'me' : 'them');
      d.innerHTML = (m.mine ? '' : '<small class="who">' + esc(m.name || '') + '</small>') + '<span>' + esc(m.body) + '</span><time>' + m.time + '</time>';
      log.appendChild(d);
    });
    if (atBottom || msgs.some(function (m) { return m.mine; })) log.scrollTop = log.scrollHeight;
  }
  function poll() {
    if (document.visibilityState !== 'visible') return;
    fetch(url + '?after=' + last, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : { messages: [] }; }).then(function (d) { render(d.messages || []); }).catch(function () {});
  }
  function send(text) {
    text = (text || '').trim(); if (!text || busy) return; busy = true;
    fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify({ body: text }) })
      .then(function (r) { if (!r.ok) throw new Error(); return r.json(); })
      .then(function () { if (form) form.body.value = ''; poll(); })
      .catch(function () { alert('Pesan gagal terkirim. Coba lagi.'); })
      .then(function () { busy = false; });
  }
  if (form) form.addEventListener('submit', function (e) { e.preventDefault(); send(form.body.value); });
  box.addEventListener('click', function (e) { var b = e.target.closest('.quick button'); if (b) send(b.dataset.q); });
  poll(); setInterval(poll, 4000);
  document.addEventListener('visibilitychange', poll);
})();
