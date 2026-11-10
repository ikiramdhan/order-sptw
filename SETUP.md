# Central Kitchen Seblak Teh Wanti: Tahap 1 (fondasi)

Isi paket ini: struktur database lengkap, login, lupa/ganti password, pengaturan SMTP, kelola pengguna dan outlet, tampilan responsif, dan dasar PWA.
Ini adalah **lapisan kode** yang ditimpa ke project Laravel 11/12 yang baru dibuat (bukan project utuh).

## 1. Pasang di atas project Laravel

1. Buat project: `composer create-project laravel/laravel ck-app` (PHP 8.2+).
2. Salin **semua isi paket ini** ke dalam folder `ck-app`, timpa file yang sama:
   - menimpa: `app/Models/User.php`, `routes/web.php`, `routes/console.php`, `database/seeders/DatabaseSeeder.php`
   - menambah: sisanya (controller, middleware, migrasi, view, `public/css`, `public/js`, `public/icons`, `public/sw.js`, `public/manifest.webmanifest`, `public/offline.html`)
3. Tambahkan isi `ENV-TAMBAHAN.txt` ke file `.env`, lalu isi data database MySQL Hostinger.

## 2. Jalankan

```bash
php artisan key:generate        # lewati bila .env sudah punya APP_KEY
php artisan migrate --seed      # tabel + satuan + 1 akun Super Admin
php artisan test                # opsional: jalankan tes
```

Login pertama: email dan password dari `.env` (`SUPERADMIN_EMAIL`, `SUPERADMIN_PASSWORD`). Sistem langsung meminta password baru.

## 3. Hostinger

- Arahkan subdomain `order.seblaktehwanti.com` ke folder `ck-app/public` (Document Root).
- Aktifkan SSL untuk subdomain (wajib untuk PWA).
- `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://order.seblaktehwanti.com`, `SESSION_DRIVER=database`.
- Cron Job (hPanel > Advanced > Cron Jobs), tiap menit:
  `* * * * * cd /home/USER/ck-app && php artisan schedule:run >> /dev/null 2>&1`
  Ini menjalankan `ck:mark-overdue` setiap hari pukul 00:10 WIB.
- Setelah login: menu **Pengaturan** untuk mengisi SMTP (host `smtp.hostinger.com`, port 465, SSL/TLS), lalu **Kirim email tes**.

## 4. Alur yang sudah jalan

| Fitur | Siapa |
|---|---|
| Login, keluar, ingat saya | semua |
| Lupa password (link email, 60 menit, sekali pakai) | semua |
| Ganti password, wajib ganti saat password sementara | semua |
| Pengguna: tambah, ubah, reset password, nonaktifkan | Super Admin |
| Outlet: tambah, ubah, termin tempo, nonaktifkan | Super Admin |
| Pengaturan SMTP + email tes | Super Admin |
| Log aktivitas (tersimpan di tabel `activity_logs`) | otomatis |

Semua tabel PRD (PO, surat jalan, invoice, pembayaran, retur, pembelian, stok, notifikasi) sudah dibuat lewat migrasi. Fiturnya dibangun di tahap berikutnya.

## 5. Catatan

- Super Admin otomatis lolos semua pembatasan peran (akses penuh).
- Akun yang dinonaktifkan langsung keluar dari semua perangkat.
- Service worker hanya menyajikan halaman "tidak ada koneksi"; tidak ada transaksi offline.
- Email pengirim dipakai dari pengaturan di database. Bila belum diisi, Laravel memakai `MAIL_*` di `.env`.
