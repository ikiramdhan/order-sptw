<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return view('notifications.index', [
            'items' => AppNotification::where('user_id', $request->user()->id)->latest('id')->limit(60)->get(),
        ]);
    }

    public function open(Request $request, AppNotification $notification): RedirectResponse
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 404);
        $notification->update(['read_at' => $notification->read_at ?? now()]);

        $url = (string) $notification->url;

        return redirect($url !== '' && str_starts_with($url, '/') ? $url : route('notifications.index'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        AppNotification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }
}
