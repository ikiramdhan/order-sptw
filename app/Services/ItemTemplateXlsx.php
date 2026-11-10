<?php

namespace App\Services;

/**
 * Membuat file contoh impor barang berformat .xlsx asli (tiap isian di kolomnya sendiri),
 * tanpa library tambahan. Lembar 1 = "Barang" (dibaca saat impor), lembar 2 = "Petunjuk".
 */
class ItemTemplateXlsx
{
    private const WIDTHS = [10, 30, 18, 14, 14, 14, 14, 12, 14, 20];

    /** @return string isi file .xlsx (biner) */
    public static function build(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'tpl');
        $zip = new \ZipArchive;
        $zip->open($tmp, \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Barang" sheetId="1" r:id="rId1"/><sheet name="Petunjuk" sheetId="2" r:id="rId2"/></sheets>'
            .'</workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/styles.xml', self::styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::sheetBarang());
        $zip->addFromString('xl/worksheets/sheet2.xml', self::sheetPetunjuk());
        $zip->close();

        $bin = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $bin;
    }

    /** Style: 0 biasa, 1 judul kolom, 2 contoh (abu miring), 3 teks bungkus, 4 tebal */
    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="4">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><i/><sz val="11"/><color rgb="FF6B6963"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFCF3F16"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFF1F0EC"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"><color rgb="FFE4E2DC"/></left><right style="thin"><color rgb="FFE4E2DC"/></right><top style="thin"><color rgb="FFE4E2DC"/></top><bottom style="thin"><color rgb="FFE4E2DC"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="5">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'</cellXfs></styleSheet>';
    }

    private static function sheetBarang(): string
    {
        $cols = '';
        foreach (self::WIDTHS as $i => $w) {
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$w.'" customWidth="1"/>';
        }

        $rows = '<row r="1" ht="32" customHeight="1">';
        foreach (ItemImportService::HEADERS as $i => $h) {
            $rows .= self::str(self::ref($i, 1), $h, 1);
        }
        $rows .= '</row>';

        $examples = [
            ['KR01', 'Kerupuk Seblak', 'Bahan Baku', 'PCS', 2000, 1000, 50, 200, 'Dus', 40],
            ['KR02', 'Cuanki', 'Bahan Baku', 'PCS', 3000, 1500, 20, 0, 'Box', 24],
            ['BB01', 'Bumbu Seblak', 'Bumbu', 'Kg', '', 45000, 5, 10, '', ''],
        ];
        foreach ($examples as $n => $row) {
            $r = $n + 2;
            $rows .= '<row r="'.$r.'">';
            foreach ($row as $i => $v) {
                if ($v === '') {
                    continue;
                }
                $rows .= is_int($v) ? '<c r="'.self::ref($i, $r).'" s="2"><v>'.$v.'</v></c>' : self::str(self::ref($i, $r), $v, 2);
            }
            $rows .= '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0" tabSelected="1"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols>'.$cols.'</cols><sheetData>'.$rows.'</sheetData></worksheet>';
    }

    private static function sheetPetunjuk(): string
    {
        $lines = [
            ['Cara mengisi (isi per kolom di lembar "Barang")', 4],
            ['1. Hapus 3 baris contoh abu-abu, lalu isi barang Anda mulai dari baris 2. Satu barang = satu baris.', 3],
            ['2. Nama Barang, Satuan Dasar, dan Harga CK wajib diisi. Kolom lain boleh kosong.', 3],
            ['3. Satuan Dasar = satuan terkecil untuk stok, mis. PCS atau Kg. Harga CK per satuan dasar, angka saja tanpa titik atau "Rp".', 3],
            ['4. Stok Minimum = batas peringatan stok menipis. Stok Awal = jumlah stok saat ini (hanya untuk barang baru).', 3],
            ['5. Satuan Besar + Isi per Satuan Besar dipakai kalau barang dijual per kemasan. Contoh: Dus dan 40 artinya 1 Dus = 40 PCS.', 3],
            ['6. Nama yang sudah ada di aplikasi akan diperbarui harganya, bukan dobel. Satuan yang belum ada dibuat otomatis.', 3],
            ['7. Harga CS = harga outlet ke konsumen (hanya info, boleh kosong). Harga CK = harga CK ke outlet (yang ditagihkan, wajib). Kode boleh kosong.', 3],
            ['8. Simpan sebagai .xlsx, lalu unggah di menu Barang → Impor Excel. Anda bisa melihat pratinjau sebelum disimpan.', 3],
        ];
        $rows = '';
        foreach ($lines as $n => [$text, $style]) {
            $rows .= '<row r="'.($n + 1).'">'.self::str('A'.($n + 1), $text, $style).'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<cols><col min="1" max="1" width="110" customWidth="1"/></cols><sheetData>'.$rows.'</sheetData></worksheet>';
    }

    private static function ref(int $col, int $row): string
    {
        return chr(65 + $col).$row;
    }

    private static function str(string $ref, string $text, int $style): string
    {
        return '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'
            .htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
    }
}
