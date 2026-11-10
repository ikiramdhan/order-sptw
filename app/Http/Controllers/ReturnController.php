<?php

namespace App\Http\Controllers;

use App\Models\DeliveryNote;
use App\Models\ReturnRequest;
use App\Services\ReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReturnController extends Controller
{
    private function authorizeReturn(Request $request, ReturnRequest $ret): void
    {
        $user = $request->user();
        if ($user->role === 'outlet' && (int) $ret->outlet_id !== (int) $user->outlet_id) {
            abort(404);
        }
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $filter = $request->query('status', 'semua');

        $returns = ReturnRequest::with(['outlet', 'items'])
            ->when($user->role === 'outlet', fn ($q) => $q->where('outlet_id', $user->outlet_id))
            ->when($filter !== 'semua', fn ($q) => $q->where('status', $filter))
            ->latest('id')->limit(200)->get();

        // Surat jalan yang sudah diterima, untuk memulai retur (khusus outlet)
        $notes = $user->role === 'outlet'
            ? DeliveryNote::with('invoice')->where('outlet_id', $user->outlet_id)->where('type', 'regular')->where('status', 'diterima')->latest('id')->limit(15)->get()->filter(fn ($n) => $n->invoice)
            : collect();

        return view('returns.index', ['returns' => $returns, 'filter' => $filter, 'notes' => $notes, 'isOutlet' => $user->role === 'outlet']);
    }

    public function create(Request $request)
    {
        $note = DeliveryNote::with(['outlet', 'invoice'])->where('outlet_id', $request->user()->outlet_id)->findOrFail($request->query('surat_jalan'));

        return view('returns.create', ['note' => $note, 'lines' => ReturnService::eligible($note), 'reasons' => ReturnRequest::REASONS]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'delivery_note_id' => ['required', 'integer'],
            'lines' => ['required', 'array'],
            'lines.*.qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.reason' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:300'],
        ], ['lines.*.qty.numeric' => 'Qty retur harus berupa angka.']);

        $note = DeliveryNote::where('outlet_id', $request->user()->outlet_id)->findOrFail($data['delivery_note_id']);

        try {
            $ret = ReturnService::create($request->user(), $note, $data['lines'], $data['notes'] ?? null);
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['lines' => $e->getMessage()]);
        }

        return redirect()->route('returns.show', $ret)->with('status', "Retur {$ret->number} diajukan. Central Kitchen akan memutuskan.");
    }

    public function show(Request $request, ReturnRequest $retur)
    {
        $this->authorizeReturn($request, $retur);
        $retur->load(['outlet', 'items', 'deliveryNote', 'invoice', 'creditNote', 'replacement', 'requester']);

        return view('returns.show', ['ret' => $retur, 'isOutlet' => $request->user()->role === 'outlet']);
    }

    public function approve(Request $request, ReturnRequest $retur): RedirectResponse
    {
        $data = $request->validate([
            'resolution' => ['required', 'in:potong_tagihan,barang_pengganti'],
            'disposition' => ['nullable', 'array'],
            'disposition.*' => ['nullable', 'in:masuk_stok,dibuang'],
        ], ['resolution.required' => 'Pilih penyelesaian retur.']);

        try {
            ReturnService::approve($retur, $data['resolution'], $data['disposition'] ?? [], $request->user());
        } catch (\DomainException $e) {
            return back()->withErrors(['decision' => $e->getMessage()]);
        }

        return back()->with('status', 'Retur disetujui.');
    }

    public function reject(Request $request, ReturnRequest $retur): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']], ['reason.required' => 'Isi alasan penolakan.']);

        try {
            ReturnService::reject($retur, $data['reason'], $request->user());
        } catch (\DomainException $e) {
            return back()->withErrors(['decision' => $e->getMessage()]);
        }

        return back()->with('status', 'Retur ditolak.');
    }

    /** Cetak Nota Retur. */
    public function creditNote(Request $request, ReturnRequest $retur)
    {
        $this->authorizeReturn($request, $retur);
        $retur->load(['outlet', 'items', 'creditNote.outlet', 'invoice']);
        abort_unless($retur->creditNote, 404);

        return view('returns.nota', ['ret' => $retur, 'note' => $retur->creditNote]);
    }
}
