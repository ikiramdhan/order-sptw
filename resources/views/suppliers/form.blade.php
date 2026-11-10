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
    <div class="card wide">
        <h3 class="sect">Rekening pembayaran</h3>
        <div class="row">
            <label class="field" style="flex:1 1 140px"><span class="lbl">Nama bank <span class="hint">(mis. BCA)</span></span>
                <input type="text" name="bank_name" value="{{ old('bank_name', $supplier->bank_name) }}">
            </label>
            <label class="field" style="flex:2 1 220px">Nomor rekening
                <input type="text" name="account_number" value="{{ old('account_number', $supplier->account_number) }}" inputmode="numeric">
                @error('account_number')<span class="err">{{ $message }}</span>@enderror
            </label>
            <label class="field" style="flex:2 1 220px">Nama pemilik rekening
                <input type="text" name="account_name" value="{{ old('account_name', $supplier->account_name) }}">
            </label>
        </div>
    </div>
    <div class="formbar">
        <a class="btn" href="{{ route('suppliers.index') }}">Batal</a>
        <button type="submit" class="btn primary">Simpan supplier</button>
    </div>
</form>
@endsection
