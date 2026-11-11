@extends('layouts.app')
@section('title', 'Impor supplier')
@section('content')
<header class="page-head compact">
    <div><h1>Impor supplier</h1><p>Salin tabel dari Google Sheets atau Excel, tempel di sini. Anda bisa melihat pratinjau sebelum disimpan.</p></div>
    <a class="btn" href="{{ route('suppliers.index') }}"><x-icon name="back" :size="18" /> Kembali</a>
</header>
<div class="two-col">
<div class="grow">
    <form method="POST" action="{{ route('suppliers.import.preview') }}" enctype="multipart/form-data" class="card">@csrf
        <label class="field">Tempel tabel (termasuk baris judul kolom)
            <textarea name="paste" rows="10" placeholder="Nama Supplier&#9;Nama Rekening&#9;Nama Bank&#9;Nomor Rekening&#10;3 PUTERI&#9;UDIN ARDI&#9;BCA&#9;5875845184" style="font-family:ui-monospace,Consolas,monospace;font-size:13px">{{ old('paste') }}</textarea>
            @error('paste')<span class="err">{{ $message }}</span>@enderror
        </label>
        <div style="text-align:center;color:var(--muted);margin:2px 0 10px">atau</div>
        <label class="field">Unggah file Excel (.xlsx) atau .csv
            <input type="file" name="file" accept=".xlsx,.csv,.txt" style="padding:12px">
            @error('file')<span class="err">{{ $message }}</span>@enderror
        </label>
        <button class="btn primary" type="submit">Lihat pratinjau</button>
    </form>
</div>
<div class="side">
    <div class="card">
        <h3 class="sect">Cara termudah</h3>
        <ol style="margin:0 0 12px;padding-left:18px;line-height:1.7">
            <li>Di Google Sheets, blok semua sel tabel supplier, <b>termasuk baris judul</b>.</li>
            <li>Salin (Ctrl+C), lalu tempel (Ctrl+V) di kotak sebelah.</li>
            <li>Klik <b>Lihat pratinjau</b>, cek, lalu simpan.</li>
        </ol>
        <h3 class="sect">Kolom yang dikenali</h3>
        <ul style="margin:0 0 12px;padding-left:18px;line-height:1.7">
            <li><b>Nama Supplier</b> (wajib)</li>
            <li>Nama Rekening, Nama Bank, Nomor Rekening</li>
            <li>Telepon, Alamat (opsional)</li>
        </ul>
        <p style="margin:0;color:var(--muted)">Supplier dengan nama yang sudah ada akan diperbarui data rekeningnya, bukan dobel. Kolom yang kosong tidak menimpa data lama.</p>
    </div>
</div>
</div>
@endsection
