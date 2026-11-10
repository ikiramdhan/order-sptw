@extends('layouts.app')
@section('title', 'Katalog')
@section('content')
@use('App\Support\Fmt')
<header class="page-head">
    <div><h1>Katalog</h1><p>Pilih barang dan jumlahnya, lalu kirim PO ke Central Kitchen</p></div>
    <a class="btn primary" href="{{ route('cart.show') }}"><x-icon name="cart" :size="18" /> Keranjang @if($cartCount)({{ $cartCount }})@endif</a>
</header>
@error('qty')<div class="alert" role="alert">{{ $message }}</div>@enderror
<form method="GET" class="toolbar"><input type="search" name="q" value="{{ $q }}" placeholder="Cari barang" aria-label="Cari barang"></form>
<section class="card">
    @forelse ($items as $i)
        @php $units = $i->sellableUnits(); @endphp
        <div class="prod">
            <div class="who"><b>{{ $i->name }}</b><small>{{ $i->category }}</small></div>
            <form method="POST" action="{{ route('cart.add') }}">@csrf
                <input type="hidden" name="item_id" value="{{ $i->id }}">
                <select name="unit_id" aria-label="Satuan {{ $i->name }}">
                    @foreach ($units->reverse() as $u)
                        <option value="{{ $u['unit_id'] }}">{{ $u['name'] }} · {{ Fmt::rp($i->priceFor($u['unit_id'])) }}</option>
                    @endforeach
                </select>
                <input type="number" name="qty" value="1" min="0" step="any" inputmode="decimal" aria-label="Jumlah {{ $i->name }}">
                <button class="btn sm primary" type="submit">Tambah</button>
            </form>
        </div>
    @empty
        <div class="empty">Belum ada barang di katalog.</div>
    @endforelse
</section>
@endsection
