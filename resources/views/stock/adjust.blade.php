@extends('layouts.app')
@section('title', 'Penyesuaian stok')
@section('content')
@use('App\Support\Fmt')
<header class="page-head">
    <div><h1>Penyesuaian stok</h1><p>Isi stok fisik hasil hitung. Kolom kosong dilewati. Selisih dicatat otomatis.</p></div>
    <a class="btn" href="{{ route('stock.index') }}"><x-icon name="back" :size="18" /> Kembali</a>
</header>
<div class="card">
    <form method="POST" action="{{ route('stock.adjust.store') }}" novalidate data-confirm="Simpan penyesuaian? Stok sistem akan disamakan dengan stok fisik.">@csrf
        <div class="row">
            <label class="field">Alasan
                <select name="reason" required>@foreach ($reasons as $k => $v)<option value="{{ $k }}" @selected(old('reason','opname') === $k)>{{ $v }}</option>@endforeach</select>
                @error('reason')<span class="err">{{ $message }}</span>@enderror
            </label>
            <label class="field">Catatan <span class="hint">(opsional)</span><input type="text" name="notes" value="{{ old('notes') }}" maxlength="200"></label>
        </div>
        @error('physical.*')<div class="alert" role="alert">{{ $message }}</div>@enderror
        <div class="tbl-wrap"><table class="tbl">
            <thead><tr><th>Barang</th><th class="num">Stok sistem</th><th style="min-width:130px">Stok fisik</th></tr></thead>
            <tbody>@foreach ($items as $i)
                <tr><td>{{ $i->name }}</td><td class="num">{{ Fmt::qty($i->stock) }} {{ $i->baseUnit->name }}</td>
                    <td><input type="number" name="physical[{{ $i->id }}]" value="{{ old("physical.$i->id") }}" step="any" min="0" inputmode="decimal" aria-label="Stok fisik {{ $i->name }}"></td></tr>
            @endforeach</tbody>
        </table></div>
        <div class="savebar">
            <a class="btn" href="{{ route('stock.index') }}">Batal</a>
            <button class="btn primary" type="submit">Simpan penyesuaian</button>
        </div>
    </form>
</div>
@endsection
