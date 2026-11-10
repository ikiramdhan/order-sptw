@extends('layouts.app')
@section('title', 'Pratinjau impor')
@section('content')
@use('App\Support\Fmt')
<header class="page-head">
    <div><h1>Pratinjau impor</h1><p>Periksa dulu. Baris yang error akan dilewati.</p></div>
    <a class="btn" href="{{ route('items.import') }}"><x-icon name="back" :size="18" /> Unggah ulang</a>
</header>
<div class="stats">
    <div class="stat"><small>Barang baru</small><b style="color:var(--ok)">{{ $new }}</b></div>
    <div class="stat"><small>Diperbarui</small><b>{{ $update }}</b></div>
    <div class="stat warn"><small>Error (dilewati)</small><b>{{ $errors }}</b></div>
</div>
<div class="card">
    <div class="tbl-wrap"><table class="tbl">
        <thead><tr><th>Baris</th><th>Status</th><th>Nama</th><th>Kategori</th><th>Satuan</th><th class="num">Harga</th><th class="num">Stok min</th><th class="num">Stok awal</th><th>Konversi</th><th>Catatan</th></tr></thead>
        <tbody>@foreach ($rows as $r)
            <tr>
                <td>{{ $r['line'] }}</td>
                <td><span class="badge {{ $r['status'] === 'error' ? 'bad' : ($r['status'] === 'baru' ? 'ok' : 'info') }}">{{ ucfirst($r['status']) }}</span></td>
                <td>{{ $r['name'] }}</td><td>{{ $r['category'] }}</td><td>{{ $r['base_unit'] }}</td>
                <td class="num">{{ $r['price'] !== null ? Fmt::rp($r['price']) : '-' }}</td>
                <td class="num">{{ $r['min_stock'] !== null ? Fmt::qty($r['min_stock']) : '-' }}</td>
                <td class="num">{{ $r['stock'] !== null ? Fmt::qty($r['stock']) : '-' }}</td>
                <td>{{ $r['big_unit'] !== '' ? '1 '.$r['big_unit'].' = '.Fmt::qty($r['factor']).' '.$r['base_unit'] : '-' }}</td>
                <td style="min-width:200px">@foreach ($r['errors'] as $e)<div class="err">{{ $e }}</div>@endforeach @foreach ($r['notes'] as $n)<div style="color:var(--muted);font-size:12px">{{ $n }}</div>@endforeach</td>
            </tr>
        @endforeach</tbody>
    </table></div>
    @if ($new + $update > 0)
    <form method="POST" action="{{ route('items.import.apply') }}" style="margin-top:16px" data-confirm="Simpan {{ $new + $update }} barang?">@csrf
        <button class="btn primary" type="submit">Simpan {{ $new + $update }} barang</button>
    </form>
    @else
        <p class="err" style="margin-top:12px">Tidak ada baris yang valid untuk disimpan.</p>
    @endif
</div>
@endsection
