@extends('layouts.app')
@section('title', 'Retur')
@section('content')
@use('App\Support\Fmt')
<header class="page-head">
    <div><h1>Retur</h1><p>{{ $isOutlet ? 'Ajukan retur barang rusak atau tidak sesuai dari surat jalan yang sudah diterima' : 'Putuskan retur: potong tagihan atau kirim barang pengganti' }}</p></div>
</header>
@if ($isOutlet)
<div class="card">
    <h3 class="sect">Ajukan retur baru</h3>
    @forelse ($notes as $n)
        <a class="item" href="{{ route('returns.create', ['surat_jalan' => $n->id]) }}" style="text-decoration:none">
            <div class="who"><b>{{ $n->number }}</b><small>Diterima, invoice {{ $n->invoice->number }}</small></div>
            <span class="btn sm">Pilih</span>
        </a>
    @empty
        <p style="color:var(--muted);margin:0">Belum ada surat jalan yang sudah diterima.</p>
    @endforelse
</div>
@endif
<form method="GET" class="toolbar">
    <select name="status" onchange="this.form.submit()" aria-label="Status">
        <option value="semua">Semua status</option>
        @foreach (\App\Models\ReturnRequest::STATUS as $k => [$label])<option value="{{ $k }}" @selected($filter===$k)>{{ $label }}</option>@endforeach
    </select>
</form>
<section class="list">
    @forelse ($returns as $r)
        @php [$sl, $sc] = $r->statusLabel(); @endphp
        <a class="item" href="{{ route('returns.show', $r) }}" style="text-decoration:none">
            <div class="who"><b>{{ $r->number }}@unless($isOutlet) · {{ $r->outlet->name }}@endunless</b><small>{{ Fmt::date($r->created_at) }} · {{ $r->items->count() }} barang</small></div>
            <div class="col"><b>{{ Fmt::rp($r->totalValue()) }}</b><small>nilai retur</small></div>
            <span class="badge {{ $sc }}">{{ $sl }}</span>
        </a>
    @empty
        <div class="empty">Belum ada retur.</div>
    @endforelse
</section>
@endsection
