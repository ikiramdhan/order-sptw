@extends('layouts.app')
@section('title', $note->number)
@section('content')
@php $st = $note->courier_status; $active = $note->status === 'dikirim' && $st !== 'sampai'; @endphp
<a class="backlink" href="{{ route('courier.index') }}"><x-icon name="back" :size="18" /> Antaran</a>
<header class="page-head">
    <div><h1>{{ $note->number }}</h1><p><span class="ochip" style="--h:{{ crc32($note->outlet->name) % 360 }}"><i></i>{{ $note->outlet->name }}</span></p></div>
</header>

<div class="card">
    @if ($st === 'diantar' && $active)
        <div id="track" data-url="{{ route('courier.location', $note) }}" data-token="{{ csrf_token() }}">
            <div class="trackstate" id="tstate"><i class="live"></i><div><b id="tmsg">Menyalakan GPS…</b><small id="tsub">Izinkan akses lokasi bila diminta</small></div></div>
            <p class="trackwarn" id="twarn" hidden>Layar/aplikasi tidak aktif — lokasi berhenti terkirim. Buka kembali aplikasi ini.</p>
        </div>
        <form method="POST" action="{{ route('courier.arrive', $note) }}" data-confirm="Tandai sudah sampai di {{ $note->outlet->name }}?">@csrf
            <button class="btn primary block" type="submit">Sudah sampai</button>
        </form>
    @elseif ($active)
        <p style="margin:0 0 12px;color:var(--muted)">Tekan tombol di bawah saat berangkat. Lokasi kamu akan terlihat oleh outlet selama diantar. Biarkan layar tetap menyala dan HP dicas.</p>
        <form method="POST" action="{{ route('courier.start', $note) }}">@csrf
            <button class="btn primary block" type="submit">Mulai antar</button>
        </form>
    @else
        <span class="badge ok">Selesai</span>
        @if ($note->courier_arrived_at)<small style="color:var(--muted);margin-left:8px">Sampai {{ $note->courier_arrived_at->format('j M Y H:i') }}</small>@endif
    @endif
</div>

<section class="card" style="margin-top:12px">
    <h3 class="sect">Barang dibawa</h3>
    @foreach ($note->items as $row)
        <div class="mrow"><div class="who"><b>{{ $row->item->name }}</b></div><div class="val"><b>{{ \App\Support\Fmt::qty($row->qty_input) }} {{ $row->unit->name }}</b></div></div>
    @endforeach
</section>

@if ($st === 'diantar' && $active)
<script>
(function () {
  var box = document.getElementById('track'); if (!box) return;
  var url = box.dataset.url, token = box.dataset.token;
  var msg = document.getElementById('tmsg'), sub = document.getElementById('tsub'), warn = document.getElementById('twarn');
  var last = null, sentAt = 0, sending = false, lock = null;
  function say(a, b) { msg.textContent = a; sub.textContent = b || ''; }
  function wake() {
    if (!('wakeLock' in navigator)) { sub.textContent = 'Layar tidak bisa dikunci di HP ini; jangan matikan layar'; return; }
    navigator.wakeLock.request('screen').then(function (l) { lock = l; }).catch(function () {});
  }
  wake();
  document.addEventListener('visibilitychange', function () {
    warn.hidden = document.visibilityState === 'visible';
    if (document.visibilityState === 'visible') { wake(); push(true); }
  });
  function push(force, native) {
    if (!last || sending || (!native && document.visibilityState !== 'visible')) return;
    var now = Date.now(); if (!force && now - sentAt < 6000) return;
    sending = true; sentAt = now;
    var fd = new FormData(); fd.append('lat', last.latitude); fd.append('lng', last.longitude); if (last.accuracy) fd.append('acc', last.accuracy);
    fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' } })
      .then(function (r) { if (r.status === 409) { location.reload(); return; } if (!r.ok) throw new Error(); say('Lokasi terkirim', 'Terakhir ' + new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })); })
      .catch(function () { say('Gagal mengirim lokasi', 'Mencoba lagi… cek sinyal internet'); })
      .then(function () { sending = false; });
  }
  var cap = window.Capacitor, BG = cap && cap.isNativePlatform && cap.isNativePlatform() ? (cap.Plugins || {}).BackgroundGeolocation : null;
  if (BG) {
    /* Aplikasi Android: lokasi tetap terkirim walau layar mati (foreground service) */
    if (lock) { try { lock.release(); } catch (_) {} }
    say('Menyalakan GPS…', 'Aplikasi kurir — lokasi tetap jalan walau layar mati');
    warn.hidden = true;
    BG.addWatcher({ backgroundTitle: 'Seblak CK — sedang mengantar', backgroundMessage: 'Lokasi dikirim ke outlet selama antaran', requestPermissions: true, stale: false, distanceFilter: 10 }, function (loc, err) {
      if (err) {
        if (err.code === 'NOT_AUTHORIZED') { say('Izin lokasi ditolak', 'Pilih "Izinkan sepanjang waktu" di pengaturan aplikasi'); }
        else { say('GPS bermasalah', String(err.message || err.code || '')); }
        return;
      }
      last = { latitude: loc.latitude, longitude: loc.longitude, accuracy: loc.accuracy };
      push(true, true);
    });
    setInterval(function () { push(false, true); }, 8000);
    return;
  }
  if (!navigator.geolocation) { say('GPS tidak tersedia', 'Gunakan HP lain atau browser lain'); return; }
  navigator.geolocation.watchPosition(function (p) { last = p.coords; push(false); }, function (e) {
    say(e.code === 1 ? 'Izin lokasi ditolak' : 'GPS belum dapat sinyal', e.code === 1 ? 'Aktifkan izin lokasi untuk aplikasi ini di pengaturan HP' : 'Pindah ke tempat terbuka');
  }, { enableHighAccuracy: true, maximumAge: 5000, timeout: 20000 });
  setInterval(function () { push(false); }, 3000);
})();
</script>
@endif
@endsection
