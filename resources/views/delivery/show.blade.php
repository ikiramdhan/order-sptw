@extends('layouts.app')
@section('title', $note->number)
@section('content')
@use('App\Support\Fmt')
<a class="backlink" href="{{ route('delivery.index') }}"><x-icon name="back" :size="18" /> Surat jalan</a>
<header class="page-head">
    <div><h1>{{ $note->number }}</h1><p>{{ $note->outlet->name }} · {{ $note->purchaseOrder?->number }}@if ($note->type === 'pengganti_retur') · barang pengganti retur @endif</p></div>
</header>
<div class="actgrid2">
    <a class="btn primary" href="{{ route('delivery.print', $note) }}" target="_blank" rel="noopener"><x-icon name="print" :size="18" /> Cetak surat jalan</a>
    @if ($isOutlet && $note->type === 'regular' && $note->status === 'diterima' && $note->invoice)<a class="btn outline" href="{{ route('returns.create', ['surat_jalan' => $note->id]) }}">Ajukan retur</a>@endif
</div>
@error('received')<div class="alert" role="alert">{{ $message }}</div>@enderror
@php $pending = $note->status === 'dikirim'; @endphp
@if ($note->courier_id || $canAssign)
<section class="card" style="margin-bottom:12px" id="courierbox">
    <h3 class="sect">Pengantaran</h3>
    @if ($note->courier_id)
        <div class="livechip">
            <div class="who"><b>{{ $note->courier?->name ?? 'Kurir' }}</b><small id="cstatus">
                @if ($note->status !== 'dikirim' || $note->courier_status === 'sampai') Sudah sampai
                @elseif ($note->courier_status === 'diantar') Sedang diantar
                @else Menunggu kurir berangkat @endif
            </small></div>
            @if ($note->status === 'dikirim' && $note->courier_status !== 'sampai')<span class="badge {{ $note->courier_status === 'diantar' ? 'ok' : 'warn' }}">{{ $note->courier_status === 'diantar' ? 'Live' : 'Ditugaskan' }}</span>@endif
        </div>
        @if ($note->status === 'dikirim' && $note->courier_status === 'diantar')
            <div class="livemap" id="livemap" data-url="{{ route('delivery.position', $note) }}"></div>
            <small id="mapago" style="color:var(--muted)">Menunggu lokasi kurir…</small>
        @endif
    @else
        <p style="margin:0 0 10px;color:var(--muted)">Belum ada kurir untuk surat jalan ini.</p>
    @endif
    @if ($canAssign)
        <form method="POST" action="{{ route('delivery.courier', $note) }}" class="courierform">@csrf
            <select name="courier_id" aria-label="Pilih kurir">
                <option value="">— Pilih kurir —</option>
                @foreach ($couriers as $c)<option value="{{ $c->id }}" @selected((int) $note->courier_id === $c->id)>{{ $c->name }}</option>@endforeach
            </select>
            <button class="btn" type="submit">{{ $note->courier_id ? 'Ganti kurir' : 'Tugaskan' }}</button>
        </form>
        @if (! $couriers->count())<small style="color:var(--muted)">Belum ada akun Kurir. Buat di menu Pengguna (peran Kurir).</small>@endif
    @endif
</section>
@endif


@if ($isOutlet && $pending)
<form method="POST" action="{{ route('delivery.receive', $note) }}" class="card" novalidate data-confirm="Konfirmasi penerimaan? Invoice akan terbit sesuai qty yang Anda isi.">@csrf
    <h3 class="sect">Konfirmasi penerimaan</h3>
    <p style="color:var(--muted);margin:0 0 10px">Isi jumlah yang benar-benar diterima. Invoice dihitung dari jumlah ini.</p>
    <div class="recvlist">@foreach ($note->items as $row)
        <div class="recv">
            <div class="who"><b>{{ $row->item->name }}</b><small>Dikirim {{ Fmt::qty($row->qty_input) }} {{ $row->unit->name }}</small></div>
            <div class="inp"><input type="number" name="received[{{ $row->id }}]" value="{{ old("received.$row->id", $row->qty_input + 0) }}" min="0" max="{{ $row->qty_input + 0 }}" step="any" inputmode="decimal" aria-label="Diterima {{ $row->item->name }}"><span>{{ $row->unit->name }}</span></div>
        </div>
    @endforeach</div>
    <label class="field" style="margin-top:14px">Catatan <span class="hint">(mis. barang rusak, kurang)</span><input type="text" name="notes" maxlength="300" value="{{ old('notes') }}"></label>
    <button class="btn primary" type="submit">Konfirmasi diterima</button>
</form>
@else
<div class="card">
    <div class="kv">
        <div><small>Dikirim</small><b>{{ Fmt::date($note->shipped_at, 'j M Y H:i') }}</b></div>
        <div><small>Status</small><span class="badge {{ $pending ? 'warn' : 'ok' }}">{{ $pending ? 'Dalam pengiriman' : 'Diterima' }}</span></div>
        @if ($note->receipt)<div><small>Diterima</small><b>{{ Fmt::date($note->receipt->received_at, 'j M Y H:i') }}</b></div>@endif
        @if ($note->invoice)<div><small>Invoice</small><a class="link" href="{{ route('invoices.show', $note->invoice) }}">{{ $note->invoice->number }}</a></div>@endif
    </div>
    @if ($note->receipt?->notes)<p style="color:var(--muted)">Catatan penerimaan: {{ $note->receipt->notes }}</p>@endif
    <h3 class="sect" style="margin-top:18px">Barang dikirim</h3>
    <div>@foreach ($note->items as $row)
        <div class="mrow"><div class="who"><b>{{ $row->item->name }}</b></div><div class="val"><b>{{ Fmt::qtyBoth($row->qty_input, $row->unit->name, $row->qty_base, $row->item->baseUnit->name) }}</b></div></div>
    @endforeach</div>
</div>
@endif
@if ($note->courier_id && $note->status === 'dikirim' && $note->courier_status === 'diantar')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
  var el = document.getElementById('livemap'); if (!el) return;
  var ago = document.getElementById('mapago'), stat = document.getElementById('cstatus');
  var map = L.map(el, { zoomControl: true, attributionControl: true }).setView([-6.2, 106.65], 14);
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);
  var icon = L.divIcon({ className: '', html: '<div class="kmarker"></div>', iconSize: [22, 22], iconAnchor: [11, 11] });
  var marker = null, first = true, lastAt = null;
  function poll() {
    fetch(el.dataset.url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
      if (d.status === 'sampai' || d.delivered) { if (stat) stat.textContent = 'Sudah sampai'; ago.textContent = 'Kurir sudah sampai.'; setTimeout(function () { location.reload(); }, 1500); return; }
      if (d.lat == null) { ago.textContent = 'Menunggu lokasi kurir…'; return; }
      var ll = [d.lat, d.lng]; lastAt = d.at;
      if (!marker) marker = L.marker(ll, { icon: icon }).addTo(map); else marker.setLatLng(ll);
      if (first) { map.setView(ll, 16); first = false; } else if (!map.getBounds().pad(-0.2).contains(ll)) { map.panTo(ll); }
      ago.textContent = 'Terakhir update ' + d.ago;
    }).catch(function () {});
  }
  poll(); setInterval(poll, 6000);
})();
</script>
@endif
@endsection
