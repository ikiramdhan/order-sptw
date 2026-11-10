<!doctype html>
<html lang="id">
<head>
@include('layouts.head')
</head>
<body>
@php
    $u = auth()->user();
    $nav = [['home', 'Beranda', 'home']];
    if ($u->role === 'outlet') {
        $nav[] = ['catalog.index', 'Katalog', 'store'];
        $nav[] = ['orders.index', 'PO', 'list'];
        $nav[] = ['delivery.index', 'Terima', 'truck'];
        $nav[] = ['invoices.index', 'Tagihan', 'receipt'];
        $nav[] = ['returns.index', 'Retur', 'undo'];
    }
    if ($u->hasRole('admin_ck', 'gudang')) {
        $nav[] = ['orders.index', 'PO', 'list'];
        $nav[] = ['delivery.index', 'Kirim', 'truck'];
        $nav[] = ['stock.index', 'Stok', 'box'];
    }
    if ($u->hasRole('admin_ck')) {
        array_splice($nav, 1, 0, [['dashboard', 'Dashboard', 'chart']]);
        $nav[] = ['returns.index', 'Retur', 'undo'];
        $nav[] = ['invoices.index', 'Invoice', 'receipt'];
        $nav[] = ['purchases.index', 'Beli', 'cart'];
        $nav[] = ['items.index', 'Barang', 'tag'];
        $nav[] = ['suppliers.index', 'Supplier', 'users'];
    } elseif ($u->role === 'gudang') {
        $nav[] = ['purchases.index', 'Beli', 'cart'];
    }
    if ($u->isSuperAdmin()) {
        $nav[] = ['users.index', 'Pengguna', 'users'];
        $nav[] = ['outlets.index', 'Outlet', 'store'];
        $nav[] = ['settings.edit', 'Pengaturan', 'settings'];
    }
    $nav[] = ['notifications.index', 'Notif', 'bell'];
    $nav[] = ['account.password', 'Akun', 'key'];

    // buang duplikat rute
    $seen = [];
    $nav = array_values(array_filter($nav, function ($n) use (&$seen) {
        if (isset($seen[$n[0]])) return false;
        return $seen[$n[0]] = true;
    }));

    // 4 menu utama di bilah bawah + "Lainnya" (HP/tablet)
    $primaryKeys = $u->role === 'outlet'
        ? ['home', 'catalog.index', 'orders.index', 'delivery.index']
        : ($u->role === 'gudang'
            ? ['home', 'orders.index', 'delivery.index', 'stock.index']
            : ['home', 'dashboard', 'orders.index', 'delivery.index']);
    $byKey = collect($nav)->keyBy(0);
    $primary = collect($primaryKeys)->map(fn ($k) => $byKey->get($k))->filter()->values()->all();
    $more = array_values(array_filter($nav, fn ($n) => ! in_array($n[0], $primaryKeys, true)));

    try { $unread = \App\Models\AppNotification::where('user_id', $u->id)->whereNull('read_at')->count(); } catch (\Throwable $e) { $unread = 0; }
    $activeMatch = fn ($route) => request()->routeIs(explode('.', $route)[0].'*') || ($route === 'invoices.index' && request()->routeIs('rekap.*'));
    $moreActive = collect($more)->contains(fn ($n) => $activeMatch($n[0]));
@endphp
<div class="shell">
    {{-- Laptop: rel samping --}}
    <nav class="rail" aria-label="Menu utama">
        <img class="logo" src="/icons/logo.png" alt="Logo Seblak Teh Wanti">
        @foreach ($nav as [$route, $label, $icon])
            <a href="{{ route($route) }}" class="{{ $activeMatch($route) ? 'active' : '' }}" @if($activeMatch($route)) aria-current="page" @endif>
                <span style="position:relative;display:inline-flex"><x-icon :name="$icon" />@if ($route === 'notifications.index' && $unread > 0)<i class="dot">{{ $unread > 9 ? '9+' : $unread }}</i>@endif</span>{{ $label }}
            </a>
        @endforeach
        <div class="spacer"></div>
        <x-theme-toggle class="nav" :label="true" />
        <form method="POST" action="{{ route('logout') }}" style="margin:0">
            @csrf
            <button type="submit" class="nav" aria-label="Keluar"><x-icon name="logout" />Keluar</button>
        </form>
    </nav>

    <div class="navbar-progress" aria-hidden="true"></div>
    <div class="main">
        <div class="topbar-mobile">
            <img src="/icons/logo.png" alt="Logo Seblak Teh Wanti">
            <div class="ttl"><b>@yield('title', 'Central Kitchen')</b><small>{{ $u->name }} · {{ $u->roleLabel() }}@if($u->outlet) · {{ $u->outlet->name }}@endif</small></div>
            <a class="bell" href="{{ route('notifications.index') }}" aria-label="Notifikasi"><x-icon name="bell" />@if ($unread > 0)<i class="dot">{{ $unread > 9 ? '9+' : $unread }}</i>@endif</a>
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

{{-- HP/tablet: bilah bawah 5 slot, tanpa geser --}}
<nav class="tabbar" aria-label="Menu utama">
    @foreach ($primary as [$route, $label, $icon])
        <a href="{{ route($route) }}" class="{{ $activeMatch($route) ? 'active' : '' }}" @if($activeMatch($route)) aria-current="page" @endif>
            <x-icon :name="$icon" />{{ $label }}
        </a>
    @endforeach
    <button type="button" data-more-open aria-haspopup="dialog" aria-expanded="false" aria-controls="more" class="{{ $moreActive ? 'active' : '' }}">
        <span style="position:relative;display:inline-flex"><x-icon name="more" />@if ($unread > 0)<i class="dot">{{ $unread > 9 ? '9+' : $unread }}</i>@endif</span>Lainnya
    </button>
</nav>

<div class="more" id="more" role="dialog" aria-modal="true" aria-label="Menu lainnya">
    <div class="backdrop" data-more-close></div>
    <div class="panel">
        <div class="handle" aria-hidden="true"></div>
        <div class="grid">
            @foreach ($more as [$route, $label, $icon])
                <a href="{{ route($route) }}" class="{{ $activeMatch($route) ? 'active' : '' }}">
                    <span style="position:relative;display:inline-flex"><x-icon :name="$icon" />@if ($route === 'notifications.index' && $unread > 0)<i class="dot">{{ $unread > 9 ? '9+' : $unread }}</i>@endif</span>{{ $label }}
                </a>
            @endforeach
            <x-theme-toggle class="themebtn" :label="true" />
            <form method="POST" action="{{ route('logout') }}" style="margin:0;display:contents">
                @csrf
                <button type="submit" class="out"><x-icon name="logout" />Keluar</button>
            </form>
        </div>
    </div>
</div>
<script src="/js/app.js?v=7" defer></script>
</body>
</html>
