<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $low = $request->boolean('menipis');

        $items = Item::with(['baseUnit', 'itemUnits', 'recipe'])
            ->where('is_active', true)
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderBy('name')->get();

        $lowCount = $items->filter->isLow()->count();
        if ($low) {
            $items = $items->filter->isLow()->values();
        }

        return view('stock.index', [
            'items' => $items,
            'q' => $q,
            'low' => $low,
            'lowCount' => $lowCount,
            'history' => StockMovement::with(['item.baseUnit', 'user'])->latest('id')->limit(15)->get(),
        ]);
    }

    public function adjustForm()
    {
        return view('stock.adjust', [
            'items' => Item::with('baseUnit')->where('is_active', true)->whereDoesntHave('recipe')->orderBy('name')->get(),
            'reasons' => StockAdjustment::REASONS,
        ]);
    }

    public function adjustStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'in:opname,rusak,hilang,lainnya'],
            'notes' => ['nullable', 'string', 'max:200'],
            'physical' => ['nullable', 'array'],
            'physical.*' => ['nullable', 'numeric', 'min:0'],
        ], [
            'reason.required' => 'Pilih alasan penyesuaian.',
            'physical.*.numeric' => 'Stok fisik harus berupa angka.',
            'physical.*.min' => 'Stok fisik tidak boleh negatif.',
        ]);

        $changed = 0;

        DB::transaction(function () use ($request, $data, &$changed) {
            foreach ($data['physical'] ?? [] as $itemId => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                $item = Item::findOrFail($itemId);
                if (StockService::adjust($item, round((float) $value, 3), $data['reason'], $data['notes'] ?? null, $request->user()->id)) {
                    $changed++;
                }
            }
        });

        return redirect()->route('stock.index')->with('status', $changed > 0 ? "{$changed} barang disesuaikan." : 'Tidak ada selisih, stok tidak berubah.');
    }
}
