<?php

namespace App\Http\Controllers;

use App\Models\DeliveryMessage;
use App\Models\DeliveryNote;
use App\Services\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Chat singkat per surat jalan antara kurir, outlet tujuan, dan Admin CK/Gudang. */
class DeliveryChatController extends Controller
{
    private function authorizeNote(Request $request, DeliveryNote $note): void
    {
        $u = $request->user();
        $ok = match ($u->role) {
            'outlet' => (int) $note->outlet_id === (int) $u->outlet_id,
            'kurir' => (int) $note->courier_id === (int) $u->id,
            default => true, // Super Admin, Admin CK, Gudang
        };
        abort_unless($ok, 404);
    }

    public function index(Request $request, DeliveryNote $note): JsonResponse
    {
        $this->authorizeNote($request, $note);
        $after = (int) $request->query('after', 0);

        $rows = DeliveryMessage::with('user:id,name')->where('delivery_note_id', $note->id)
            ->when($after > 0, fn ($q) => $q->where('id', '>', $after))
            ->orderBy('id')->limit(100)->get();

        return response()->json(['messages' => $rows->map(fn ($m) => [
            'id' => $m->id,
            'mine' => (int) $m->user_id === (int) $request->user()->id,
            'name' => $m->user?->name,
            'role' => $m->role,
            'body' => $m->body,
            'time' => $m->created_at->timezone('Asia/Jakarta')->format('H:i'),
        ])->values()]);
    }

    public function store(Request $request, DeliveryNote $note): JsonResponse
    {
        $this->authorizeNote($request, $note);
        abort_unless($note->courier_id && $note->status === 'dikirim', 422, 'Chat hanya aktif selama pengantaran.');
        $d = $request->validate(['body' => ['required', 'string', 'max:300']], ['body.required' => 'Tulis pesan.']);
        $u = $request->user();

        $m = DeliveryMessage::create(['delivery_note_id' => $note->id, 'user_id' => $u->id, 'role' => $u->role, 'body' => trim($d['body'])]);

        $title = "Pesan {$note->number} dari ".$u->name;
        if ($u->role === 'kurir') {
            Notifier::outlet($note->outlet_id, $title, $m->body, '/surat-jalan/'.$note->id);
            Notifier::roles(['admin_ck', 'gudang'], $title, $m->body, '/surat-jalan/'.$note->id);
        } else {
            Notifier::user($note->courier_id, $title, $m->body, '/kurir/'.$note->id);
        }

        return response()->json(['ok' => true, 'id' => $m->id]);
    }
}
