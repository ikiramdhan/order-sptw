@extends('layouts.print')
@section('title', $note->number)
@section('content')
@use('App\Support\Fmt')
<h1 style="margin-top:18px">NOTA RETUR</h1>
<div class="meta">
    <div><b>No.</b> {{ $note->number }}<br><b>Tanggal</b> {{ Fmt::date($note->issued_at, 'j F Y') }}<br><b>Ref. retur</b> {{ $ret->number }}<br><b>Ref. invoice</b> {{ $ret->invoice?->number ?? '-' }}</div>
    <div><b>Kepada</b><br>{{ $note->outlet->name }}<br>{{ $note->outlet->address }}</div>
</div>
<table class="tbl">
    <thead><tr><th>Barang</th><th class="num">Qty</th><th class="num">Nilai</th></tr></thead>
    <tbody>@foreach ($ret->items as $row)
        <tr><td>{{ $row->item->name }}</td><td class="num">{{ Fmt::qtyBoth($row->qty_input, $row->unit->name, $row->qty_base, $row->item->baseUnit->name) }}</td><td class="num">{{ Fmt::rp($row->value) }}</td></tr>
    @endforeach</tbody>
    <tfoot>
        <tr><td colspan="2" class="num">Total nota retur</td><td class="num">{{ Fmt::rp($note->amount) }}</td></tr>
        <tr><td colspan="2" class="num">Memotong invoice</td><td class="num">{{ Fmt::rp($note->applied_to_invoice) }}</td></tr>
        <tr><td colspan="2" class="num">Menjadi saldo kredit</td><td class="num">{{ Fmt::rp($note->to_balance) }}</td></tr>
    </tfoot>
</table>
@endsection
