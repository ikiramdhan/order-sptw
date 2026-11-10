@extends('layouts.app')
@section('title', 'Pengaturan')
@section('content')
<header class="page-head">
    <div>
        <h1>Pengaturan</h1>
        <p>Email (SMTP) dipakai untuk mengirim link lupa password. Hanya Super Admin.</p>
    </div>
    <span class="badge {{ $smtp['configured'] ? 'ok' : 'warn' }}">{{ $smtp['configured'] ? 'SMTP terisi' : 'Belum diatur' }}</span>
</header>

@error('smtp_test')<div class="alert bad" role="alert">{{ $message }}</div>@enderror

<div class="fbento">
<form method="POST" action="{{ route('settings.update') }}" novalidate style="display:contents">
    @csrf @method('PUT')
    <div class="card">
        <h3 class="sect">Server email (SMTP)</h3>
        <div class="row">
            <label class="field" style="flex:2 1 200px">Host SMTP
                <input type="text" name="smtp_host" value="{{ old('smtp_host', $smtp['host']) }}" required>
                @error('smtp_host')<span class="err">{{ $message }}</span>@enderror
            </label>
            <label class="field" style="flex:1 1 90px">Port
                <input type="text" name="smtp_port" value="{{ old('smtp_port', $smtp['port']) }}" inputmode="numeric" required>
                @error('smtp_port')<span class="err">{{ $message }}</span>@enderror
            </label>
        </div>
        @php $enc = old('smtp_encryption', $smtp['encryption']); @endphp
        <div class="field">Enkripsi
            <div class="seg" role="radiogroup" aria-label="Enkripsi">
                <label><input type="radio" name="smtp_encryption" value="ssl" {{ $enc === 'ssl' ? 'checked' : '' }}><span>SSL/TLS (465)</span></label>
                <label><input type="radio" name="smtp_encryption" value="tls" {{ $enc === 'tls' ? 'checked' : '' }}><span>STARTTLS (587)</span></label>
                <label><input type="radio" name="smtp_encryption" value="none" {{ $enc === 'none' ? 'checked' : '' }}><span>Tanpa</span></label>
            </div>
        </div>
        <div class="alert info">Buat dulu kotak email di Hostinger (misal noreply@seblaktehwanti.com), lalu isi detailnya di sini. Password disimpan terenkripsi dan tidak ditampilkan lagi.</div>
    </div>
    <div class="card">
        <h3 class="sect">Akun pengirim</h3>
        <label class="field">Username (email pengirim)
            <input type="email" name="smtp_username" value="{{ old('smtp_username', $smtp['username']) }}" placeholder="noreply@seblaktehwanti.com" required>
            @error('smtp_username')<span class="err">{{ $message }}</span>@enderror
        </label>
        <label class="field">Password email
            <span class="pw">
                <input type="password" id="smtp_password" name="smtp_password" autocomplete="new-password" placeholder="{{ $smtp['has_password'] ? 'Tersimpan. Kosongkan jika tidak diganti' : '' }}">
                <button type="button" data-toggle-password="smtp_password" aria-label="Tampilkan password"><x-icon name="eye" :size="20" /></button>
            </span>
            @error('smtp_password')<span class="err">{{ $message }}</span>@enderror
        </label>
        <label class="field">Nama pengirim
            <input type="text" name="smtp_from_name" value="{{ old('smtp_from_name', $smtp['from_name']) }}" required>
            @error('smtp_from_name')<span class="err">{{ $message }}</span>@enderror
        </label>
    </div>
    <div class="formbar">
        <button type="submit" class="btn primary">Simpan pengaturan</button>
    </div>
</form>
<form method="POST" action="{{ route('settings.test') }}" class="card wide" style="flex-direction:row;flex-wrap:wrap;align-items:center;gap:12px">
    @csrf
    <div style="flex:1 1 240px"><b>Uji kirim email</b>
        <div style="font-size:12px;color:var(--muted)">@if ($smtp['last_test'])<span style="color:var(--ok)">Email tes terakhir terkirim {{ \Illuminate\Support\Carbon::parse($smtp['last_test'])->translatedFormat('j M Y, H:i') }}</span>@else Pastikan pengaturan sudah disimpan dulu.@endif</div></div>
    <button type="submit" class="btn">Kirim email tes ke {{ auth()->user()->email }}</button>
</form>
</div>
@endsection
