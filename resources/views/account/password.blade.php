@extends($forced ? 'layouts.guest' : 'layouts.app')
@section('title', 'Ganti password')
@section('content')
@if ($forced)
    <div class="alert warn" role="status">Anda memakai password sementara. Buat password baru untuk melanjutkan.</div>
@endif
@if ($forced)
<div class="card">
    <h1 style="font-size:24px;font-weight:800;margin-bottom:14px">Ganti password</h1>
    <form method="POST" action="{{ route('account.password.update') }}" novalidate>
        @csrf @method('PUT')
        <label class="field">Password sementara
            <input type="password" name="current_password" autocomplete="current-password" required>
            @error('current_password')<span class="err">{{ $message }}</span>@enderror
        </label>
        <label class="field"><span class="lbl">Password baru <span class="hint">(minimal 8 karakter, huruf dan angka)</span></span>
            <span class="pw">
                <input type="password" id="password" name="password" autocomplete="new-password" required>
                <button type="button" data-toggle-password="password" aria-label="Tampilkan password"><x-icon name="eye" :size="20" /></button>
            </span>
            @error('password')<span class="err">{{ $message }}</span>@enderror
        </label>
        <label class="field">Ulangi password baru
            <input type="password" name="password_confirmation" autocomplete="new-password" required>
        </label>
        <button type="submit" class="btn dark">Simpan password</button>
    </form>
    <form method="POST" action="{{ route('logout') }}" style="margin-top:10px">@csrf
        <button type="submit" class="btn sm">Keluar</button>
    </form>
</div>
@else
<header class="page-head">
    <div><h1>Akun</h1><p>{{ auth()->user()->name }} · {{ auth()->user()->email }} · {{ auth()->user()->roleLabel() }}</p></div>
</header>
<form method="POST" class="fbento" action="{{ route('account.password.update') }}" novalidate>
    @csrf @method('PUT')
    <div class="card">
        <h3 class="sect">Verifikasi</h3>
        <label class="field">Password saat ini
            <input type="password" name="current_password" autocomplete="current-password" required>
            @error('current_password')<span class="err">{{ $message }}</span>@enderror
        </label>
    </div>
    <div class="card">
        <h3 class="sect">Password baru</h3>
        <label class="field"><span class="lbl">Password baru <span class="hint">(minimal 8 karakter, huruf dan angka)</span></span>
            <span class="pw">
                <input type="password" id="password" name="password" autocomplete="new-password" required>
                <button type="button" data-toggle-password="password" aria-label="Tampilkan password"><x-icon name="eye" :size="20" /></button>
            </span>
            @error('password')<span class="err">{{ $message }}</span>@enderror
        </label>
        <label class="field">Ulangi password baru
            <input type="password" name="password_confirmation" autocomplete="new-password" required>
        </label>
    </div>
    <div class="formbar">
        <button type="submit" class="btn dark">Simpan password</button>
    </div>
</form>
@endif
@endsection
