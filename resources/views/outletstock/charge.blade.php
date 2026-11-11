@extends('layouts.app')
@section('title', $charge->number)
@section('content')
@use('App\Support\Fmt')
<header class="page-head compact">
    <div><h1>{{ $charge->number }}</h1><p>Tagihan minusan · {{ $charge->outlet->name }} · {{ $charge->period }}</p></div>
    <div style="display:flex;gap:8px;align-items:center"><span class="badge {{ $charge->status === 'lunas' ? 'ok' : 'warn' }}">{{ $charge->status === 'lunas' ? 'Lunas' : 'Belum dibayar' }}</span>
        <a class="btn" href="{{ route('outletstock.recap', ['outlet' => $charge->outlet_id, 'bulan' => $charge->period]) }}"><x-icon name="back" :size="18" /> Kembali</a></div>
</header>
@error('charge')<div class="alert bad" role="alert">{{ $message }}</div>@enderror
<div class="stats">
    <div class="stat warn"><small>Total tagihan</small><b>{{ Fmt::rp($charge->total) }}</b></div>
    <div class="stat"><small>Jatuh tempo</small><b>{{ Fmt::date($charge->due_at) }}</b></div>
</div>
<div class="card"><h3 class="sect">Rincian</h3>
    <div class="tbl-wrap"><table class="tbl stackable">
        <thead><tr><th>Barang</th><th>Keterangan</th><th class="num">Jumlah</th><th class="num">Harga CK</th><th class="num">Nilai</th></tr></thead>
        <tbody>@foreach ($charge->items as $i)
            <tr><td data-label="Barang">{{ $i->item->name }}</td><td data-label="Keterangan">{{ $i->description }}</td>
                <td class="num" data-label="Jumlah">{{ Fmt::qty($i->qty_base) }} {{ $i->item->baseUnit->name }}</td>
                <td class="num" data-label="Harga CK">{{ Fmt::rp($i->unit_price) }}</td><td class="num" data-label="Nilai">{{ Fmt::rp($i->value) }}</td></tr>
        @endforeach</tbody>
        <tfoot><tr><td colspan="4" class="num">Total</td><td class="num">{{ Fmt::rp($charge->total) }}</td></tr></tfoot>
    </table></div>
</div>
@if (auth()->user()->hasRole('admin_ck') && $charge->status !== 'lunas')
<form method="POST" action="{{ route('charges.paid', $charge) }}" data-confirm="Tandai tagihan ini lunas?">@csrf<div class="savebar"><button class="btn primary" type="submit">Tandai lunas</button></div></form>
@elseif ($charge->status === 'lunas')<p style="color:var(--muted)">Lunas pada {{ Fmt::date($charge->paid_at) }}.</p>@endif
@endsection
