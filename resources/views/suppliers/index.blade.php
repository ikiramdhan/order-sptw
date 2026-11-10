@extends('layouts.app')
@section('title', 'Supplier')
@section('content')
<header class="page-head">
    <div><h1>Supplier</h1><p>Tempat Central Kitchen membeli bahan</p></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn" href="{{ route('suppliers.import') }}">Impor</a>
        <a class="btn primary" href="{{ route('suppliers.create') }}"><x-icon name="plus" :size="18" /> Tambah supplier</a>
    </div>
</header>
<section class="list">
    @forelse ($suppliers as $s)
        <div class="item {{ $s->is_active ? '' : 'off' }}">
            <div class="avatar sq">{{ strtoupper(mb_substr($s->name, 0, 2)) }}</div>
            <div class="who"><b>{{ $s->name }}</b><small>{{ $s->bankLine() ?: ($s->address ?: 'Rekening belum diisi') }}</small></div>
            <div class="col"><b>{{ $s->phone ?: '-' }}</b></div>
            <span class="badge {{ $s->is_active ? 'ok' : 'bad' }}">{{ $s->is_active ? 'Aktif' : 'Nonaktif' }}</span>
            <div class="actions">
                <a class="btn sm" href="{{ route('suppliers.edit', $s) }}">Ubah</a>
                <form method="POST" action="{{ route('suppliers.toggle', $s) }}" data-confirm="{{ $s->is_active ? 'Nonaktifkan' : 'Aktifkan' }} {{ $s->name }}?">@csrf
                    <button class="btn sm {{ $s->is_active ? 'danger' : 'ok' }}" type="submit">{{ $s->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                </form>
            </div>
        </div>
    @empty
        <div class="empty">Belum ada supplier.</div>
    @endforelse
</section>
@endsection
