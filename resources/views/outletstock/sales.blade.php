@extends('layouts.app')
@section('title', 'Penjualan ESB')
@section('content')
<header class="page-head">
    <div><h1>Upload penjualan ESB</h1><p>Unggah laporan barang terjual per hari. Stok outlet berkurang sesuai jumlah terjual.</p></div>
    <a class="btn" href="{{ route('outletstock.index', ['outlet' => $outlet?->id]) }}"><x-icon name="back" :size="18" /> Kembali</a>
</header>
<div class="two-col">
<div class="grow">
    <form method="POST" action="{{ route('outletstock.sales.store') }}" enctype="multipart/form-data" class="card" novalidate>@csrf
        @if ($outlets->isNotEmpty())
        <label class="field">Outlet<select name="outlet">@foreach ($outlets as $o)<option value="{{ $o->id }}" @selected($outlet && $o->id === $outlet->id)>{{ $o->name }}</option>@endforeach</select>@error('outlet')<span class="err">{{ $message }}</span>@enderror</label>
        @endif
        <label class="field">Tanggal penjualan<input type="date" name="date" value="{{ old('date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>@error('date')<span class="err">{{ $message }}</span>@enderror</label>
        <label class="field">File laporan (.xlsx atau .csv)<input type="file" name="file" accept=".xlsx,.csv,.txt" required style="padding:12px">@error('file')<span class="err">{{ $message }}</span>@enderror</label>
        <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="replace" value="1"> Ganti data tanggal itu bila sudah pernah diunggah</label>
        <button class="btn primary" type="submit">Simpan penjualan</button>
    </form>
</div>
<div class="side"><div class="card">
    <h3 class="sect">Format kolom</h3>
    <ul style="margin:0 0 12px;padding-left:18px;line-height:1.7">
        <li><b>Kode</b> barang (atau <b>Nama Barang</b>), harus sama dengan daftar barang</li>
        <li><b>Jumlah Terjual</b> dalam satuan dasar</li>
    </ul>
    <p style="margin:0 0 12px;color:var(--muted)">Baris yang tidak cocok dilewati dan ditampilkan setelah disimpan. Kolom lain di laporan diabaikan.</p>
    <a class="btn block" data-download href="{{ route('outletstock.sales.template') }}">Unduh contoh (.csv)</a>
</div></div>
</div>
@endsection
