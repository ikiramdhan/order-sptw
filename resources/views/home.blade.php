@extends('layouts.app')
@section('title', 'Beranda')
@section('content')
@use('App\Support\Fmt')
<div class="hero">
    <small>Selamat datang</small>
    <h2>{{ $user->name }}</h2>
    <small>{{ $user->roleLabel() }}@if($user->outlet) · {{ $user->outlet->name }}@endif</small>
</div>

@if ($user->role === 'outlet')
    <div class="stats">
        <div class="stat"><small>PO berjalan</small><b>{{ $stats['po_jalan'] ?? 0 }}</b></div>
        <div class="stat warn"><small>Perlu dikonfirmasi</small><b>{{ $stats['perlu_konfirmasi'] ?? 0 }}</b></div>
        <div class="stat"><small>Tagihan belum lunas</small><b>{{ Fmt::rp($stats['tagihan'] ?? 0) }}</b></div>
    </div>
    <div class="chips">
        <a class="chip" href="{{ route('catalog.index') }}">Buat PO</a>
        <a class="chip" href="{{ route('delivery.index') }}">Terima barang</a>
        <a class="chip" href="{{ route('invoices.index') }}">Tagihan</a>
        <a class="chip" href="{{ route('rekap.index') }}">Rekap bulanan</a>
        <a class="chip" href="{{ route('returns.index') }}">Retur</a>
    </div>
@endif

@if ($user->hasRole('admin_ck', 'gudang'))
    <div class="stats">
        <div class="stat warn"><small>PO menunggu</small><b>{{ $stats['po_baru'] }}</b></div>
        <div class="stat"><small>Backorder / sebagian</small><b>{{ $stats['backorder'] }}</b></div>
        <div class="stat warn"><small>Stok menipis</small><b>{{ $stats['stok_menipis'] }}</b></div>
        @if (isset($stats['piutang']))<div class="stat"><small>Piutang outlet</small><b>{{ Fmt::rp($stats['piutang']) }}</b></div>@endif
    </div>
    <div class="chips">
        @if ($user->hasRole('admin_ck'))<a class="chip" href="{{ route('dashboard') }}">Dashboard</a><a class="chip" href="{{ route('returns.index') }}">Retur</a>@endif
        <a class="chip" href="{{ route('orders.index') }}">Antrean PO</a>
        <a class="chip" href="{{ route('stock.index') }}">Stok</a>
        <a class="chip" href="{{ route('purchases.index') }}">Pembelian</a>
        @if ($user->hasRole('admin_ck'))
            <a class="chip" href="{{ route('invoices.index') }}">Invoice</a>
            <a class="chip" href="{{ route('items.index') }}">Barang</a>
            <a class="chip" href="{{ route('suppliers.index') }}">Supplier</a>
        @endif
    </div>
@endif

@if ($user->isSuperAdmin())
    <div class="stats">
        <div class="stat"><small>Pengguna aktif</small><b>{{ $stats['users'] }}</b></div>
        <div class="stat"><small>Outlet aktif</small><b>{{ $stats['outlets'] }}</b></div>
    </div>
    <div class="chips">
        <a class="chip" href="{{ route('users.index') }}">Kelola pengguna</a>
        <a class="chip" href="{{ route('outlets.index') }}">Kelola outlet</a>
        <a class="chip" href="{{ route('settings.edit') }}">Pengaturan email</a>
    </div>
@endif

@endsection
