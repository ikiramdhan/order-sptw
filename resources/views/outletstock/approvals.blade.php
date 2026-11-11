@extends('layouts.app')
@section('title', 'Persetujuan stok')
@section('content')
@use('App\Support\Fmt')
<header class="page-head"><div><h1>Persetujuan stok outlet</h1><p>Waste dan selisih opname dari outlet. Stok baru berubah setelah disetujui.</p></div></header>
@error('req')<div class="alert bad" role="alert">{{ $message }}</div>@enderror
@error('note')<div class="alert bad" role="alert">{{ $message }}</div>@enderror
<div class="card"><h3 class="sect">Menunggu ({{ $pending->count() }})</h3>
    <div class="tbl-wrap"><table class="tbl stackable">
        <thead><tr><th>Outlet</th><th>Jenis</th><th>Barang</th><th class="num">Detail</th><th>Catatan</th><th></th></tr></thead>
        <tbody>@forelse ($pending as $r)
            <tr><td data-label="Outlet">{{ $r->outlet?->name ?? 'CK' }}<br><small style="color:var(--muted)">{{ $r->creator->name }} · {{ $r->created_at->translatedFormat('j M H:i') }}</small></td>
                <td data-label="Jenis">{{ $r->type === 'waste' ? 'Waste' : 'Opname' }}</td>
                <td data-label="Barang">{{ $r->item->name }}</td>
                <td class="num" data-label="Detail">@if ($r->type === 'waste')-{{ Fmt::qty($r->qty_base) }} {{ $r->item->baseUnit->name }}<br><small>{{ \App\Models\StockRequest::REASONS[$r->reason] ?? $r->reason }}</small>@else Sistem {{ Fmt::qty($r->qty_system) }} → fisik {{ Fmt::qty($r->qty_base) }}@endif</td>
                <td data-label="Catatan">{{ $r->notes ?? '-' }}</td>
                <td>
                    <form method="POST" action="{{ route('approvals.approve', $r) }}" style="display:inline">@csrf
                        @if ($r->type === 'waste' && $r->reason === 'tumpah' && $r->outlet_id)<label style="display:flex;gap:6px;align-items:center;font-size:12px;margin-bottom:6px"><input type="hidden" name="charge" value="0"><input type="checkbox" name="charge" value="1" checked> Bebankan ke outlet</label>@endif
                        @if ($r->type === 'so')<div style="font-size:12px;color:var(--muted);margin-bottom:6px">Selisih kurang akan ditagihkan ke outlet.</div>@endif
                        <button class="btn primary" type="submit">Setujui</button></form>
                    <form method="POST" action="{{ route('approvals.reject', $r) }}" style="display:inline-flex;gap:4px;margin-top:6px">@csrf<input type="text" name="note" placeholder="Alasan tolak" maxlength="200" required style="min-width:120px"><button class="btn" type="submit">Tolak</button></form>
                </td></tr>
        @empty<tr><td colspan="6" class="empty">Tidak ada yang menunggu.</td></tr>@endforelse</tbody>
    </table></div></div>
<div class="card"><h3 class="sect">Keputusan terakhir</h3>
    <div class="tbl-wrap"><table class="tbl stackable"><thead><tr><th>Outlet</th><th>Jenis</th><th>Barang</th><th>Status</th></tr></thead>
    <tbody>@forelse ($recent as $r)<tr><td>{{ $r->outlet?->name }}</td><td>{{ $r->type === 'waste' ? 'Waste' : 'Opname' }}</td><td>{{ $r->item->name }}</td><td><span class="badge {{ $r->status === 'disetujui' ? 'ok' : 'bad' }}">{{ ucfirst($r->status) }}</span>@if ($r->decision_note) <small>{{ $r->decision_note }}</small>@endif</td></tr>
    @empty<tr><td colspan="4" class="empty">Belum ada.</td></tr>@endforelse</tbody></table></div></div>
@endsection
