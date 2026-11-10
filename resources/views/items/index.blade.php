@extends('layouts.app')
@section('title', 'Barang')
@section('content')
@use('App\Support\Fmt')
<header class="page-head">
    <div><h1>Barang</h1><p>Master barang, harga jual, dan konversi satuan</p></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn" href="{{ route('units.index') }}">Satuan</a>
        <a class="btn" href="{{ route('items.import') }}">Impor Excel</a>
        <a class="btn primary" href="{{ route('items.create') }}"><x-icon name="plus" :size="18" /> Tambah barang</a>
    </div>
</header>
<form method="GET" class="toolbar">
    <input type="search" name="q" value="{{ $q }}" placeholder="Cari nama barang" aria-label="Cari barang">
    <select name="status" aria-label="Status" onchange="this.form.submit()">
        <option value="aktif" @selected($status==='aktif')>Aktif</option>
        <option value="nonaktif" @selected($status==='nonaktif')>Nonaktif</option>
        <option value="semua" @selected($status==='semua')>Semua</option>
    </select>
</form>
<section class="list">
    @forelse ($items as $i)
        <div class="item {{ $i->is_active ? '' : 'off' }}">
            <div class="who"><b>{{ $i->code ? $i->code.' · ' : '' }}{{ $i->name }}</b>
                <small>{{ $i->category ?: 'Tanpa kategori' }} · Satuan dasar {{ $i->baseUnit->name }}@if ($i->hasRecipe()) · Resep: {{ $i->recipe->map(fn ($r) => $r->ingredient->name.' '.Fmt::qty($r->qty_base).' '.$r->ingredient->baseUnit->name)->join(', ') }}@endif
                    @foreach ($i->itemUnits->where('factor', '>', 0) as $iu) · 1 {{ $iu->unit->name }} = {{ Fmt::qty($iu->factor) }} {{ $i->baseUnit->name }}@endforeach</small></div>
            <div class="col"><b>{{ Fmt::rp($i->price) }}</b><small>CK · per {{ $i->baseUnit->name }}{{ $i->retail_price ? ' · CS '.Fmt::rp($i->retail_price) : '' }}</small></div>
            <div class="col"><b class="{{ $i->isLow() ? 'low' : '' }}">{{ Fmt::qty($i->availableStock()) }} {{ $i->baseUnit->name }}</b><small>{{ $i->hasRecipe() ? 'stok dari bahan' : 'stok'.($i->isLow() ? ' menipis' : '') }}</small></div>
            <div class="actions">
                <a class="btn sm" href="{{ route('items.edit', $i) }}">Ubah</a>
                <form method="POST" action="{{ route('items.toggle', $i) }}" data-confirm="{{ $i->is_active ? 'Nonaktifkan' : 'Aktifkan' }} {{ $i->name }}?">@csrf
                    <button class="btn sm {{ $i->is_active ? 'danger' : 'ok' }}" type="submit">{{ $i->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                </form>
            </div>
        </div>
    @empty
        <div class="empty">Belum ada barang.</div>
    @endforelse
</section>
@endsection
