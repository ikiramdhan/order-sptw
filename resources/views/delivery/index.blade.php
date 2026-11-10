@extends('layouts.app')
@section('title', 'Surat jalan')
@section('content')
@use('App\Support\Fmt')
<header class="page-head">
    <div><h1>{{ $isOutlet ? 'Penerimaan barang' : 'Surat jalan' }}</h1><p>{{ $isOutlet ? 'Konfirmasi barang yang tiba untuk menerbitkan invoice' : 'Surat jalan tanpa harga untuk dibawa kurir' }}</p></div>
</header>
<section class="list">
    @forelse ($notes as $n)
        <a class="item" href="{{ route('delivery.show', $n) }}" style="text-decoration:none">
            <div class="who"><b>{{ $n->number }}@unless($isOutlet) · {{ $n->outlet->name }}@endunless</b><small>PO {{ $n->purchaseOrder?->number }} · dikirim {{ Fmt::date($n->shipped_at, 'j M Y H:i') }} · {{ $n->items->count() }} barang</small></div>
            <span class="badge {{ $n->status === 'diterima' ? 'ok' : 'warn' }}">{{ $n->status === 'diterima' ? 'Diterima' : ($isOutlet ? 'Perlu dikonfirmasi' : 'Dalam pengiriman') }}</span>
        </a>
    @empty
        <div class="empty">Belum ada surat jalan.</div>
    @endforelse
</section>
@endsection
