@extends('layouts.app')
@section('title', 'Keranjang')
@section('content')
@use('App\Support\Fmt')
<header class="page-head compact">
    <div><h1>Keranjang</h1><p>Periksa pesanan sebelum dikirim</p></div>
    <a class="btn" href="{{ route('catalog.index') }}"><x-icon name="back" :size="18" /> Katalog</a>
</header>
@error('cart')<div class="alert" role="alert">{{ $message }}</div>@enderror
<div class="card">
@forelse ($lines as $l)
    <div class="cartline">
        <div class="who"><b>{{ $l['item']->name }}</b><small>{{ Fmt::rp($l['price']) }} per {{ $l['unit']['name'] }}</small></div>
        <b class="sub">{{ Fmt::rp(round($l['qty'] * $l['price'])) }}</b>
        <form class="qtyf" method="POST" action="{{ route('cart.update', $l['key']) }}">@csrf @method('PUT')
            <input type="number" name="qty" value="{{ $l['qty'] + 0 }}" min="0" step="any" inputmode="decimal" aria-label="Jumlah">
            <span class="un">{{ $l['unit']['name'] }}</span>
            <button class="btn sm" type="submit">Ubah</button>
        </form>
        <form class="delf" method="POST" action="{{ route('cart.remove', $l['key']) }}">@csrf @method('DELETE')<button class="btn sm danger" type="submit">Hapus</button></form>
    </div>
@empty
    <div class="empty">Keranjang kosong. Pilih barang dari katalog.</div>
@endforelse
@if ($lines->count())
    <div class="sum"><span>Perkiraan total</span><b>{{ Fmt::rp($total) }}</b></div>
    <p style="color:var(--muted);font-size:12px;text-align:right;margin:4px 0 0">Total final mengikuti qty yang benar-benar Anda terima.</p>
    <form method="POST" action="{{ route('cart.submit') }}" style="margin-top:16px">@csrf
        <label class="field">Catatan untuk Central Kitchen <span class="hint">(opsional)</span><input type="text" name="notes" maxlength="300"></label>
        <button class="btn primary block" type="submit">Kirim PO</button>
    </form>
@endif
</div>
@endsection
