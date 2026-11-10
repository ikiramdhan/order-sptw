@extends('layouts.app')
@section('title', 'Rekap bulanan')
@section('content')
@use('App\Support\Fmt')
<header class="page-head">
    <div><h1>Rekap bulanan</h1><p>Semua invoice satu outlet dalam satu bulan</p></div>
    @if ($outlet)<a class="btn" href="{{ route('rekap.print', ['outlet' => $outlet->id, 'bulan' => $month, 'status' => $filter]) }}" target="_blank" rel="noopener"><x-icon name="print" :size="18" /> Cetak</a>@endif
</header>
<form method="GET" class="toolbar">
    @unless($isOutlet)
    <select name="outlet" aria-label="Outlet" onchange="this.form.submit()">
        <option value="">Pilih outlet</option>
        @foreach ($outlets as $o)<option value="{{ $o->id }}" @selected($outlet && $outlet->id === $o->id)>{{ $o->name }}</option>@endforeach
    </select>
    @endunless
    <input type="month" name="bulan" value="{{ $month }}" aria-label="Bulan" onchange="this.form.submit()">
    <select name="status" aria-label="Status" onchange="this.form.submit()">
        <option value="semua" @selected($filter==='semua')>Semua</option>
        <option value="belum" @selected($filter==='belum')>Belum lunas</option>
        <option value="lunas" @selected($filter==='lunas')>Lunas</option>
    </select>
    <noscript><button class="btn" type="submit">Tampilkan</button></noscript>
</form>
@if (! $outlet)
    <div class="empty">Pilih outlet untuk melihat rekap.</div>
@else
<div class="stats">
    <div class="stat"><small>Total tagihan {{ $label }}</small><b>{{ Fmt::rp($totalAll) }}</b></div>
    <div class="stat"><small>Sudah dibayar</small><b style="color:var(--ok)">{{ Fmt::rp($paidAll) }}</b></div>
    <div class="stat warn"><small>Belum dibayar</small><b>{{ Fmt::rp($remainingAll) }}</b></div>
</div>
<div class="card">
    <div class="tbl-wrap"><table class="tbl">
        <thead><tr><th>Tanggal</th><th>Invoice</th><th>Surat jalan</th><th class="num">Tagihan</th><th class="num">Dibayar</th><th class="num">Sisa</th><th>Status</th></tr></thead>
        <tbody>@forelse ($invoices as $i)
            @php [$sl, $sc] = \App\Models\Invoice::STATUS[$i->status] ?? [$i->status, 'info']; @endphp
            <tr><td>{{ Fmt::date($i->issued_at, 'j M') }}</td><td><a class="link" href="{{ route('invoices.show', $i) }}">{{ $i->number }}</a></td><td>{{ $i->deliveryNote?->number }}</td>
                <td class="num">{{ Fmt::rp($i->total - $i->credit_applied) }}</td><td class="num">{{ Fmt::rp($i->paid) }}</td><td class="num">{{ Fmt::rp($i->remaining()) }}</td>
                <td><span class="badge {{ $sc }}">{{ $sl }}</span></td></tr>
        @empty<tr><td colspan="7" class="empty">Tidak ada invoice pada bulan ini.</td></tr>@endforelse</tbody>
    </table></div>
</div>
@endif
@endsection
