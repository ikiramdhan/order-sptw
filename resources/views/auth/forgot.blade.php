@extends('layouts.guest')
@section('title', 'Lupa password')
@section('content')
<div class="card">
    <a href="{{ route('login') }}" class="link" style="margin-bottom:6px"><x-icon name="back" :size="18" /> Kembali ke login</a>
    <h1>Lupa password</h1>
    <p class="sub">Masukkan email akun Anda. Kami kirim link untuk membuat password baru.</p>

    @if (session('status'))<div class="alert ok" style="margin-bottom:14px" role="status">{{ session('status') }}</div>@endif

    <form method="POST" action="{{ route('password.email') }}" novalidate data-auth-form>
        @csrf
        <label class="field">Email
            <input type="email" name="email" value="{{ old('email') }}" autocomplete="username" autofocus required>
            @error('email')<span class="err">{{ $message }}</span>@enderror
        </label>
        <button type="submit" class="btn primary block" style="min-height:52px">Kirim link reset</button>
    </form>
</div>
<div class="center">Tidak menerima email? Hubungi Super Admin untuk reset password manual.</div>
@endsection
