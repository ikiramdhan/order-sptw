@extends('layouts.app')
@section('title', $item->exists ? 'Ubah barang' : 'Tambah barang')
@section('content')
@php
    $rowsData = old('units') ?? $rows;
    $recipeData = old('recipe') ?? $recipeRows;
@endphp
<header class="page-head compact">
    <div><h1>{{ $item->exists ? 'Ubah barang' : 'Tambah barang' }}</h1>
        <p>Stok selalu dihitung dalam satuan dasar. Satuan lain (mis. Dus) dikonversi ke satuan dasar.</p></div>
    <a class="btn" href="{{ route('items.index') }}"><x-icon name="back" :size="18" /> Kembali</a>
</header>
<form method="POST" class="fbento cols-5-6" action="{{ $item->exists ? route('items.update', $item) : route('items.store') }}" novalidate>
    <div class="card">
        <h3 class="sect">Data barang</h3>
        @csrf
        @if ($item->exists) @method('PUT') @endif
        <div class="row">
            <label class="field"><span class="lbl">Kode <span class="hint">(opsional)</span></span>
                <input type="text" name="code" value="{{ old('code', $item->code) }}" maxlength="30">
            </label>
            <label class="field">Nama barang
                <input type="text" name="name" value="{{ old('name', $item->name) }}" required>
                @error('name')<span class="err">{{ $message }}</span>@enderror
            </label>
        </div>
        <div class="row">
            <label class="field"><span class="lbl">Kategori <span class="hint">(opsional)</span></span>
                <input type="text" name="category" value="{{ old('category', $item->category) }}">
            </label>
            <label class="field">Satuan dasar
                <select name="base_unit_id" required>
                    <option value="">Pilih</option>
                    @foreach ($units as $u)
                        <option value="{{ $u->id }}" @selected((int) old('base_unit_id', $item->base_unit_id) === $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
                @error('base_unit_id')<span class="err">{{ $message }}</span>@enderror
            </label>
        </div>
        <div class="row">
            <label class="field">Harga CK ke outlet per satuan dasar (Rp)
                <input type="text" data-money name="price" value="{{ number_format((int) round((float) old('price', $item->price)), 0, ',', '.') }}" inputmode="numeric" autocomplete="off" required>
                @error('price')<span class="err">{{ $message }}</span>@enderror
            </label>
            <label class="field"><span class="lbl">Harga CS <span class="hint">(outlet ke konsumen, info)</span></span>
                <input type="text" data-money name="retail_price" value="{{ ($v = old('retail_price', $item->retail_price)) === null || $v === '' ? '' : number_format((int) $v, 0, ',', '.') }}" inputmode="numeric" autocomplete="off">
            </label>
            <label class="field"><span class="lbl">Stok minimum <span class="hint">(peringatan menipis)</span></span>
                <input type="number" name="min_stock" value="{{ old('min_stock', $item->min_stock + 0) }}" min="0" step="any" inputmode="decimal">
            </label>
            <label class="field"><span class="lbl">{{ $item->exists ? 'Stok saat ini' : 'Stok awal' }} <span class="hint">(satuan dasar, boleh dikosongkan)</span></span>
                <input type="number" name="stock" value="{{ old('stock', $item->exists ? $item->stock + 0 : '') }}" min="0" step="any" inputmode="decimal" @if ($item->exists && $item->hasRecipe()) disabled placeholder="dihitung dari bahan" @endif>
                @error('stock')<span class="err">{{ $message }}</span>@enderror
            </label>
        </div>

    </div>
    <div class="card">
        <h3 class="sect">Konversi satuan</h3>
        <p style="color:var(--muted);margin:0 0 10px">Contoh: Dus, jumlah per satuan dasar = 48 artinya 1 Dus = 48 satuan dasar. Kosongkan baris yang tidak dipakai.</p>
        <div class="tbl-wrap">
        <table class="tbl">
            <thead><tr><th>Satuan</th><th>Isi (satuan dasar)</th><th>Dijual</th><th>Dibeli</th></tr></thead>
            <tbody>
            @foreach ($rowsData as $n => $r)
                <tr>
                    <td><select name="units[{{ $n }}][unit_id]" aria-label="Satuan">
                        <option value="">-</option>
                        @foreach ($units as $u)<option value="{{ $u->id }}" @selected((string) ($r['unit_id'] ?? '') === (string) $u->id)>{{ $u->name }}</option>@endforeach
                    </select>
                    @error("units.$n.unit_id")<span class="err">{{ $message }}</span>@enderror</td>
                    <td><input type="number" name="units[{{ $n }}][factor]" value="{{ $r['factor'] ?? '' }}" step="any" min="0" inputmode="decimal" aria-label="Isi">
                    @error("units.$n.factor")<span class="err">{{ $message }}</span>@enderror</td>
                    <td><input type="checkbox" name="units[{{ $n }}][is_sellable]" value="1" @checked(! empty($r['is_sellable'])) style="min-width:0;width:auto;min-height:0"></td>
                    <td><input type="checkbox" name="units[{{ $n }}][is_purchasable]" value="1" @checked(! empty($r['is_purchasable'])) style="min-width:0;width:auto;min-height:0"></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    </div>
    <div class="card" style="grid-column:1/-1">
        <h3 class="sect">Resep <span class="hint" style="font-weight:500">(opsional)</span></h3>
        <p style="color:var(--muted);margin:0 0 10px">Isi kalau barang ini dibuat CK dari bahan lain, mis. Bumbu Seblak. Takaran dihitung per 1 satuan dasar barang ini. Saat barang dikirim ke outlet, stok bahan terpotong otomatis, dan stok barang ini dihitung dari bahan yang tersedia.</p>
        @error('recipe')<div class="alert" role="alert">{{ $message }}</div>@enderror
        <div class="tbl-wrap">
        <table class="tbl" id="recipe-tbl">
            <thead><tr><th>Bahan</th><th>Takaran per 1 satuan dasar</th></tr></thead>
            <tbody>
            @foreach ($recipeData as $n => $r)
                <tr>
                    <td data-label="Bahan" class="has-input"><select name="recipe[{{ $n }}][ingredient_id]" aria-label="Bahan">
                        <option value="">-</option>
                        @foreach ($ingredients as $g)<option value="{{ $g->id }}" @selected((string) ($r['ingredient_id'] ?? '') === (string) $g->id)>{{ $g->name }} ({{ $g->baseUnit->name }})</option>@endforeach
                    </select>@error("recipe.$n.ingredient_id")<span class="err">{{ $message }}</span>@enderror</td>
                    <td data-label="Takaran" class="has-input"><input type="text" name="recipe[{{ $n }}][qty]" value="{{ $r['qty'] ?? '' }}" inputmode="decimal" autocomplete="off" placeholder="mis. 0,05" aria-label="Takaran">@error("recipe.$n.qty")<span class="err">{{ $message }}</span>@enderror</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        <template id="recipe-tpl"><table><tbody><tr>
            <td data-label="Bahan" class="has-input"><select name="recipe[__N__][ingredient_id]" data-recipe-ing aria-label="Bahan"><option value="">-</option>@foreach ($ingredients as $g)<option value="{{ $g->id }}">{{ $g->name }} ({{ $g->baseUnit->name }})</option>@endforeach</select></td>
            <td data-label="Takaran" class="has-input"><input type="text" name="recipe[__N__][qty]" inputmode="decimal" autocomplete="off" placeholder="mis. 0,05" aria-label="Takaran"></td>
        </tr></tbody></table></template>
        <button type="button" class="btn" id="recipe-add" style="margin-top:8px;align-self:flex-start">+ Tambah bahan</button>
    </div>
    <div class="formbar">
        <a class="btn" href="{{ route('items.index') }}">Batal</a>
        <button type="submit" class="btn primary">Simpan barang</button>
    </div>
</form>
<script>
(function () {
    var tb = document.querySelector('#recipe-tbl tbody'), idx = {{ count($recipeData) }};
    function add() {
        var html = document.getElementById('recipe-tpl').innerHTML.replace(/__N__/g, idx++).replace(/^\s*<table>\s*<tbody>|<\/tbody>\s*<\/table>\s*$/g, '');
        var h = document.createElement('tbody'); h.innerHTML = html; var tr = h.firstElementChild; tb.appendChild(tr); return tr;
    }
    document.getElementById('recipe-add').addEventListener('click', function () { add(); });
    tb.addEventListener('change', function (e) {
        if (e.target.matches('select') && e.target.value && e.target.closest('tr') === tb.lastElementChild) add();
    });
})();
</script>
@endsection
