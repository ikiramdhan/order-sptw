@extends('layouts.print')
@section('title', $note->number)
@section('content')
@use('App\Support\Fmt')
<h1 style="margin-top:18px">SURAT JALAN{{ $note->type === 'pengganti_retur' ? ' (PENGGANTI RETUR)' : '' }}</h1>
<div class="meta">
    <div><b>No.</b> {{ $note->number }}<br><b>Tanggal kirim</b> {{ Fmt::date($note->shipped_at, 'j F Y') }}<br><b>Ref. PO</b> {{ $note->purchaseOrder?->number }}</div>
    <div><b>Kepada</b><br>{{ $note->outlet->name }}<br>{{ $note->outlet->address }}<br>{{ $note->outlet->pic_name }} {{ $note->outlet->phone }}</div>
</div>
<table class="tbl">
    <thead><tr><th style="width:36px">No</th><th>Nama barang</th><th class="num">Jumlah</th><th class="num">Diterima</th></tr></thead>
    <tbody>@foreach ($note->items as $n => $row)
        <tr><td>{{ $n + 1 }}</td><td>{{ $row->item->name }}</td><td class="num">{{ Fmt::qtyBoth($row->qty_input, $row->unit->name, $row->qty_base, $row->item->baseUnit->name) }}</td><td class="num">&nbsp;</td></tr>
    @endforeach</tbody>
</table>
<div class="sign"><div>Dibuat oleh<span>Central Kitchen</span></div><div>Kurir<span>&nbsp;</span></div><div>Diterima oleh<span>{{ $note->outlet->name }}</span></div></div>
@endsection
