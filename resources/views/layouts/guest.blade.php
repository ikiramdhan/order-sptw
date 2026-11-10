<!doctype html>
<html lang="id">
<head>
@include('layouts.head')
</head>
<body>
<main class="auth">
    <div class="box">
        <div class="brand">
            <img src="/icons/logo.png" alt="Logo Seblak Teh Wanti">
            <span>Central Kitchen · Seblak Teh Wanti</span>
        </div>
        @yield('content')
    </div>
</main>
<script src="/js/app.js?v=1" defer></script>
</body>
</html>
