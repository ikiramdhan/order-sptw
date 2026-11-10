<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Item;
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

        $items = Item::with(['baseUnit', 'itemUnits'])
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->when($status === 'aktif', fn ($query) => $query->where('is_active', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->get();

        return view('items.index', ['items' => $items, 'q' => $q, 'status' => $status]);
    }

    public function create()
    {
        return view('items.form', ['item' => new Item(['is_active' => true, 'price' => 0, 'min_stock' => 0]), 'units' => Unit::orderBy('name')->get(), 'rows' => $this->rows(null)]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $conversions] = $this->validated($request);

        $item = DB::transaction(function () use ($data, $conversions) {
            $item = Item::create($data + ['stock' => 0, 'is_active' => true]);
            $this->syncConversions($item, $conversions);

            return $item;
        });

        ActivityLog::record('item_created', $item->name, $item);

        return redirect()->route('items.index')->with('status', "Barang {$item->name} ditambahkan.");
    }

    public function edit(Item $item)
    {
        $item->load(['baseUnit', 'itemUnits']);

        return view('items.form', ['item' => $item, 'units' => Unit::orderBy('name')->get(), 'rows' => $this->rows($item)]);
    }

    public function update(Request $request, Item $item): RedirectResponse
    {
        [$data, $conversions] = $this->validated($request, $item);

        DB::transaction(function () use ($item, $data, $conversions) {
            $item->update($data);
            $this->syncConversions($item, $conversions);
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
            'name' => ['required', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:60'],
            'base_unit_id' => ['required', 'exists:units,id'],
            'price' => ['required', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
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
            'name' => $data['name'],
            'category' => $data['category'] ?? null,
            'base_unit_id' => $baseUnit,
            'price' => (int) $data['price'],
            'min_stock' => $data['min_stock'] ?? 0,
        ];

        if ($item && $item->stock != 0 && (int) $item->base_unit_id !== $baseUnit) {
            throw ValidationException::withMessages(['base_unit_id' => 'Satuan dasar tidak bisa diubah setelah barang punya stok.']);
        }

        return [$payload, $conversions];
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
