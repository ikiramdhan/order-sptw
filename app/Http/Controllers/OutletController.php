<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Outlet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Hanya Super Admin (dijaga di routes/web.php). */
class OutletController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));

        $outlets = Outlet::withCount(['users as accounts_count' => fn ($query) => $query->where('is_active', true)])
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('outlets.index', [
            'outlets' => $outlets,
            'q' => $q,
            'total' => Outlet::count(),
            'active' => Outlet::where('is_active', true)->count(),
        ]);
    }

    public function create()
    {
        return view('outlets.form', ['outlet' => new Outlet(['payment_term_days' => 7, 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $outlet = Outlet::create($this->validated($request) + ['is_active' => true]);
        ActivityLog::record('outlet_created', "Menambah outlet {$outlet->name}", $outlet);

        return redirect()->route('outlets.index')
            ->with('status', "{$outlet->name} ditambahkan. Buat akun login untuknya di menu Pengguna.");
    }

    public function edit(Outlet $outlet)
    {
        return view('outlets.form', ['outlet' => $outlet]);
    }

    public function update(Request $request, Outlet $outlet): RedirectResponse
    {
        $outlet->update($this->validated($request, $outlet));
        ActivityLog::record('outlet_updated', "Mengubah outlet {$outlet->name}", $outlet);

        return redirect()->route('outlets.index')->with('status', "{$outlet->name} disimpan.");
    }

    /** Outlet nonaktif tidak bisa membuat PO baru; riwayat PO dan invoice tetap tersimpan. */
    public function toggle(Outlet $outlet): RedirectResponse
    {
        $outlet->update(['is_active' => ! $outlet->is_active]);
        ActivityLog::record($outlet->is_active ? 'outlet_activated' : 'outlet_deactivated', $outlet->name, $outlet);

        return back()->with('status', $outlet->is_active ? "{$outlet->name} diaktifkan." : "{$outlet->name} dinonaktifkan.");
    }

    private function validated(Request $request, ?Outlet $outlet = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('outlets', 'name')->ignore($outlet?->id)],
            'address' => ['nullable', 'string', 'max:255'],
            'pic_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'payment_term_days' => ['required', Rule::in(array_keys(Outlet::TERMS))],
        ], [
            'name.required' => 'Nama outlet wajib diisi.',
            'name.unique' => 'Nama outlet sudah ada.',
            'payment_term_days.required' => 'Pilih termin tempo.',
        ]);
    }
}
