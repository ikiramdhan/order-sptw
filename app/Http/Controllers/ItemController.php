<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Item;
use App\Services\StockService;
use App\Models\ItemUnit;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $status = $request->query('status', 'aktif');

        $items = Item::with(['baseUnit', 'itemUnits', 'recipe'])
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->when($status === 'aktif', fn ($query) => $query->where('is_active', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->get();

        return view('items.index', ['items' => $items, 'q' => $q, 'status' => $status]);
    }

    public function create()
    {
        return view('items.form', ['item' => new Item(['is_active' => true, 'price' => 0, 'min_stock' => 0]), 'units' => Unit::orderBy('name')->get(), 'rows' => $this->rows(null), 'ingredients' => $this->ingredientOptions(null), 'recipeRows' => $this->recipeRows(null)]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $conversions, $recipe] = $this->validated($request);

        $initial = $data['_stock']; unset($data['_stock']);
        $item = DB::transaction(function () use ($data, $conversions, $recipe, $initial) {
            $item = Item::create($data + ['stock' => 0, 'is_active' => true]);
            if ($initial) {
                StockService::move($item, 'penyesuaian', $initial, null, auth()->id(), 'Stok awal');
            }
            $this->syncConversions($item, $conversions);
            $this->syncRecipe($item, $recipe);

            return $item;
        });

        ActivityLog::record('item_created', $item->name, $item);

        return redirect()->route('items.index')->with('status', "Barang {$item->name} ditambahkan.");
    }

    public function edit(Item $item)
    {
        $item->load(['baseUnit', 'itemUnits', 'recipe']);

        return view('items.form', ['item' => $item, 'units' => Unit::orderBy('name')->get(), 'rows' => $this->rows($item), 'ingredients' => $this->ingredientOptions($item), 'recipeRows' => $this->recipeRows($item)]);
    }

    public function update(Request $request, Item $item): RedirectResponse
    {
        [$data, $conversions, $recipe] = $this->validated($request, $item);

        $stock = $data['_stock']; unset($data['_stock']);
        DB::transaction(function () use ($item, $data, $conversions, $recipe, $stock) {
            $item->update($data);
            if ($stock !== null && ! $item->hasRecipe() && abs($stock - (float) $item->fresh()->stock) >= 0.0005) {
                StockService::adjust($item, $stock, 'opname', 'Koreksi stok awal', auth()->id());
            }
            $this->syncConversions($item, $conversions);
            $this->syncRecipe($item, $recipe);
        });

        ActivityLog::record('item_updated', $item->name, $item);

        return redirect()->route('items.index')->with('status', "{$item->name} disimpan.");
    }

    public function toggle(Item $item): RedirectResponse
    {
        $item->update(['is_active' => ! $item->is_active]);
        ActivityLog::record($item->is_active ? 'item_activated' : 'item_deactivated', $item->name, $item);

        return back()->with('status', $item->is_active ? "{$item->name} dijual lagi." : "{$item->name} dinonaktifkan (tidak tampil di katalog).");
    }

    /** Bahan yang boleh dipakai di resep: barang aktif tanpa resep, bukan barang ini sendiri. */
    private function ingredientOptions(?Item $item)
    {
        return Item::with('baseUnit')->where('is_active', true)->whereDoesntHave('recipe')
            ->when($item?->exists, fn ($q) => $q->whereKeyNot($item->id))->orderBy('name')->get();
    }

    private function recipeRows(?Item $item): array
    {
        $rows = [];
        if ($item?->exists) {
            foreach ($item->recipe as $r) {
                $rows[] = ['ingredient_id' => $r->ingredient_id, 'qty' => $r->qty_base + 0];
            }
        }
        for ($i = 0; $i < 2; $i++) {
            $rows[] = ['ingredient_id' => '', 'qty' => ''];
        }

        return $rows;
    }

    private function syncRecipe(Item $item, array $recipe): void
    {
        $item->recipe()->delete();
        foreach ($recipe as $ingredientId => $qty) {
            $item->recipe()->create(['ingredient_id' => $ingredientId, 'qty_base' => $qty]);
        }
        $item->unsetRelation('recipe');
    }

    /** Baris form konversi: yang sudah ada + 3 baris kosong. */
    private function rows(?Item $item): array
    {
        $rows = [];

        if ($item) {
            foreach ($item->itemUnits as $iu) {
                $rows[] = ['unit_id' => $iu->unit_id, 'factor' => $iu->factor + 0, 'is_sellable' => $iu->is_sellable, 'is_purchasable' => $iu->is_purchasable];
            }
        }

        for ($i = 0; $i < 3; $i++) {
            $rows[] = ['unit_id' => '', 'factor' => '', 'is_sellable' => true, 'is_purchasable' => true];
        }

        return $rows;
    }

    private function validated(Request $request, ?Item $item = null): array
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:60'],
            'base_unit_id' => ['required', 'exists:units,id'],
            'price' => ['required', 'integer', 'min:0'],
            'retail_price' => ['nullable', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'numeric', 'min:0'],
            'units' => ['nullable', 'array'],
            'units.*.unit_id' => ['nullable', 'exists:units,id'],
            'units.*.factor' => ['nullable', 'numeric', 'gt:0'],
        ], [
            'name.required' => 'Nama barang wajib diisi.',
            'base_unit_id.required' => 'Pilih satuan dasar.',
            'price.required' => 'Harga jual wajib diisi.',
            'price.integer' => 'Harga jual harus berupa angka bulat (rupiah).',
            'units.*.factor.gt' => 'Isi konversi dengan angka lebih dari 0.',
        ]);

        $baseUnit = (int) $data['base_unit_id'];
        $conversions = [];

        foreach ($request->input('units', []) as $i => $row) {
            $unitId = (int) ($row['unit_id'] ?? 0);
            if ($unitId === 0) {
                continue;
            }
            if ($unitId === $baseUnit) {
                throw ValidationException::withMessages(["units.$i.unit_id" => 'Satuan dasar tidak perlu diisi lagi di konversi.']);
            }
            if (isset($conversions[$unitId])) {
                throw ValidationException::withMessages(["units.$i.unit_id" => 'Satuan ini diisi dua kali.']);
            }
            if (($row['factor'] ?? '') === '') {
                throw ValidationException::withMessages(["units.$i.factor" => 'Isi jumlah satuan dasar per 1 satuan ini.']);
            }

            $conversions[$unitId] = [
                'factor' => round((float) $row['factor'], 3),
                'is_sellable' => ! empty($row['is_sellable']),
                'is_purchasable' => ! empty($row['is_purchasable']),
            ];
        }

        $payload = [
            'code' => ($data['code'] ?? '') !== '' ? $data['code'] : null,
            'retail_price' => ($data['retail_price'] ?? '') !== '' ? (int) $data['retail_price'] : null,
            'name' => $data['name'],
            'category' => $data['category'] ?? null,
            'base_unit_id' => $baseUnit,
            'price' => (int) $data['price'],
            'min_stock' => $data['min_stock'] ?? 0,
        ];

        if ($item && $item->stock != 0 && (int) $item->base_unit_id !== $baseUnit) {
            throw ValidationException::withMessages(['base_unit_id' => 'Satuan dasar tidak bisa diubah setelah barang punya stok.']);
        }

        $recipe = [];
        foreach ($request->input('recipe', []) as $i => $row) {
            $ingId = (int) ($row['ingredient_id'] ?? 0);
            $qty = str_replace(',', '.', trim((string) ($row['qty'] ?? '')));
            if ($ingId === 0 && $qty === '') {
                continue;
            }
            if ($ingId === 0) {
                throw ValidationException::withMessages(["recipe.$i.ingredient_id" => 'Pilih bahannya.']);
            }
            if (! is_numeric($qty) || (float) $qty <= 0) {
                throw ValidationException::withMessages(["recipe.$i.qty" => 'Isi takaran bahan dengan angka lebih dari 0.']);
            }
            if ($item && $ingId === $item->id) {
                throw ValidationException::withMessages(["recipe.$i.ingredient_id" => 'Barang tidak bisa jadi bahan untuk dirinya sendiri.']);
            }
            if (isset($recipe[$ingId])) {
                throw ValidationException::withMessages(["recipe.$i.ingredient_id" => 'Bahan ini diisi dua kali.']);
            }
            $ing = Item::with('recipe')->find($ingId);
            if (! $ing || $ing->hasRecipe()) {
                throw ValidationException::withMessages(["recipe.$i.ingredient_id" => 'Bahan harus barang biasa (bukan barang beresep).']);
            }
            $recipe[$ingId] = round((float) $qty, 4);
        }
        if ($recipe !== [] && $item) {
            if (\App\Models\ItemRecipe::where('ingredient_id', $item->id)->exists()) {
                throw ValidationException::withMessages(['recipe' => 'Barang ini dipakai sebagai bahan di resep lain, jadi tidak bisa diberi resep.']);
            }
            if (! $item->hasRecipe() && (float) $item->stock != 0.0) {
                throw ValidationException::withMessages(['recipe' => 'Barang masih punya stok sendiri. Nolkan dulu lewat Penyesuaian stok, baru beri resep.']);
            }
        }

        $payload['_stock'] = ($data['stock'] ?? '') !== '' && $recipe === [] ? round((float) $data['stock'], 3) : null;

        return [$payload, $conversions, $recipe];
    }

    /** Konversi tidak pernah dihapus (dokumen lama masih memakainya); yang dilepas hanya dinonaktifkan. */
    private function syncConversions(Item $item, array $conversions): void
    {
        foreach ($conversions as $unitId => $c) {
            ItemUnit::updateOrCreate(['item_id' => $item->id, 'unit_id' => $unitId], $c);
        }

        ItemUnit::where('item_id', $item->id)
            ->whereNotIn('unit_id', array_keys($conversions) ?: [0])
            ->update(['is_sellable' => false, 'is_purchasable' => false]);
    }
}
