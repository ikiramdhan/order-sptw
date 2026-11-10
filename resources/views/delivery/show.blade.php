@extends('layouts.app')
@section('title', $note->number)
@section('content')
@use('App\Support\Fmt')
<header class="page-head">
    <div><h1>{{ $note->number }}</h1><p>{{ $note->outlet->name }} · PO {{ $note->purchaseOrder?->number }}@if($note->type === 'pengganti_retur') · barang pengganti retur@endif</p></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        @if ($isOutlet && $note->type === 'regular' && $note->status === 'diterima' && $note->invoice)<a class="btn" href="{{ route('returns.create', ['surat_jalan' => $note->id]) }}">Ajukan retur</a>@endif
        <a class="btn" href="{{ route('delivery.print', $note) }}" target="_blank" rel="noopener"><x-icon name="print" :size="18" /> Cetak</a>
        <a class="btn" href="{{ route('delivery.index') }}"><x-icon name="back" :size="18" /> Kembali</a>
    </div>
</header>
@error('received')<div class="alert" role="alert">{{ $message }}</div>@enderror
@php $pending = $note->status === 'dikirim'; @endphp

@if ($isOutlet && $pending)
<form method="POST" action="{{ route('delivery.receive', $note) }}" class="card" novalidate data-confirm="Konfirmasi penerimaan? Invoice akan terbit sesuai qty yang Anda isi.">@csrf
    <h3 class="sect">Konfirmasi penerimaan</h3>
    <p style="color:var(--muted);margin:0 0 10px">Isi jumlah yang benar-benar diterima. Invoice dihitung dari jumlah ini.</p>
    <div class="tbl-wrap"><table class="tbl">
        <thead><tr><th>Barang</th><th class="num">Dikirim</th><th style="min-width:120px">Diterima</th></tr></thead>
        <tbody>@foreach ($note->items as $row)
            <tr><td>{{ $row->item->name }}</td>
                <td class="num">{{ Fmt::qty($row->qty_input) }} {{ $row->unit->name }}</td>
                <td><input type="number" name="received[{{ $row->id }}]" value="{{ old("received.$row->id", $row->qty_input + 0) }}" min="0" max="{{ $row->qty_input + 0 }}" step="any" inputmode="decimal" aria-label="Diterima {{ $row->item->name }}"></td></tr>
        @endforeach</tbody>
    </table></div>
    <label class="field" style="margin-top:14px">Catatan <span class="hint">(mis. barang rusak, kurang)</span><input type="text" name="notes" maxlength="300" value="{{ old('notes') }}"></label>
    <button class="btn primary" type="submit">Konfirmasi diterima</button>
</form>
@else
<div class="card">
    <div class="kv">
        <div><small>Dikirim</small><b>{{ Fmt::date($note->shipped_at, 'j M Y H:i') }}</b></div>
        <div><small>Status</small><span class="badge {{ $pending ? 'warn' : 'ok' }}">{{ $pending ? 'Dalam pengiriman' : 'Diterima' }}</span></div>
        @if ($note->receipt)<div><small>Diterima</small><b>{{ Fmt::date($note->receipt->received_at, 'j M Y H:i') }}</b></div>@endif
        @if ($note->invoice)<div><small>Invoice</small><a class="link" href="{{ route('invoices.show', $note->invoice) }}">{{ $note->invoice->number }}</a></div>@endif
    </div>
    @if ($note->receipt?->notes)<p style="color:var(--muted)">Catatan penerimaan: {{ $note->receipt->notes }}</p>@endif
    <div class="tbl-wrap"><table class="tbl">
        <thead><tr><th>Barang</th><th class="num">Dikirim</th></tr></thead>
        <tbody>@foreach ($note->items as $row)
            <tr><td>{{ $row->item->name }}</td><td class="num">{{ Fmt::qtyBoth($row->qty_input, $row->unit->name, $row->qty_base, $row->item->baseUnit->name) }}</td></tr>
        @endforeach</tbody>
    </table></div>
</div>
@endif
@endsection
