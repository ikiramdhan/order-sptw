<!doctype html>
<html lang="id">
<head>
@include('layouts.head')
</head>
<body>
@php
    $u = auth()->user();
    $nav = [['home', 'Beranda', 'home', true]];
    if ($u->role === 'outlet') {
        $nav[] = ['catalog.index', 'Katalog', 'store', true];
        $nav[] = ['orders.index', 'PO', 'list', true];
        $nav[] = ['delivery.index', 'Terima', 'truck', true];
        $nav[] = ['invoices.index', 'Tagihan', 'receipt', true];
        $nav[] = ['returns.index', 'Retur', 'undo', true];
    }
    if ($u->hasRole('admin_ck', 'gudang')) {
        $nav[] = ['orders.index', 'PO', 'list', true];
        $nav[] = ['delivery.index', 'Kirim', 'truck', true];
        $nav[] = ['stock.index', 'Stok', 'box', true];
    }
    if ($u->hasRole('admin_ck')) {
        array_splice($nav, 1, 0, [['dashboard', 'Dashboard', 'chart', true]]);
        $nav[] = ['returns.index', 'Retur', 'undo', true];
        $nav[] = ['invoices.index', 'Invoice', 'receipt', true];
        $nav[] = ['purchases.index', 'Beli', 'cart', true];
        $nav[] = ['items.index', 'Barang', 'tag', true];
    } elseif ($u->role === 'gudang') {
        $nav[] = ['purchases.index', 'Beli', 'cart', true];
    }
    if ($u->isSuperAdmin()) {
        $nav[] = ['users.index', 'Pengguna', 'users', true];
        $nav[] = ['outlets.index', 'Outlet', 'store', true];
        $nav[] = ['settings.edit', 'Pengaturan', 'settings', true];
    }
    $nav[] = ['notifications.index', 'Notif', 'bell', true];
    $nav[] = ['account.password', 'Akun', 'key', true];
    try { $unread = \App\Models\AppNotification::where('user_id', $u->id)->whereNull('read_at')->count(); } catch (\Throwable $e) { $unread = 0; }
    $activeMatch = fn ($route) => request()->routeIs(explode('.', $route)[0].'*') || ($route === 'invoices.index' && request()->routeIs('rekap.*'));
@endphp
<div class="shell">
    <nav class="rail" aria-label="Menu utama">
        <img class="logo" src="/icons/logo.png" alt="Logo Seblak Teh Wanti">
        @foreach ($nav as [$route, $label, $icon])
            <a href="{{ route($route) }}" class="{{ $activeMatch($route) ? 'active' : '' }}" @if($activeMatch($route)) aria-current="page" @endif>
                <span style="position:relative;display:inline-flex"><x-icon :name="$icon" />@if ($route === 'notifications.index' && $unread > 0)<i class="dot">{{ $unread > 9 ? '9+' : $unread }}</i>@endif</span>{{ $label }}
            </a>
        @endforeach
        <div class="spacer"></div>
        <form method="POST" action="{{ route('logout') }}" style="margin:0">
            @csrf
            <button type="submit" class="nav" aria-label="Keluar"><x-icon name="logout" />Keluar</button>
        </form>
    </nav>

    <div class="main">
        <div class="topbar-mobile">
            <img src="/icons/logo.png" alt="Logo Seblak Teh Wanti">
            <b>{{ $u->name }}<br><small style="color:var(--muted);font-weight:500">{{ $u->roleLabel() }}@if($u->outlet) · {{ $u->outlet->name }}@endif</small></b>
        </div>

        @if (session('status'))
            <div class="alert ok" role="status">{{ session('status') }}</div>
        @endif
        @if (session('temp_password'))
            @php $tp = session('temp_password'); @endphp
            <div class="tempbox" role="status">
                <b>Password sementara untuk {{ $tp['name'] }} ({{ $tp['email'] }})</b><br>
                <code>{{ $tp['password'] }}</code>
                <div style="font-size:12px;margin-top:8px">Salin dan kirim ke yang bersangkutan sekarang. Password ini tidak ditampilkan lagi, dan wajib diganti saat login pertama.</div>
            </div>
        @endif

        @yield('content')
    </div>
</div>
<script src="/js/app.js?v=2" defer></script>
</body>
</html>
