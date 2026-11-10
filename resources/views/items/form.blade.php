@extends('layouts.app')
@section('title', $item->exists ? 'Ubah barang' : 'Tambah barang')
@section('content')
@php
    $rowsData = old('units') ?? $rows;
@endphp
<header class="page-head">
    <div><h1>{{ $item->exists ? 'Ubah barang' : 'Tambah barang' }}</h1>
        <p>Stok selalu dihitung dalam satuan dasar. Satuan lain (mis. Dus) dikonversi ke satuan dasar.</p></div>
    <a class="btn" href="{{ route('items.index') }}"><x-icon name="back" :size="18" /> Kembali</a>
</header>
<form method="POST" class="fbento cols-5-6" action="{{ $item->exists ? route('items.update', $item) : route('items.store') }}" novalidate>
    <div class="card">
        <h3 class="sect">Data barang</h3>
        @csrf
        @if ($item->exists) @method('PUT') @endif
        <label class="field">Nama barang
            <input type="text" name="name" value="{{ old('name', $item->name) }}" required>
            @error('name')<span class="err">{{ $message }}</span>@enderror
        </label>
        <div class="row">
            <label class="field"><span class="lbl">Kategori <span class="hint">(opsional)</span></span>
                <input type="text" name="category" value="{{ old('category', $item->category) }}">
            </label>
            <label class="field">Satuan dasar
                <select name="base_unit_id" required>
                    <option value="">Pilih</option>
                    @foreach ($units as $u)
                        <option value="{{ $u->id }}" @selected((int) old('base_unit_id', $item->base_unit_id) === $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
                @error('base_unit_id')<span class="err">{{ $message }}</span>@enderror
            </label>
        </div>
        <div class="row">
            <label class="field">Harga jual per satuan dasar (Rp)
                <input type="number" name="price" value="{{ old('price', $item->price) }}" min="0" step="1" inputmode="numeric" required>
                @error('price')<span class="err">{{ $message }}</span>@enderror
            </label>
            <label class="field"><span class="lbl">Stok minimum <span class="hint">(peringatan menipis)</span></span>
                <input type="number" name="min_stock" value="{{ old('min_stock', $item->min_stock + 0) }}" min="0" step="any" inputmode="decimal">
            </label>
        </div>

    </div>
    <div class="card">
        <h3 class="sect">Konversi satuan</h3>
        <p style="color:var(--muted);margin:0 0 10px">Contoh: Dus, jumlah per satuan dasar = 48 artinya 1 Dus = 48 satuan dasar. Kosongkan baris yang tidak dipakai.</p>
        <div class="tbl-wrap">
        <table class="tbl">
            <thead><tr><th>Satuan</th><th>Isi (satuan dasar)</th><th>Dijual</th><th>Dibeli</th></tr></thead>
            <tbody>
            @foreach ($rowsData as $n => $r)
                <tr>
                    <td><select name="units[{{ $n }}][unit_id]" aria-label="Satuan">
                        <option value="">-</option>
                        @foreach ($units as $u)<option value="{{ $u->id }}" @selected((string) ($r['unit_id'] ?? '') === (string) $u->id)>{{ $u->name }}</option>@endforeach
                    </select>
                    @error("units.$n.unit_id")<span class="err">{{ $message }}</span>@enderror</td>
                    <td><input type="number" name="units[{{ $n }}][factor]" value="{{ $r['factor'] ?? '' }}" step="any" min="0" inputmode="decimal" aria-label="Isi">
                    @error("units.$n.factor")<span class="err">{{ $message }}</span>@enderror</td>
                    <td><input type="checkbox" name="units[{{ $n }}][is_sellable]" value="1" @checked(! empty($r['is_sellable'])) style="min-width:0;width:auto;min-height:0"></td>
                    <td><input type="checkbox" name="units[{{ $n }}][is_purchasable]" value="1" @checked(! empty($r['is_purchasable'])) style="min-width:0;width:auto;min-height:0"></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    </div>
    <div class="formbar">
        <a class="btn" href="{{ route('items.index') }}">Batal</a>
        <button type="submit" class="btn primary">Simpan barang</button>
    </div>
</form>
@endsection
