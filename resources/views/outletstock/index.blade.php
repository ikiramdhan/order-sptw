@extends('layouts.app')
@section('title', 'Stok outlet')
@section('content')
@use('App\Support\Fmt')
@php $isOutlet = auth()->user()->role === 'outlet'; $canInput = auth()->user()->hasRole('admin_ck') || $isOutlet; @endphp
<header class="page-head">
    <div><h1>Stok outlet</h1><p>{{ $outlet?->name }}</p></div>
    @if (auth()->user()->hasRole('admin_ck') && $pending)<a class="btn" href="{{ route('approvals.index') }}">{{ $pending }} menunggu persetujuan</a>@endif
</header>
@if (! $outlet)<div class="empty">Belum ada outlet.</div>@else
@if (! $isOutlet)
<form method="GET" class="toolbar"><select name="outlet" aria-label="Outlet" onchange="this.form.submit()">@foreach ($outlets as $o)<option value="{{ $o->id }}" @selected($o->id === $outlet->id)>{{ $o->name }}</option>@endforeach</select></form>
@endif
@if ($canInput)
<div class="actgrid" style="margin-bottom:12px">
    <a class="btn primary wide" href="{{ route('outletstock.sales', ['outlet' => $outlet->id]) }}">Upload penjualan ESB</a>
    <a class="btn" href="{{ route('outletstock.count', ['outlet' => $outlet->id]) }}">Stok opname</a>
    <a class="btn" href="{{ route('outletstock.waste', ['outlet' => $outlet->id]) }}">Catat waste</a>
    <a class="btn" href="{{ route('outletstock.recap', ['outlet' => $outlet->id]) }}">Rekap minusan</a>
</div>
@endif
@if (session('skipped') && count(session('skipped')))
<div class="alert" role="status"><b>Baris dilewati:</b> {{ implode('; ', array_slice(session('skipped'), 0, 8)) }}@if (count(session('skipped')) > 8) …@endif</div>
@endif
<div class="stats flat">
    <div class="stat"><small>Jenis barang</small><b>{{ $rows->count() }}</b></div>
    <div class="stat"><small>Terjual (bulan ini)</small><b>{{ Fmt::qty($sold) }}</b></div>
    <div class="stat warn"><small>Waste (bulan ini)</small><b>{{ Fmt::rp($wasteVal) }}</b></div>
</div>
<form method="GET" class="toolbar">@if (! $isOutlet)<input type="hidden" name="outlet" value="{{ $outlet->id }}">@endif
    <input type="search" name="q" value="{{ $q }}" placeholder="Cari barang" aria-label="Cari barang"></form>
<section class="list">
    @forelse ($rows as $r)
        <div class="mrow"><div class="who"><b>{{ $r->name }}</b>@if ($r->code)<small>{{ $r->code }}</small>@endif</div>
            <div class="val"><b class="{{ $r->qty < 0 ? 'low' : '' }}">{{ Fmt::qty($r->qty) }} {{ $r->unit }}</b>@if ($r->qty < 0)<span class="badge bad">Minus</span>@endif</div></div>
    @empty
        <div class="empty">Belum ada stok. Barang muncul setelah outlet menerima kiriman dari CK.</div>
    @endforelse
</section>
@php $histByDay = $history->groupBy('occurred_on'); @endphp
<section class="list"><h3 class="sect" style="margin:10px 0 0">Pergerakan terbaru</h3>
    @forelse ($histByDay as $day => $ms)
        <div class="dayhead"><b>{{ \Illuminate\Support\Carbon::parse($day)->translatedFormat('l, j M Y') }}</b><span>{{ $ms->count() }} pergerakan</span></div>
        @foreach ($ms as $m)
        <div class="mrow"><div class="who"><b>{{ $m->item_name }}</b>
            <small>{{ \App\Services\OutletStockService::LABELS[$m->type] ?? $m->type }}@if ($m->notes && $m->notes !== (\App\Services\OutletStockService::LABELS[$m->type] ?? '') && $m->notes !== 'Stok opname') · {{ $m->notes }}@endif@if ($m->user_name) · {{ $m->user_name }}@endif</small></div>
            <div class="val"><b style="color:{{ $m->qty_base < 0 ? 'var(--bad)' : 'var(--ok)' }}">{{ $m->qty_base > 0 ? '+' : '' }}{{ Fmt::qty($m->qty_base) }}</b><small>saldo {{ Fmt::qty($m->balance_after) }}</small></div></div>
        @endforeach
    @empty
        <div class="empty">Belum ada pergerakan.</div>
    @endforelse
</section>
@endif
@endsection
