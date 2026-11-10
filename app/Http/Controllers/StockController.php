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

    public function wasteForm()
    {
        return view('outletstock.entry', [
            'mode' => 'ckwaste', 'outlet' => null, 'outlets' => collect(), 'reasons' => \App\Models\StockRequest::REASONS,
            'items' => Item::with('baseUnit')->where('is_active', true)->whereDoesntHave('recipe')->orderBy('name')->get()->map(fn ($i) => (object) ['id' => $i->id, 'name' => $i->name, 'unit' => $i->baseUnit->name, 'qty' => $i->stock]),
            'history' => \App\Models\StockRequest::with(['item.baseUnit', 'creator'])->where('type', 'waste')->whereNull('outlet_id')->latest('id')->limit(15)->get(),
        ]);
    }

    public function wasteStore(Request $request): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'in:'.implode(',', array_keys(\App\Models\StockRequest::REASONS))], 'qty' => ['nullable', 'array'], 'qty.*' => ['nullable', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:200']], ['reason.required' => 'Pilih alasan waste.']);
        $n = 0;
        try {
            DB::transaction(function () use ($request, &$n) {
                foreach ($request->input('qty', []) as $itemId => $v) {
                    if ($v === null || $v === '' || (float) $v <= 0) {
                        continue;
                    }
                    \App\Services\OutletStockService::submitWaste($request->user(), null, (int) $itemId, (float) $v, $request->input('reason'), $request->input('notes'));
                    $n++;
                }
            });
        } catch (\DomainException $e) {
            return back()->withErrors(['qty' => $e->getMessage()])->withInput();
        }
        if ($n === 0) {
            return back()->withErrors(['qty' => 'Isi jumlah waste minimal satu barang.'])->withInput();
        }

        return redirect()->route('stock.waste')->with('status', "{$n} waste dicatat, stok gudang berkurang.");
    }
}
