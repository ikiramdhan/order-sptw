@extends('layouts.guest')
@section('title', 'Masuk')
@section('content')
<div class="card {{ $errors->any() ? 'shake' : '' }}">
    <h1>Selamat datang</h1>
    <p class="sub">Masuk dengan akun yang dibuat oleh Super Admin.</p>

    @if (session('status'))<div class="alert ok" style="margin-bottom:14px" role="status">{{ session('status') }}</div>@endif

    <form method="POST" action="{{ route('login') }}" novalidate data-auth-form>
        @csrf
        <label class="field">Email
            <input type="email" name="email" value="{{ old('email') }}" autocomplete="username" autofocus required>
            @error('email')<span class="err">{{ $message }}</span>@enderror
        </label>
        <label class="field">Password
            <span class="pw">
                <input type="password" id="password" name="password" autocomplete="current-password" required>
                <button type="button" data-toggle-password="password" aria-label="Tampilkan password"><x-icon name="eye" :size="20" /></button>
            </span>
            @error('password')<span class="err">{{ $message }}</span>@enderror
        </label>
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;margin-bottom:10px">
            <label class="check"><input type="checkbox" name="remember" value="1"> Ingat saya</label>
            <a class="link" href="{{ route('password.request') }}">Lupa password?</a>
        </div>
        <button type="submit" class="btn primary block" style="min-height:52px">Masuk</button>
    </form>
</div>
<div class="center">Belum punya akun? Hubungi Super Admin Central Kitchen.</div>
@endsection
