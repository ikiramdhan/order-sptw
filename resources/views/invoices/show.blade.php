@extends('layouts.app')
@section('title', $invoice->number)
@section('content')
@use('App\Support\Fmt')
@php
    $st = $invoice->status;
    if (in_array($st, ['belum_dibayar', 'sebagian'], true) && $invoice->due_at->lt(today())) $st = 'jatuh_tempo';
    [$sl, $sc] = \App\Models\Invoice::STATUS[$st] ?? [$st, 'info'];
@endphp
<header class="page-head">
    <div><h1>{{ $invoice->number }}</h1><p>{{ $invoice->outlet->name }}</p></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn" href="{{ route('invoices.print', $invoice) }}" target="_blank" rel="noopener"><x-icon name="print" :size="18" /> Cetak</a>
        <a class="btn" href="{{ route('invoices.index') }}"><x-icon name="back" :size="18" /> Kembali</a>
    </div>
</header>
<div class="two-col">
<div class="grow">
    <div class="card">
        <div class="kv">
            <div><small>Terbit</small><b>{{ Fmt::date($invoice->issued_at) }}</b></div>
            <div><small>Jatuh tempo</small><b>{{ Fmt::date($invoice->due_at) }}</b></div>
            <div><small>Surat jalan</small><b>{{ $invoice->deliveryNote?->number }}</b></div>
            <div><small>Status</small><span class="badge {{ $sc }}">{{ $sl }}</span></div>
        </div>
        <div class="tbl-wrap"><table class="tbl">
            <thead><tr><th>Barang</th><th class="num">Qty diterima</th><th class="num">Harga</th><th class="num">Subtotal</th></tr></thead>
            <tbody>@foreach ($invoice->items as $row)
                <tr><td>{{ $row->item->name }}</td>
                    <td class="num">{{ Fmt::qtyBoth($row->qty_input, $row->unit->name, $row->qty_base, $row->item->baseUnit->name) }}</td>
                    <td class="num">{{ Fmt::rp($row->unit_price_base) }}/{{ $row->item->baseUnit->name }}</td>
                    <td class="num">{{ Fmt::rp($row->subtotal) }}</td></tr>
            @endforeach</tbody>
            <tfoot>
                <tr><td colspan="3" class="num">Total</td><td class="num">{{ Fmt::rp($invoice->total) }}</td></tr>
                @if ($invoice->credit_applied > 0)<tr><td colspan="3" class="num">Potongan retur / kredit</td><td class="num">- {{ Fmt::rp($invoice->credit_applied) }}</td></tr>@endif
                <tr><td colspan="3" class="num">Sudah dibayar</td><td class="num">{{ Fmt::rp($invoice->paid) }}</td></tr>
                <tr><td colspan="3" class="num">Sisa tagihan</td><td class="num">{{ Fmt::rp($invoice->remaining()) }}</td></tr>
            </tfoot>
        </table></div>
    </div>
</div>
<div class="side">
    <div class="card">
        <h3 class="sect">Pembayaran</h3>
        @forelse ($invoice->payments as $pay)
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--bg-2)"><span>{{ Fmt::date($pay->paid_at) }} · {{ $methods[$pay->method] ?? $pay->method }}</span><b>{{ Fmt::rp($pay->amount) }}</b></div>
        @empty
            <p style="color:var(--muted);margin:0">Belum ada pembayaran.</p>
        @endforelse
        @if (! $isOutlet && $invoice->remaining() > 0 && $invoice->status !== 'dibatalkan')
            <form method="POST" action="{{ route('invoices.pay', $invoice) }}" style="margin-top:14px" novalidate>@csrf
                <label class="field">Jumlah diterima (Rp)
                    <input type="text" data-money name="amount" value="{{ number_format((int) round((float) old('amount', $invoice->remaining())), 0, ',', '.') }}" inputmode="numeric" autocomplete="off" required>
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
