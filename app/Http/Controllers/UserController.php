<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Outlet;
use App\Models\User;
use App\Services\Sessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Hanya Super Admin (dijaga di routes/web.php). */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $role = $request->query('role');
        $q = trim((string) $request->query('q'));

        $users = User::with('outlet')
            ->when($role && $role !== 'semua', fn ($query) => $query->where('role', $role))
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%");
            }))
            ->orderByRaw("case role when 'super_admin' then 0 when 'admin_ck' then 1 when 'gudang' then 2 else 3 end")
            ->orderBy('name')
            ->get();

        $counts = User::selectRaw('role, count(*) as total')->where('is_active', true)->groupBy('role')->pluck('total', 'role');

        return view('users.index', [
            'users' => $users,
            'counts' => $counts,
            'inactive' => User::where('is_active', false)->count(),
            'role' => $role ?: 'semua',
            'q' => $q,
        ]);
    }

    public function create()
    {
        return view('users.form', ['user' => new User(['role' => User::ROLE_OUTLET]), 'outlets' => $this->outlets()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $temp = $this->tempPassword();

        $user = User::create($data + [
            'password' => $temp,
            'must_change_password' => true,
            'is_active' => true,
        ]);

        ActivityLog::record('user_created', "Membuat akun {$user->email} ({$user->roleLabel()})", $user);

        return redirect()->route('users.index')
            ->with('status', "Pengguna {$user->name} dibuat.")
            ->with('temp_password', ['name' => $user->name, 'email' => $user->email, 'password' => $temp]);
    }

    public function edit(User $user)
    {
        abort_if($user->isSuperAdmin() && $user->id !== auth()->id(), 403);

        return view('users.form', ['user' => $user, 'outlets' => $this->outlets()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isSuperAdmin(), 403, 'Akun Super Admin diubah lewat menu Pengaturan.');

        $user->update($this->validated($request, $user));
        ActivityLog::record('user_updated', "Mengubah akun {$user->email}", $user);

        return redirect()->route('users.index')->with('status', "Data {$user->name} disimpan.");
    }

    public function resetPassword(User $user): RedirectResponse
    {
        abort_if($user->id === auth()->id(), 403, 'Ganti password sendiri lewat menu Akun.');

        $temp = $this->tempPassword();
        $user->forceFill([
            'password' => $temp,
            'must_change_password' => true,
            'remember_token' => Str::random(60),
        ])->save();

        Sessions::forgetUser($user->id);
        ActivityLog::record('password_reset_by_admin', "Reset password {$user->email}", $user);

        return redirect()->route('users.index')
            ->with('status', "Password {$user->name} direset.")
            ->with('temp_password', ['name' => $user->name, 'email' => $user->email, 'password' => $temp]);
    }

    public function toggle(User $user): RedirectResponse
    {
        abort_if($user->id === auth()->id(), 403, 'Akun sendiri tidak bisa dinonaktifkan.');

        $user->update(['is_active' => ! $user->is_active]);

        if (! $user->is_active) {
            Sessions::forgetUser($user->id);
        }

        ActivityLog::record($user->is_active ? 'user_activated' : 'user_deactivated', $user->email, $user);

        return back()->with('status', $user->is_active ? "{$user->name} diaktifkan." : "{$user->name} dinonaktifkan.");
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in(array_keys($user ? User::ASSIGNABLE_ROLES : User::CREATABLE_ROLES))],
            'outlet_id' => ['nullable', 'required_if:role,'.User::ROLE_OUTLET, 'exists:outlets,id'],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah dipakai akun lain.',
            'role.required' => 'Pilih peran.',
            'outlet_id.required_if' => 'Pilih outlet untuk peran Outlet.',
        ]);

        if ($data['role'] !== User::ROLE_OUTLET) {
            $data['outlet_id'] = null;
        }

        return $data;
    }

    private function outlets()
    {
        return Outlet::where('is_active', true)->orderBy('name')->get();
    }

    private function tempPassword(): string
    {
        return Str::lower(Str::random(6)).'-'.random_int(1000, 9999).Str::upper(Str::random(2));
    }
}
