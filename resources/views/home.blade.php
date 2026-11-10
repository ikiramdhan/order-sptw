@extends('layouts.app')
@section('title', 'Beranda')
@section('content')
@use('App\Support\Fmt')
@php
    $isOutlet = $user->role === 'outlet';
    $isStaff = $user->hasRole('admin_ck', 'gudang');
    $isAdmin = $user->hasRole('admin_ck');
    $hour = (int) now('Asia/Jakarta')->format('G');
    $greet = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 18 ? 'Selamat sore' : 'Selamat malam'));
@endphp
<div class="bento">
    {{-- Hero: satu angka yang paling penting untuk peran ini --}}
    <section class="tile hero w2 h2" aria-label="Sorotan">
        <div>
            <small>{{ $greet }}</small>
            <h2>{{ $user->name }}</h2>
            <small>{{ $user->roleLabel() }}@if($user->outlet) · {{ $user->outlet->name }}@endif</small>
        </div>
        @if ($isOutlet)
            <div>
                <small>Tagihan belum lunas</small>
                <div class="big">{{ Fmt::rp($stats['tagihan'] ?? 0) }}</div>
                <a class="btn light" href="{{ route('catalog.index') }}">Buat PO <x-icon name="arrow" :size="18" /></a>
            </div>
        @else
            <div>
                <small>PO menunggu diproses</small>
                <div class="big">{{ $stats['po_baru'] ?? 0 }}</div>
                <a class="btn light" href="{{ route('orders.index') }}">Buka antrean PO <x-icon name="arrow" :size="18" /></a>
            </div>
        @endif
    </section>

    @if ($isOutlet)
        <div class="tile"><small>PO berjalan</small><b>{{ $stats['po_jalan'] ?? 0 }}</b></div>
        <a class="tile warn" href="{{ route('delivery.index') }}"><small>Perlu dikonfirmasi</small><b>{{ $stats['perlu_konfirmasi'] ?? 0 }}</b></a>
        <a class="tile link" href="{{ route('delivery.index') }}"><x-icon name="truck" />Terima barang</a>
        <a class="tile link" href="{{ route('invoices.index') }}"><x-icon name="receipt" />Tagihan</a>
        <a class="tile link" href="{{ route('catalog.index') }}"><x-icon name="store" />Katalog</a>
        <a class="tile link" href="{{ route('orders.index') }}"><x-icon name="list" />PO saya</a>
        <a class="tile link" href="{{ route('rekap.index') }}"><x-icon name="chart" />Rekap bulanan</a>
        <a class="tile link" href="{{ route('returns.index') }}"><x-icon name="undo" />Retur</a>
    @endif

    @if ($isStaff)
        <a class="tile" href="{{ route('orders.index', ['status' => 'menunggu_backorder']) }}"><small>Backorder / sebagian</small><b>{{ $stats['backorder'] }}</b></a>
        <a class="tile warn" href="{{ route('stock.index', ['menipis' => 1]) }}"><small>Stok menipis</small><b>{{ $stats['stok_menipis'] }}</b></a>
        @if (isset($stats['piutang']))
            <a class="tile w2" href="{{ route('invoices.index') }}"><small>Piutang outlet</small><b>{{ Fmt::rp($stats['piutang']) }}</b></a>
        @endif
        @if ($isAdmin)<a class="tile link" href="{{ route('dashboard') }}"><x-icon name="chart" />Dashboard</a>@endif
        <a class="tile link" href="{{ route('orders.index') }}"><x-icon name="list" />Antrean PO</a>
        <a class="tile link" href="{{ route('delivery.index') }}"><x-icon name="truck" />Surat jalan</a>
        <a class="tile link" href="{{ route('stock.index') }}"><x-icon name="box" />Stok</a>
        <a class="tile link" href="{{ route('purchases.index') }}"><x-icon name="cart" />Pembelian</a>
        @if ($isAdmin)
            <a class="tile link" href="{{ route('invoices.index') }}"><x-icon name="receipt" />Invoice</a>
            <a class="tile link" href="{{ route('returns.index') }}"><x-icon name="undo" />Retur</a>
            <a class="tile link" href="{{ route('items.index') }}"><x-icon name="tag" />Barang</a>
            <a class="tile link" href="{{ route('suppliers.index') }}"><x-icon name="users" />Supplier</a>
        @endif
    @endif

    @if ($user->isSuperAdmin())
        <div class="tile"><small>Pengguna aktif</small><b>{{ $stats['users'] }}</b></div>
        <div class="tile"><small>Outlet aktif</small><b>{{ $stats['outlets'] }}</b></div>
        <a class="tile link" href="{{ route('users.index') }}"><x-icon name="users" />Kelola pengguna</a>
        <a class="tile link" href="{{ route('outlets.index') }}"><x-icon name="store" />Kelola outlet</a>
        <a class="tile link" href="{{ route('settings.edit') }}"><x-icon name="settings" />Pengaturan</a>
    @endif
</div>
@endsection
