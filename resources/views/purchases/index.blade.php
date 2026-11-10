@extends('layouts.app')
@section('title', 'Pembelian')
@section('content')
@use('App\Support\Fmt')
<header class="page-head">
    <div><h1>Pembelian supplier</h1><p>Catat belanja bahan, terima barang, dan lunasi hutang</p></div>
    @if (auth()->user()->hasRole('admin_ck'))
        <a class="btn primary" href="{{ route('purchases.create') }}"><x-icon name="plus" :size="18" /> Catat pembelian</a>
    @endif
</header>
@if (auth()->user()->hasRole('admin_ck'))
<div class="stats">
    <div class="stat"><small>Hutang ke supplier</small><b>{{ Fmt::rp($unpaid) }}</b></div>
    <div class="stat warn"><small>Lewat jatuh tempo</small><b>{{ Fmt::rp($overdue) }}</b></div>
</div>
@endif
<form method="GET" class="toolbar">
    <select name="status" onchange="this.form.submit()" aria-label="Filter status">
        <option value="semua">Semua status</option>
        @foreach (\App\Models\SupplierPurchase::STATUS as $k => [$label])
            <option value="{{ $k }}" @selected($status === $k)>{{ $label }}</option>
        @endforeach
    </select>
</form>
<section class="list">
    @forelse ($purchases as $p)
        @php [$sl, $sc] = \App\Models\SupplierPurchase::STATUS[$p->payment_status] ?? [$p->payment_status, 'info']; @endphp
        <a class="item" href="{{ route('purchases.show', $p) }}" style="text-decoration:none">
            <div class="who"><b>{{ $p->number }} · {{ $p->supplier->name }}</b><small>Beli {{ Fmt::date($p->purchased_at) }} · jatuh tempo {{ Fmt::date($p->due_at) }}</small></div>
            <div class="col"><b>{{ Fmt::rp($p->total) }}</b><small>sisa {{ Fmt::rp($p->remaining()) }}</small></div>
            <span class="badge {{ $p->received_at ? 'ok' : 'warn' }}">{{ $p->received_at ? 'Barang diterima' : 'Belum diterima' }}</span>
            <span class="badge {{ $sc }}">{{ $sl }}</span>
        </a>
    @empty
        <div class="empty">Belum ada pembelian.</div>
    @endforelse
</section>
@endsection
