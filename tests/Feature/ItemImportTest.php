<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Unit;
use App\Models\User;
use App\Services\ItemImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ItemImportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'admin_ck'): User
    {
        return User::create(['name' => 'A', 'email' => $role.'@example.com', 'password' => 'Password123', 'role' => $role, 'is_active' => true, 'must_change_password' => false]);
    }

    private function xlsx(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE);
        $strings = [];
        $sheet = '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($rows as $ri => $row) {
            $sheet .= '<row r="'.($ri + 1).'">';
            foreach ($row as $ci => $v) {
                $ref = chr(65 + $ci).($ri + 1);
                if (is_numeric($v)) {
                    $sheet .= "<c r=\"$ref\"><v>$v</v></c>";
                } else {
                    $strings[] = $v;
                    $sheet .= "<c r=\"$ref\" t=\"s\"><v>".(count($strings) - 1).'</v></c>';
                }
            }
            $sheet .= '</row>';
        }
        $sheet .= '</sheetData></worksheet>';
        $ss = '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.implode('', array_map(fn ($s) => '<si><t>'.htmlspecialchars($s).'</t></si>', $strings)).'</sst>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->addFromString('xl/sharedStrings.xml', $ss);
        $zip->close();

        return new UploadedFile($path, 'barang.xlsx', null, null, true);
    }

    public function test_xlsx_import_creates_items_units_conversion_and_opening_stock(): void
    {
        $file = $this->xlsx([
            ['Nama Barang', 'Kategori', 'Satuan Dasar', 'Harga Jual', 'Stok Minimum', 'Stok Awal', 'Satuan Besar', 'Isi per Satuan Besar'],
            ['Kerupuk', 'Bahan', 'PCS', 1000, 50, 200, 'Dus', 40],
            ['Bumbu', 'Bumbu', 'Kg', 45000, 5, 10, '', ''],
            ['', 'x', 'PCS', 100, '', '', '', ''],
        ]);

        $admin = $this->admin();
        $res = $this->actingAs($admin)->post(route('items.import.preview'), ['file' => $file]);
        $res->assertOk()->assertSee('Kerupuk')->assertSee('Nama barang kosong');

        $this->post(route('items.import.apply'))->assertRedirect(route('items.index'));

        $k = Item::where('name', 'Kerupuk')->firstOrFail();
        $this->assertEquals(200, (float) $k->stock);
        $this->assertEquals(1000, $k->price);
        $dus = Unit::where('name', 'Dus')->firstOrFail();
        $this->assertEquals(40, $k->factorFor($dus->id));
        $this->assertDatabaseHas('stock_movements', ['item_id' => $k->id, 'qty_base' => 200]);
        $this->assertSame(2, Item::count()); // baris error dilewati
    }

    public function test_csv_semicolon_rupiah_format_and_update_existing_without_touching_stock(): void
    {
        $admin = $this->admin();
        $csv = "Nama;Satuan;Harga\nKerupuk;PCS;Rp 1.250\n";
        $f = UploadedFile::fake()->createWithContent('a.csv', $csv);
        $this->actingAs($admin)->post(route('items.import.preview'), ['file' => $f])->assertOk();
        $this->post(route('items.import.apply'));
        $this->assertEquals(1250, Item::firstOrFail()->price);

        Item::first()->update(['stock' => 7]);
        $csv2 = "Nama Barang;Satuan Dasar;Harga Jual;Stok Awal\nkerupuk;PCS;2000;999\n";
        $this->post(route('items.import.preview'), ['file' => UploadedFile::fake()->createWithContent('b.csv', $csv2)])->assertOk();
        $this->post(route('items.import.apply'));

        $this->assertSame(1, Item::count());
        $this->assertEquals(2000, Item::first()->price);
        $this->assertEquals(7, (float) Item::first()->stock);
    }

    public function test_missing_required_column_and_access_control(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('items.import.preview'), ['file' => UploadedFile::fake()->createWithContent('c.csv', "Nama;Kategori\nA;B\n")])->assertSessionHasErrors('file');
        $this->get(route('items.import.template'))->assertOk()->assertHeader('content-disposition', 'attachment; filename="contoh-impor-barang.xlsx"');

        // template .xlsx bisa dibaca balik oleh pengimpor, tiap isian di kolomnya sendiri
        $tmp = tempnam(sys_get_temp_dir(), 'tpl');
        file_put_contents($tmp, \App\Services\ItemTemplateXlsx::build());
        $rows = \App\Services\SheetReader::read($tmp, 'contoh.xlsx');
        @unlink($tmp);
        $this->assertSame(ItemImportService::HEADERS, $rows[0]);
        $this->assertSame(['KR01', 'Kerupuk Seblak', 'Bahan Baku', 'PCS', '2000', '1000', '50', '200', 'Dus', '40'], $rows[1]);
        $this->assertSame('Bumbu Seblak', $rows[3][1]);

        $this->actingAs($this->admin('gudang'))->get(route('items.import'))->assertForbidden();
        $this->assertStringContainsString('Nama Barang', ItemImportService::templateCsv());
    }

    public function test_sheet_with_kode_harga_cs_and_harga_ck_columns(): void
    {
        $admin = $this->admin();
        $csv = "KODE;KATEGORI;NAMA BARANG;Satuan;HARGA CS;HARGA CK\nKR01;Kerupuk;CIKUR;PCS;Rp 1,000;Rp 500\nMI8;MIE;INDOMIE AYAM BAWANG;PCS;;Rp 3,000\n";
        $this->actingAs($admin)->post(route('items.import.preview'), ['file' => UploadedFile::fake()->createWithContent('i.csv', $csv)])->assertOk();
        $this->post(route('items.import.apply'))->assertSessionHasNoErrors();

        $cikur = \App\Models\Item::where('name', 'CIKUR')->firstOrFail();
        $this->assertSame('KR01', $cikur->code);
        $this->assertSame(500, $cikur->price);
        $this->assertSame(1000, $cikur->retail_price);
        $mie = \App\Models\Item::where('name', 'INDOMIE AYAM BAWANG')->firstOrFail();
        $this->assertSame(3000, $mie->price);
        $this->assertNull($mie->retail_price);
    }
}
