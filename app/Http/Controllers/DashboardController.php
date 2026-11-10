<?php

namespace App\Http\Controllers;

use App\Models\InvoiceItem;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\ReturnRequest;
use App\Models\SupplierPurchase;

class DashboardController extends Controller
{
    public function index()
    {
        $monthStart = today()->startOfMonth();
        $net = fn ($i) => $i->total - $i->credit_applied;

        $invoices = Invoice::where('status', '!=', 'dibatalkan')->whereDate('issued_at', '>=', today()->subMonths(5)->startOfMonth())->get();
        $thisMonth = $invoices->filter(fn ($i) => $i->issued_at->gte($monthStart));

        // Penjualan 6 bulan terakhir
        $bars = collect(range(5, 0))->map(function ($ago) use ($invoices, $net) {
            $m = today()->subMonths($ago)->startOfMonth();
            $sum = $invoices->filter(fn ($i) => $i->issued_at->format('Y-m') === $m->format('Y-m'))->sum($net);

            return ['label' => $m->locale('id')->translatedFormat('M'), 'value' => (int) $sum];
        });

        $open = Invoice::with('outlet')->whereIn('status', ['belum_dibayar', 'sebagian', 'jatuh_tempo'])->get();
        $overdueInv = $open->filter(fn ($i) => $i->due_at->lt(today()));

        $purchasesOpen = SupplierPurchase::with('supplier')->whereIn('payment_status', ['belum_dibayar', 'sebagian', 'jatuh_tempo'])->orderBy('due_at')->get();

        // Per outlet
        $perOutlet = Outlet::where('is_active', true)->orderBy('name')->get()->map(function ($o) use ($thisMonth, $open, $net) {
            return [
                'outlet' => $o,
                'sales' => (int) $thisMonth->where('outlet_id', $o->id)->sum($net),
                'open' => (int) $open->where('outlet_id', $o->id)->sum(fn ($i) => $i->remaining()),
                'overdue' => (int) $open->where('outlet_id', $o->id)->filter(fn ($i) => $i->due_at->lt(today()))->sum(fn ($i) => $i->remaining()),
            ];
        })->sortByDesc('sales')->values();

        // Barang terlaris bulan ini
        $top = InvoiceItem::with('item.baseUnit')
            ->whereHas('invoice', fn ($q) => $q->where('status', '!=', 'dibatalkan')->whereDate('issued_at', '>=', $monthStart))
            ->get()->groupBy('item_id')
            ->map(fn ($rows) => ['item' => $rows->first()->item, 'qty' => (float) $rows->sum('qty_base'), 'value' => (int) $rows->sum('subtotal')])
            ->sortByDesc('value')->take(5)->values();

        return view('dashboard.index', [
            'bars' => $bars,
            'maxBar' => max(1, $bars->max('value')),
            'sales' => (int) $thisMonth->sum($net),
            'collected' => (int) Payment::whereDate('paid_at', '>=', $monthStart)->sum('amount'),
            'receivable' => (int) $open->sum(fn ($i) => $i->remaining()),
            'receivableOverdue' => (int) $overdueInv->sum(fn ($i) => $i->remaining()),
            'payable' => (int) $purchasesOpen->sum(fn ($p) => $p->remaining()),
            'payableDue' => $purchasesOpen->filter(fn ($p) => $p->due_at->lte(today()->addDays(7)))->values(),
            'poWaiting' => PurchaseOrder::where('status', 'diajukan')->count(),
            'backorder' => PurchaseOrder::whereIn('status', ['menunggu_backorder', 'dikirim_sebagian'])->count(),
            'returnsWaiting' => ReturnRequest::where('status', 'diajukan')->count(),
            'lowStock' => Item::with('baseUnit')->where('is_active', true)->where('min_stock', '>', 0)->whereColumn('stock', '<=', 'min_stock')->orderBy('name')->limit(8)->get(),
            'perOutlet' => $perOutlet,
            'top' => $top,
            'monthLabel' => today()->locale('id')->translatedFormat('F Y'),
        ]);
    }
}
