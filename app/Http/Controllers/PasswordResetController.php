<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\MailSettings;
use App\Services\Sessions;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetController extends Controller
{
    public function showForgot()
    {
        return view('auth.forgot');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
        ]);

        MailSettings::apply();

        try {
            // Hasil sengaja tidak dibedakan agar tidak membocorkan email yang terdaftar.
            Password::sendResetLink(['email' => $request->string('email')->toString(), 'is_active' => true]);
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim email reset password: '.$e->getMessage());
        }

        return back()->with('status', 'Jika email terdaftar, link reset sudah dikirim. Link berlaku 60 menit dan hanya bisa dipakai sekali. Cek folder spam bila belum masuk.')
            ->withInput($request->only('email'));
    }

    public function showReset(Request $request, string $token)
    {
        return view('auth.reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ], [
            'password.confirmed' => 'Konfirmasi password tidak sama.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.letters' => 'Password harus mengandung huruf.',
            'password.numbers' => 'Password harus mengandung angka.',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password, // di-hash otomatis oleh cast
                    'must_change_password' => false,
                    'remember_token' => Str::random(60),
                ])->save();

                Sessions::forgetUser($user->id);
                ActivityLog::record('password_reset', 'Password diganti lewat link email', $user, $user->id);
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Link reset tidak valid atau sudah kedaluwarsa. Minta link baru.']);
        }

        return redirect()->route('login')->with('status', 'Password berhasil diganti. Silakan masuk.');
    }
}
