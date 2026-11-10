<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class Sessions
{
    /** Keluarkan user dari semua perangkat (hanya jika SESSION_DRIVER=database). */
    public static function forgetUser(int $userId, ?string $exceptSessionId = null): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $query = DB::table(config('session.table', 'sessions'))->where('user_id', $userId);

        if ($exceptSessionId) {
            $query->where('id', '!=', $exceptSessionId);
        }

        $query->delete();
    }
}
