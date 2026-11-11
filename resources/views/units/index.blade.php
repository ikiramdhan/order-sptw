@extends('layouts.app')
@section('title', 'Satuan')
@section('content')
<header class="page-head compact">
    <div><h1>Satuan</h1><p>Daftar satuan (PCS, Dus, Kg, ...). Konversi per barang diatur di menu Barang.</p></div>
    <a class="btn" href="{{ route('items.index') }}"><x-icon name="back" :size="18" /> Barang</a>
</header>
<div class="fbento cols-5-6" style="align-items:start">
<div class="card">
        <h3 class="sect">Tambah satuan</h3>
    <form method="POST" action="{{ route('units.store') }}" class="row" style="align-items:flex-end">
        @csrf
        <label class="field" style="margin:0">Satuan baru
            <input type="text" name="name" value="{{ old('name') }}" placeholder="mis. Bal" maxlength="30" required>
            @error('name')<span class="err">{{ $message }}</span>@enderror
        </label>
        <button class="btn primary" type="submit" style="margin-bottom:0">Tambah</button>
    </form>
</div>
<section class="list" aria-label="Daftar satuan">
    @foreach ($units as $u)
        <div class="item">
            <form method="POST" action="{{ route('units.update', $u) }}" class="who" style="display:flex;gap:8px;align-items:center">
                @csrf @method('PUT')
                <input type="text" name="name" value="{{ $u->name }}" maxlength="30" aria-label="Nama satuan"
                       style="min-height:40px;padding:0 12px;border-radius:12px;border:1px solid var(--line);background:var(--field);max-width:200px">
                <button class="btn sm" type="submit">Simpan</button>
            </form>
            <div class="col"><small>Dipakai di {{ $u->usage }} barang</small></div>
        </div>
    @endforeach
</section>
</div>
@endsection
