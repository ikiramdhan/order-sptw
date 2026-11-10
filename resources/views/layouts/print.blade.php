<!doctype html>
<html lang="id">
<head>
@include('layouts.head')
</head>
<body style="background:#eee">
<div class="printbar">
    <button class="btn primary" onclick="window.print()"><x-icon name="print" :size="18" /> Cetak / simpan PDF</button>
    <a class="btn" href="javascript:history.back()">Kembali</a>
</div>
<div class="sheet">
    <div style="display:flex;align-items:center;gap:12px">
        <img src="/icons/logo.png" alt="" style="width:52px;height:52px;object-fit:contain">
        <div><b style="font-size:16px">Seblak Teh Wanti</b><br><span style="color:#555">Central Kitchen</span></div>
    </div>
    @yield('content')
</div>
</body>
</html>
