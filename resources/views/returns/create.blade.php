@extends('layouts.app')
@section('title', 'Ajukan retur')
@section('content')
@use('App\Support\Fmt')
<header class="page-head">
    <div><h1>Ajukan retur</h1><p>Surat jalan {{ $note->number }}</p></div>
    <a class="btn" href="{{ route('delivery.show', $note) }}"><x-icon name="back" :size="18" /> Kembali</a>
</header>
@error('lines')<div class="alert" role="alert">{{ $message }}</div>@enderror
<div class="card">
@if ($lines->isEmpty())
    <div class="empty">Tidak ada barang yang bisa diretur dari surat jalan ini.</div>
@else
<form method="POST" action="{{ route('returns.store') }}" novalidate>@csrf
    <input type="hidden" name="delivery_note_id" value="{{ $note->id }}">
    <p style="color:var(--muted);margin:0 0 10px">Isi jumlah hanya untuk barang yang diretur, dan pilih alasannya. Central Kitchen yang memutuskan penyelesaiannya.</p>
    <div class="tbl-wrap"><table class="tbl">
        <thead><tr><th>Barang</th><th class="num">Bisa diretur</th><th style="min-width:100px">Jumlah retur</th><th style="min-width:170px">Alasan</th></tr></thead>
        <tbody>@foreach ($lines as $itemId => $l)
            <tr><td>{{ $l['item']->name }}</td>
                <td class="num">{{ Fmt::qty($l['available_base'] / $l['factor']) }} {{ $l['unit']->name }}</td>
                <td>@if ($l['available_base'] > 0)<input type="number" name="lines[{{ $itemId }}][qty]" value="{{ old("lines.$itemId.qty") }}" min="0" step="any" inputmode="decimal" aria-label="Jumlah retur {{ $l['item']->name }}">@else<span class="badge ok">Sudah diretur</span>@endif</td>
                <td>@if ($l['available_base'] > 0)<select name="lines[{{ $itemId }}][reason]" aria-label="Alasan">@foreach ($reasons as $k => $v)<option value="{{ $k }}" @selected(old("lines.$itemId.reason") === $k)>{{ $v }}</option>@endforeach</select>@endif</td></tr>
        @endforeach</tbody>
    </table></div>
    <label class="field" style="margin-top:14px">Catatan <span class="hint">(opsional)</span><input type="text" name="notes" maxlength="300" value="{{ old('notes') }}"></label>
    <button class="btn primary" type="submit">Kirim pengajuan retur</button>
</form>
@endif
</div>
@endsection
