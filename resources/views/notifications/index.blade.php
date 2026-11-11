@extends('layouts.app')
@section('title', 'Notifikasi')
@section('content')
@php
    $kind = function ($n) {
        $u = (string) $n->url; $t = strtolower($n->title);
        if (str_contains($t, 'ditolak')) return ['bad', 'undo'];
        if (str_starts_with($u, '/retur')) return ['warn', 'undo'];
        if (str_starts_with($u, '/surat-jalan')) return ['info', 'truck'];
        if (str_starts_with($u, '/po')) return ['brand', 'list'];
        if (str_starts_with($u, '/tagihan') || str_starts_with($u, '/invoice')) return ['warn', 'receipt'];
        if (str_starts_with($u, '/persetujuan') || str_starts_with($u, '/stok')) return ['ok', 'box'];
        return ['brand', 'bell'];
    };
    $label = function ($d) {
        if ($d->isToday()) return 'Hari ini';
        if ($d->isYesterday()) return 'Kemarin';
        return $d->translatedFormat('l, j M Y');
    };
    $unread = $items->whereNull('read_at')->count();
@endphp
<header class="page-head compact">
    <div><h1>Notifikasi</h1><p>{{ $unread ? $unread.' belum dibaca' : 'Semua sudah dibaca' }}</p></div>
    @if ($unread)<form method="POST" action="{{ route('notifications.read') }}">@csrf<button class="btn outline" type="submit">Tandai dibaca</button></form>@endif
</header>
@forelse ($items->groupBy(fn ($n) => $n->created_at->toDateString()) as $day => $rows)
    <div class="ndayhead">{{ $label(\Illuminate\Support\Carbon::parse($day)) }}</div>
    <section class="nlist">
        @foreach ($rows as $n)
            @php [$tone, $ico] = $kind($n); @endphp
            <a class="nitem {{ $n->read_at ? '' : 'unread' }}" href="{{ route('notifications.open', $n) }}">
                <span class="nico {{ $tone }}"><x-icon name="{{ $ico }}" :size="20" /></span>
                <span class="ntxt"><b>{{ $n->title }}</b>@if ($n->body)<span class="nbody">{{ $n->body }}</span>@endif</span>
                <span class="nmeta"><time>{{ $n->created_at->format('H:i') }}</time>@unless ($n->read_at)<i class="ndot" aria-label="Belum dibaca"></i>@endunless</span>
            </a>
        @endforeach
    </section>
@empty
    <div class="card"><div class="empty">Belum ada notifikasi.</div></div>
@endforelse
@endsection
