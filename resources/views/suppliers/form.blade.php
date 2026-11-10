@extends('layouts.app')
@section('title', $supplier->exists ? 'Ubah supplier' : 'Tambah supplier')
@section('content')
<header class="page-head">
    <div><h1>{{ $supplier->exists ? 'Ubah supplier' : 'Tambah supplier' }}</h1></div>
    <a class="btn" href="{{ route('suppliers.index') }}"><x-icon name="back" :size="18" /> Kembali</a>
</header>
<div class="card" style="max-width:560px">
    <form method="POST" action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}" novalidate>
        @csrf
        @if ($supplier->exists) @method('PUT') @endif
        <label class="field">Nama supplier
            <input type="text" name="name" value="{{ old('name', $supplier->name) }}" required>
            @error('name')<span class="err">{{ $message }}</span>@enderror
        </label>
        <label class="field">No. telepon / WhatsApp
            <input type="text" name="phone" value="{{ old('phone', $supplier->phone) }}" inputmode="tel">
        </label>
        <label class="field">Alamat
            <input type="text" name="address" value="{{ old('address', $supplier->address) }}">
        </label>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn" href="{{ route('suppliers.index') }}">Batal</a>
            <button type="submit" class="btn primary">Simpan supplier</button>
        </div>
    </form>
</div>
@endsection
