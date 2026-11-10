@extends('layouts.app')
@section('title', 'Stok outlet')
@section('content')
@use('App\Support\Fmt')
@php $isOutlet = auth()->user()->role === 'outlet'; $canInput = auth()->user()->hasRole('admin_ck') || $isOutlet; @endphp
<header class="page-head">
    <div><h1>Stok outlet{{ $outlet ? ' · '.$outlet->name : '' }}</h1><p>Masuk dari PO ke CK, terjual dari laporan ESB, ditambah opname dan waste</p></div>
    @if (auth()->user()->hasRole('admin_ck') && $pending)<a class="btn" href="{{ route('approvals.index') }}">{{ $pending }} menunggu persetujuan</a>@endif
</header>
@if (! $outlet)<div class="empty">Belum ada outlet.</div>@else
@if (! $isOutlet)
<form method="GET" class="toolbar"><select name="outlet" aria-label="Outlet" onchange="this.form.submit()">@foreach ($outlets as $o)<option value="{{ $o->id }}" @selected($o->id === $outlet->id)>{{ $o->name }}</option>@endforeach</select></form>
@endif
@if ($canInput)
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
    <a class="btn primary" href="{{ route('outletstock.sales', ['outlet' => $outlet->id]) }}">Upload penjualan ESB</a>
    <a class="btn" href="{{ route('outletstock.count', ['outlet' => $outlet->id]) }}">Stok opname</a>
    <a class="btn" href="{{ route('outletstock.waste', ['outlet' => $outlet->id]) }}">Catat waste</a>
</div>
@endif
@if (session('skipped') && count(session('skipped')))
<div class="alert" role="status"><b>Baris dilewati:</b> {{ implode('; ', array_slice(session('skipped'), 0, 8)) }}@if (count(session('skipped')) > 8) …@endif</div>
@endif
<div class="stats">
    <div class="stat"><small>Jenis barang</small><b>{{ $rows->count() }}</b></div>
    <div class="stat"><small>Terjual bulan ini</small><b>{{ Fmt::qty($sold) }}</b></div>
    <div class="stat warn"><small>Waste bulan ini</small><b>{{ Fmt::rp($wasteVal) }}</b></div>
</div>
<form method="GET" class="toolbar">@if (! $isOutlet)<input type="hidden" name="outlet" value="{{ $outlet->id }}">@endif
    <input type="search" name="q" value="{{ $q }}" placeholder="Cari barang" aria-label="Cari barang"></form>
<section class="list">
    @forelse ($rows as $r)
        <div class="item"><div class="who"><b>{{ $r->name }}</b>@if ($r->code)<small>{{ $r->code }}</small>@endif</div>
            <div class="col"><b class="{{ $r->qty < 0 ? 'low' : '' }}">{{ Fmt::qty($r->qty) }} {{ $r->unit }}</b></div>
            @if ($r->qty < 0)<span class="badge bad">Minus</span>@endif</div>
    @empty
        <div class="empty">Belum ada stok. Barang muncul setelah outlet menerima kiriman dari CK.</div>
    @endforelse
</section>
<div class="card">
    <h3 class="sect">Pergerakan terbaru</h3>
    <div class="tbl-wrap"><table class="tbl stackable">
        <thead><tr><th>Tanggal</th><th>Barang</th><th>Jenis</th><th class="num">Jumlah</th><th class="num">Saldo</th><th>Oleh</th></tr></thead>
        <tbody>@forelse ($history as $m)
            <tr><td>{{ \Illuminate\Support\Carbon::parse($m->occurred_on)->translatedFormat('j M') }}</td><td>{{ $m->item_name }}</td>
                <td>{{ \App\Services\OutletStockService::LABELS[$m->type] ?? $m->type }}@if ($m->notes)<br><small style="color:var(--muted)">{{ $m->notes }}</small>@endif</td>
                <td class="num" style="color:{{ $m->qty_base < 0 ? 'var(--bad)' : 'var(--ok)' }}">{{ $m->qty_base > 0 ? '+' : '' }}{{ Fmt::qty($m->qty_base) }}</td>
                <td class="num">{{ Fmt::qty($m->balance_after) }}</td><td>{{ $m->user_name ?? '-' }}</td></tr>
        @empty<tr><td colspan="6" class="empty">Belum ada pergerakan.</td></tr>@endforelse</tbody>
    </table></div>
</div>
@endif
@endsection
