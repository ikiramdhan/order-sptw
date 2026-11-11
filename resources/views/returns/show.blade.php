@extends('layouts.app')
@section('title', $ret->number)
@section('content')
@use('App\Support\Fmt')
@php [$sl, $sc] = $ret->statusLabel(); $pending = $ret->status === 'diajukan'; @endphp
<header class="page-head compact">
    <div><h1>{{ $ret->number }}</h1><p>{{ $ret->outlet->name }} · surat jalan {{ $ret->deliveryNote->number }}</p></div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <span class="badge {{ $sc }}">{{ $sl }}</span>
        @if ($ret->creditNote)<a class="btn" href="{{ route('returns.nota', $ret) }}" target="_blank" rel="noopener"><x-icon name="print" :size="18" /> Nota Retur</a>@endif
        <a class="btn" href="{{ route('returns.index') }}"><x-icon name="back" :size="18" /> Kembali</a>
    </div>
</header>
@error('decision')<div class="alert" role="alert">{{ $message }}</div>@enderror
@error('resolution')<div class="alert" role="alert">{{ $message }}</div>@enderror
@error('reason')<div class="alert" role="alert">{{ $message }}</div>@enderror
@if ($ret->notes)<div class="hint-box">Catatan outlet: {{ $ret->notes }}</div>@endif
@if ($ret->status === 'ditolak')<div class="alert" role="status">Ditolak: {{ $ret->reject_reason }}</div>@endif

@if (! $isOutlet && $pending)
<form method="POST" action="{{ route('returns.approve', $ret) }}" class="card" novalidate>@csrf
    <h3 class="sect">Putuskan retur</h3>
    <div class="tbl-wrap"><table class="tbl">
        <thead><tr><th>Barang</th><th class="num">Qty</th><th>Alasan</th><th class="num">Nilai</th><th style="min-width:160px">Barang retur</th></tr></thead>
        <tbody>@foreach ($ret->items as $row)
            <tr><td>{{ $row->item->name }}</td><td class="num">{{ Fmt::qtyBoth($row->qty_input, $row->unit->name, $row->qty_base, $row->item->baseUnit->name) }}</td>
                <td>{{ \App\Models\ReturnRequest::REASONS[$row->reason] ?? $row->reason }}</td><td class="num">{{ Fmt::rp($row->value) }}</td>
                <td><select name="disposition[{{ $row->id }}]" aria-label="Nasib barang">@foreach (\App\Models\ReturnItem::DISPOSITIONS as $k => $v)<option value="{{ $k }}" @selected($row->reason !== 'tidak_sesuai' ? $k === 'dibuang' : $k === 'masuk_stok')>{{ $v }}</option>@endforeach</select></td></tr>
        @endforeach</tbody>
        <tfoot><tr><td colspan="3" class="num">Total nilai retur</td><td class="num">{{ Fmt::rp($ret->totalValue()) }}</td><td></td></tr></tfoot>
    </table></div>
    <div class="field" style="margin-top:14px">Penyelesaian
        <div class="seg" role="radiogroup">
            @foreach (\App\Models\ReturnRequest::RESOLUTIONS as $k => $v)<label><input type="radio" name="resolution" value="{{ $k }}" {{ old('resolution', 'potong_tagihan') === $k ? 'checked' : '' }}><span>{{ $v }}</span></label>@endforeach
        </div>
    </div>
    <button class="btn primary" type="submit">Setujui retur</button>
</form>
<form method="POST" action="{{ route('returns.reject', $ret) }}" class="card" data-confirm="Tolak retur ini?">@csrf
    <h3 class="sect">Tolak retur</h3>
    <div class="row" style="align-items:flex-end"><label class="field" style="margin:0">Alasan<input type="text" name="reason" maxlength="200" required></label><button class="btn danger" type="submit">Tolak</button></div>
</form>
@else
<div class="card">
    <div class="tbl-wrap"><table class="tbl">
        <thead><tr><th>Barang</th><th class="num">Qty</th><th>Alasan</th><th class="num">Nilai</th>@if($ret->resolution)<th>Barang retur</th>@endif</tr></thead>
        <tbody>@foreach ($ret->items as $row)
            <tr><td>{{ $row->item->name }}</td><td class="num">{{ Fmt::qtyBoth($row->qty_input, $row->unit->name, $row->qty_base, $row->item->baseUnit->name) }}</td>
                <td>{{ \App\Models\ReturnRequest::REASONS[$row->reason] ?? $row->reason }}</td><td class="num">{{ Fmt::rp($row->value) }}</td>
                @if($ret->resolution)<td>{{ \App\Models\ReturnItem::DISPOSITIONS[$row->disposition] ?? '-' }}</td>@endif</tr>
        @endforeach</tbody>
        <tfoot><tr><td colspan="3" class="num">Total nilai retur</td><td class="num">{{ Fmt::rp($ret->totalValue()) }}</td>@if($ret->resolution)<td></td>@endif</tr></tfoot>
    </table></div>
    @if ($ret->resolution)
        <p style="margin:12px 0 0"><b>Penyelesaian:</b> {{ \App\Models\ReturnRequest::RESOLUTIONS[$ret->resolution] }}</p>
        @if ($ret->creditNote)
            <p style="margin:4px 0 0;color:var(--muted)">Nota Retur {{ $ret->creditNote->number }}: {{ Fmt::rp($ret->creditNote->applied_to_invoice) }} memotong invoice {{ $ret->invoice?->number }}@if($ret->creditNote->to_balance > 0), {{ Fmt::rp($ret->creditNote->to_balance) }} menjadi saldo kredit untuk tagihan berikutnya @endif.</p>
        @endif
        @if ($ret->replacement)
            <p style="margin:4px 0 0;color:var(--muted)">Barang pengganti: <a class="link" href="{{ route('delivery.show', $ret->replacement) }}">{{ $ret->replacement->number }}</a> ({{ $ret->replacement->status === 'diterima' ? 'sudah diterima' : 'dalam pengiriman' }})</p>
        @endif
    @endif
</div>
@endif
@endsection
