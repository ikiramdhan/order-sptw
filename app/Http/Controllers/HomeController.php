<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Item;
use App\Models\Outlet;
use App\Models\PurchaseOrder;
use App\Models\User;

class HomeController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $stats = [];

        if ($user->isSuperAdmin()) {
            $stats['users'] = User::where('is_active', true)->count();
            $stats['outlets'] = Outlet::where('is_active', true)->count();
        }

        if ($user->hasRole('admin_ck', 'gudang')) {
            $stats['po_baru'] = PurchaseOrder::where('status', 'diajukan')->count();
            $stats['backorder'] = PurchaseOrder::whereIn('status', ['menunggu_backorder', 'dikirim_sebagian'])->count();
            $stats['stok_menipis'] = Item::where('is_active', true)->where('min_stock', '>', 0)->whereColumn('stock', '<=', 'min_stock')->count();
        }

        if ($user->hasRole('admin_ck')) {
            $open = Invoice::whereIn('status', ['belum_dibayar', 'sebagian', 'jatuh_tempo'])->get();
            $stats['piutang'] = $open->sum(fn ($i) => $i->remaining());
        }

        if ($user->role === 'outlet' && $user->outlet_id) {
            $stats['po_jalan'] = PurchaseOrder::where('outlet_id', $user->outlet_id)->whereIn('status', ['diajukan', 'dikirim_sebagian', 'menunggu_backorder', 'dikirim'])->count();
            $stats['perlu_konfirmasi'] = \App\Models\DeliveryNote::where('outlet_id', $user->outlet_id)->where('status', 'dikirim')->count();
            $stats['tagihan'] = Invoice::where('outlet_id', $user->outlet_id)->whereIn('status', ['belum_dibayar', 'sebagian', 'jatuh_tempo'])->get()->sum(fn ($i) => $i->remaining());
        }

        return view('home', ['user' => $user, 'stats' => $stats]);
    }
}
