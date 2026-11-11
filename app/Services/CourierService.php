<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\DeliveryNote;
use App\Models\User;

/** Penugasan kurir dan pelacakan lokasi antar (posisi terakhir disimpan di surat jalan). */
class CourierService
{
    public static function assign(DeliveryNote $note, int $courierId, ?User $by = null): void
    {
        $same = (int) $note->courier_id === $courierId;
        $note->courier_id = $courierId;
        if (! $same || ! $note->courier_status) {
            $note->courier_status = 'ditugaskan';
            $note->courier_started_at = null;
            $note->courier_arrived_at = null;
            $note->last_lat = $note->last_lng = $note->last_accuracy = $note->last_loc_at = null;
        }
        $note->save();

        if (! $same) {
            Notifier::user($courierId, "Antaran baru {$note->number}", $note->outlet?->name ?? $note->outlet()->value('name'), '/kurir/'.$note->id);
            if ($by) {
                ActivityLog::record('courier_assigned', "{$note->number}: kurir ditugaskan", $note, $by->id);
            }
        }
    }

    public static function start(DeliveryNote $note): void
    {
        if ($note->courier_status === 'diantar') {
            return;
        }
        $note->update(['courier_status' => 'diantar', 'courier_started_at' => now(), 'courier_arrived_at' => null]);
        $kurir = $note->courier?->name ?? 'Kurir';
        Notifier::outlet($note->outlet_id, "Kurir menuju lokasi: {$note->number}", "{$kurir} sedang mengantar pesanan Anda", '/surat-jalan/'.$note->id);
        Notifier::roles(['admin_ck', 'gudang'], "{$note->number} sedang diantar", "{$kurir} → ".$note->outlet()->value('name'), '/surat-jalan/'.$note->id);
    }

    public static function location(DeliveryNote $note, float $lat, float $lng, ?int $accuracy): void
    {
        $note->update(['last_lat' => $lat, 'last_lng' => $lng, 'last_accuracy' => $accuracy, 'last_loc_at' => now()]);
    }

    public static function arrive(DeliveryNote $note): void
    {
        if ($note->courier_status === 'sampai') {
            return;
        }
        $note->update(['courier_status' => 'sampai', 'courier_arrived_at' => now()]);
        $kurir = $note->courier?->name ?? 'Kurir';
        Notifier::outlet($note->outlet_id, "Kurir sudah sampai: {$note->number}", "{$kurir} tiba. Mohon periksa dan konfirmasi barang.", '/surat-jalan/'.$note->id);
        Notifier::roles(['admin_ck', 'gudang'], "{$note->number} sudah sampai", $note->outlet()->value('name'), '/surat-jalan/'.$note->id);
    }
}
