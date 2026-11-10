@extends('layouts.print')
@section('title', 'Rekap '.$label)
@section('content')
@use('App\Support\Fmt')
<h1 style="margin-top:18px">REKAP TAGIHAN {{ strtoupper($label) }}</h1>
@if ($outlet)
<div class="meta"><div><b>Outlet</b><br>{{ $outlet->name }}<br>{{ $outlet->address }}</div><div><b>Dicetak</b> {{ Fmt::date(today(), 'j F Y') }}</div></div>
<table class="tbl">
    <thead><tr><th>Tanggal</th><th>Invoice</th><th class="num">Tagihan</th><th class="num">Dibayar</th><th class="num">Sisa</th><th>Status</th></tr></thead>
    <tbody>@foreach ($invoices as $i)
        <tr><td>{{ Fmt::date($i->issued_at, 'j M') }}</td><td>{{ $i->number }}</td><td class="num">{{ Fmt::rp($i->total - $i->credit_applied) }}</td><td class="num">{{ Fmt::rp($i->paid) }}</td><td class="num">{{ Fmt::rp($i->remaining()) }}</td><td>{{ \App\Models\Invoice::STATUS[$i->status][0] ?? $i->status }}</td></tr>
    @endforeach</tbody>
    <tfoot><tr><td colspan="2" class="num">Total bulan ini</td><td class="num">{{ Fmt::rp($totalAll) }}</td><td class="num">{{ Fmt::rp($paidAll) }}</td><td class="num">{{ Fmt::rp($remainingAll) }}</td><td></td></tr></tfoot>
</table>
@else
<p>Pilih outlet terlebih dahulu.</p>
@endif
@endsection
