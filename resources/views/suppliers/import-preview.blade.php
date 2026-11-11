@extends('layouts.app')
@section('title', 'Pratinjau impor supplier')
@section('content')
<header class="page-head compact">
    <div><h1>Pratinjau impor supplier</h1><p>Periksa dulu. Baris yang error akan dilewati.</p></div>
    <a class="btn" href="{{ route('suppliers.import') }}"><x-icon name="back" :size="18" /> Ulangi</a>
</header>
<div class="stats">
    <div class="stat"><small>Supplier baru</small><b style="color:var(--ok)">{{ $new }}</b></div>
    <div class="stat"><small>Diperbarui</small><b>{{ $update }}</b></div>
    <div class="stat warn"><small>Error (dilewati)</small><b>{{ $errors }}</b></div>
</div>
<div class="card">
    <div class="tbl-wrap"><table class="tbl">
        <thead><tr><th>Baris</th><th>Status</th><th>Nama supplier</th><th>Nama rekening</th><th>Bank</th><th>Nomor rekening</th><th>Catatan</th></tr></thead>
        <tbody>@foreach ($rows as $r)
            <tr>
                <td>{{ $r['line'] }}</td>
                <td><span class="badge {{ $r['status'] === 'error' ? 'bad' : ($r['status'] === 'baru' ? 'ok' : 'info') }}">{{ ucfirst($r['status']) }}</span></td>
                <td>{{ $r['name'] }}</td><td>{{ $r['account_name'] ?: '-' }}</td><td>{{ $r['bank'] ?: '-' }}</td><td>{{ $r['account_number'] ?: '-' }}</td>
                <td style="min-width:180px">@foreach ($r['errors'] as $e)<div class="err">{{ $e }}</div>@endforeach @foreach ($r['notes'] as $n)<div style="color:var(--muted);font-size:12px">{{ $n }}</div>@endforeach</td>
            </tr>
        @endforeach</tbody>
    </table></div>
    @if ($new + $update > 0)
    <form method="POST" action="{{ route('suppliers.import.apply') }}" style="margin-top:16px" data-confirm="Simpan {{ $new + $update }} supplier?">@csrf
        <button class="btn primary" type="submit">Simpan {{ $new + $update }} supplier</button>
    </form>
    @else
        <p class="err" style="margin-top:12px">Tidak ada baris yang valid untuk disimpan.</p>
    @endif
</div>
@endsection
