@extends('layouts.app')
@section('title', $supplier->exists ? 'Ubah supplier' : 'Tambah supplier')
@section('content')
<header class="page-head">
    <div><h1>{{ $supplier->exists ? 'Ubah supplier' : 'Tambah supplier' }}</h1></div>
    <a class="btn" href="{{ route('suppliers.index') }}"><x-icon name="back" :size="18" /> Kembali</a>
</header>
<form method="POST" class="fbento" action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}" novalidate>
    @csrf
    @if ($supplier->exists) @method('PUT') @endif
    <div class="card">
        <h3 class="sect">Info supplier</h3>
        <label class="field">Nama supplier
            <input type="text" name="name" value="{{ old('name', $supplier->name) }}" required>
            @error('name')<span class="err">{{ $message }}</span>@enderror
        </label>
        <label class="field">No. telepon / WhatsApp
            <input type="text" name="phone" value="{{ old('phone', $supplier->phone) }}" inputmode="tel">
        </label>
    </div>
    <div class="card">
        <h3 class="sect">Alamat</h3>
        <label class="field"><span class="lbl">Alamat lengkap <span class="hint">(opsional)</span></span>
            <textarea name="address" rows="4">{{ old('address', $supplier->address) }}</textarea>
        </label>
    </div>
    <div class="formbar">
        <a class="btn" href="{{ route('suppliers.index') }}">Batal</a>
        <button type="submit" class="btn primary">Simpan supplier</button>
    </div>
</form>
@endsection
