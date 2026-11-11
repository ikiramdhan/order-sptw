@extends('layouts.app')
@section('title', 'Rekap minusan')
@section('content')
@use('App\Support\Fmt')
@php $isCk = auth()->user()->hasRole('admin_ck'); @endphp
<header class="page-head compact">
    <div><h1>Rekap minusan</h1><p>{{ $outlet?->name }}. Ditanggung outlet: opname kurang dan waste tumpah.</p></div>
    <a class="btn" href="{{ route('outletstock.index', ['outlet' => $outlet?->id]) }}"><x-icon name="back" :size="18" /> Kembali</a>
</header>
@if (! $outlet)<div class="empty">Belum ada outlet.</div>@else
@error('charge')<div class="alert bad" role="alert">{{ $message }}</div>@enderror
<form method="GET" class="toolbar">
    @if ($outlets->isNotEmpty())<select name="outlet" aria-label="Outlet" onchange="this.form.submit()">@foreach ($outlets as $o)<option value="{{ $o->id }}" @selected($o->id === $outlet->id)>{{ $o->name }}</option>@endforeach</select>@endif
    <input type="month" name="bulan" value="{{ $month }}" aria-label="Bulan" onchange="this.form.submit()">
</form>
<div class="stats s3">
    <div class="stat warn"><small>Ditanggung outlet</small><b>{{ Fmt::rp($billed) }}</b></div>
    <div class="stat"><small>Selisih lebih</small><b>{{ Fmt::rp($over) }}</b></div>
    <div class="stat"><small>Waste tidak ditagih</small><b>{{ Fmt::rp($notBilled) }}</b></div>
</div>
@if ($isCk)
<div class="card actrow">
    <div class="grow"><b>Belum ditagih</b><div style="font-size:13px;color:var(--muted)">{{ $pendingCount }} item · {{ Fmt::rp($pendingTotal) }}</div></div>
    <form method="POST" action="{{ route('charges.store') }}" data-confirm="Buat tagihan minusan untuk {{ $outlet->name }}?">@csrf
        <input type="hidden" name="outlet" value="{{ $outlet->id }}"><input type="hidden" name="bulan" value="{{ $month }}">
        <button class="btn primary" type="submit" @disabled($pendingCount === 0)>Buat tagihan minusan</button></form>
    @if ($pendingCount === 0)<div style="flex-basis:100%;font-size:12px;color:var(--muted)">Belum ada minusan yang bisa ditagih (atau sudah masuk tagihan).</div>@endif
</div>
@endif
<section class="list">
    @forelse ($byDay as $day => $rows)
        <div class="dayhead"><b>{{ \Illuminate\Support\Carbon::parse($day)->translatedFormat('l, j M Y') }}</b><span>{{ $rows->count() }} item</span></div>
        @foreach ($rows as $r)
        <div class="mrow">
            <div class="who"><b>{{ $r->item->name }}</b>
                <small>@if ($r->type === 'so')Opname: sistem {{ Fmt::qty($r->qty_system) }} → fisik {{ Fmt::qty($r->qty_base) }} {{ $r->item->baseUnit->name }}@else Waste {{ \App\Models\StockRequest::REASONS[$r->reason] ?? $r->reason }}{{ $r->notes ? ' · '.$r->notes : '' }}@endif</small></div>
            <div class="val"><b style="color:{{ $r->value < 0 ? 'var(--bad)' : 'var(--ok)' }}">{{ $r->value < 0 ? '−' : '+' }}{{ Fmt::rp(abs($r->value)) }}</b>
                <small>{{ $r->diff > 0 ? '+' : '' }}{{ Fmt::qty($r->diff) }} {{ $r->item->baseUnit->name }}</small>
                @if ($r->charge_id)<span class="badge info">Sudah ditagih</span>@elseif ($r->bill)<span class="badge warn">Ditagih</span>@else<span class="badge">{{ $r->type === 'so' ? 'Info' : 'Tidak ditagih' }}</span>@endif</div>
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
