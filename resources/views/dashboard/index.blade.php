@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
@use('App\Support\Fmt')
<header class="page-head">
    <div><h1>Dashboard</h1><p>Ringkasan Central Kitchen · {{ $monthLabel }}</p></div>
</header>
<div class="bento">
    <section class="tile hero w2 h2" aria-label="Sorotan">
        <div><small>Penjualan bulan ini</small><div class="big" data-count>{{ Fmt::rp($sales) }}</div></div>
        <div><small>Uang masuk bulan ini</small><div class="mid" data-count>{{ Fmt::rp($collected) }}</div></div>
    </section>
    <div class="tile"><small>Piutang outlet</small><b data-count>{{ Fmt::rp($receivable) }}</b></div>
    <div class="tile warn"><small>Piutang lewat tempo</small><b data-count>{{ Fmt::rp($receivableOverdue) }}</b></div>
    <div class="tile w2"><small>Hutang supplier</small><b data-count>{{ Fmt::rp($payable) }}</b></div>
    <a class="tile link" href="{{ route('orders.index') }}"><small>PO menunggu</small><b data-count>{{ $poWaiting }}</b></a>
    <a class="tile link" href="{{ route('orders.index', ['status' => 'menunggu_backorder']) }}"><small>Backorder</small><b data-count>{{ $backorder }}</b></a>
    <a class="tile link" href="{{ route('returns.index', ['status' => 'diajukan']) }}"><small>Retur menunggu</small><b data-count>{{ $returnsWaiting }}</b></a>
    <a class="tile link" href="{{ route('stock.index', ['menipis' => 1]) }}"><small>Stok menipis</small><b data-count>{{ $lowStock->count() }}</b></a>
</div>
<div class="two-col">
<div class="grow stack">
    <div class="card">
        <h3 class="sect">Penjualan 6 bulan terakhir</h3>
        <div class="bars grow" role="img" aria-label="Grafik penjualan 6 bulan terakhir">
            @foreach ($bars as $b)
                <div class="bar {{ $loop->last ? 'now' : '' }}"><b>{{ $b['value'] > 0 ? number_format($b['value'] / 1000000, 1, ',', '.').' jt' : '-' }}</b><i style="height:{{ max(2, round($b['value'] / $maxBar * 100)) }}%"></i>{{ $b['label'] }}</div>
            @endforeach
        </div>
    </div>
    <div class="card">
        <h3 class="sect">Per outlet</h3>
        <div class="tbl-wrap"><table class="tbl">
            <thead><tr><th>Outlet</th><th class="num">Penjualan bulan ini</th><th class="num">Piutang</th><th class="num">Lewat tempo</th></tr></thead>
            <tbody>@forelse ($perOutlet as $r)
                <tr><td><a class="link" href="{{ route('rekap.index', ['outlet' => $r['outlet']->id]) }}">{{ $r['outlet']->name }}</a></td>
                    <td class="num">{{ Fmt::rp($r['sales']) }}</td><td class="num">{{ Fmt::rp($r['open']) }}</td>
                    <td class="num {{ $r['overdue'] > 0 ? 'low' : '' }}">{{ Fmt::rp($r['overdue']) }}</td></tr>
            @empty<tr><td colspan="4" class="empty">Belum ada outlet.</td></tr>@endforelse</tbody>
        </table></div>
    </div>
</div>
<div class="side stack">
    <div class="card">
        <h3 class="sect">Barang terlaris bulan ini</h3>
        @forelse ($top as $t)
            <div style="display:flex;justify-content:space-between;gap:8px;padding:6px 0;border-bottom:1px solid var(--bg-2)"><span>{{ $t['item']->name }}<br><small style="color:var(--muted)">{{ Fmt::qty($t['qty']) }} {{ $t['item']->baseUnit->name }}</small></span><b>{{ Fmt::rp($t['value']) }}</b></div>
        @empty<p style="color:var(--muted);margin:0">Belum ada penjualan.</p>@endforelse
    </div>
    <div class="card">
        <h3 class="sect">Hutang jatuh tempo ≤ 7 hari</h3>
        @forelse ($payableDue as $p)
            <a href="{{ route('purchases.show', $p) }}" style="display:flex;justify-content:space-between;gap:8px;padding:6px 0;border-bottom:1px solid var(--bg-2);text-decoration:none"><span>{{ $p->supplier->name }}<br><small class="{{ $p->due_at->lt(today()) ? 'low' : '' }}" style="color:var(--muted)">{{ Fmt::date($p->due_at) }}</small></span><b>{{ Fmt::rp($p->remaining()) }}</b></a>
        @empty<p style="color:var(--muted);margin:0">Tidak ada.</p>@endforelse
    </div>
    <div class="card">
        <h3 class="sect">Stok menipis</h3>
        @forelse ($lowStock as $i)
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--bg-2)"><span>{{ $i->name }}</span><b class="low">{{ Fmt::qty($i->stock) }} {{ $i->baseUnit->name }}</b></div>
        @empty<p style="color:var(--muted);margin:0">Semua aman.</p>@endforelse
    </div>
</div>
</div>
@endsection
