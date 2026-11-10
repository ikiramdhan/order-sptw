@extends('layouts.app')
@section('title', $purchase->number)
@section('content')
@use('App\Support\Fmt')
@php
    $isAdmin = auth()->user()->hasRole('admin_ck');
    [$sl, $sc] = $purchase->statusLabel();
@endphp
<header class="page-head">
    <div><h1>{{ $purchase->number }}</h1><p>{{ $purchase->supplier->name }}@if ($purchase->supplier->bankLine()) · {{ $purchase->supplier->bankLine() }}@endif</p></div>
    <a class="btn" href="{{ route('purchases.index') }}"><x-icon name="back" :size="18" /> Kembali</a>
</header>
@error('receive')<div class="alert" role="alert">{{ $message }}</div>@enderror
<div class="two-col">
<div class="grow stack">
    <div class="card">
        <div class="kv">
            <div><small>Tanggal beli</small><b>{{ Fmt::date($purchase->purchased_at) }}</b></div>
            <div><small>Jatuh tempo</small><b>{{ Fmt::date($purchase->due_at) }}</b></div>
            <div><small>Barang</small><b>{{ $purchase->received_at ? 'Diterima '.Fmt::date($purchase->received_at) : 'Belum diterima' }}</b></div>
            <div><small>Pembayaran</small><span class="badge {{ $sc }}">{{ $sl }}</span></div>
        </div>
        @if ($purchase->notes)<p style="color:var(--muted)">{{ $purchase->notes }}</p>@endif
        <div class="tbl-wrap"><table class="tbl">
            <thead><tr><th>Barang</th><th class="num">Qty</th><th class="num">Harga</th><th class="num">Subtotal</th></tr></thead>
            <tbody>@foreach ($purchase->items as $row)
                <tr><td>{{ $row->item->name }}</td>
                    <td class="num">{{ Fmt::qtyBoth($row->qty_input, $row->unit->name, $row->qty_base, $row->item->baseUnit->name ?? $row->unit->name) }}</td>
                    <td class="num">{{ Fmt::rp($row->unit_price) }}</td><td class="num">{{ Fmt::rp($row->subtotal) }}</td></tr>
            @endforeach</tbody>
            <tfoot><tr><td colspan="3" class="num">Total</td><td class="num">{{ Fmt::rp($purchase->total) }}</td></tr></tfoot>
        </table></div>
        @if (! $purchase->received_at)
            <form method="POST" action="{{ route('purchases.receive', $purchase) }}" style="margin-top:14px" data-confirm="Tandai barang sudah diterima? Stok akan bertambah.">@csrf
                <button class="btn primary" type="submit">Barang sudah diterima, tambah stok</button>
            </form>
        @endif
    </div>
</div>
<div class="side stack">
    <div class="card">
        <h3 class="sect">Pembayaran</h3>
        <div class="kv"><div><small>Dibayar</small><b>{{ Fmt::rp($purchase->paid) }}</b></div><div><small>Sisa</small><b>{{ Fmt::rp($purchase->remaining()) }}</b></div></div>
        @foreach ($purchase->payments as $pay)
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-top:1px solid var(--bg-2)"><span>{{ Fmt::date($pay->paid_at) }} · {{ $methods[$pay->method] ?? $pay->method }}</span><b>{{ Fmt::rp($pay->amount) }}</b></div>
        @endforeach
        @if ($isAdmin && $purchase->remaining() > 0)
            <form method="POST" action="{{ route('purchases.pay', $purchase) }}" style="margin-top:14px" novalidate>@csrf
                <label class="field">Jumlah bayar (Rp)
                    <input type="text" data-money name="amount" value="{{ number_format((int) round((float) old('amount', $purchase->remaining())), 0, ',', '.') }}" inputmode="numeric" autocomplete="off" required>
                    @error('amount')<span class="err">{{ $message }}</span>@enderror
                </label>
                <div class="row">
                    <label class="field">Tanggal<input type="date" name="paid_at" value="{{ old('paid_at', today()->toDateString()) }}" required></label>
                    <label class="field">Cara<select name="method">@foreach ($methods as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></label>
                </div>
                <button class="btn primary block" type="submit">Catat pembayaran</button>
            </form>
        @endif
    </div>
</div>
</div>
@endsection
