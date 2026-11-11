@extends('layouts.app')
@section('title', 'Rekap minusan')
@section('content')
@use('App\Support\Fmt')
@php $isCk = auth()->user()->hasRole('admin_ck'); @endphp
<header class="page-head">
    <div><h1>Rekap minusan{{ $outlet ? ' · '.$outlet->name : '' }}</h1><p>Selisih opname kurang dan waste karena kelalaian (tumpah / salah olah) ditanggung outlet. Waste rusak dan kadaluarsa tidak ditagih.</p></div>
    <a class="btn" href="{{ route('outletstock.index', ['outlet' => $outlet?->id]) }}"><x-icon name="back" :size="18" /> Kembali</a>
</header>
@if (! $outlet)<div class="empty">Belum ada outlet.</div>@else
@error('charge')<div class="alert bad" role="alert">{{ $message }}</div>@enderror
<form method="GET" class="toolbar">
    @if ($outlets->isNotEmpty())<select name="outlet" aria-label="Outlet" onchange="this.form.submit()">@foreach ($outlets as $o)<option value="{{ $o->id }}" @selected($o->id === $outlet->id)>{{ $o->name }}</option>@endforeach</select>@endif
    <input type="month" name="bulan" value="{{ $month }}" aria-label="Bulan" onchange="this.form.submit()">
</form>
<div class="stats">
    <div class="stat warn"><small>Ditanggung outlet bulan ini</small><b>{{ Fmt::rp($billed) }}</b></div>
    <div class="stat"><small>Selisih lebih (info)</small><b>{{ Fmt::rp($over) }}</b></div>
    <div class="stat"><small>Waste tidak ditagih</small><b>{{ Fmt::rp($notBilled) }}</b></div>
</div>
@if ($isCk)
<div class="card" style="flex-direction:row;flex-wrap:wrap;align-items:center;gap:12px">
    <div style="flex:1 1 240px"><b>Belum ditagih sampai akhir bulan ini</b><div style="font-size:12px;color:var(--muted)">{{ $pendingCount }} item · {{ Fmt::rp($pendingTotal) }}</div></div>
    <form method="POST" action="{{ route('charges.store') }}" data-confirm="Buat tagihan minusan untuk {{ $outlet->name }}?">@csrf
        <input type="hidden" name="outlet" value="{{ $outlet->id }}"><input type="hidden" name="bulan" value="{{ $month }}">
        <button class="btn primary" type="submit" @disabled($pendingCount === 0)>Buat tagihan minusan</button></form>
</div>
@endif
<section class="list">
    @forelse ($byDay as $day => $rows)
        <div class="dayhead"><b>{{ \Illuminate\Support\Carbon::parse($day)->translatedFormat('l, j M Y') }}</b><span>{{ $rows->count() }} item</span></div>
        @foreach ($rows as $r)
        <div class="item">
            <div class="who"><b>{{ $r->item->name }}</b>
                <small>@if ($r->type === 'so')Opname: sistem {{ Fmt::qty($r->qty_system) }} → fisik {{ Fmt::qty($r->qty_base) }} {{ $r->item->baseUnit->name }}@else Waste {{ \App\Models\StockRequest::REASONS[$r->reason] ?? $r->reason }}{{ $r->notes ? ' · '.$r->notes : '' }}@endif</small></div>
            <div class="col"><b style="color:{{ $r->value < 0 ? 'var(--bad)' : 'var(--ok)' }}">{{ $r->diff > 0 ? '+' : '' }}{{ Fmt::qty($r->diff) }}</b><small>{{ $r->value < 0 ? '−' : '+' }}{{ Fmt::rp(abs($r->value)) }}</small></div>
            @if ($r->charge_id)<span class="badge info">Sudah ditagih</span>@elseif ($r->bill)<span class="badge warn">Ditagih</span>@else<span class="badge">{{ $r->type === 'so' ? 'Info' : 'Tidak ditagih' }}</span>@endif
        </div>
        @endforeach
    @empty
        <div class="empty">Belum ada minusan di bulan ini.</div>
    @endforelse
</section>
<div class="card"><h3 class="sect">Tagihan minusan</h3>
    @forelse ($charges as $c)
    <a class="item" href="{{ route('charges.show', $c) }}" style="text-decoration:none">
        <div class="who"><b>{{ $c->number }}</b><small>Periode {{ $c->period }} · jatuh tempo {{ Fmt::date($c->due_at) }}</small></div>
        <div class="col"><b>{{ Fmt::rp($c->total) }}</b></div>
        <span class="badge {{ $c->status === 'lunas' ? 'ok' : 'warn' }}">{{ $c->status === 'lunas' ? 'Lunas' : 'Belum dibayar' }}</span>
    </a>
    @empty<div class="empty">Belum ada tagihan minusan.</div>@endforelse
</div>
@endif
@endsection
