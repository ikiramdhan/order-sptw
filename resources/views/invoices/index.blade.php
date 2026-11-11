@extends('layouts.app')
@section('title', 'Invoice')
@section('content')
@use('App\Support\Fmt')
<header class="page-head">
    <div><h1>{{ $isOutlet ? 'Tagihan saya' : 'Invoice' }}</h1><p>Invoice terbit otomatis setelah outlet konfirmasi penerimaan</p></div>
    <a class="btn" href="{{ route('rekap.index', $outletId ? ['outlet' => $outletId] : []) }}">Rekap bulanan</a>
</header>
<div class="stats">
    <div class="stat"><small>Belum dibayar</small><b>{{ Fmt::rp($unpaid) }}</b></div>
    <div class="stat warn"><small>Lewat jatuh tempo</small><b>{{ Fmt::rp($overdue) }}</b></div>
</div>
<form method="GET" class="toolbar">
    <select name="status" onchange="this.form.submit()" aria-label="Status">
        <option value="belum" @selected($status==='belum')>Belum lunas</option>
        <option value="lunas" @selected($status==='lunas')>Lunas</option>
        <option value="semua" @selected($status==='semua')>Semua</option>
    </select>
    @unless($isOutlet)
    <select name="outlet" onchange="this.form.submit()" aria-label="Outlet">
        <option value="">Semua outlet</option>
        @foreach ($outlets as $o)<option value="{{ $o->id }}" @selected((int) $outletId === $o->id)>{{ $o->name }}</option>@endforeach
    </select>
    @endunless
</form>
@php $byDay = $invoices->groupBy(fn ($i) => $i->issued_at->toDateString()); @endphp
<section class="list">
    @forelse ($byDay as $day => $rows)
        <div class="dayhead"><b>{{ \Illuminate\Support\Carbon::parse($day)->translatedFormat('l, j M Y') }}</b><span>{{ $rows->count() }} invoice · {{ Fmt::rp($rows->sum(fn ($r) => $r->remaining())) }} sisa</span></div>
        @foreach ($rows as $i)
        @php [$sl, $sc] = \App\Models\Invoice::STATUS[$i->status] ?? [$i->status, 'info']; @endphp
        <a class="item" href="{{ route('invoices.show', $i) }}" style="text-decoration:none">
            <div class="who"><b>{{ $i->number }}@unless($isOutlet) · {{ $i->outlet->name }}@endunless</b><small>Jatuh tempo {{ Fmt::date($i->due_at) }}</small></div>
            <div class="col"><b>{{ Fmt::rp($i->total - $i->credit_applied) }}</b><small>sisa {{ Fmt::rp($i->remaining()) }}</small></div>
            <span class="badge {{ $sc }}">{{ $sl }}</span>
        </a>
        @endforeach
    @empty
        <div class="empty">Tidak ada invoice.</div>
    @endforelse
</section>
@endsection
