<!doctype html>
<html lang="id">
<head>
@include('layouts.head')
</head>
<body class="authbody">
<x-theme-toggle class="theme-toggle floating" />
<div class="auth2">
    <aside class="auth-hero">
        <span class="blob b1" aria-hidden="true"></span><span class="blob b2" aria-hidden="true"></span><span class="blob b3" aria-hidden="true"></span>
        <div class="hero-in">
            <div class="logo-wrap" style="--i:0"><img src="/icons/logo.png" alt="Logo Seblak Teh Wanti"></div>
            <p class="eyebrow" style="--i:1">Central Kitchen</p>
            <h2 style="--i:2">Seblak Teh Wanti</h2>
            <p class="lead" style="--i:3">Pesan bahan baku, pantau pengiriman, dan bereskan tagihan outlet dalam satu aplikasi.</p>
            <ul class="pills" style="--i:4">
                <li>PO ke Central Kitchen</li><li>Surat jalan</li><li>Invoice &amp; pembayaran</li>
            </ul>
        </div>
        <div class="floaters" aria-hidden="true">
            <div class="fl f1"><i></i><span><b>PO-2611-0012</b><small>Sedang dikirim</small></span></div>
            <div class="fl f2"><i class="ok"></i><span><b>Invoice lunas</b><small>Tepat waktu</small></span></div>
            <div class="fl f3"><i class="warn"></i><span><b>Stok aman</b><small>Semua barang</small></span></div>
        </div>
    </aside>
    <main class="auth-main">
        <div class="box">
            @yield('content')
        </div>
        <p class="copy">&copy; {{ date('Y') }} Seblak Teh Wanti</p>
    </main>
</div>
<script src="/js/app.js?v=5" defer></script>
</body>
</html>
