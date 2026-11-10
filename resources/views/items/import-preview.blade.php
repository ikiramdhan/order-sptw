@extends('layouts.app')
@section('title', 'Pratinjau impor')
@section('content')
@use('App\Support\Fmt')
<header class="page-head">
    <div><h1>Pratinjau impor</h1><p>Periksa dan sunting langsung di tabel. Tekan "Periksa ulang" setelah mengubah. Baris yang masih error dilewati.</p></div>
    <a class="btn" href="{{ route('items.import') }}"><x-icon name="back" :size="18" /> Unggah ulang</a>
</header>
<div class="stats">
    <div class="stat"><small>Barang baru</small><b id="s-new" style="color:var(--ok)">{{ $new }}</b></div>
    <div class="stat"><small>Diperbarui</small><b id="s-upd">{{ $update }}</b></div>
    <div class="stat warn"><small>Error (dilewati)</small><b id="s-err">{{ $errors }}</b></div>
</div>
<form method="POST" action="{{ route('items.import.apply') }}" id="impform" class="card" novalidate>@csrf
    <input type="hidden" name="payload" id="payload">
    <div class="tbl-wrap"><table class="tbl editable" id="imptbl">
        <thead><tr><th>Status</th><th>Kode</th><th>Kategori</th><th>Nama</th><th>Satuan</th><th>Harga CS</th><th>Harga CK</th><th>Stok min</th><th>Stok awal</th><th>Satuan besar</th><th>Isi</th><th>Catatan</th><th></th></tr></thead>
        <tbody>@foreach ($rows as $r)
            @php $raw = $r['raw'] ?? []; @endphp
            <tr class="{{ $r['status'] === 'error' ? 'rowerr' : '' }}">
                <td data-label="Status"><span class="badge {{ $r['status'] === 'error' ? 'bad' : ($r['status'] === 'baru' ? 'ok' : 'info') }}">{{ ucfirst($r['status']) }}</span></td>
                <td data-label="Kode" class="has-input"><input type="text" data-f="code" value="{{ $r['code'] ?? '' }}" aria-label="Kode" style="min-width:70px"></td>
                <td data-label="Kategori" class="has-input"><input type="text" data-f="category" value="{{ $r['category'] }}" aria-label="Kategori" style="min-width:110px"></td>
                <td data-label="Nama" class="has-input"><input type="text" data-f="name" value="{{ $r['name'] }}" aria-label="Nama" style="min-width:190px"></td>
                <td data-label="Satuan" class="has-input"><input type="text" data-f="base_unit" value="{{ $r['base_unit'] }}" aria-label="Satuan" style="min-width:70px"></td>
                <td data-label="Harga CS" class="has-input"><input type="text" data-f="retail_price" value="{{ $raw['retail_price'] ?? '' }}" inputmode="numeric" aria-label="Harga CS" style="min-width:90px"></td>
                <td data-label="Harga CK" class="has-input"><input type="text" data-f="price" value="{{ $raw['price'] ?? '' }}" inputmode="numeric" aria-label="Harga CK" style="min-width:90px"></td>
                <td data-label="Stok min" class="has-input"><input type="text" data-f="min_stock" value="{{ $raw['min_stock'] ?? '' }}" inputmode="decimal" aria-label="Stok minimum" style="min-width:70px"></td>
                <td data-label="Stok awal" class="has-input"><input type="text" data-f="stock" value="{{ $raw['stock'] ?? '' }}" inputmode="decimal" aria-label="Stok awal" style="min-width:70px"></td>
                <td data-label="Satuan besar" class="has-input"><input type="text" data-f="big_unit" value="{{ $r['big_unit'] }}" aria-label="Satuan besar" style="min-width:80px"></td>
                <td data-label="Isi" class="has-input"><input type="text" data-f="factor" value="{{ $raw['factor'] ?? '' }}" inputmode="decimal" aria-label="Isi" style="min-width:60px"></td>
                <td data-label="Catatan" style="min-width:150px">@foreach ($r['errors'] as $e)<div class="err">{{ $e }}</div>@endforeach @foreach ($r['notes'] as $n)<div style="color:var(--muted);font-size:12px">{{ $n }}</div>@endforeach</td>
                <td><button type="button" class="btn sm danger rm" aria-label="Hapus baris" title="Hapus baris">✕</button></td>
            </tr>
        @endforeach</tbody>
    </table></div>
    <div class="impbar">
        <button class="btn" type="submit" name="action" value="recheck">Periksa ulang</button>
        <button class="btn primary" type="submit" name="action" value="save" id="save-btn" @if ($new + $update === 0) hidden @endif>Simpan {{ $new + $update }} barang</button>
        <span class="err" id="save-none" @if ($new + $update > 0) hidden @endif>Belum ada baris yang valid untuk disimpan.</span>
        <span id="chk-state" style="color:var(--muted);font-size:12px;align-self:center"></span>
    </div>
</form>
<script>
(function () {
    var form = document.getElementById('impform'), tb = document.querySelector('#imptbl tbody'), state = document.getElementById('chk-state');
    var url = @json(route('items.import.check')), token = document.querySelector('meta[name=csrf-token]').content, timer = null, seq = 0;
    function collect() {
        var rows = [];
        tb.querySelectorAll('tr').forEach(function (tr) {
            var o = {}; tr.querySelectorAll('[data-f]').forEach(function (i) { o[i.getAttribute('data-f')] = i.value; }); rows.push(o);
        });
        return rows;
    }
    function esc(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
    function apply(res) {
        var trs = tb.querySelectorAll('tr');
        res.rows.forEach(function (r, i) {
            var tr = trs[i]; if (!tr) return;
            tr.classList.toggle('rowerr', r.status === 'error');
            var b = tr.querySelector('.badge');
            b.className = 'badge ' + (r.status === 'error' ? 'bad' : (r.status === 'baru' ? 'ok' : 'info'));
            b.textContent = r.status.charAt(0).toUpperCase() + r.status.slice(1);
            var note = tr.querySelector('td[data-label=Catatan]');
            note.innerHTML = r.errors.map(function (e) { return '<div class="err">' + esc(e) + '</div>'; }).join('') + r.notes.map(function (n) { return '<div style="color:var(--muted);font-size:12px">' + esc(n) + '</div>'; }).join('');
        });
        document.getElementById('s-new').textContent = res.new;
        document.getElementById('s-upd').textContent = res.update;
        document.getElementById('s-err').textContent = res.errors;
        var n = res.new + res.update, btn = document.getElementById('save-btn');
        btn.textContent = 'Simpan ' + n + ' barang'; btn.hidden = n === 0; document.getElementById('save-none').hidden = n > 0;
        state.textContent = 'Sudah diperiksa';
    }
    function check() {
        var my = ++seq, fd = new FormData(); fd.append('payload', JSON.stringify(collect())); fd.append('_token', token);
        state.textContent = 'Memeriksa…';
        fetch(url, { method: 'POST', body: fd, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
            .then(function (res) { if (my === seq) apply(res); })
            .catch(function () { if (my === seq) state.textContent = 'Gagal memeriksa, tekan Periksa ulang'; });
    }
    function later() { state.textContent = 'Diubah…'; clearTimeout(timer); timer = setTimeout(check, 600); }
    tb.addEventListener('input', later);
    tb.addEventListener('click', function (e) { var b = e.target.closest('.rm'); if (b) { b.closest('tr').remove(); later(); } });
    form.addEventListener('submit', function () { document.getElementById('payload').value = JSON.stringify(collect()); });
})();
</script>
@endsection
