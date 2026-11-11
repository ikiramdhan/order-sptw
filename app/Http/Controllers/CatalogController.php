<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Katalog + keranjang + kirim PO (khusus Outlet). */
class CatalogController extends Controller
{
    private const CART = 'cart';

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));

        $items = Item::with(['baseUnit', 'itemUnits'])
            ->where('is_active', true)
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderBy('name')->get();

        return view('catalog.index', ['items' => $items, 'q' => $q, 'cartCount' => count(session(self::CART, []))]);
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'exists:items,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'qty' => ['required', 'numeric', 'gt:0', 'max:100000'],
        ], ['qty.required' => 'Isi jumlah.', 'qty.gt' => 'Jumlah harus lebih dari 0.']);

        $item = Item::with(['baseUnit', 'itemUnits'])->where('is_active', true)->findOrFail($data['item_id']);

        if (! $item->sellableUnits()->contains('unit_id', (int) $data['unit_id'])) {
            return back()->withErrors(['qty' => 'Satuan tidak tersedia untuk barang ini.']);
        }

        $cart = session(self::CART, []);
        $key = $item->id.'-'.$data['unit_id'];
        $cart[$key] = [
            'item_id' => $item->id,
            'unit_id' => (int) $data['unit_id'],
            'qty' => round(($cart[$key]['qty'] ?? 0) + (float) $data['qty'], 3),
        ];
        session([self::CART => $cart]);

        return back()->with('status', "{$item->name} masuk keranjang.");
    }

    public function cart()
    {
        $cart = session(self::CART, []);
        $items = Item::with(['baseUnit', 'itemUnits'])->whereIn('id', collect($cart)->pluck('item_id'))->get()->keyBy('id');

        $lines = collect($cart)->map(function ($c, $key) use ($items) {
            $item = $items->get($c['item_id']);
            if (! $item) {
                return null;
            }

            return $c + [
                'key' => $key, 'item' => $item,
                'unit' => $item->sellableUnits()->firstWhere('unit_id', $c['unit_id']),
                'price' => $item->priceFor($c['unit_id']),
            ];
        })->filter()->values();

        return view('catalog.cart', ['lines' => $lines, 'total' => $lines->sum(fn ($l) => round($l['qty'] * $l['price']))]);
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        $cart = session(self::CART, []);
        $qty = (float) $request->input('qty');

        if (isset($cart[$key])) {
            if ($qty > 0) {
                $cart[$key]['qty'] = round($qty, 3);
            } else {
                unset($cart[$key]);
            }
            session([self::CART => $cart]);
        }

        return back();
    }

    public function remove(string $key): RedirectResponse
    {
        $cart = session(self::CART, []);
        unset($cart[$key]);
        session([self::CART => $cart]);

        return back();
    }

    public function submit(Request $request): RedirectResponse
    {
        $request->validate(['notes' => ['nullable', 'string', 'max:300']]);
        $user = $request->user();

        if (! $user->outlet) {
            return back()->withErrors(['cart' => 'Akun ini belum terhubung ke outlet. Hubungi Super Admin.']);
        }

        try {
            $po = OrderService::createFromCart($user, $user->outlet, array_values(session(self::CART, [])), $request->input('notes'));
        } catch (\DomainException $e) {
            return back()->withErrors(['cart' => $e->getMessage()]);
        }

        session()->forget(self::CART);

        return redirect()->route('orders.show', $po)->with('status', "{$po->number} terkirim. Menunggu persetujuan Central Kitchen.");
    }
}
