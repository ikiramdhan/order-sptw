@extends('layouts.app')
@section('title', $po->number)
@section('content')
@use('App\Support\Fmt')
@php [$sl, $sc] = $po->statusLabel(); $open = $po->isOpenForShipping(); @endphp
<header class="page-head compact">
    <div><h1>{{ $po->number }}</h1><p>{{ $po->outlet->name }} · diajukan {{ Fmt::date($po->submitted_at ?? $po->created_at, 'j M Y H:i') }}</p></div>
    <div style="display:flex;gap:8px;align-items:center"><span class="badge {{ $sc }}">{{ $sl }}</span><a class="btn" href="{{ route('orders.index') }}"><x-icon name="back" :size="18" /> Kembali</a></div>
</header>
@foreach (['ship','cancel','reason'] as $k)@error($k)<div class="alert" role="alert">{{ $message }}</div>@enderror @endforeach
@if ($po->notes)<div class="hint-box">Catatan outlet: {{ $po->notes }}</div>@endif

@if ($canProcess && $open)
<form method="POST" action="{{ route('orders.ship', $po) }}" class="card" novalidate>@csrf
    <h3 class="sect">Setujui &amp; kirim</h3>
    <p style="color:var(--muted);margin:0 0 10px">Qty kirim diisi otomatis sebesar yang bisa dipenuhi stok. Ubah bila perlu; sisanya menjadi backorder.</p>
    <div class="tbl-wrap"><table class="tbl stackable">
        <thead><tr><th>Barang</th><th class="num">Dipesan</th><th class="num">Sisa</th><th class="num">Stok</th><th style="min-width:110px">Kirim sekarang</th></tr></thead>
        <tbody>@foreach ($po->items as $row)
            @php
                $f = $row->factor(); $remBase = $row->remainingBase();
                $stock = $row->item->availableStock();
                $default = $remBase > 0 ? floor(min($remBase, $stock) / $f * 1000) / 1000 : 0;
            @endphp
            <tr>
                <td>{{ $row->item->name }}</td>
                <td class="num">{{ Fmt::qty($row->qty_input) }} {{ $row->unit->name }}</td>
                <td class="num">{{ Fmt::qty($remBase / $f) }} {{ $row->unit->name }}</td>
                <td class="num {{ $stock < $remBase ? 'low' : '' }}">{{ Fmt::qty($stock) }} {{ $row->item->baseUnit->name }}</td>
                <td>@if ($remBase > 0.0005)<input type="number" name="ship[{{ $row->id }}]" value="{{ old("ship.$row->id", $default + 0) }}" min="0" step="any" inputmode="decimal" aria-label="Kirim {{ $row->item->name }}">@else<span class="badge ok">Lengkap</span>@endif</td>
            </tr>
        @endforeach</tbody>
    </table></div>
    <div class="savebar"><button class="btn primary" type="submit">Setujui &amp; buat surat jalan</button></div>
</form>
@else
<div class="card">
    <h3 class="sect">Isi pesanan</h3>
    <div class="tbl-wrap"><table class="tbl">
        <thead><tr><th>Barang</th><th class="num">Dipesan</th><th class="num">Terkirim</th><th class="num">Sisa</th><th class="num">Harga</th></tr></thead>
        <tbody>@foreach ($po->items as $row)
            @php $f = $row->factor(); @endphp
            <tr><td>{{ $row->item->name }}</td>
                <td class="num">{{ Fmt::qty($row->qty_input) }} {{ $row->unit->name }}</td>
                <td class="num">{{ Fmt::qty($row->qty_shipped_base / $f) }} {{ $row->unit->name }}</td>
                <td class="num">{{ Fmt::qty($row->remainingBase() / $f) }} {{ $row->unit->name }}</td>
                <td class="num">{{ Fmt::rp(round($row->unit_price_base * $f)) }}</td></tr>
        @endforeach</tbody>
        <tfoot><tr><td colspan="4" class="num">Nilai pesanan</td><td class="num">{{ Fmt::rp($po->totalValue()) }}</td></tr></tfoot>
    </table></div>
</div>
@endif

@if ($po->deliveryNotes->count())
<div class="card">
    <h3 class="sect">Surat jalan</h3>
    @foreach ($po->deliveryNotes as $n)
        <a class="item" href="{{ route('delivery.show', $n) }}" style="text-decoration:none">
            <div class="who"><b>{{ $n->number }}</b><small>Dikirim {{ Fmt::date($n->shipped_at, 'j M Y H:i') }}</small></div>
            <span class="badge {{ $n->status === 'diterima' ? 'ok' : 'info' }}">{{ $n->status === 'diterima' ? 'Diterima' : 'Dalam pengiriman' }}</span>
        </a>
    @endforeach
</div>
@endif

@if ($canProcess && $open && $po->hasBackorder() && $po->deliveryNotes->count())
<form method="POST" action="{{ route('orders.backorder.cancel', $po) }}" class="card" data-confirm="Batalkan sisa backorder? Sisa barang tidak akan dikirim.">@csrf
    <h3 class="sect">Batalkan sisa backorder</h3>
    <div class="cancelrow"><label class="field">Alasan<input type="text" name="reason" maxlength="200" placeholder="mis. stok tidak tersedia" required></label>
    <button class="btn danger" type="submit">Batalkan sisa</button></div>
</form>
@endif

@if (in_array($po->status, ['diajukan', 'menunggu_backorder'], true) && ! $po->deliveryNotes->count() && ($canProcess || ($isOutlet && $po->status === 'diajukan')))
<form method="POST" action="{{ route('orders.cancel', $po) }}" class="card" data-confirm="Batalkan PO ini?">@csrf
    <h3 class="sect">Batalkan PO</h3>
    <div class="cancelrow"><label class="field">Alasan<input type="text" name="reason" maxlength="200" required></label>
    <button class="btn danger" type="submit">Batalkan PO</button></div>
</form>
@endif
@endsection
