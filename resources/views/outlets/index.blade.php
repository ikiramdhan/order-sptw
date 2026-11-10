@extends('layouts.app')
@section('title', 'Outlet')
@section('content')
<header class="page-head">
    <div>
        <h1>Outlet</h1>
        <p>Tambah outlet baru, atur termin tempo, dan nonaktifkan outlet yang tutup</p>
    </div>
    <a class="btn primary" href="{{ route('outlets.create') }}"><x-icon name="plus" :size="18" /> Tambah outlet</a>
</header>

<div class="stats">
    <div class="stat"><small>Total outlet</small><b>{{ $total }}</b></div>
    <div class="stat"><small>Aktif</small><b style="color:var(--ok)">{{ $active }}</b></div>
    <div class="stat warn"><small>Nonaktif</small><b>{{ $total - $active }}</b></div>
</div>

<form method="GET">
    <input type="search" name="q" value="{{ $q }}" placeholder="Cari nama outlet" aria-label="Cari outlet"
           style="width:100%;min-height:44px;padding:0 16px;border-radius:22px;border:0;background:#fff">
</form>

<section class="list" aria-label="Daftar outlet">
    @forelse ($outlets as $o)
        <div class="item {{ $o->is_active ? '' : 'off' }}">
            <div class="avatar sq">{{ $o->initials() }}</div>
            <div class="who"><b>{{ $o->name }}</b><small>{{ $o->address ?: 'Alamat belum diisi' }}</small></div>
            <div class="col"><b>{{ $o->pic_name ?: '-' }}</b><small>{{ $o->termLabel() }} · {{ $o->accounts_count }} akun</small></div>
            <span class="badge {{ $o->is_active ? 'ok' : 'bad' }}">{{ $o->is_active ? 'Aktif' : 'Nonaktif' }}</span>
            <div class="actions">
                <a class="btn sm" href="{{ route('outlets.edit', $o) }}">Ubah</a>
                <form method="POST" action="{{ route('outlets.toggle', $o) }}" data-confirm="{{ $o->is_active ? 'Nonaktifkan' : 'Aktifkan' }} {{ $o->name }}?">@csrf
                    <button class="btn sm {{ $o->is_active ? 'danger' : 'ok' }}" type="submit">{{ $o->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                </form>
            </div>
        </div>
    @empty
        <div class="empty">Belum ada outlet. Tambahkan outlet pertama.</div>
    @endforelse
</section>
@endsection
