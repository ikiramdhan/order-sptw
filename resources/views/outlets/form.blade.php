@extends('layouts.app')
@section('title', $outlet->exists ? 'Ubah outlet' : 'Tambah outlet')
@section('content')
@php $term = (string) old('payment_term_days', $outlet->payment_term_days ?? 7); @endphp
<header class="page-head">
    <div>
        <h1>{{ $outlet->exists ? 'Ubah outlet' : 'Tambah outlet' }}</h1>
        <p>{{ $outlet->exists ? $outlet->name : 'Setelah disimpan, buat akun login untuk outlet ini di menu Pengguna.' }}</p>
    </div>
    <a class="btn" href="{{ route('outlets.index') }}"><x-icon name="back" :size="18" /> Kembali</a>
</header>

<div class="card" style="max-width:560px">
    <form method="POST" action="{{ $outlet->exists ? route('outlets.update', $outlet) : route('outlets.store') }}" novalidate>
        @csrf
        @if ($outlet->exists) @method('PUT') @endif

        <label class="field">Nama outlet
            <input type="text" name="name" value="{{ old('name', $outlet->name) }}" placeholder="Outlet Bintaro" required>
            @error('name')<span class="err">{{ $message }}</span>@enderror
        </label>
        <label class="field">Alamat
            <input type="text" name="address" value="{{ old('address', $outlet->address) }}">
            @error('address')<span class="err">{{ $message }}</span>@enderror
        </label>
        <div class="row">
            <label class="field">Penanggung jawab
                <input type="text" name="pic_name" value="{{ old('pic_name', $outlet->pic_name) }}">
            </label>
            <label class="field">No. WhatsApp <span class="hint">(untuk kirim invoice)</span>
                <input type="text" name="phone" value="{{ old('phone', $outlet->phone) }}" inputmode="tel" placeholder="08xx-xxxx-xxxx">
            </label>
        </div>

        <div class="field">Termin tempo
            <div class="seg" role="radiogroup" aria-label="Termin tempo">
                @foreach (\App\Models\Outlet::TERMS as $days => $label)
                    <label><input type="radio" name="payment_term_days" value="{{ $days }}" {{ $term === (string) $days ? 'checked' : '' }}><span>{{ $days === 0 ? 'Tunai' : $days.' hari' }}</span></label>
                @endforeach
            </div>
            @error('payment_term_days')<span class="err">{{ $message }}</span>@enderror
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn" href="{{ route('outlets.index') }}">Batal</a>
            <button type="submit" class="btn primary">Simpan outlet</button>
        </div>
    </form>
</div>
@endsection
