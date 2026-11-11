<?php

namespace App\Http\Controllers;

use App\Models\DeliveryNote;
use App\Models\User;
use App\Services\CourierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourierController extends Controller
{
    private function mine(Request $request, DeliveryNote $note): void
    {
        abort_unless((int) $note->courier_id === (int) $request->user()->id, 404);
    }

    public function index(Request $request)
    {
        $uid = $request->user()->id;
        $notes = DeliveryNote::with(['outlet', 'items'])->where('courier_id', $uid)->latest('id')->limit(60)->get();
        $active = $notes->filter(fn ($n) => $n->status === 'dikirim' && $n->courier_status !== 'sampai')->values();
        $done = $notes->reject(fn ($n) => $active->contains('id', $n->id))->take(15)->values();

        return view('courier.index', ['active' => $active, 'done' => $done]);
    }

    public function show(Request $request, DeliveryNote $note)
    {
        $this->mine($request, $note);
        $note->load(['outlet', 'items.item', 'items.unit']);

        return view('courier.show', ['note' => $note]);
    }

    public function start(Request $request, DeliveryNote $note): RedirectResponse
    {
        $this->mine($request, $note);
        abort_unless($note->status === 'dikirim', 422);
        CourierService::start($note);

        return redirect()->route('courier.show', $note)->with('status', 'Antaran dimulai. Biarkan layar tetap menyala.');
    }

    public function location(Request $request, DeliveryNote $note): JsonResponse
    {
        $this->mine($request, $note);
        if ($note->courier_status !== 'diantar') {
            return response()->json(['ok' => false, 'status' => $note->courier_status], 409);
        }
        $d = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'acc' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ]);
        CourierService::location($note, (float) $d['lat'], (float) $d['lng'], isset($d['acc']) ? (int) round($d['acc']) : null);

        return response()->json(['ok' => true]);
    }

    public function arrive(Request $request, DeliveryNote $note): RedirectResponse
    {
        $this->mine($request, $note);
        CourierService::arrive($note);

        return redirect()->route('courier.index')->with('status', "{$note->number}: ditandai sudah sampai. Outlet sudah diberi tahu.");
    }

    /** Admin CK / Gudang: tugaskan atau ganti kurir. */
    public function assign(Request $request, DeliveryNote $note): RedirectResponse
    {
        abort_unless($note->status === 'dikirim', 422);
        $data = $request->validate([
            'courier_id' => ['required', Rule::exists('users', 'id')->where('role', User::ROLE_KURIR)->where('is_active', true)],
        ], ['courier_id.required' => 'Pilih kurir.']);
        CourierService::assign($note, (int) $data['courier_id'], $request->user());

        return back()->with('status', 'Kurir ditugaskan.');
    }

    /** Posisi terakhir kurir (dipolling peta). Outlet hanya untuk suratnya sendiri. */
    public function position(Request $request, DeliveryNote $note): JsonResponse
    {
        $u = $request->user();
        if ($u->role === 'outlet' && (int) $note->outlet_id !== (int) $u->outlet_id) {
            abort(404);
        }

        return response()->json([
            'status' => $note->courier_status,
            'courier' => $note->courier?->name,
            'lat' => $note->last_lat,
            'lng' => $note->last_lng,
            'acc' => $note->last_accuracy,
            'at' => $note->last_loc_at?->toIso8601String(),
            'ago' => $note->last_loc_at ? $note->last_loc_at->diffForHumans() : null,
            'delivered' => $note->status !== 'dikirim',
        ]);
    }
}
