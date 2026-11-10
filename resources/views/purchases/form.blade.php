@extends('layouts.app')
@section('title', 'Catat pembelian')
@section('content')
@php
    $oldLines = old('lines', array_fill(0, 4, ['item_id' => '', 'unit_id' => '', 'qty' => '', 'unit_price' => '']));
@endphp
<header class="page-head">
    <div><h1>Catat pembelian</h1><p>Harga beli dicatat per satuan yang dibeli. Stok bertambah saat barang ditandai diterima.</p></div>
    <a class="btn" href="{{ route('purchases.index') }}"><x-icon name="back" :size="18" /> Kembali</a>
</header>
<div class="card">
    <form method="POST" action="{{ route('purchases.store') }}" novalidate>
        @csrf
        <div class="row">
            <label class="field">Supplier
                <select name="supplier_id" required>
                    <option value="">Pilih supplier</option>
                    @foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected((int) old('supplier_id') === $s->id)>{{ $s->name }}</option>@endforeach
                </select>
                @error('supplier_id')<span class="err">{{ $message }}</span>@enderror
            </label>
            <label class="field">Tanggal beli
                <input type="date" name="purchased_at" value="{{ old('purchased_at', today()->toDateString()) }}" required>
                @error('purchased_at')<span class="err">{{ $message }}</span>@enderror
            </label>
            <label class="field">Jatuh tempo bayar
                <input type="date" name="due_at" value="{{ old('due_at', today()->addDays(7)->toDateString()) }}" required>
                @error('due_at')<span class="err">{{ $message }}</span>@enderror
            </label>
        </div>

        <h3 class="sect">Barang yang dibeli</h3>
        @error('lines')<div class="alert" role="alert">{{ $message }}</div>@enderror
        <div class="tbl-wrap">
        <table class="tbl">
            <thead><tr><th style="min-width:200px">Barang</th><th style="min-width:110px">Satuan</th><th style="min-width:90px">Qty</th><th style="min-width:130px">Harga / satuan (Rp)</th></tr></thead>
            <tbody>
            @foreach ($oldLines as $n => $l)
                <tr>
                    <td><select name="lines[{{ $n }}][item_id]" data-item aria-label="Barang">
                        <option value="">-</option>
                        @foreach ($items as $i)<option value="{{ $i->id }}" @selected((string) ($l['item_id'] ?? '') === (string) $i->id)>{{ $i->name }}</option>@endforeach
                    </select></td>
                    <td><select name="lines[{{ $n }}][unit_id]" data-unit data-selected="{{ $l['unit_id'] ?? '' }}" aria-label="Satuan"><option value="">-</option></select></td>
                    <td><input type="number" name="lines[{{ $n }}][qty]" value="{{ $l['qty'] ?? '' }}" step="any" min="0" inputmode="decimal" aria-label="Qty">
                        @error("lines.$n.qty")<span class="err">{{ $message }}</span>@enderror</td>
                    <td><input type="number" name="lines[{{ $n }}][unit_price]" value="{{ $l['unit_price'] ?? '' }}" step="1" min="0" inputmode="numeric" aria-label="Harga">
                        @error("lines.$n.unit_price")<span class="err">{{ $message }}</span>@enderror</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>

        <label class="field" style="margin-top:14px">Catatan <span class="hint">(opsional)</span>
            <input type="text" name="notes" value="{{ old('notes') }}" maxlength="500">
        </label>
        <label style="display:flex;gap:8px;align-items:center;margin-bottom:16px;font-weight:600">
            <input type="checkbox" name="receive_now" value="1" @checked(old('receive_now')) style="width:20px;height:20px"> Barang sudah diterima, langsung tambahkan ke stok
        </label>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn" href="{{ route('purchases.index') }}">Batal</a>
            <button type="submit" class="btn primary">Simpan pembelian</button>
        </div>
    </form>
</div>
<script>
const UNITS = @json($itemUnits);
document.querySelectorAll('tr').forEach(function (tr) {
    var it = tr.querySelector('[data-item]'), un = tr.querySelector('[data-unit]');
    if (!it || !un) return;
    function fill(keep) {
        var list = UNITS[it.value] || [];
        un.innerHTML = '<option value="">-</option>' + list.map(function (u) {
            return '<option value="' + u.unit_id + '">' + u.name + (u.factor !== 1 ? ' (isi ' + u.factor + ')' : '') + '</option>';
        }).join('');
        if (keep) un.value = keep; else if (list.length) un.value = list[list.length - 1].unit_id;
    }
    it.addEventListener('change', function () { fill(''); });
    if (it.value) fill(un.dataset.selected);
});
</script>
@endsection
