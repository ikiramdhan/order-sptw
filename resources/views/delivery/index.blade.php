@extends('layouts.app')
@section('title', 'Surat jalan')
@section('content')
@use('App\Support\Fmt')
<header class="page-head">
    <div><h1>{{ $isOutlet ? 'Penerimaan barang' : 'Surat jalan' }}</h1><p>{{ $isOutlet ? 'Konfirmasi barang yang tiba untuk menerbitkan invoice' : 'Surat jalan tanpa harga untuk dibawa kurir' }}</p></div>
</header>
@php $byDay = $notes->groupBy(fn ($n) => $n->shipped_at->toDateString()); @endphp
<section class="list">
    @forelse ($byDay as $day => $rows)
        <div class="dayhead"><b>{{ \Illuminate\Support\Carbon::parse($day)->translatedFormat('l, j M Y') }}</b><span>{{ $rows->count() }} surat jalan</span></div>
        @foreach ($rows as $n)
        <a class="item" href="{{ route('delivery.show', $n) }}" style="text-decoration:none">
            <div class="who"><b>{{ $n->number }}@unless($isOutlet) · {{ $n->outlet->name }}@endunless</b><small>{{ $n->purchaseOrder?->number }} · {{ $n->shipped_at->format('H:i') }} · {{ $n->items->count() }} barang</small></div>
            <span class="badge {{ $n->status === 'diterima' ? 'ok' : 'warn' }}">{{ $n->status === 'diterima' ? 'Diterima' : ($isOutlet ? 'Perlu dikonfirmasi' : 'Dalam pengiriman') }}</span>
        </a>
        @endforeach
    @empty
        <div class="empty">Belum ada surat jalan.</div>
    @endforelse
</section>
@endsection
