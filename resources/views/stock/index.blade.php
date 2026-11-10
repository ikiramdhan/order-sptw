@extends('layouts.app')
@section('title', 'Stok')
@section('content')
@use('App\Support\Fmt')
<header class="page-head">
    <div><h1>Stok gudang</h1><p>Stok dalam satuan dasar; konversi ke Dus ditampilkan bila ada</p></div>
    <a class="btn primary" href="{{ route('stock.adjust') }}">Penyesuaian stok</a>
</header>
<div class="stats">
    <div class="stat"><small>Jenis barang</small><b>{{ $items->count() }}</b></div>
    <div class="stat warn"><small>Stok menipis</small><b>{{ $lowCount }}</b></div>
</div>
<form method="GET" class="toolbar">
    <input type="search" name="q" value="{{ $q }}" placeholder="Cari barang" aria-label="Cari barang">
    <label style="display:flex;gap:6px;align-items:center;font-weight:600"><input type="checkbox" name="menipis" value="1" @checked($low) onchange="this.form.submit()"> Hanya yang menipis</label>
</form>
<section class="list">
    @forelse ($items as $i)
        @php $big = $i->itemUnits->where('factor', '>', 1)->sortByDesc('factor')->first(); @endphp
        <div class="item">
            <div class="who"><b>{{ $i->name }}</b><small>Minimum {{ Fmt::qty($i->min_stock) }} {{ $i->baseUnit->name }}</small></div>
            <div class="col"><b class="{{ $i->isLow() ? 'low' : '' }}">{{ Fmt::qty($i->stock) }} {{ $i->baseUnit->name }}</b>
                @if ($big && $i->stock >= $big->factor)<small>≈ {{ Fmt::qty(floor($i->stock / $big->factor)) }} {{ $big->unit->name }} + {{ Fmt::qty(fmod((float) $i->stock, (float) $big->factor)) }} {{ $i->baseUnit->name }}</small>@endif</div>
            @if ($i->isLow())<span class="badge bad">Menipis</span>@endif
        </div>
    @empty
        <div class="empty">Tidak ada barang.</div>
    @endforelse
</section>
<div class="card">
    <h3 class="sect">Pergerakan stok terbaru</h3>
    <div class="tbl-wrap"><table class="tbl">
        <thead><tr><th>Waktu</th><th>Barang</th><th>Jenis</th><th class="num">Jumlah</th><th class="num">Saldo</th><th>Oleh</th></tr></thead>
        <tbody>@forelse ($history as $m)
            <tr><td>{{ $m->created_at->translatedFormat('j M H:i') }}</td><td>{{ $m->item->name }}</td>
                <td>{{ \App\Models\StockMovement::LABELS[$m->type] ?? $m->type }}</td>
                <td class="num" style="color:{{ $m->qty_base < 0 ? 'var(--bad)' : 'var(--ok)' }}">{{ $m->qty_base > 0 ? '+' : '' }}{{ Fmt::qty($m->qty_base) }}</td>
                <td class="num">{{ Fmt::qty($m->balance_after) }}</td><td>{{ $m->user?->name ?? '-' }}</td></tr>
        @empty<tr><td colspan="6" class="empty">Belum ada pergerakan.</td></tr>@endforelse</tbody>
    </table></div>
</div>
@endsection
