@extends('layouts.print')
@section('title', $invoice->number)
@section('content')
@use('App\Support\Fmt')
<h1 style="margin-top:18px">INVOICE</h1>
<div class="meta">
    <div><b>No.</b> {{ $invoice->number }}<br><b>Tanggal</b> {{ Fmt::date($invoice->issued_at, 'j F Y') }}<br><b>Jatuh tempo</b> {{ Fmt::date($invoice->due_at, 'j F Y') }}<br><b>Ref. surat jalan</b> {{ $invoice->deliveryNote?->number }}</div>
    <div><b>Ditagihkan ke</b><br>{{ $invoice->outlet->name }}<br>{{ $invoice->outlet->address }}<br>{{ $invoice->outlet->pic_name }}</div>
</div>
<table class="tbl">
    <thead><tr><th>Barang</th><th class="num">Qty diterima</th><th class="num">Harga</th><th class="num">Subtotal</th></tr></thead>
    <tbody>@foreach ($invoice->items as $row)
        <tr><td>{{ $row->item->name }}</td>
            <td class="num">{{ Fmt::qtyBoth($row->qty_input, $row->unit->name, $row->qty_base, $row->item->baseUnit->name) }}</td>
            <td class="num">{{ Fmt::rp($row->unit_price_base) }}/{{ $row->item->baseUnit->name }}</td><td class="num">{{ Fmt::rp($row->subtotal) }}</td></tr>
    @endforeach</tbody>
    <tfoot>
        <tr><td colspan="3" class="num">Total</td><td class="num">{{ Fmt::rp($invoice->total) }}</td></tr>
        @if ($invoice->credit_applied > 0)<tr><td colspan="3" class="num">Potongan retur / kredit</td><td class="num">- {{ Fmt::rp($invoice->credit_applied) }}</td></tr>@endif
        @if ($invoice->paid > 0)<tr><td colspan="3" class="num">Sudah dibayar</td><td class="num">- {{ Fmt::rp($invoice->paid) }}</td></tr>@endif
        <tr><td colspan="3" class="num">Sisa tagihan</td><td class="num">{{ Fmt::rp($invoice->remaining()) }}</td></tr>
    </tfoot>
</table>
@endsection
