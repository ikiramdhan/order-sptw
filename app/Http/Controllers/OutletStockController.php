<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Outlet;
use App\Models\StockRequest;
use App\Services\OutletStockService;
use App\Services\SheetReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Stok di outlet: pantauan, penjualan ESB, opname, waste. */
class OutletStockController extends Controller
{
    /** Outlet yang sedang dilihat: outlet sendiri, atau pilihan Admin/Gudang. */
    private function outlet(Request $request): ?Outlet
    {
        $u = $request->user();
        if ($u->role === 'outlet') {
            return Outlet::find($u->outlet_id);
        }
        $id = (int) ($request->input('outlet') ?: $request->query('outlet'));

        return $id ? Outlet::find($id) : Outlet::where('is_active', true)->orderBy('name')->first();
    }

    private function outlets(Request $request)
    {
        return $request->user()->role === 'outlet' ? collect() : Outlet::where('is_active', true)->orderBy('name')->get();
    }

    public function index(Request $request)
    {
        $outlet = $this->outlet($request);
        $q = trim((string) $request->query('q'));

        $rows = $outlet ? DB::table('outlet_stocks as s')->join('items as i', 'i.id', '=', 's.item_id')
            ->join('units as u', 'u.id', '=', 'i.base_unit_id')
            ->where('s.outlet_id', $outlet->id)
            ->when($q !== '', fn ($x) => $x->where('i.name', 'like', "%{$q}%"))
            ->orderBy('i.name')->get(['i.id', 'i.name', 'i.code', 'u.name as unit', 's.qty']) : collect();

        $history = $outlet ? DB::table('outlet_stock_movements as m')->join('items as i', 'i.id', '=', 'm.item_id')
            ->leftJoin('users as us', 'us.id', '=', 'm.user_id')
            ->where('m.outlet_id', $outlet->id)->orderByDesc('m.id')->limit(25)
            ->get(['m.*', 'i.name as item_name', 'us.name as user_name']) : collect();

        $monthStart = now()->startOfMonth()->toDateString();
        $sold = $outlet ? (float) DB::table('outlet_stock_movements')->where('outlet_id', $outlet->id)->where('type', 'terjual')->where('occurred_on', '>=', $monthStart)->sum('qty_base') : 0;
        $wasteVal = $outlet ? (int) round(StockRequest::where('outlet_id', $outlet->id)->where('type', 'waste')->where('status', 'disetujui')->where('decided_at', '>=', $monthStart)
            ->with('item')->get()->sum(fn ($r) => (float) $r->qty_base * (float) $r->item->price)) : 0;
        $pending = StockRequest::where('status', 'menunggu')->when($outlet && $request->user()->role === 'outlet', fn ($x) => $x->where('outlet_id', $outlet->id))->count();

        return view('outletstock.index', [
            'outlet' => $outlet, 'outlets' => $this->outlets($request), 'rows' => $rows, 'history' => $history, 'q' => $q,
            'sold' => abs($sold), 'wasteVal' => $wasteVal, 'pending' => $pending,
        ]);
    }

    // ---- Penjualan ESB ----
    public function salesForm(Request $request)
    {
        return view('outletstock.sales', ['outlet' => $this->outlet($request), 'outlets' => $this->outlets($request)]);
    }

    public function salesTemplate()
    {
        return response("Kode,Jumlah Terjual\nSBK-001,12\nSBK-002,5\n", 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="contoh-penjualan-esb.csv"']);
    }

    public function salesStore(Request $request): RedirectResponse
    {
        $request->validate([
            'outlet' => ['nullable', 'integer'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'file' => ['required', 'file', 'max:5120'],
        ], ['date.required' => 'Isi tanggal penjualan.', 'date.before_or_equal' => 'Tanggal tidak boleh di masa depan.', 'file.required' => 'Pilih file laporan ESB.']);

        $outlet = $this->outlet($request);
        if (! $outlet) {
            return back()->withErrors(['outlet' => 'Pilih outlet.']);
        }

        $file = $request->file('file');
        try {
            $raw = SheetReader::read($file->getRealPath(), $file->getClientOriginalName());
        } catch (\DomainException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        $norm = fn ($v) => preg_replace('/[^a-z0-9]/', '', strtolower((string) $v));
        $hdr = array_map($norm, $raw[0] ?? []);
        $col = function (array $names) use ($hdr) {
            foreach ($hdr as $i => $h) {
                if (in_array($h, $names, true)) {
                    return $i;
                }
            }

            return null;
        };
        $cCode = $col(['kode', 'code', 'sku', 'kodebarang', 'kodemenu']);
        $cName = $col(['namabarang', 'nama', 'barang', 'item', 'menu', 'namamenu', 'namaitem']);
        $cQty = $col(['jumlahterjual', 'jumlah', 'qty', 'terjual', 'quantity', 'qtyterjual']);

        if ($cQty === null || ($cCode === null && $cName === null)) {
            return back()->withErrors(['file' => 'Kolom tidak dikenali. Harus ada "Kode" (atau "Nama Barang") dan "Jumlah Terjual".']);
        }

        $byCode = Item::whereNotNull('code')->get()->keyBy(fn ($i) => $norm($i->code));
        $byName = Item::all()->keyBy(fn ($i) => $norm($i->name));
        $totals = [];
        $skipped = [];

        foreach (array_slice($raw, 1) as $n => $r) {
            $qtyRaw = str_replace(',', '.', trim((string) ($r[$cQty] ?? '')));
            $code = $cCode !== null ? $norm($r[$cCode] ?? '') : '';
            $name = $cName !== null ? $norm($r[$cName] ?? '') : '';
            if ($qtyRaw === '' && $code === '' && $name === '') {
                continue;
            }
            $item = ($code !== '' ? $byCode->get($code) : null) ?? ($name !== '' ? $byName->get($name) : null);
            if (! $item || ! is_numeric($qtyRaw) || (float) $qtyRaw <= 0) {
                $skipped[] = 'Baris '.($n + 2).': '.trim((string) (($cCode !== null ? ($r[$cCode] ?? '') : '') ?: ($r[$cName] ?? '')));
                continue;
            }
            $totals[$item->id] = ['item' => $item, 'qty' => ($totals[$item->id]['qty'] ?? 0) + (float) $qtyRaw];
        }

        if ($totals === []) {
            return back()->withErrors(['file' => 'Tidak ada baris yang cocok dengan barang. Pastikan kolom Kode/Nama sama dengan di daftar barang.'])->with('skipped', $skipped);
        }

        try {
            OutletStockService::applySales($outlet->id, $request->input('date'), array_values($totals), count($skipped), $file->getClientOriginalName(), $request->user(), $request->boolean('replace'));
        } catch (\DomainException $e) {
            return back()->withErrors(['file' => $e->getMessage()])->withInput();
        }

        return redirect()->route('outletstock.index', ['outlet' => $outlet->id])->with('status', count($totals).' barang terjual dicatat untuk '.$outlet->name.'.'.($skipped ? ' '.count($skipped).' baris dilewati (tidak cocok).' : ''))->with('skipped', $skipped);
    }

    // ---- Stok opname & waste outlet ----
    private function itemsInOutlet(Outlet $outlet)
    {
        return DB::table('outlet_stocks as s')->join('items as i', 'i.id', '=', 's.item_id')->join('units as u', 'u.id', '=', 'i.base_unit_id')
            ->where('s.outlet_id', $outlet->id)->orderBy('i.name')->get(['i.id', 'i.name', 'u.name as unit', 's.qty']);
    }

    /** Semua barang aktif beserta stok outlet (0 bila belum pernah ada), untuk opname/stok awal. */
    private function allItemsWithStock(Outlet $outlet)
    {
        return DB::table('items as i')->join('units as u', 'u.id', '=', 'i.base_unit_id')
            ->leftJoin('outlet_stocks as s', fn ($j) => $j->on('s.item_id', '=', 'i.id')->where('s.outlet_id', '=', $outlet->id))
            ->where('i.is_active', true)->orderBy('i.name')->get(['i.id', 'i.name', 'u.name as unit', DB::raw('COALESCE(s.qty, 0) as qty')]);
    }

    public function countForm(Request $request)
    {
        $o = $this->outlet($request);

        return view('outletstock.entry', ['mode' => 'so', 'outlet' => $o, 'outlets' => $this->outlets($request), 'items' => $o ? $this->allItemsWithStock($o) : collect(), 'reasons' => []]);
    }

    public function countStore(Request $request): RedirectResponse
    {
        $request->validate(['qty' => ['nullable', 'array'], 'qty.*' => ['nullable', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:200']], ['qty.*.numeric' => 'Stok fisik harus berupa angka.', 'qty.*.min' => 'Stok fisik tidak boleh negatif.']);
        $o = $this->outlet($request);
        $n = 0;
        DB::transaction(function () use ($request, $o, &$n) {
            foreach ($request->input('qty', []) as $itemId => $v) {
                if ($v === null || $v === '') {
                    continue;
                }
                if (OutletStockService::submitCount($request->user(), $o->id, (int) $itemId, (float) $v, $request->input('notes'), $request->boolean('initial'))) {
                    $n++;
                }
            }
        });
        if ($n > 0 && $request->user()->role === 'outlet') {
            \App\Services\Notifier::roles(['admin_ck'], "SO {$o->name} menunggu persetujuan", "{$n} barang berselisih", '/persetujuan-stok');
        }
        $msg = $n === 0 ? 'Tidak ada selisih, stok tidak berubah.' : ($request->user()->role === 'outlet' ? "{$n} selisih diajukan, menunggu persetujuan Admin CK." : "{$n} barang disesuaikan.");

        return redirect()->route('outletstock.index', ['outlet' => $o->id])->with('status', $msg);
    }

    public function wasteForm(Request $request)
    {
        $o = $this->outlet($request);

        return view('outletstock.entry', ['mode' => 'waste', 'outlet' => $o, 'outlets' => $this->outlets($request), 'items' => $o ? $this->itemsInOutlet($o) : collect(), 'reasons' => StockRequest::REASONS]);
    }

    public function wasteStore(Request $request): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'in:'.implode(',', array_keys(StockRequest::REASONS))], 'qty' => ['nullable', 'array'], 'qty.*' => ['nullable', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:200']], ['reason.required' => 'Pilih alasan waste.', 'qty.*.numeric' => 'Jumlah harus berupa angka.']);
        $o = $this->outlet($request);
        $n = 0;
        try {
            DB::transaction(function () use ($request, $o, &$n) {
                foreach ($request->input('qty', []) as $itemId => $v) {
                    if ($v === null || $v === '' || (float) $v <= 0) {
                        continue;
                    }
                    OutletStockService::submitWaste($request->user(), $o->id, (int) $itemId, (float) $v, $request->input('reason'), $request->input('notes'));
                    $n++;
                }
            });
        } catch (\DomainException $e) {
            return back()->withErrors(['qty' => $e->getMessage()])->withInput();
        }
        if ($n === 0) {
            return back()->withErrors(['qty' => 'Isi jumlah waste minimal satu barang.'])->withInput();
        }

        return redirect()->route('outletstock.index', ['outlet' => $o->id])->with('status', $request->user()->role === 'outlet' ? "{$n} waste diajukan, menunggu persetujuan Admin CK." : "{$n} waste dicatat.");
    }

    // ---- Persetujuan (Admin CK) ----
    public function approvals()
    {
        return view('outletstock.approvals', [
            'pending' => StockRequest::with(['item.baseUnit', 'outlet', 'creator'])->where('status', 'menunggu')->orderBy('id')->get(),
            'recent' => StockRequest::with(['item.baseUnit', 'outlet'])->whereIn('status', ['disetujui', 'ditolak'])->whereNotNull('outlet_id')->latest('id')->limit(15)->get(),
        ]);
    }

    public function approve(Request $request, StockRequest $req): RedirectResponse
    {
        try {
            OutletStockService::approve($req, $request->user());
        } catch (\DomainException $e) {
            return back()->withErrors(['req' => $e->getMessage()]);
        }

        return back()->with('status', 'Disetujui, stok outlet diperbarui.');
    }

    public function reject(Request $request, StockRequest $req): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:200']], ['note.required' => 'Isi alasan penolakan.']);
        try {
            OutletStockService::reject($req, $data['note'], $request->user());
        } catch (\DomainException $e) {
            return back()->withErrors(['req' => $e->getMessage()]);
        }

        return back()->with('status', 'Pengajuan ditolak.');
    }

    // ---- Rekap stok opname: selisih kurang/lebih dalam rupiah ----
    public function recap(Request $request)
    {
        $u = $request->user();
        $outlet = $this->outlet($request);
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('bulan')) ? $request->query('bulan') : now()->format('Y-m');
        $start = \Illuminate\Support\Carbon::parse($month.'-01')->startOfMonth();

        $rows = $outlet ? StockRequest::with('item.baseUnit')->where('type', 'so')->where('status', 'disetujui')->where('outlet_id', $outlet->id)->where(fn ($q) => $q->whereNull('reason')->orWhere('reason', '!=', 'awal'))
            ->whereBetween('decided_at', [$start, $start->copy()->endOfMonth()])->orderByDesc('decided_at')->orderBy('id')->get()
            ->map(function ($r) {
                $diff = round((float) $r->qty_base - (float) $r->qty_system, 3);
                $price = (int) ($r->unit_price ?? round((float) $r->item->price));
                $r->diff = $diff;
                $r->value = (int) round($diff * $price);

                return $r;
            })->filter(fn ($r) => abs($r->diff) >= 0.0005)->values() : collect();

        $short = (int) $rows->where('value', '<', 0)->sum('value');
        $over = (int) $rows->where('value', '>', 0)->sum('value');

        return view('outletstock.recap', [
            'outlet' => $outlet, 'outlets' => $this->outlets($request), 'month' => $month, 'start' => $start,
            'byDay' => $rows->groupBy(fn ($r) => $r->decided_at->toDateString()),
            'short' => abs($short), 'over' => $over, 'net' => abs($short) - $over,
        ]);
    }
}
