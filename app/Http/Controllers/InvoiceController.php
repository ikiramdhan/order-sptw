<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Outlet;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    private function authorizeInvoice(Request $request, Invoice $invoice): void
    {
        $user = $request->user();
        if ($user->role === 'outlet' && (int) $invoice->outlet_id !== (int) $user->outlet_id) {
            abort(404);
        }
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status', 'belum');
        $outletId = $user->role === 'outlet' ? $user->outlet_id : $request->query('outlet');

        $invoices = Invoice::with('outlet')
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->when($status === 'belum', fn ($q) => $q->whereIn('status', ['belum_dibayar', 'sebagian', 'jatuh_tempo']))
            ->when($status === 'lunas', fn ($q) => $q->where('status', 'lunas'))
            ->orderBy('due_at')->orderByDesc('id')->limit(300)->get();

        // status jatuh tempo ditampilkan live, walau cron belum jalan
        $invoices->each(function (Invoice $i) {
            if (in_array($i->status, ['belum_dibayar', 'sebagian'], true) && $i->due_at->lt(today())) {
                $i->status = 'jatuh_tempo';
            }
        });

        $open = Invoice::whereIn('status', ['belum_dibayar', 'sebagian', 'jatuh_tempo'])
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))->get();

        return view('invoices.index', [
            'invoices' => $invoices, 'status' => $status,
            'isOutlet' => $user->role === 'outlet',
            'outlets' => $user->role === 'outlet' ? collect() : Outlet::orderBy('name')->get(),
            'outletId' => $outletId,
            'unpaid' => $open->sum(fn ($i) => $i->remaining()),
            'overdue' => $open->filter(fn ($i) => $i->due_at->lt(today()))->sum(fn ($i) => $i->remaining()),
        ]);
    }

    public function show(Request $request, Invoice $invoice)
    {
        $this->authorizeInvoice($request, $invoice);
        $invoice->load(['outlet', 'items.item', 'items.unit', 'payments', 'deliveryNote']);

        return view('invoices.show', ['invoice' => $invoice, 'methods' => Payment::METHODS, 'isOutlet' => $request->user()->role === 'outlet']);
    }

    public function print(Request $request, Invoice $invoice)
    {
        $this->authorizeInvoice($request, $invoice);
        $invoice->load(['outlet', 'items.item', 'items.unit', 'payments', 'deliveryNote']);

        return view('invoices.print', ['invoice' => $invoice]);
    }

    public function pay(Request $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'paid_at' => ['required', 'date'],
            'method' => ['required', 'in:transfer,tunai,lainnya'],
            'notes' => ['nullable', 'string', 'max:200'],
        ], ['amount.required' => 'Jumlah bayar wajib diisi.', 'amount.integer' => 'Jumlah harus angka bulat (rupiah).', 'amount.min' => 'Jumlah bayar harus lebih dari 0.']);

        try {
            PaymentService::recordInvoicePayment($invoice, (int) $data['amount'], $data['paid_at'], $data['method'], $data['notes'] ?? null, $request->user());
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', 'Pembayaran dicatat.');
    }
}
