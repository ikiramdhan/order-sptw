@extends('layouts.app')
@section('title', 'Rekap opname')
@section('content')
@use('App\Support\Fmt')
<header class="page-head">
    <div><h1>Rekap stok opname{{ $outlet ? ' · '.$outlet->name : '' }}</h1><p>Selisih hasil opname dalam rupiah (harga CK saat opname disetujui). Selisih kurang menjadi tanggungan outlet.</p></div>
    <a class="btn" href="{{ route('outletstock.index', ['outlet' => $outlet?->id]) }}"><x-icon name="back" :size="18" /> Kembali</a>
</header>
@if (! $outlet)<div class="empty">Belum ada outlet.</div>@else
<form method="GET" class="toolbar">
    @if ($outlets->isNotEmpty())<select name="outlet" aria-label="Outlet" onchange="this.form.submit()">@foreach ($outlets as $o)<option value="{{ $o->id }}" @selected($o->id === $outlet->id)>{{ $o->name }}</option>@endforeach</select>@endif
    <input type="month" name="bulan" value="{{ $month }}" aria-label="Bulan" onchange="this.form.submit()">
</form>
<div class="stats">
    <div class="stat warn"><small>Ditanggung outlet (kurang)</small><b>{{ Fmt::rp($short) }}</b></div>
    <div class="stat"><small>Selisih lebih</small><b>{{ Fmt::rp($over) }}</b></div>
    <div class="stat"><small>Bersih (kurang − lebih)</small><b>{{ Fmt::rp($net) }}</b></div>
</div>
<section class="list">
    @forelse ($byDay as $day => $rows)
        <div class="dayhead"><b>{{ \Illuminate\Support\Carbon::parse($day)->translatedFormat('l, j M Y') }}</b><span>{{ $rows->count() }} barang berselisih · {{ Fmt::rp($rows->sum('value')) }}</span></div>
        @foreach ($rows as $r)
        <div class="item">
            <div class="who"><b>{{ $r->item->name }}</b><small>Sistem {{ Fmt::qty($r->qty_system) }} → fisik {{ Fmt::qty($r->qty_base) }} {{ $r->item->baseUnit->name }}</small></div>
            <div class="col"><b style="color:{{ $r->value < 0 ? 'var(--bad)' : 'var(--ok)' }}">{{ $r->diff > 0 ? '+' : '' }}{{ Fmt::qty($r->diff) }}</b><small>{{ $r->value < 0 ? '−' : '+' }}{{ Fmt::rp(abs($r->value)) }}</small></div>
        </div>
        @endforeach
    @empty
        <div class="empty">Belum ada selisih opname di bulan ini.</div>
    @endforelse
</section>
@endif
@endsection
