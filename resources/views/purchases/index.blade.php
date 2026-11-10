@extends('layouts.app')
@section('title', 'Pembelian')
@section('content')
@use('App\Support\Fmt')
<header class="page-head">
    <div><h1>Pembelian supplier</h1><p>Catat belanja bahan, terima barang, dan lunasi hutang</p></div>
    @if (auth()->user()->hasRole('admin_ck'))
        <a class="btn primary" href="{{ route('purchases.create') }}"><x-icon name="plus" :size="18" /> Catat pembelian</a>
    @endif
</header>
@if (auth()->user()->hasRole('admin_ck'))
<div class="stats">
    <div class="stat"><small>Hutang ke supplier</small><b>{{ Fmt::rp($unpaid) }}</b></div>
    <div class="stat warn"><small>Lewat jatuh tempo</small><b>{{ Fmt::rp($overdue) }}</b></div>
</div>
@endif
<form method="GET" class="toolbar pfilter" id="pf">
    <select name="status" onchange="this.form.submit()" aria-label="Filter status">
        <option value="semua">Semua status</option>
        @foreach (\App\Models\SupplierPurchase::STATUS as $k => [$label])
            <option value="{{ $k }}" @selected($status === $k)>{{ $label }}</option>
        @endforeach
    </select>
    <select name="supplier" onchange="this.form.submit()" aria-label="Supplier">
        <option value="">Semua supplier</option>
        @foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected($supplierId === $s->id)>{{ $s->name }}</option>@endforeach
    </select>
    <label class="dt"><span>Jatuh tempo dari</span><input type="date" name="dari" value="{{ $dari }}" onchange="this.form.submit()"></label>
    <label class="dt"><span>sampai</span><input type="date" name="sampai" value="{{ $sampai }}" onchange="this.form.submit()"></label>
    <div class="seg" role="radiogroup" aria-label="Tampilan">
        <label><input type="radio" name="tampil" value="daftar" @checked($tampil === 'daftar') onchange="this.form.submit()"><span>Daftar</span></label>
        <label><input type="radio" name="tampil" value="supplier" @checked($tampil === 'supplier') onchange="this.form.submit()"><span>Per supplier</span></label>
    </div>
    @if ($filterDue || $supplierId || $status !== 'semua')<a class="btn" href="{{ route('purchases.index') }}">Reset</a>@endif
</form>
@if ($tampil === 'supplier')
@php
    $rangeLabel = $dari && $sampai ? ($dari === $sampai ? Fmt::date($dari) : Fmt::date($dari).' – '.Fmt::date($sampai)) : ($dari ? 'mulai '.Fmt::date($dari) : ($sampai ? 'sampai '.Fmt::date($sampai) : 'semua tanggal'));
@endphp
<div class="stats">
    <div class="stat"><small>Harus dibayar · jatuh tempo {{ $rangeLabel }}</small><b>{{ Fmt::rp($payableTotal) }}</b></div>
    <div class="stat"><small>Supplier</small><b>{{ $groups->count() }}</b></div>
    <div class="stat"><small>Pembelian</small><b>{{ $payable->count() }}</b></div>
</div>
@if ($byDate->count() > 1)
<section class="card bydate" aria-label="Per tanggal jatuh tempo">
    <h3 class="sect" style="margin-top:0">Total per tanggal jatuh tempo</h3>
    @foreach ($byDate as $d => $tot)
        <div class="r"><span>{{ Fmt::date($d) }}</span><b>{{ Fmt::rp($tot) }}</b></div>
    @endforeach
</section>
@endif
<section class="groups">
    @forelse ($groups as $g)
        @php
            $sp = $g['supplier'];
            $copy = $sp->name."\n".($sp->bankLine() ?: 'Rekening belum diisi')."\nTotal transfer: ".Fmt::rp($g['total']);
        @endphp
        <article class="card sgroup">
            <header>
                <div class="who">
                    <b>{{ $sp->name }}</b>
                    @if ($sp->bankLine())<span class="rek">{{ $sp->bankLine() }}</span>@else<span class="rek none">Rekening belum diisi @if (auth()->user()->hasRole('admin_ck')) — <a href="{{ route('suppliers.edit', $sp) }}">lengkapi</a>@endif</span>@endif
                </div>
                <div class="tot"><small>Transfer</small><b>{{ Fmt::rp($g['total']) }}</b></div>
            </header>
            <ul>
                @foreach ($g['rows'] as $p)
                    <li><a href="{{ route('purchases.show', $p) }}"><span>{{ $p->number }} <small>beli {{ Fmt::date($p->purchased_at) }} · tempo {{ Fmt::date($p->due_at) }}</small></span><b>{{ Fmt::rp($p->remaining()) }}</b></a></li>
                @endforeach
            </ul>
            <button type="button" class="btn" data-copy="{{ $copy }}">Salin info transfer</button>
        </article>
    @empty
        <div class="empty">Tidak ada tagihan supplier yang harus dibayar pada filter ini.</div>
    @endforelse
</section>
@else
<section class="list">
    @forelse ($purchases as $p)
        @php [$sl, $sc] = \App\Models\SupplierPurchase::STATUS[$p->payment_status] ?? [$p->payment_status, 'info']; @endphp
        <a class="item" href="{{ route('purchases.show', $p) }}" style="text-decoration:none">
            <div class="who"><b>{{ $p->number }} · {{ $p->supplier->name }}</b><small>Beli {{ Fmt::date($p->purchased_at) }} · jatuh tempo {{ Fmt::date($p->due_at) }}</small></div>
            <div class="col"><b>{{ Fmt::rp($p->total) }}</b><small>sisa {{ Fmt::rp($p->remaining()) }}</small></div>
            <span class="badge {{ $p->received_at ? 'ok' : 'warn' }}">{{ $p->received_at ? 'Barang diterima' : 'Belum diterima' }}</span>
            <span class="badge {{ $sc }}">{{ $sl }}</span>
        </a>
    @empty
        <div class="empty">Belum ada pembelian.</div>
    @endforelse
</section>
@endif
@endsection
