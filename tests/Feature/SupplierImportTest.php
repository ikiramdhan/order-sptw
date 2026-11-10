<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierImportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'a@example.com', 'password' => 'Password123', 'role' => 'admin_ck', 'is_active' => true, 'must_change_password' => false]);
    }

    private const TSV = "Nama Supplier\tNama Rekening\tNama Bank\tNomor Rekening\n"
        ."3 PUTERI\tUDIN ARDI\tBCA\t5875845184\n"
        ."ES BATU - KUTA BUMI\t\tDANA\t089524101878\n"
        ."LEMINERALE - B\tCIPTA NIAGA SEMESTA\tMandiri\t1760001128006\n"
        ."KURNI KIKIL\t\tDANA\t087881229706\n"
        ."ES BATU - X\tPUJIONO\tBCA\t12A45\n";

    public function test_paste_from_sheets_previews_and_saves(): void
    {
        $admin = $this->admin();

        $res = $this->actingAs($admin)->post(route('suppliers.import.preview'), ['paste' => self::TSV])->assertOk();
        $res->assertViewHas('new', 4)->assertViewHas('errors', 1);

        $this->actingAs($admin)->post(route('suppliers.import.apply'))->assertRedirect(route('suppliers.index'));

        $this->assertSame(4, Supplier::count());
        $es = Supplier::where('name', 'ES BATU - KUTA BUMI')->first();
        $this->assertSame('089524101878', $es->account_number); // nol di depan tetap
        $this->assertSame('DANA', $es->bank_name);
        $this->assertNull($es->account_name);
        $this->assertSame('BCA 5875845184 a.n. UDIN ARDI', Supplier::where('name', '3 PUTERI')->first()->bankLine());
    }

    public function test_existing_supplier_is_updated_not_duplicated_and_blank_keeps_old(): void
    {
        $admin = $this->admin();
        Supplier::create(['name' => '3 puteri', 'phone' => '0812', 'is_active' => true]);

        $this->actingAs($admin)->post(route('suppliers.import.preview'), ['paste' => "Nama Supplier\tNama Bank\tNomor Rekening\n3 PUTERI\tBCA\t5875845184\n"])->assertViewHas('update', 1);
        $this->actingAs($admin)->post(route('suppliers.import.apply'));

        $this->assertSame(1, Supplier::count());
        $s = Supplier::first();
        $this->assertSame('0812', $s->phone);
        $this->assertSame('5875845184', $s->account_number);
    }

    public function test_requires_name_column_and_admin_role(): void
    {
        $this->actingAs($this->admin())->post(route('suppliers.import.preview'), ['paste' => "Foo\tBar\n1\t2\n"])->assertSessionHasErrors('paste');

        $gudang = User::create(['name' => 'G', 'email' => 'g@example.com', 'password' => 'Password123', 'role' => 'gudang', 'is_active' => true, 'must_change_password' => false]);
        $this->actingAs($gudang)->get(route('suppliers.import'))->assertForbidden();
    }

    public function test_supplier_form_saves_bank_fields(): void
    {
        $this->actingAs($this->admin())->post(route('suppliers.store'), ['name' => 'PT ACB', 'bank_name' => 'BCA', 'account_number' => '7120308861', 'account_name' => 'PT ANUGRAH CITRA BOGA'])->assertSessionHasNoErrors();
        $this->assertSame('7120308861', Supplier::where('name', 'PT ACB')->value('account_number'));
    }
}
