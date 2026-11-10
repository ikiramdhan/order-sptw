<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;

/** Notifikasi dalam aplikasi (lonceng). Gagal kirim tidak boleh menggagalkan proses utama. */
class Notifier
{
    public static function roles(array $roles, string $title, ?string $body = null, ?string $url = null): void
    {
        self::send(User::where('is_active', true)->whereIn('role', $roles)->pluck('id'), $title, $body, $url);
    }

    public static function outlet(int $outletId, string $title, ?string $body = null, ?string $url = null): void
    {
        self::send(User::where('is_active', true)->where('role', 'outlet')->where('outlet_id', $outletId)->pluck('id'), $title, $body, $url);
    }

    private static function send($userIds, string $title, ?string $body, ?string $url): void
    {
        try {
            foreach ($userIds as $id) {
                AppNotification::create(['user_id' => $id, 'title' => $title, 'body' => $body, 'url' => $url]);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
