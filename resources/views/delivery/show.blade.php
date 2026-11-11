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
@if ($note->courier_id && $note->status === 'dikirim' && auth()->user()->hasRole('admin_ck') && $note->outlet->lat === null)@include('partials.outlet-location', ['outlet' => $note->outlet])@endif
@if ($note->courier_id || $canAssign)
<section class="card dlv" style="margin-bottom:12px" id="courierbox">
    <h3 class="sect">Pengantaran</h3>
    @if ($note->courier_id)
        @php($nm = $note->courier?->name ?? 'Kurir')
        <div class="dlv-head">
            <div class="dlv-av">{{ collect(explode(' ', $nm))->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') }}</div>
            <div class="dlv-who"><b>{{ $nm }}</b><small id="cstatus">
                @if ($note->status !== 'dikirim' || $note->courier_status === 'sampai') Sudah sampai
                @elseif ($note->courier_status === 'diantar') Sedang diantar
                @else Menunggu kurir berangkat @endif
            </small></div>
            @if ($note->status === 'dikirim' && $note->courier_status !== 'sampai')<span class="dlv-pill {{ $note->courier_status === 'diantar' ? 'live' : '' }}">@if ($note->courier_status === 'diantar')<i></i>LIVE @else Ditugaskan @endif</span>@endif
        </div>
        @if ($note->status === 'dikirim' && $note->courier_status === 'diantar')
            <div class="dlv-eta"><div class="wind"><i></i><i></i><i></i><i></i><i></i></div>
                <div class="dlv-msg" id="herosub">{{ $isOutlet ? 'Kurir menuju lokasi kamu' : 'Kurir menuju '.$note->outlet->name }}</div>
                @if ($note->outlet->lat !== null)
                <div id="etabar" hidden>
                    <div class="dlv-big"><b id="etamin">–</b><small id="etaunit"></small><span id="etadist"></span></div>
                    <div class="etatrack"><div class="etafill" id="etafill"></div><div class="etarider" id="etarider">@include('partials.motor')</div><i class="etapin"></i></div>
                    <div class="dlv-ends"><span class="dlv-end a"><i></i><em>Kurir</em><b>{{ \Illuminate\Support\Str::before($nm, ' ') }}</b></span><span class="dlv-end b"><i></i><em>Tujuan</em><b>{{ $note->outlet->name }}</b></span></div>
                </div>
                @else
                <div class="dlv-road"><span class="road"></span>@include('partials.motor')</div>
                @if (! $isOutlet)<small class="dlv-warn">Estimasi belum tersedia: titik lokasi outlet belum diisi.</small>@endif
                @endif
            </div>
            <span id="livemap" data-url="{{ route('delivery.position', $note) }}" hidden></span>
            <a class="btn primary block dlv-btn" href="{{ route('delivery.track', $note) }}"><x-icon name="search" :size="18" /> Lacak di peta</a>
            <small id="mapago" class="dlv-ago">Menunggu lokasi kurir…</small>
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
@if ($note->courier_id)@include('partials.chat', ['note' => $note])@endif


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
<script>
(function () {
  var el = document.getElementById('livemap'); if (!el) return;
  var ago = document.getElementById('mapago'), stat = document.getElementById('cstatus');
  var bar = document.getElementById('etabar'), fill = document.getElementById('etafill'), rider = document.getElementById('etarider');
  var emin = document.getElementById('etamin'), eunit = document.getElementById('etaunit'), edist = document.getElementById('etadist'), hero = document.getElementById('herosub');
  function fmtDist(m) { return m < 1000 ? Math.round(m / 10) * 10 + ' m lagi' : (m / 1000).toFixed(1).replace('.', ',') + ' km lagi'; }
  function poll() {
    fetch(el.dataset.url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
      if (d.status === 'sampai' || d.delivered) { if (stat) stat.textContent = 'Sudah sampai'; ago.textContent = 'Kurir sudah sampai.'; setTimeout(function () { location.reload(); }, 1500); return; }
      if (bar && d.eta_s != null) {
        bar.hidden = false;
        var pct = Math.round((d.progress || 0) * 100);
        fill.style.width = pct + '%'; rider.style.left = Math.max(4, Math.min(88, pct)) + '%';
        var m = Math.max(1, Math.round(d.eta_s / 60));
        if (d.dist_m < 80) { emin.textContent = 'Tiba'; eunit.textContent = ''; }
        else if (m < 60) { emin.textContent = m; eunit.textContent = 'menit'; }
        else { emin.textContent = Math.floor(m / 60) + ':' + ('0' + (m % 60)).slice(-2); eunit.textContent = 'jam'; }
        edist.textContent = fmtDist(d.dist_m);
        if (hero) hero.textContent = d.dist_m < 80 ? 'Kurir sudah di depan' : 'Kurir dalam perjalanan';
      }
      ago.textContent = d.ago ? 'Terakhir update ' + d.ago : 'Menunggu lokasi kurir…';
    }).catch(function () {});
  }
  poll(); setInterval(poll, 6000);
})();
</script>
@endif
@endsection
