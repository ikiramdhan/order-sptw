<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

/**
 * Menerapkan pengaturan SMTP yang disimpan Super Admin di database
 * ke konfigurasi mail Laravel saat runtime. Dipanggil sebelum mengirim email.
 */
class MailSettings
{
    public const KEYS = ['smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password', 'smtp_from_name'];

    public static function isConfigured(): bool
    {
        return (bool) Setting::get('smtp_host') && (bool) Setting::get('smtp_username');
    }

    public static function password(): ?string
    {
        $raw = Setting::get('smtp_password');

        if (! $raw) {
            return null;
        }

        try {
            return Crypt::decryptString($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return bool true jika SMTP dari database dipakai; false jika memakai .env bawaan */
    public static function apply(): bool
    {
        if (! self::isConfigured()) {
            return false;
        }

        $encryption = Setting::get('smtp_encryption', 'ssl'); // ssl | tls | none
        $username = Setting::get('smtp_username');

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => [
                'transport' => 'smtp',
                'host' => Setting::get('smtp_host'),
                'port' => (int) Setting::get('smtp_port', 465),
                // Laravel 11+: smtps = SSL/TLS implisit (port 465); smtp = STARTTLS bila server mendukung
                'scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
                'username' => $username,
                'password' => self::password(),
                'timeout' => 15,
                'auto_tls' => $encryption !== 'none',
            ],
            'mail.from.address' => $username,
            'mail.from.name' => Setting::get('smtp_from_name', 'Seblak Teh Wanti CK'),
        ]);

        if (method_exists(app('mail.manager'), 'forgetMailers')) {
            app('mail.manager')->forgetMailers();
        }

        return true;
    }
}
