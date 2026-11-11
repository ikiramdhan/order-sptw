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
    <div class="tk-rt"><i class="tk-a"></i><span id="tkfrom">Kurir · {{ $note->courier?->name }}</span></div>
    <div class="tk-rt"><i class="tk-b"></i><span>{{ $note->outlet->name }}@if ($note->outlet->address) <small>· {{ $note->outlet->address }}</small>@endif</span></div>
</div>

@if ($note->outlet->lat === null)
<div class="tk-setloc"><b>Titik outlet belum diatur</b><small>Atur titik lokasi agar rute dan estimasi tampil.</small><a class="btn primary" href="{{ route('delivery.show', $note) }}#locform">Atur sekarang</a></div>
@endif
<div class="tk-sheet">
    <div class="tk-banner" id="tkbanner">
        <span class="tk-bico"><x-icon name="truck" :size="22" /></span>
        <span id="tkmsg">Menunggu kurir berangkat</span>
        <b class="tk-pill" id="tkpill" hidden></b>
    </div>
    <div class="tk-card">
        <i class="tk-handle"></i>
        <div class="tk-row">
            <div>
                <b class="tk-num">{{ $note->number }}</b>
                <div class="tk-sub">{{ $note->courier?->name }} <span class="tk-tag">Motor</span></div>
            </div>
            <div class="tk-avatar">{{ $initials }}</div>
        </div>
        <div class="tk-meta"><span>{{ $note->items->count() }} barang · {{ $note->outlet->name }}</span><span id="tkago"></span></div>
        <div class="tk-actions">
            <button type="button" class="btn primary" id="openchat"><x-icon name="bell" :size="18" /> Chat kurir <i class="tk-dot" id="chatdot" hidden></i></button>
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
  L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', { subdomains: 'abcd', maxZoom: 20, attribution: '© OpenStreetMap © CARTO' }).addTo(map);
  var tpl = document.getElementById('motortpl').innerHTML;
  function kicon(flip) { return L.divIcon({ className: 'kbike' + (flip ? ' flip' : ''), html: tpl, iconSize: [64, 42], iconAnchor: [32, 30] }); }
  var bicon = L.divIcon({ className: '', html: '<div class="tk-pin"><i></i></div>', iconSize: [34, 34], iconAnchor: [17, 17] });
  var kmark = null, bmark = hasB ? L.marker([olat, olng], { icon: bicon }).addTo(map) : null, casing = null, line = null, first = true, prev = null, flip = false;
  var msg = document.getElementById('tkmsg'), pill = document.getElementById('tkpill'), ago = document.getElementById('tkago'), banner = document.getElementById('tkbanner');

  function fmt(s) { var m = Math.max(1, Math.round(s / 60)); return m < 60 ? m + ' mnt' : Math.floor(m / 60) + ' j ' + (m % 60) + ' mnt'; }
  function fit() {
    if (kmark && bmark) map.fitBounds(L.latLngBounds([kmark.getLatLng(), bmark.getLatLng()]), { paddingTopLeft: [40, 150], paddingBottomRight: [40, 300] });
    else if (kmark) map.setView(kmark.getLatLng(), 16);
  }
  document.getElementById('recenter').addEventListener('click', fit);

  function poll() {
    fetch(el.dataset.url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
      if (d.status === 'sampai' || d.delivered) {
        banner.classList.add('done'); msg.textContent = 'Kurir sudah sampai'; pill.hidden = false; pill.textContent = '✓';
        ago.textContent = ''; return;
      }
      if (d.status !== 'diantar') { msg.textContent = 'Menunggu kurir berangkat'; pill.hidden = true; return; }
      if (d.lat == null) { msg.textContent = 'Mencari posisi kurir…'; pill.hidden = true; return; }
      var ll = [d.lat, d.lng];
      if (prev && Math.abs(ll[1] - prev[1]) > 0.00005) flip = ll[1] < prev[1];
      prev = ll;
      if (!kmark) kmark = L.marker(ll, { icon: kicon(flip), zIndexOffset: 800 }).addTo(map); else { kmark.setLatLng(ll); kmark.setIcon(kicon(flip)); }
      var pts = (d.route && d.route.length > 1) ? d.route : (bmark ? [ll, [olat, olng]] : null);
      if (pts) {
        if (casing) { casing.setLatLngs(pts); line.setLatLngs(pts); }
        else { casing = L.polyline(pts, { color: '#fff', weight: 10, opacity: .95, lineCap: 'round' }).addTo(map); line = L.polyline(pts, { color: '#1E9E4A', weight: 5, lineCap: 'round', dashArray: d.route && d.route.length > 1 ? null : '2 10' }).addTo(map); }
      }
      if (first) { fit(); first = false; } else if (!map.getBounds().pad(-0.12).contains(ll)) { map.panTo(ll); }
      if (d.eta_s != null) {
        if (d.dist_m < 80) { msg.textContent = 'Kurir sudah di depan'; pill.hidden = true; }
        else { msg.textContent = 'Kurir akan sampai dalam'; pill.hidden = false; pill.textContent = fmt(d.eta_s); }
      } else { msg.textContent = 'Kurir sedang dalam perjalanan'; pill.hidden = true; }
      ago.textContent = d.ago || '';
    }).catch(function () {});
  }
  poll(); setInterval(poll, 5000);

  var chat = document.getElementById('tkchat');
  document.getElementById('openchat').addEventListener('click', function () { chat.hidden = false; document.getElementById('chatdot').hidden = true; var l = document.getElementById('chatlog'); if (l) l.scrollTop = l.scrollHeight; });
  document.getElementById('closechat').addEventListener('click', function () { chat.hidden = true; });
})();
</script>
</body>
</html>
