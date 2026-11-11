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
        $outlet = Outlet::create($this->withMapsLink($request, $this->validated($request)) + ['is_active' => true]);
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
        $outlet->update($this->withMapsLink($request, $this->validated($request, $outlet)));
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

    /** Link Google Maps yang ditempel mengisi lat/lng otomatis. */
    private function withMapsLink(Request $request, array $data): array
    {
        $link = trim((string) $request->input('maps_link'));
        if ($link !== '') {
            [$lat, $lng] = \App\Services\MapsLink::resolve($link);
            if ($lat === null) {
                throw \Illuminate\Validation\ValidationException::withMessages(['maps_link' => 'Titik tidak terbaca dari link itu. Pastikan link Google Maps outlet (boleh link pendek), atau tempel koordinat seperti -6.2105, 106.6188.']);
            }
            $data['lat'] = $lat;
            $data['lng'] = $lng;
        }

        return $data;
    }

    private function validated(Request $request, ?Outlet $outlet = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('outlets', 'name')->ignore($outlet?->id)],
            'address' => ['nullable', 'string', 'max:255'],
            'pic_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'payment_term_days' => ['required', Rule::in(array_keys(Outlet::TERMS))],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
        ], [
            'lng.required_with' => 'Isi longitude juga.',
            'name.required' => 'Nama outlet wajib diisi.',
            'name.unique' => 'Nama outlet sudah ada.',
            'payment_term_days.required' => 'Pilih termin tempo.',
        ]);
    }

    /** Admin CK / Super Admin: isi titik lokasi outlet dari GPS, link Google Maps, atau koordinat. */
    public function setLocation(Request $request, Outlet $outlet): RedirectResponse
    {
        $u = $request->user();

        $d = $request->validate(['lat' => ['nullable', 'numeric', 'between:-90,90'], 'lng' => ['nullable', 'numeric', 'between:-180,180'], 'link' => ['nullable', 'string', 'max:500']]);
        $lat = $d['lat'] ?? null;
        $lng = $d['lng'] ?? null;
        if (($lat === null || $lng === null) && ! empty($d['link'])) {
            [$lat, $lng] = \App\Services\MapsLink::resolve($d['link']);
        }
        if ($lat === null || $lng === null) {
            return back()->withErrors(['link' => 'Titik tidak terbaca. Tempel link Google Maps outlet (boleh link pendek), atau koordinat seperti -6.2105, 106.6188.']);
        }

        $outlet->update(['lat' => $lat, 'lng' => $lng]);
        ActivityLog::record('outlet_location_set', "Titik lokasi {$outlet->name} diatur", $outlet, $u->id);

        return back()->with('status', 'Titik lokasi outlet tersimpan. Estimasi kedatangan kurir kini aktif.');
    }
}
