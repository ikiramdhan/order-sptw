<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SupplierImportService
{
    public const MAX_ROWS = 1000;

    private const ALIASES = [
        'name' => ['namasupplier', 'supplier', 'nama', 'namatoko', 'namavendor', 'vendor'],
        'account_name' => ['namarekening', 'atasnama', 'an', 'pemilikrekening', 'namapemilik'],
        'bank' => ['namabank', 'bank'],
        'account_number' => ['nomorrekening', 'norekening', 'norek', 'nomorrek', 'rekening', 'noakun'],
        'phone' => ['telepon', 'notelepon', 'notelp', 'telp', 'nohp', 'whatsapp', 'nowa', 'hp'],
        'address' => ['alamat'],
    ];

    /** @return array{rows: array, errors: int, new: int, update: int} */
    public static function parse(array $raw): array
    {
        $raw = array_values(array_filter($raw, fn ($r) => count(array_filter($r, fn ($v) => trim((string) $v) !== '')) > 0));

        if (count($raw) < 2) {
            throw new \DomainException('Data kosong atau hanya berisi judul kolom.');
        }
        if (count($raw) - 1 > self::MAX_ROWS) {
            throw new \DomainException('Maksimal '.self::MAX_ROWS.' baris per impor.');
        }

        $map = self::mapHeader($raw[0]);
        if (! isset($map['name'])) {
            throw new \DomainException('Kolom "Nama Supplier" tidak ditemukan. Pastikan baris pertama berisi judul kolom saat menyalin.');
        }

        $get = fn (array $r, string $k) => isset($map[$k]) ? trim(preg_replace('/\s+/', ' ', (string) ($r[$map[$k]] ?? ''))) : '';

        $existing = Supplier::all()->keyBy(fn ($s) => mb_strtolower($s->name));
        $seen = [];
        $out = [];
        $stats = ['errors' => 0, 'new' => 0, 'update' => 0];

        foreach (array_slice($raw, 1) as $n => $r) {
            $number = str_replace([' ', '-', '.'], '', $get($r, 'account_number'));
            $row = [
                'line' => $n + 2,
                'name' => $get($r, 'name'),
                'account_name' => $get($r, 'account_name'),
                'bank' => $get($r, 'bank'),
                'account_number' => $number,
                'phone' => $get($r, 'phone'),
                'address' => $get($r, 'address'),
                'errors' => [],
                'notes' => [],
            ];

            $key = mb_strtolower($row['name']);

            if ($row['name'] === '') {
                $row['errors'][] = 'Nama supplier kosong';
            } elseif (isset($seen[$key])) {
                $row['errors'][] = 'Nama sama dengan baris '.$seen[$key];
            } else {
                $seen[$key] = $row['line'];
            }

            if ($number !== '' && ! ctype_digit($number)) {
                $row['errors'][] = 'Nomor rekening hanya boleh angka';
            }
            if (mb_strlen($row['name']) > 120) {
                $row['errors'][] = 'Nama terlalu panjang';
            }
            if ($number !== '' && $row['bank'] === '') {
                $row['notes'][] = 'Nama bank kosong';
            }
            if ($number === '' && ($row['bank'] !== '' || $row['account_name'] !== '')) {
                $row['notes'][] = 'Nomor rekening kosong';
            }

            $exists = $existing->has($key);
            $row['status'] = $exists ? 'ubah' : 'baru';

            if ($row['errors']) {
                $row['status'] = 'error';
                $stats['errors']++;
            } else {
                $stats[$exists ? 'update' : 'new']++;
            }

            $out[] = $row;
        }

        return ['rows' => $out] + $stats;
    }

    public static function apply(array $rows, User $user): array
    {
        $result = ['new' => 0, 'update' => 0, 'skipped' => 0];

        DB::transaction(function () use ($rows, &$result) {
            foreach ($rows as $row) {
                if ($row['errors'] ?? false) {
                    $result['skipped']++;
                    continue;
                }

                $data = array_filter([
                    'bank_name' => $row['bank'],
                    'account_name' => $row['account_name'],
                    'account_number' => $row['account_number'],
                    'phone' => $row['phone'],
                    'address' => $row['address'],
                ], fn ($v) => $v !== '');

                $supplier = Supplier::whereRaw('lower(name) = ?', [mb_strtolower($row['name'])])->first();

                if ($supplier) {
                    $supplier->update($data + ['is_active' => true]);
                    $result['update']++;
                } else {
                    Supplier::create($data + ['name' => $row['name'], 'is_active' => true]);
                    $result['new']++;
                }
            }
        });

        ActivityLog::record('suppliers_imported', "Impor supplier: {$result['new']} baru, {$result['update']} diperbarui, {$result['skipped']} dilewati", null, $user->id);

        return $result;
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
}
