@extends('layouts.app')
@section('title', 'Pengguna')
@section('content')
<header class="page-head">
    <div>
        <h1>Pengguna</h1>
        <p>Hanya Super Admin yang bisa membuat dan menonaktifkan akun</p>
    </div>
    <a class="btn primary" href="{{ route('users.create') }}"><x-icon name="plus" :size="18" /> Tambah pengguna</a>
</header>

<div class="stats">
    <div class="stat"><small>Admin CK</small><b>{{ $counts['admin_ck'] ?? 0 }}</b></div>
    <div class="stat"><small>Staf Gudang</small><b>{{ $counts['gudang'] ?? 0 }}</b></div>
    <div class="stat"><small>User Outlet</small><b>{{ $counts['outlet'] ?? 0 }}</b></div>
    <div class="stat warn"><small>Nonaktif</small><b>{{ $inactive }}</b></div>
</div>

<form method="GET" class="chips">
    @foreach (['semua' => 'Semua', 'admin_ck' => 'Admin CK', 'gudang' => 'Staf Gudang', 'outlet' => 'Outlet'] as $key => $label)
        <a class="chip {{ $role === $key ? 'on' : '' }}" href="{{ route('users.index', array_filter(['role' => $key, 'q' => $q])) }}">{{ $label }}</a>
    @endforeach
    <input type="hidden" name="role" value="{{ $role }}">
    <input type="search" name="q" value="{{ $q }}" placeholder="Cari nama atau email" aria-label="Cari pengguna"
           style="flex:1 1 200px;min-height:44px;padding:0 16px;border-radius:22px;border:0;background:#fff">
</form>

<section class="list" aria-label="Daftar pengguna">
    @forelse ($users as $u)
        <div class="item {{ $u->is_active ? '' : 'off' }}">
            <div class="avatar">{{ $u->initials() }}</div>
            <div class="who"><b>{{ $u->name }}</b><small>{{ $u->email }}</small></div>
            <div class="col"><b>{{ $u->roleLabel() }}</b><small>{{ $u->outlet?->name ?? ($u->isSuperAdmin() ? 'Semua modul' : 'Central Kitchen') }}</small></div>
            <span class="badge {{ $u->is_active ? 'ok' : 'bad' }}">{{ $u->is_active ? 'Aktif' : 'Nonaktif' }}</span>
            @if ($u->id !== auth()->id())
                <div class="actions">
                    @unless ($u->isSuperAdmin())<a class="btn sm" href="{{ route('users.edit', $u) }}">Ubah</a>@endunless
                    <form method="POST" action="{{ route('users.reset', $u) }}" data-confirm="Reset password {{ $u->name }}? Password lama tidak berlaku lagi dan semua perangkatnya keluar.">@csrf
                        <button class="btn sm" type="submit">Reset password</button>
                    </form>
                    <form method="POST" action="{{ route('users.toggle', $u) }}" data-confirm="{{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }} {{ $u->name }}?">@csrf
                        <button class="btn sm {{ $u->is_active ? 'danger' : 'ok' }}" type="submit">{{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                    </form>
                </div>
            @endif
        </div>
    @empty
        <div class="empty">Belum ada pengguna yang cocok.</div>
    @endforelse
</section>
@endsection
