<?php

namespace App\Http\Controllers;

use App\Models\DeliveryNote;
use App\Services\ReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    private function authorizeNote(Request $request, DeliveryNote $note): void
    {
        $user = $request->user();
        if ($user->role === 'outlet' && (int) $note->outlet_id !== (int) $user->outlet_id) {
            abort(404);
        }
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $notes = DeliveryNote::with(['outlet', 'purchaseOrder', 'items'])
            ->when($user->role === 'outlet', fn ($q) => $q->where('outlet_id', $user->outlet_id))
            ->latest('id')->limit(200)->get();

        return view('delivery.index', ['notes' => $notes, 'isOutlet' => $user->role === 'outlet']);
    }

    public function show(Request $request, DeliveryNote $note)
    {
        $this->authorizeNote($request, $note);
        $note->load(['outlet', 'purchaseOrder', 'items.item.baseUnit', 'items.unit', 'receipt', 'invoice']);

        return view('delivery.show', ['note' => $note, 'isOutlet' => $request->user()->role === 'outlet']);
    }

    /** Cetak: tanpa harga. */
    public function print(Request $request, DeliveryNote $note)
    {
        $this->authorizeNote($request, $note);
        $note->load(['outlet', 'purchaseOrder', 'items.item.baseUnit', 'items.unit']);

        return view('delivery.print', ['note' => $note]);
    }

    /** Outlet: konfirmasi qty yang benar-benar diterima -> invoice otomatis. */
    public function receive(Request $request, DeliveryNote $note): RedirectResponse
    {
        $this->authorizeNote($request, $note);

        $data = $request->validate([
            'received' => ['required', 'array'],
            'received.*' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:300'],
        ], ['received.*.numeric' => 'Qty diterima harus berupa angka.', 'received.*.min' => 'Qty diterima tidak boleh negatif.']);

        foreach ($note->items as $row) {
            $v = $data['received'][$row->id] ?? null;
            if ($v !== null && $v !== '' && (float) $v > (float) $row->qty_input + 0.0005) {
                return back()->withInput()->withErrors(['received' => 'Qty diterima tidak boleh lebih dari yang dikirim ('.$row->item->name.').']);
            }
        }

        try {
            $invoice = ReceiptService::confirm($note, $data['received'], $data['notes'] ?? null, $request->user());
        } catch (\DomainException $e) {
            return back()->withErrors(['received' => $e->getMessage()]);
        }

        return redirect()->route('delivery.show', $note)->with('status', $invoice ? "Penerimaan dikonfirmasi. Invoice {$invoice->number} terbit." : 'Penerimaan dikonfirmasi. Tidak ada barang diterima, invoice tidak dibuat.');
    }
}
