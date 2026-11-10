<?php

namespace App\Services;

/** Baca file .xlsx (tanpa library tambahan) atau .csv menjadi array baris. */
class SheetReader
{
    public static function read(string $path, string $originalName): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        return match ($ext) {
            'xlsx' => self::xlsx($path),
            'csv', 'txt' => self::csv($path),
            default => throw new \DomainException('Format file harus .xlsx atau .csv.'),
        };
    }

    private static function csv(string $path): array
    {
        $raw = (string) file_get_contents($path);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $first = strtok($raw, "\n") ?: '';
        $delim = substr_count($first, ';') > substr_count($first, ',') ? ';' : (substr_count($first, "\t") > substr_count($first, ',') ? "\t" : ',');

        $rows = [];
        $h = fopen('php://temp', 'r+');
        fwrite($h, $raw);
        rewind($h);
        while (($r = fgetcsv($h, 0, $delim, '"', '')) !== false) {
            $rows[] = array_map(fn ($v) => trim((string) $v), $r);
        }
        fclose($h);

        return $rows;
    }

    private static function xlsx(string $path): array
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new \DomainException('Server belum mendukung .xlsx. Simpan file sebagai .csv lalu unggah lagi.');
        }

        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \DomainException('File .xlsx tidak bisa dibaca.');
        }

        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $sx = @simplexml_load_string($xml);
            foreach ($sx->si ?? [] as $si) {
                $text = '';
                if (isset($si->t)) {
                    $text = (string) $si->t;
                } else {
                    foreach ($si->r ?? [] as $run) {
                        $text .= (string) $run->t;
                    }
                }
                $shared[] = $text;
            }
        }

        // sheet pertama: cari nama berkas worksheet terkecil
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $n = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet(\d+)\.xml$#', $n, $m)) {
                $names[(int) $m[1]] = $n;
            }
        }
        ksort($names);
        $sheetXml = $names ? $zip->getFromName(reset($names)) : false;
        $zip->close();

        if ($sheetXml === false) {
            throw new \DomainException('Tidak ada lembar kerja di file ini.');
        }

        $sx = @simplexml_load_string($sheetXml);
        $rows = [];

        foreach ($sx->sheetData->row ?? [] as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $col = self::colIndex((string) $c['r']);
                $type = (string) $c['t'];

                if ($type === 's') {
                    $val = $shared[(int) $c->v] ?? '';
                } elseif ($type === 'inlineStr') {
                    $val = (string) ($c->is->t ?? '');
                } else {
                    $val = (string) $c->v;
                    if ($val !== '' && is_numeric($val)) {
                        $val = rtrim(rtrim(number_format((float) $val, 6, '.', ''), '0'), '.');
                    }
                }

                $cells[$col] = trim($val);
            }

            if ($cells) {
                $line = [];
                for ($i = 0; $i <= max(array_keys($cells)); $i++) {
                    $line[] = $cells[$i] ?? '';
                }
                $rows[] = $line;
            }
        }

        return $rows;
    }

    private static function colIndex(string $ref): int
    {
        preg_match('/^([A-Z]+)/', $ref, $m);
        $n = 0;
        foreach (str_split($m[1] ?? 'A') as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }
}
