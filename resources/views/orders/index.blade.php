@extends('layouts.app')
@section('title', 'PO')
@section('content')
@use('App\Support\Fmt')
<header class="page-head">
    <div><h1>{{ $isOutlet ? 'PO saya' : 'Antrean PO' }}</h1><p>{{ $isOutlet ? 'Riwayat dan status pesanan ke Central Kitchen' : 'Setujui dan kirim pesanan outlet, penuh atau sebagian' }}</p></div>
    @if ($isOutlet)<a class="btn primary" href="{{ route('catalog.index') }}"><x-icon name="plus" :size="18" /> Buat PO</a>@endif
</header>
<form method="GET" class="toolbar">
    <select name="status" onchange="this.form.submit()" aria-label="Filter">
        <option value="aktif" @selected($filter==='aktif')>Yang berjalan</option>
        <option value="semua" @selected($filter==='semua')>Semua</option>
        @foreach (\App\Models\PurchaseOrder::STATUS as $k => [$label])<option value="{{ $k }}" @selected($filter===$k)>{{ $label }}</option>@endforeach
    </select>
</form>
<section class="list">
    @forelse ($orders as $o)
        @php [$sl, $sc] = $o->statusLabel(); @endphp
        <a class="item" href="{{ route('orders.show', $o) }}" style="text-decoration:none">
            <div class="who"><b>{{ $o->number }}@unless($isOutlet) · {{ $o->outlet->name }}@endunless</b><small>Diajukan {{ Fmt::date($o->submitted_at ?? $o->created_at, 'j M Y H:i') }} · {{ $o->items->count() }} barang</small></div>
            <div class="col"><b>{{ Fmt::rp($o->totalValue()) }}</b><small>nilai pesanan</small></div>
            <span class="badge {{ $sc }}">{{ $sl }}</span>
        </a>
    @empty
        <div class="empty">Tidak ada PO.</div>
    @endforelse
</section>
@endsection
