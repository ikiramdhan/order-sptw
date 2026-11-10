@extends('layouts.guest')
@section('title', 'Buat password baru')
@section('content')
<div class="card">
    <h1>Buat password baru</h1>
    <p class="sub">Minimal 8 karakter dengan huruf dan angka.</p>

    <form method="POST" action="{{ route('password.update') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label class="field">Email
            <input type="email" name="email" value="{{ old('email', $email) }}" autocomplete="username" required>
            @error('email')<span class="err">{{ $message }}</span>@enderror
        </label>
        <label class="field">Password baru
            <span class="pw">
                <input type="password" id="password" name="password" autocomplete="new-password" required>
                <button type="button" data-toggle-password="password" aria-label="Tampilkan password"><x-icon name="eye" :size="20" /></button>
            </span>
            @error('password')<span class="err">{{ $message }}</span>@enderror
        </label>
        <label class="field">Ulangi password baru
            <input type="password" name="password_confirmation" autocomplete="new-password" required>
        </label>
        <button type="submit" class="btn primary block" style="min-height:52px">Simpan &amp; masuk</button>
    </form>
</div>
<div class="alert warn">Link berlaku 60 menit dan hanya bisa dipakai sekali. Setelah password diganti, semua perangkat lain otomatis keluar.</div>
@endsection
