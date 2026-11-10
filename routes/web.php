<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemImportController;
use App\Http\Controllers\SupplierImportController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\RekapController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\OutletController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Route;

// ---- Tamu (belum login) ----
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

    Route::get('/lupa-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
    Route::post('/lupa-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:10,1')->name('password.update');
});

// ---- Sudah login ----
Route::middleware(['auth', EnsureActiveUser::class, EnsurePasswordChanged::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', [HomeController::class, 'index'])->name('home');

    // Semua peran: ganti password sendiri
    Route::get('/akun/password', [AccountController::class, 'password'])->name('account.password');
    Route::put('/akun/password', [AccountController::class, 'updatePassword'])->name('account.password.update');

    // ---- Tahap 2 & 3 ----
    $ck = RoleMiddleware::class.':admin_ck';          // Admin CK (+ Super Admin)
    $ckGudang = RoleMiddleware::class.':admin_ck,gudang';
    $outletOnly = RoleMiddleware::class.':outlet';
    $semua = RoleMiddleware::class.':admin_ck,gudang,outlet';

    // Master: barang, satuan, supplier, pembelian (Admin CK)
    Route::middleware($ck)->group(function () {
        Route::get('/satuan', [UnitController::class, 'index'])->name('units.index');
        Route::post('/satuan', [UnitController::class, 'store'])->name('units.store');
        Route::put('/satuan/{unit}', [UnitController::class, 'update'])->name('units.update');

        Route::get('/barang', [ItemController::class, 'index'])->name('items.index');
        Route::get('/barang/impor', [ItemImportController::class, 'form'])->name('items.import');
        Route::get('/barang/impor/contoh', [ItemImportController::class, 'template'])->name('items.import.template');
        Route::post('/barang/impor', [ItemImportController::class, 'preview'])->name('items.import.preview');
        Route::post('/barang/impor/simpan', [ItemImportController::class, 'apply'])->name('items.import.apply');
        Route::get('/barang/baru', [ItemController::class, 'create'])->name('items.create');
        Route::post('/barang', [ItemController::class, 'store'])->name('items.store');
        Route::get('/barang/{item}/ubah', [ItemController::class, 'edit'])->name('items.edit');
        Route::put('/barang/{item}', [ItemController::class, 'update'])->name('items.update');
        Route::post('/barang/{item}/toggle', [ItemController::class, 'toggle'])->name('items.toggle');

        Route::get('/supplier/impor', [SupplierImportController::class, 'form'])->name('suppliers.import');
        Route::post('/supplier/impor', [SupplierImportController::class, 'preview'])->name('suppliers.import.preview');
        Route::post('/supplier/impor/simpan', [SupplierImportController::class, 'apply'])->name('suppliers.import.apply');
        Route::get('/supplier', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::get('/supplier/baru', [SupplierController::class, 'create'])->name('suppliers.create');
        Route::post('/supplier', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::get('/supplier/{supplier}/ubah', [SupplierController::class, 'edit'])->name('suppliers.edit');
        Route::put('/supplier/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::post('/supplier/{supplier}/toggle', [SupplierController::class, 'toggle'])->name('suppliers.toggle');

        Route::get('/pembelian/baru', [PurchaseController::class, 'create'])->name('purchases.create');
        Route::post('/pembelian', [PurchaseController::class, 'store'])->name('purchases.store');
        Route::post('/pembelian/{purchase}/bayar', [PurchaseController::class, 'pay'])->name('purchases.pay');
    });

    // Stok: Admin CK & Gudang. Terima barang supplier: Gudang juga boleh.
    Route::middleware($ckGudang)->group(function () {
        Route::get('/stok', [StockController::class, 'index'])->name('stock.index');
        Route::get('/stok/penyesuaian', [StockController::class, 'adjustForm'])->name('stock.adjust');
        Route::post('/stok/penyesuaian', [StockController::class, 'adjustStore'])->name('stock.adjust.store');
        Route::get('/pembelian', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::get('/pembelian/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
        Route::post('/pembelian/{purchase}/terima', [PurchaseController::class, 'receive'])->name('purchases.receive');

        Route::post('/po/{order}/kirim', [OrderController::class, 'ship'])->name('orders.ship');
        Route::post('/po/{order}/batalkan-backorder', [OrderController::class, 'cancelBackorder'])->name('orders.backorder.cancel');
    });

    // Katalog & keranjang (Outlet)
    Route::middleware($outletOnly)->group(function () {
        Route::get('/katalog', [CatalogController::class, 'index'])->name('catalog.index');
        Route::post('/keranjang', [CatalogController::class, 'add'])->name('cart.add');
        Route::get('/keranjang', [CatalogController::class, 'cart'])->name('cart.show');
        Route::put('/keranjang/{key}', [CatalogController::class, 'update'])->name('cart.update');
        Route::delete('/keranjang/{key}', [CatalogController::class, 'remove'])->name('cart.remove');
        Route::post('/keranjang/kirim', [CatalogController::class, 'submit'])->name('cart.submit');

        Route::post('/surat-jalan/{note}/terima', [DeliveryController::class, 'receive'])->name('delivery.receive');
    });

    // PO, surat jalan, invoice, rekap: semua peran (outlet hanya data miliknya)
    Route::middleware($semua)->group(function () {
        Route::get('/po', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/po/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('/po/{order}/batalkan', [OrderController::class, 'cancel'])->name('orders.cancel');

        Route::get('/surat-jalan', [DeliveryController::class, 'index'])->name('delivery.index');
        Route::get('/surat-jalan/{note}', [DeliveryController::class, 'show'])->name('delivery.show');
        Route::get('/surat-jalan/{note}/cetak', [DeliveryController::class, 'print'])->name('delivery.print');
    });

    Route::middleware(RoleMiddleware::class.':admin_ck,outlet')->group(function () {
        Route::get('/invoice', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoice/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('/invoice/{invoice}/cetak', [InvoiceController::class, 'print'])->name('invoices.print');
        Route::get('/rekap', [RekapController::class, 'index'])->name('rekap.index');
        Route::get('/rekap/cetak', [RekapController::class, 'print'])->name('rekap.print');
    });
    Route::post('/invoice/{invoice}/bayar', [InvoiceController::class, 'pay'])->middleware($ck)->name('invoices.pay');

    // ---- Tahap 4: retur, dashboard, notifikasi ----
    Route::get('/notifikasi', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifikasi/baca-semua', [NotificationController::class, 'readAll'])->name('notifications.read');
    Route::get('/notifikasi/{notification}/buka', [NotificationController::class, 'open'])->name('notifications.open');

    Route::middleware($ck)->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('/retur/{retur}/setujui', [ReturnController::class, 'approve'])->name('returns.approve');
        Route::post('/retur/{retur}/tolak', [ReturnController::class, 'reject'])->name('returns.reject');
    });

    Route::middleware($outletOnly)->group(function () {
        Route::get('/retur/baru', [ReturnController::class, 'create'])->name('returns.create');
        Route::post('/retur', [ReturnController::class, 'store'])->name('returns.store');
    });

    Route::middleware(RoleMiddleware::class.':admin_ck,outlet')->group(function () {
        Route::get('/retur', [ReturnController::class, 'index'])->name('returns.index');
        Route::get('/retur/{retur}', [ReturnController::class, 'show'])->name('returns.show');
        Route::get('/retur/{retur}/nota', [ReturnController::class, 'creditNote'])->name('returns.nota');
    });

    // Super Admin saja: pengguna, outlet, pengaturan email
    Route::middleware(RoleMiddleware::class.':super_admin')->group(function () {
        Route::get('/pengguna', [UserController::class, 'index'])->name('users.index');
        Route::get('/pengguna/baru', [UserController::class, 'create'])->name('users.create');
        Route::post('/pengguna', [UserController::class, 'store'])->name('users.store');
        Route::get('/pengguna/{user}/ubah', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/pengguna/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/pengguna/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset');
        Route::post('/pengguna/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');

        Route::get('/outlet', [OutletController::class, 'index'])->name('outlets.index');
        Route::get('/outlet/baru', [OutletController::class, 'create'])->name('outlets.create');
        Route::post('/outlet', [OutletController::class, 'store'])->name('outlets.store');
        Route::get('/outlet/{outlet}/ubah', [OutletController::class, 'edit'])->name('outlets.edit');
        Route::put('/outlet/{outlet}', [OutletController::class, 'update'])->name('outlets.update');
        Route::post('/outlet/{outlet}/toggle', [OutletController::class, 'toggle'])->name('outlets.toggle');

        Route::get('/pengaturan', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('/pengaturan', [SettingController::class, 'update'])->name('settings.update');
        Route::post('/pengaturan/tes-email', [SettingController::class, 'test'])->name('settings.test');
    });
});
