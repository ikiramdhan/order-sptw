<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\MailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/** Pengaturan sistem (SMTP) - hanya Super Admin. */
class SettingController extends Controller
{
    public function edit()
    {
        return view('settings.edit', [
            'locked' => (bool) Setting::get('reset_locked'),
            'smtp' => [
                'host' => Setting::get('smtp_host', 'smtp.hostinger.com'),
                'port' => Setting::get('smtp_port', '465'),
                'encryption' => Setting::get('smtp_encryption', 'ssl'),
                'username' => Setting::get('smtp_username'),
                'from_name' => Setting::get('smtp_from_name', 'Seblak Teh Wanti CK'),
                'has_password' => (bool) Setting::get('smtp_password'),
                'configured' => MailSettings::isConfigured(),
                'last_test' => Setting::get('smtp_last_test_at'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'smtp_host' => ['required', 'string', 'max:150'],
            'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'smtp_encryption' => ['required', Rule::in(['ssl', 'tls', 'none'])],
            'smtp_username' => ['required', 'email', 'max:150'],
            'smtp_password' => ['nullable', 'string', 'max:200'],
            'smtp_from_name' => ['required', 'string', 'max:100'],
        ], [
            'smtp_host.required' => 'Host SMTP wajib diisi.',
            'smtp_port.required' => 'Port wajib diisi.',
            'smtp_username.required' => 'Username (email pengirim) wajib diisi.',
            'smtp_username.email' => 'Username harus berupa alamat email.',
            'smtp_from_name.required' => 'Nama pengirim wajib diisi.',
        ]);

        foreach (['smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_from_name'] as $key) {
            Setting::put($key, (string) $data[$key]);
        }

        // Password hanya diganti bila diisi; disimpan terenkripsi dan tidak pernah ditampilkan lagi.
        if (! empty($data['smtp_password'])) {
            Setting::put('smtp_password', Crypt::encryptString($data['smtp_password']));
        }

        ActivityLog::record('smtp_updated', 'Mengubah pengaturan SMTP');

        return back()->with('status', 'Pengaturan email disimpan. Kirim email tes untuk memastikan berfungsi.');
    }

    public function test(Request $request): RedirectResponse
    {
        if (! MailSettings::apply()) {
            return back()->withErrors(['smtp_test' => 'Simpan pengaturan SMTP terlebih dahulu.']);
        }

        $to = $request->user()->email;

        try {
            Mail::raw(
                "Ini email tes dari aplikasi Central Kitchen Seblak Teh Wanti.\nJika Anda menerima email ini, pengaturan SMTP sudah benar.",
                fn ($message) => $message->to($to)->subject('Email tes Central Kitchen')
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['smtp_test' => 'Gagal mengirim: '.$e->getMessage()]);
        }

        Setting::put('smtp_last_test_at', now()->toDateTimeString());
        ActivityLog::record('smtp_tested', "Email tes dikirim ke {$to}");

        return back()->with('status', "Email tes terkirim ke {$to}.");
    }

    /** Hapus data latihan (transaksi, atau semuanya termasuk master barang/outlet/supplier). */
    public function reset(Request $request): RedirectResponse
    {
        if (Setting::get('reset_locked')) {
            return redirect()->route('settings.edit')->withErrors(['confirm' => 'Reset dikunci karena aplikasi sudah live. Buka kunci dulu bila memang perlu.']);
        }

        $data = $request->validate([
            'scope' => ['required', Rule::in([\App\Services\PracticeReset::TRANSACTIONS, \App\Services\PracticeReset::ALL])],
            'confirm' => ['required', 'in:HAPUS DATA'],
        ], [
            'confirm.in' => 'Ketik persis HAPUS DATA untuk melanjutkan.',
            'confirm.required' => 'Ketik persis HAPUS DATA untuk melanjutkan.',
        ]);

        \App\Services\PracticeReset::run($data['scope']);
        ActivityLog::record('practice_reset', 'Reset data latihan: '.$data['scope']);

        return redirect()->route('settings.edit')->with('status', $data['scope'] === \App\Services\PracticeReset::ALL
            ? 'Semua data latihan dihapus (transaksi, barang, outlet, supplier).'
            : 'Semua transaksi dihapus. Barang, outlet, dan supplier tetap ada; stok kembali 0.');
    }

    /** Kunci / buka kunci fitur reset (aplikasi sudah live). */
    public function lockReset(Request $request): RedirectResponse
    {
        $locked = (bool) Setting::get('reset_locked');
        $phrase = $locked ? 'BUKA KUNCI' : 'SUDAH LIVE';
        $request->validate(['lock_confirm' => ['required', 'in:'.$phrase]], ['lock_confirm.in' => "Ketik persis {$phrase} untuk melanjutkan.", 'lock_confirm.required' => "Ketik persis {$phrase} untuk melanjutkan."]);

        Setting::put('reset_locked', $locked ? null : '1');
        ActivityLog::record($locked ? 'reset_unlocked' : 'reset_locked', $locked ? 'Kunci reset data dibuka' : 'Reset data dikunci (aplikasi live)');

        return redirect()->route('settings.edit')->with('status', $locked ? 'Kunci dibuka. Hati-hati, reset bisa menghapus data.' : 'Reset data dikunci. Data real aman dari penghapusan.');
    }
}
