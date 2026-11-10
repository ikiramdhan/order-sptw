<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Outlet;
use Illuminate\Http\Request;

/** Rekap tagihan bulanan per outlet. */
class RekapController extends Controller
{
    private function build(Request $request): array
    {
        $user = $request->user();
        $outletId = $user->role === 'outlet' ? $user->outlet_id : (int) $request->query('outlet');
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('bulan')) ? $request->query('bulan') : now()->format('Y-m');
        $filter = $request->query('status', 'semua');
        [$y, $m] = array_map('intval', explode('-', $month));

        $outlet = $outletId ? Outlet::find($outletId) : null;

        $invoices = $outlet
            ? Invoice::with('deliveryNote')->where('outlet_id', $outlet->id)
                ->whereYear('issued_at', $y)->whereMonth('issued_at', $m)
                ->where('status', '!=', 'dibatalkan')->orderBy('issued_at')->orderBy('id')->get()
            : collect();

        $all = $invoices;
        if ($filter === 'lunas') {
            $invoices = $all->where('status', 'lunas')->values();
        } elseif ($filter === 'belum') {
            $invoices = $all->where('status', '!=', 'lunas')->values();
        }

        return [
            'outlet' => $outlet, 'month' => $month, 'filter' => $filter, 'invoices' => $invoices,
            'totalAll' => $all->sum('total') - $all->sum('credit_applied'),
            'paidAll' => $all->sum('paid'),
            'remainingAll' => $all->sum(fn ($i) => $i->remaining()),
            'outlets' => $user->role === 'outlet' ? collect() : Outlet::orderBy('name')->get(),
            'isOutlet' => $user->role === 'outlet',
            'label' => \Carbon\Carbon::create($y, $m, 1)->locale('id')->translatedFormat('F Y'),
        ];
    }

    public function index(Request $request)
    {
        return view('rekap.index', $this->build($request));
    }

    public function print(Request $request)
    {
        return view('rekap.print', $this->build($request));
    }
}
