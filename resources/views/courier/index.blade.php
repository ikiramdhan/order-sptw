@extends('layouts.app')
@section('title', 'Antaran')
@section('content')
<header class="page-head">
    <div><h1>Antaran</h1><p>{{ $active->count() ? $active->count().' antaran aktif' : 'Belum ada antaran aktif' }}</p></div>
</header>
@forelse ($active as $n)
    <a class="courcard" href="{{ route('courier.show', $n) }}">
        <span class="nico {{ $n->courier_status === 'diantar' ? 'ok' : 'brand' }}"><x-icon name="truck" :size="22" /></span>
        <span class="ntxt"><span class="ochip" style="--h:{{ crc32($n->outlet->name) % 360 }}"><i></i>{{ $n->outlet->name }}</span><b>{{ $n->number }}</b><span class="nbody">{{ $n->items->count() }} barang · {{ $n->courier_status === 'diantar' ? 'Sedang diantar' : 'Menunggu dijemput' }}</span></span>
        <x-icon name="arrow" :size="18" />
    </a>
@empty
    <div class="card"><div class="empty">Belum ada antaran. Admin CK akan menugaskan surat jalan ke kamu.</div></div>
@endforelse
@if ($done->count())
    <div class="ndayhead">Selesai terakhir</div>
    <section class="nlist">
        @foreach ($done as $n)
            <a class="nitem" href="{{ route('courier.show', $n) }}">
                <span class="nico ok"><x-icon name="truck" :size="20" /></span>
                <span class="ntxt"><b>{{ $n->number }}</b><span class="nbody">{{ $n->outlet->name }}</span></span>
                <span class="nmeta"><time>{{ ($n->courier_arrived_at ?? $n->updated_at)->format('j M H:i') }}</time></span>
            </a>
        @endforeach
    </section>
@endif
@endsection
