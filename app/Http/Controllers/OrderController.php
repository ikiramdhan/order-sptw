<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * PO untuk semua peran. Outlet hanya melihat PO miliknya sendiri;
 * Admin CK / Gudang / Super Admin melihat semua dan memproses persetujuan + pengiriman.
 */
class OrderController extends Controller
{
    private function scope($query, Request $request)
    {
        $user = $request->user();

        return $user->role === 'outlet' ? $query->where('outlet_id', $user->outlet_id) : $query;
    }

    private function authorizePo(Request $request, PurchaseOrder $po): void
    {
        $user = $request->user();
        if ($user->role === 'outlet' && (int) $po->outlet_id !== (int) $user->outlet_id) {
            abort(404);
        }
    }

    public function index(Request $request)
    {
        $filter = $request->query('status', 'aktif');

        $orders = $this->scope(PurchaseOrder::with(['outlet', 'items']), $request)
            ->when($filter === 'aktif', fn ($q) => $q->whereIn('status', ['diajukan', 'dikirim_sebagian', 'menunggu_backorder', 'dikirim']))
            ->when(! in_array($filter, ['aktif', 'semua'], true), fn ($q) => $q->where('status', $filter))
            ->latest('id')->limit(200)->get();

        return view('orders.index', ['orders' => $orders, 'filter' => $filter, 'isOutlet' => $request->user()->role === 'outlet']);
    }

    public function show(Request $request, PurchaseOrder $order)
    {
        $this->authorizePo($request, $order);
        $order->load(['outlet', 'items', 'deliveryNotes.items', 'creator']);

        return view('orders.show', [
            'po' => $order,
            'isOutlet' => $request->user()->role === 'outlet',
            'canProcess' => $request->user()->hasRole('admin_ck', 'gudang'),
        ]);
    }

    /** Admin/Gudang: setujui dan kirim (penuh atau sebagian). */
    public function ship(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'ship' => ['required', 'array'],
            'ship.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $note = OrderService::approveAndShip($order, $data['ship'], $request->user());
        } catch (\DomainException $e) {
            return back()->withErrors(['ship' => $e->getMessage()]);
        }

        if ($note) {
            return redirect()->route('delivery.show', $note)->with('status', "Surat jalan {$note->number} dibuat. Stok sudah berkurang.");
        }

        return redirect()->route('orders.show', $order)->with('status', 'PO disetujui. Belum ada barang dikirim, semuanya menjadi backorder.');
    }

    public function cancelBackorder(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']], ['reason.required' => 'Isi alasan pembatalan backorder.']);

        try {
            OrderService::cancelBackorder($order, $data['reason'], $request->user());
        } catch (\DomainException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return back()->with('status', 'Sisa backorder dibatalkan.');
    }

    public function cancel(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $this->authorizePo($request, $order);
        $user = $request->user();

        if ($user->role === 'outlet' && $order->status !== 'diajukan') {
            return back()->withErrors(['cancel' => 'PO yang sudah diproses hanya bisa dibatalkan oleh Central Kitchen.']);
        }

        $data = $request->validate(['reason' => ['required', 'string', 'max:200']], ['reason.required' => 'Isi alasan pembatalan.']);

        try {
            OrderService::cancel($order, $data['reason'], $user);
        } catch (\DomainException $e) {
            return back()->withErrors(['cancel' => $e->getMessage()]);
        }

        return redirect()->route('orders.index')->with('status', 'PO dibatalkan.');
    }
}
