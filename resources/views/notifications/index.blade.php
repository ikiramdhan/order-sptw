@extends('layouts.app')
@section('title', 'Notifikasi')
@section('content')
<header class="page-head">
    <div><h1>Notifikasi</h1><p>Kabar terbaru tentang PO, pengiriman, dan retur</p></div>
    <form method="POST" action="{{ route('notifications.read') }}">@csrf<button class="btn" type="submit">Tandai semua dibaca</button></form>
</header>
<section class="list">
    @forelse ($items as $n)
        <a class="item {{ $n->read_at ? '' : 'unread' }}" href="{{ route('notifications.open', $n) }}" style="text-decoration:none">
            <div class="who"><b>{{ $n->title }}</b><small>{{ $n->body }}</small></div>
            <div class="col"><small>{{ $n->created_at->diffForHumans() }}</small></div>
        </a>
    @empty
        <div class="empty">Belum ada notifikasi.</div>
    @endforelse
</section>
@endsection
