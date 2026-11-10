<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ItemImportService
{
    public const MAX_ROWS = 1000;

    public const HEADERS = ['Nama Barang', 'Kategori', 'Satuan Dasar', 'Harga Jual', 'Stok Minimum', 'Stok Awal', 'Satuan Besar', 'Isi per Satuan Besar'];

    private const ALIASES = [
        'name' => ['namabarang', 'nama', 'barang', 'item', 'produk', 'namaproduk'],
        'category' => ['kategori', 'jenis', 'kelompok'],
        'base_unit' => ['satuandasar', 'satuan', 'unit', 'sat'],
        'price' => ['hargajual', 'harga', 'hargasatuan', 'hargapcs'],
        'min_stock' => ['stokminimum', 'stokmin', 'minstok', 'minimumstok', 'stokminimal'],
        'stock' => ['stokawal', 'stok', 'stock', 'saldoawal'],
        'big_unit' => ['satuanbesar', 'satuankonversi', 'satuan2', 'satuankedua', 'satuanbox'],
        'factor' => ['isiperbesar', 'isipersatuanbesar', 'isi', 'konversi', 'isisatuanbesar', 'isipersatuan', 'isiperdus', 'isiperbox'],
    ];

    /** Contoh file untuk diunduh (pemisah titik koma supaya langsung rapi di Excel Indonesia). */
    public static function templateCsv(): string
    {
        $rows = [
            self::HEADERS,
            ['Kerupuk Seblak', 'Bahan Baku', 'PCS', '1000', '50', '200', 'Dus', '40'],
            ['Cuanki', 'Bahan Baku', 'PCS', '1500', '20', '0', 'Box', '24'],
            ['Bumbu Seblak', 'Bumbu', 'Kg', '45000', '5', '10', '', ''],
        ];

        return "\xEF\xBB\xBF".implode("\r\n", array_map(fn ($r) => implode(';', $r), $rows))."\r\n";
    }

    /**
     * Ubah baris mentah menjadi baris siap-pratinjau.
     * @return array{rows: array, errors: int, new: int, update: int}
     */
    public static function parse(array $raw): array
    {
        $raw = array_values(array_filter($raw, fn ($r) => count(array_filter($r, fn ($v) => $v !== '')) > 0));

        if (count($raw) < 2) {
            throw new \DomainException('File kosong atau hanya berisi judul kolom.');
        }
        if (count($raw) - 1 > self::MAX_ROWS) {
            throw new \DomainException('Maksimal '.self::MAX_ROWS.' baris per impor.');
        }

        $map = self::mapHeader($raw[0]);
        foreach (['name', 'base_unit', 'price'] as $need) {
            if (! isset($map[$need])) {
                throw new \DomainException('Kolom wajib tidak ditemukan: '.['name' => 'Nama Barang', 'base_unit' => 'Satuan Dasar', 'price' => 'Harga Jual'][$need].'. Gunakan file contoh sebagai acuan.');
            }
        }

        $get = fn (array $r, string $k) => isset($map[$k]) ? trim((string) ($r[$map[$k]] ?? '')) : '';

        $existing = Item::with('baseUnit')->get()->keyBy(fn ($i) => mb_strtolower($i->name));
        $units = Unit::all()->keyBy(fn ($u) => mb_strtolower($u->name));
        $seen = [];
        $out = [];
        $stats = ['errors' => 0, 'new' => 0, 'update' => 0];

        foreach (array_slice($raw, 1) as $n => $r) {
            $row = [
                'line' => $n + 2,
                'name' => $get($r, 'name'),
                'category' => $get($r, 'category'),
                'base_unit' => $get($r, 'base_unit'),
                'price' => self::rupiah($get($r, 'price')),
                'min_stock' => self::number($get($r, 'min_stock')),
                'stock' => self::number($get($r, 'stock')),
                'big_unit' => $get($r, 'big_unit'),
                'factor' => self::number($get($r, 'factor')),
                'errors' => [],
                'notes' => [],
            ];

            $key = mb_strtolower($row['name']);

            if ($row['name'] === '') {
                $row['errors'][] = 'Nama barang kosong';
            } elseif (isset($seen[$key])) {
                $row['errors'][] = 'Nama sama dengan baris '.$seen[$key];
            } else {
                $seen[$key] = $row['line'];
            }

            if ($row['base_unit'] === '') {
                $row['errors'][] = 'Satuan dasar kosong';
            }
            if ($row['price'] === null) {
                $row['errors'][] = 'Harga jual tidak valid';
            }
            if (($get($r, 'stock') !== '' && $row['stock'] === null) || ($get($r, 'min_stock') !== '' && $row['min_stock'] === null)) {
                $row['errors'][] = 'Stok harus berupa angka';
            }
            if ($row['stock'] !== null && $row['stock'] < 0) {
                $row['errors'][] = 'Stok awal tidak boleh negatif';
            }

            if ($row['big_unit'] !== '') {
                if ($row['factor'] === null || $row['factor'] <= 0) {
                    $row['errors'][] = 'Isi per satuan besar harus lebih dari 0';
                }
                if (mb_strtolower($row['big_unit']) === mb_strtolower($row['base_unit'])) {
                    $row['errors'][] = 'Satuan besar sama dengan satuan dasar';
                }
            } elseif ($row['factor'] !== null) {
                $row['errors'][] = 'Isi diisi tetapi satuan besar kosong';
            }

            $item = $existing->get($key);
            $row['status'] = $item ? 'ubah' : 'baru';

            if ($item) {
                if (mb_strtolower($item->baseUnit->name) !== mb_strtolower($row['base_unit']) && (float) $item->stock != 0.0 && $row['base_unit'] !== '') {
                    $row['errors'][] = 'Satuan dasar tidak bisa diubah karena barang sudah punya stok';
                }
                if ($row['stock'] !== null && $row['stock'] > 0) {
                    $row['notes'][] = 'Stok awal diabaikan (barang sudah ada)';
                }
            }

            foreach ([$row['base_unit'], $row['big_unit']] as $u) {
                if ($u !== '' && ! $units->has(mb_strtolower($u))) {
                    $row['notes'][] = "Satuan \"{$u}\" akan dibuat";
                }
            }

            if ($row['errors']) {
                $row['status'] = 'error';
                $stats['errors']++;
            } else {
                $stats[$item ? 'update' : 'new']++;
            }

            $out[] = $row;
        }

        return ['rows' => $out] + $stats;
    }

    /** Simpan baris yang valid. Baris error dilewati. */
    public static function apply(array $rows, User $user): array
    {
        $result = ['new' => 0, 'update' => 0, 'skipped' => 0];

        DB::transaction(function () use ($rows, $user, &$result) {
            foreach ($rows as $row) {
                if ($row['errors'] ?? false) {
                    $result['skipped']++;
                    continue;
                }

                $base = self::unit($row['base_unit']);
                $item = Item::whereRaw('lower(name) = ?', [mb_strtolower($row['name'])])->first();

                if ($item) {
                    $data = ['price' => (int) $row['price'], 'is_active' => true];
                    if ($row['category'] !== '') {
                        $data['category'] = $row['category'];
                    }
                    if ($row['min_stock'] !== null) {
                        $data['min_stock'] = $row['min_stock'];
                    }
                    if ((float) $item->stock == 0.0) {
                        $data['base_unit_id'] = $base->id;
                    }
                    $item->update($data);
                    $result['update']++;
                } else {
                    $item = Item::create([
                        'name' => $row['name'], 'category' => $row['category'] ?: null, 'base_unit_id' => $base->id,
                        'price' => (int) $row['price'], 'stock' => 0, 'min_stock' => $row['min_stock'] ?? 0, 'is_active' => true,
                    ]);

                    if (($row['stock'] ?? 0) > 0) {
                        StockService::move($item, 'penyesuaian', (float) $row['stock'], null, $user->id, 'Stok awal (impor)');
                    }
                    $result['new']++;
                }

                if ($row['big_unit'] !== '') {
                    $big = self::unit($row['big_unit']);
                    ItemUnit::updateOrCreate(
                        ['item_id' => $item->id, 'unit_id' => $big->id],
                        ['factor' => $row['factor'], 'is_sellable' => true, 'is_purchasable' => true]
                    );
                }
            }
        });

        ActivityLog::record('items_imported', "Impor barang: {$result['new']} baru, {$result['update']} diperbarui, {$result['skipped']} dilewati", null, $user->id);

        return $result;
    }

    private static function unit(string $name): Unit
    {
        return Unit::whereRaw('lower(name) = ?', [mb_strtolower($name)])->first() ?? Unit::create(['name' => $name]);
    }

    private static function mapHeader(array $header): array
    {
        $map = [];
        foreach ($header as $i => $h) {
            $norm = preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $h));
            foreach (self::ALIASES as $field => $aliases) {
                if (! isset($map[$field]) && in_array($norm, $aliases, true)) {
                    $map[$field] = $i;
                    break;
                }
            }
        }

        return $map;
    }

    /** "Rp 12.500", "12,500", "12500,00" -> 12500; tidak valid -> null. */
    private static function rupiah(string $v): ?int
    {
        $v = trim(preg_replace('/^rp\.?\s*/i', '', trim($v)));
        if ($v === '') {
            return null;
        }
        $v = preg_replace('/[.,]00$/', '', $v);
        $digits = preg_replace('/[.,\s]/', '', $v);

        return ctype_digit($digits) ? (int) $digits : null;
    }

    /** Angka dengan koma/titik desimal; kosong atau tidak valid -> null. */
    private static function number(string $v): ?float
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        if (str_contains($v, ',') && str_contains($v, '.')) {
            $v = str_replace('.', '', $v);
        }
        $v = str_replace(',', '.', $v);

        return is_numeric($v) ? round((float) $v, 3) : null;
    }
}
