<!doctype html>
<html lang="id">
<head>
@include('layouts.head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body class="trackpage">
@php
    $parts = preg_split('/\s+/', trim($note->courier?->name ?? 'K'));
    $initials = mb_strtoupper(mb_substr($parts[0] ?? 'K', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
@endphp
<div id="gmap" data-url="{{ route('delivery.position', $note) }}"
     data-olat="{{ $note->outlet->lat }}" data-olng="{{ $note->outlet->lng }}" data-detail="{{ route('delivery.show', $note) }}"></div>
<template id="motortpl">@include('partials.motor')</template>

<a class="tk-round tk-back" href="{{ route('delivery.show', $note) }}" aria-label="Kembali"><x-icon name="back" :size="22" /></a>
<button class="tk-round tk-locate" id="recenter" type="button" aria-label="Pusatkan peta"><x-icon name="search" :size="20" /></button>

<div class="tk-top">
    <div class="tk-route"><i class="tk-rline"></i>
        <div class="tk-rt r1"><i class="tk-a"><u></u></i><div><small>Posisi kurir</small><b>{{ $note->courier?->name }}</b></div><span class="tk-chip" id="tkchip1">Jalan</span></div>
        <div class="tk-rt r2"><i class="tk-b"><u></u></i><div><small>Tujuan</small><b>{{ $note->outlet->name }}</b>@if ($note->outlet->address)<em>{{ $note->outlet->address }}</em>@endif</div><span class="tk-chip hot" id="tkchip2">–</span></div>
    </div>
</div>

@if ($note->outlet->lat === null)
<div class="tk-setloc"><b>Estimasi belum tersedia</b><small>Titik lokasi {{ $note->outlet->name }} belum diatur oleh admin.</small>@unless ($isOutlet)<a class="btn primary" href="{{ route('delivery.show', $note) }}#locform">Atur sekarang</a>@endunless</div>
@endif

<div class="tk-sheet">
    <div class="tk-eta" id="tkbanner"><div class="wind"><i></i><i></i><i></i><i></i><i></i></div>
        <div class="tk-etatop"><span class="tk-live"><i></i>LIVE</span><span id="tkmsg">Menunggu kurir berangkat</span></div>
        <div class="tk-etabig"><b id="tkbig">–</b><small id="tkunit"></small><span class="tk-arr" id="tkarr"></span></div>
        <div class="tk-prog"><i id="tkfill"></i><span id="tkbike">@include('partials.motor')</span><em class="tk-flag"></em></div>
    </div>
    <div class="tk-card">
        <i class="tk-handle"></i>
        <div class="tk-row">
            <div class="tk-avatar">{{ $initials }}</div>
            <div class="tk-who"><b>{{ $note->courier?->name }}</b><div class="tk-sub"><span>{{ $note->number }}</span></div></div>
        </div>
        <div class="tk-stats">
            <div><small>Jarak</small><b id="tkdist">–</b></div>
            <div><small>Tiba sekitar</small><b id="tkat">–</b></div>
            <div><small>Barang</small><b>{{ $note->items->count() }}</b></div>
        </div>
        <div class="tk-actions">
            <button type="button" class="btn primary" id="openchat"><x-icon name="bell" :size="18" /> Chat kurir</button>
            <a class="btn outline" href="{{ route('delivery.show', $note) }}">Detail</a>
        </div>
    </div>
</div>

<div class="tk-chat" id="tkchat" hidden>
    <div class="tk-chathead"><b>Chat kurir</b><button type="button" id="closechat" aria-label="Tutup">✕</button></div>
    @include('partials.chat', ['note' => $note])
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
  var el = document.getElementById('gmap');
  var olat = parseFloat(el.dataset.olat), olng = parseFloat(el.dataset.olng), hasB = isFinite(olat) && isFinite(olng);
  var map = L.map(el, { zoomControl: false, attributionControl: true }).setView(hasB ? [olat, olng] : [-6.2, 106.65], 15);
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);
  var tpl = document.getElementById('motortpl').innerHTML;
  function kicon(flip) { return L.divIcon({ className: 'kbike' + (flip ? ' flip' : ''), html: '<div class="khalo"></div>' + tpl, iconSize: [72, 48], iconAnchor: [36, 34] }); }
  var bicon = L.divIcon({ className: '', html: '<div class="tk-drop"><i></i></div>', iconSize: [38, 48], iconAnchor: [19, 46] });
  var kmark = null, bmark = hasB ? L.marker([olat, olng], { icon: bicon }).addTo(map) : null, casing = null, line = null, flow = null, first = true, prev = null, flip = false;
  var $ = function (id) { return document.getElementById(id); };
  var msg = $('tkmsg'), big = $('tkbig'), unit = $('tkunit'), arr = $('tkarr'), fill = $('tkfill'), bike = $('tkbike'), dist = $('tkdist'), at = $('tkat'), panel = $('tkbanner'), chip1 = $('tkchip1'), chip2 = $('tkchip2');

  function fmtDist(m) { return m < 1000 ? Math.round(m / 10) * 10 + ' m' : (m / 1000).toFixed(1).replace('.', ',') + ' km'; }
  function clock(sec) { var d = new Date(Date.now() + sec * 1000); return ('0' + d.getHours()).slice(-2) + '.' + ('0' + d.getMinutes()).slice(-2); }
  function progress(p) { var v = Math.max(0, Math.min(1, p || 0)) * 100; fill.style.width = v + '%'; bike.style.left = Math.max(4, Math.min(88, v)) + '%'; }
  function fit() {
    if (kmark && bmark) map.fitBounds(L.latLngBounds([kmark.getLatLng(), bmark.getLatLng()]), { paddingTopLeft: [40, 170], paddingBottomRight: [40, 380] });
    else if (kmark) map.setView(kmark.getLatLng(), 16);
  }
  $('recenter').addEventListener('click', fit);

  function poll() {
    fetch(el.dataset.url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
      if (d.status === 'sampai' || d.delivered) {
        panel.classList.add('done'); chip1.textContent = 'Selesai'; chip2.textContent = '✓'; msg.textContent = 'Kurir sudah sampai'; big.textContent = '✓'; unit.textContent = ''; arr.textContent = ''; dist.textContent = '0 m'; at.textContent = 'Sampai'; progress(1); return;
      }
      if (d.status !== 'diantar') { msg.textContent = 'Menunggu kurir berangkat'; return; }
      if (d.lat == null) { msg.textContent = 'Mencari posisi kurir…'; return; }
      var ll = [d.lat, d.lng];
      if (prev && Math.abs(ll[1] - prev[1]) > 0.00005) flip = ll[1] < prev[1];
      prev = ll;
      if (!kmark) kmark = L.marker(ll, { icon: kicon(flip), zIndexOffset: 800 }).addTo(map); else { kmark.setLatLng(ll); kmark.setIcon(kicon(flip)); }
      var pts = (d.route && d.route.length > 1) ? d.route : (bmark ? [ll, [olat, olng]] : null);
      if (pts) {
        if (casing) { casing.setLatLngs(pts); line.setLatLngs(pts); flow.setLatLngs(pts); }
        else {
          casing = L.polyline(pts, { color: '#fff', weight: 12, opacity: .96, lineCap: 'round', lineJoin: 'round' }).addTo(map);
          line = L.polyline(pts, { color: '#D43C14', weight: 7, lineCap: 'round', lineJoin: 'round' }).addTo(map);
          flow = L.polyline(pts, { color: '#fff', weight: 2.5, opacity: .9, className: 'tk-flow', lineCap: 'round' }).addTo(map);
        }
      }
      if (first) { fit(); first = false; } else if (!map.getBounds().pad(-0.12).contains(ll)) { map.panTo(ll); }
      if (d.eta_s != null) {
        progress(d.progress); dist.textContent = fmtDist(d.dist_m); at.textContent = clock(d.eta_s); chip1.textContent = 'Live'; chip2.textContent = d.dist_m < 80 ? 'Tiba' : fmtDist(d.dist_m);
        if (d.dist_m < 80) { msg.textContent = 'Kurir sudah di depan'; big.textContent = 'Tiba'; unit.textContent = ''; arr.textContent = ''; }
        else {
          var m = Math.max(1, Math.round(d.eta_s / 60));
          msg.textContent = 'Kurir sedang dalam perjalanan';
          if (m < 60) { big.textContent = m; unit.textContent = 'menit'; } else { big.textContent = Math.floor(m / 60) + ':' + ('0' + (m % 60)).slice(-2); unit.textContent = 'jam'; }
          arr.textContent = 'Tiba ± ' + clock(d.eta_s);
        }
      } else { msg.textContent = 'Kurir sedang dalam perjalanan'; }
    }).catch(function () {});
  }
  poll(); setInterval(poll, 5000);

  var chat = $('tkchat');
  $('openchat').addEventListener('click', function () { chat.hidden = false; var l = $('chatlog'); if (l) l.scrollTop = l.scrollHeight; });
  $('closechat').addEventListener('click', function () { chat.hidden = true; });
})();
</script>
</body>
</html>
