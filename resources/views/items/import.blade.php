@extends('layouts.app')
@section('title', 'Impor barang')
@section('content')
<header class="page-head compact">
    <div><h1>Impor barang dari Excel</h1><p>Unggah daftar barang sekaligus. Anda bisa melihat pratinjau sebelum disimpan.</p></div>
    <a class="btn" href="{{ route('items.index') }}"><x-icon name="back" :size="18" /> Kembali</a>
</header>
<div class="two-col">
<div class="grow">
    <form method="POST" action="{{ route('items.import.preview') }}" enctype="multipart/form-data" class="card">@csrf
        <label class="field">File Excel (.xlsx) atau .csv
            <input type="file" name="file" accept=".xlsx,.csv,.txt" required style="padding:12px">
            @error('file')<span class="err">{{ $message }}</span>@enderror
        </label>
        <button class="btn primary" type="submit">Lihat pratinjau</button>
    </form>
</div>
<div class="side">
    <div class="card">
        <h3 class="sect">Format kolom</h3>
        <p style="margin:0 0 8px;color:var(--muted)">Unduh file contoh (.xlsx), lalu isi per kolom. Baris pertama adalah judul kolom. Petunjuk ada di lembar kedua.</p>
        <ul style="margin:0 0 12px;padding-left:18px;line-height:1.7">
            <li><b>Nama Barang</b> (wajib)</li>
            <li><b>Satuan Dasar</b> (wajib), mis. PCS, Kg</li>
            <li><b>Harga CS</b> (opsional), harga outlet ke konsumen, hanya sebagai info</li>
            <li><b>Harga CK</b> (wajib), harga CK ke outlet per satuan dasar</li>
            <li>Kode, Kategori, Stok Minimum, Stok Awal (opsional)</li>
            <li>Satuan Besar + Isi per Satuan Besar (opsional), mis. Dus dan 40</li>
        </ul>
        <p style="margin:0 0 12px;color:var(--muted)">Barang dengan nama yang sudah ada akan diperbarui harganya. Stok awal hanya berlaku untuk barang baru. Satuan yang belum ada dibuat otomatis.</p>
        <a class="btn block" data-download href="{{ route('items.import.template') }}">Unduh template Excel (.xlsx)</a>
    </div>
</div>
</div>
@endsection
