@extends('layouts.app')
@section('title', $mode === 'so' ? 'Stok opname outlet' : 'Catat waste')
@section('content')
@use('App\Support\Fmt')
@php
    $isSo = $mode === 'so'; $isCk = $mode === 'ckwaste';
    $action = $isSo ? route('outletstock.count.store') : ($isCk ? route('stock.waste.store') : route('outletstock.waste.store'));
    $back = $isCk ? route('stock.index') : route('outletstock.index', ['outlet' => $outlet?->id]);
    $needsApproval = auth()->user()->role === 'outlet';
@endphp
<header class="page-head">
    <div><h1>{{ $isSo ? 'Stok opname outlet' : ($isCk ? 'Waste gudang CK' : 'Waste outlet'.($outlet ? ' · '.$outlet->name : '')) }}</h1>
        <p>{{ $isSo ? 'Isi hasil hitung fisik. Kolom kosong dilewati.' : 'Isi jumlah barang yang dibuang. Kolom kosong atau 0 dilewati.' }}@if (! $isCk && $needsApproval) Perlu persetujuan Admin CK.@endif</p></div>
    <a class="btn" href="{{ $back }}"><x-icon name="back" :size="18" /> Kembali</a>
</header>
@if (! $isCk && $outlets->isNotEmpty())
<form method="GET" class="toolbar"><select name="outlet" aria-label="Outlet" onchange="this.form.submit()">@foreach ($outlets as $o)<option value="{{ $o->id }}" @selected($outlet && $o->id === $outlet->id)>{{ $o->name }}</option>@endforeach</select></form>
@endif
<div class="card">
    <form method="POST" action="{{ $action }}" novalidate>@csrf
        @if ($outlet && $outlets->isNotEmpty())<input type="hidden" name="outlet" value="{{ $outlet->id }}">@endif
        <div class="row">
            @if (! $isSo)
            <label class="field">Alasan<select name="reason" required>@foreach ($reasons as $k => $v)<option value="{{ $k }}" @selected(old('reason') === $k)>{{ $v }}</option>@endforeach</select>@error('reason')<span class="err">{{ $message }}</span>@enderror</label>
            @endif
            <label class="field">Catatan <span class="hint">(opsional)</span><input type="text" name="notes" value="{{ old('notes') }}" maxlength="200"></label>
        </div>
        @error('qty')<div class="alert" role="alert">{{ $message }}</div>@enderror
        <div class="tbl-wrap"><table class="tbl stackable">
            <thead><tr><th>Barang</th><th class="num">Stok sistem</th><th style="min-width:130px">{{ $isSo ? 'Stok fisik' : 'Jumlah waste' }}</th></tr></thead>
            <tbody>@forelse ($items as $i)
                <tr><td data-label="Barang">{{ $i->name }}</td><td class="num" data-label="Stok sistem">{{ Fmt::qty($i->qty) }} {{ $i->unit }}</td>
                    <td class="has-input" data-label="{{ $isSo ? 'Stok fisik' : 'Jumlah waste' }}"><input type="number" name="qty[{{ $i->id }}]" value="{{ old("qty.$i->id") }}" step="any" min="0" inputmode="decimal" aria-label="{{ $i->name }}"></td></tr>
            @empty<tr><td colspan="3" class="empty">Belum ada barang. Barang muncul setelah outlet menerima kiriman dari CK.</td></tr>@endforelse</tbody>
        </table></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px"><a class="btn" href="{{ $back }}">Batal</a><button class="btn primary" type="submit">{{ $needsApproval ? 'Ajukan' : 'Simpan' }}</button></div>
    </form>
</div>
@if ($isCk && isset($history))
<div class="card"><h3 class="sect">Waste gudang terbaru</h3>
    <div class="tbl-wrap"><table class="tbl stackable"><thead><tr><th>Tanggal</th><th>Barang</th><th class="num">Jumlah</th><th>Alasan</th><th class="num">Nilai</th><th>Oleh</th></tr></thead>
    <tbody>@forelse ($history as $h)<tr><td>{{ $h->created_at->translatedFormat('j M H:i') }}</td><td>{{ $h->item->name }}</td><td class="num">{{ Fmt::qty($h->qty_base) }} {{ $h->item->baseUnit->name }}</td><td>{{ \App\Models\StockRequest::REASONS[$h->reason] ?? $h->reason }}</td><td class="num">{{ Fmt::rp((int) round($h->qty_base * $h->item->price)) }}</td><td>{{ $h->creator->name }}</td></tr>
    @empty<tr><td colspan="6" class="empty">Belum ada waste.</td></tr>@endforelse</tbody></table></div></div>
@endif
@endsection
