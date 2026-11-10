<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\SupplierPurchase;
use App\Services\PaymentService;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'semua');
        $date = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
        $dari = $date($request->query('dari'));
        $sampai = $date($request->query('sampai'));
        $supplierId = (int) $request->query('supplier') ?: null;
        $filterDue = $dari || $sampai;
        $tampil = $request->query('tampil', $filterDue ? 'supplier' : 'daftar');
        $tampil = in_array($tampil, ['daftar', 'supplier'], true) ? $tampil : 'daftar';
        $open = ['belum_dibayar', 'sebagian', 'jatuh_tempo'];

        $purchases = SupplierPurchase::with('supplier')
            ->when($status !== 'semua', fn ($q) => $q->where('payment_status', $status))
            ->when($dari, fn ($q) => $q->whereDate('due_at', '>=', $dari))
            ->when($sampai, fn ($q) => $q->whereDate('due_at', '<=', $sampai))
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->orderBy($tampil === 'supplier' ? 'due_at' : 'purchased_at', $tampil === 'supplier' ? 'asc' : 'desc')->orderByDesc('id')
            ->limit(500)->get();

        // status jatuh tempo ditampilkan live walau cron belum jalan
        $purchases->each(function ($p) {
            if (in_array($p->payment_status, ['belum_dibayar', 'sebagian'], true) && $p->due_at->lt(today())) {
                $p->payment_status = 'jatuh_tempo';
            }
        });

        // Ringkasan "yang harus dibayar": hanya yang masih ada sisa
        $payable = $purchases->filter(fn ($p) => in_array($p->payment_status, $open, true) && $p->remaining() > 0)->values();
        $groups = $payable->groupBy('supplier_id')->map(fn ($rows) => [
            'supplier' => $rows->first()->supplier,
            'total' => $rows->sum(fn ($p) => $p->remaining()),
            'rows' => $rows,
        ])->sortBy(fn ($g) => mb_strtolower($g['supplier']->name))->values();
        $byDate = $payable->groupBy(fn ($p) => $p->due_at->toDateString())->map(fn ($rows) => $rows->sum(fn ($p) => $p->remaining()))->sortKeys();

        return view('purchases.index', [
            'purchases' => $purchases,
            'status' => $status,
            'dari' => $dari,
            'sampai' => $sampai,
            'supplierId' => $supplierId,
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'tampil' => $tampil,
            'filterDue' => $filterDue,
            'payable' => $payable,
            'groups' => $groups,
            'byDate' => $byDate,
            'payableTotal' => $payable->sum(fn ($p) => $p->remaining()),
            'unpaid' => SupplierPurchase::whereIn('payment_status', $open)->get()->sum(fn ($p) => $p->remaining()),
            'overdue' => SupplierPurchase::whereIn('payment_status', $open)->whereDate('due_at', '<', today())->get()->sum(fn ($p) => $p->remaining()),
        ]);
    }

    public function create()
    {
        $items = Item::with(['baseUnit', 'itemUnits'])->where('is_active', true)->orderBy('name')->get();

        return view('purchases.form', [
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'items' => $items,
            'itemUnits' => $items->mapWithKeys(fn ($i) => [$i->id => $i->purchasableUnits()->values()]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'purchased_at' => ['required', 'date'],
            'due_at' => ['required', 'date', 'after_or_equal:purchased_at'],
            'notes' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array'],
            'lines.*.item_id' => ['nullable', 'exists:items,id'],
            'lines.*.unit_id' => ['nullable', 'exists:units,id'],
            'lines.*.qty' => ['nullable', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['nullable', 'integer', 'min:0'],
        ], [
            'supplier_id.required' => 'Pilih supplier.',
            'purchased_at.required' => 'Tanggal beli wajib diisi.',
            'due_at.required' => 'Tanggal jatuh tempo wajib diisi.',
            'due_at.after_or_equal' => 'Jatuh tempo tidak boleh sebelum tanggal beli.',
            'lines.*.qty.gt' => 'Qty harus lebih dari 0.',
            'lines.*.unit_price.integer' => 'Harga harus angka bulat (rupiah).',
        ]);

        $lines = collect($data['lines'])->filter(fn ($l) => ! empty($l['item_id']))->values()->all();

        foreach ($lines as $l) {
            if (empty($l['unit_id']) || empty($l['qty']) || ! isset($l['unit_price'])) {
                return back()->withInput()->withErrors(['lines' => 'Lengkapi satuan, qty, dan harga di setiap baris barang.']);
            }
        }

        try {
            $purchase = PurchaseService::create(
                $request->user(), Supplier::findOrFail($data['supplier_id']),
                $data['purchased_at'], $data['due_at'], $lines, $data['notes'] ?? null, $request->boolean('receive_now')
            );
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['lines' => $e->getMessage()]);
        }

        return redirect()->route('purchases.show', $purchase)->with('status', "Pembelian {$purchase->number} dicatat.");
    }

    public function show(SupplierPurchase $purchase)
    {
        $purchase->load(['supplier', 'items', 'payments']);

        return view('purchases.show', ['purchase' => $purchase, 'methods' => Payment::METHODS]);
    }

    public function receive(Request $request, SupplierPurchase $purchase): RedirectResponse
    {
        try {
            PurchaseService::receive($purchase, $request->user());
        } catch (\DomainException $e) {
            return back()->withErrors(['receive' => $e->getMessage()]);
        }

        return back()->with('status', 'Barang diterima, stok bertambah.');
    }

    public function pay(Request $request, SupplierPurchase $purchase): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'paid_at' => ['required', 'date'],
            'method' => ['required', 'in:transfer,tunai,lainnya'],
            'notes' => ['nullable', 'string', 'max:200'],
        ], ['amount.required' => 'Jumlah bayar wajib diisi.', 'amount.integer' => 'Jumlah harus angka bulat (rupiah).', 'amount.min' => 'Jumlah bayar harus lebih dari 0.']);

        try {
            PaymentService::recordSupplierPayment($purchase, (int) $data['amount'], $data['paid_at'], $data['method'], $data['notes'] ?? null, $request->user());
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', 'Pembayaran dicatat.');
    }
}
