<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Outlet;
use App\Models\StockRequest;
use App\Models\Unit;
use App\Models\User;
use App\Services\OutletStockService as S;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class OutletStockTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;
    private Item $item;
    private User $admin;
    private User $ou;

    protected function setUp(): void
    {
        parent::setUp();
        $pcs = Unit::create(['name' => 'PCS']);
        $this->outlet = Outlet::create(['name' => 'Outlet A', 'payment_term_days' => 7, 'credit_balance' => 0, 'is_active' => true]);
        $this->item = Item::create(['code' => 'SBK-1', 'name' => 'Bumbu', 'base_unit_id' => $pcs->id, 'price' => 1000, 'stock' => 20, 'min_stock' => 0, 'is_active' => true]);
        $mk = fn ($role, $x = []) => User::create(array_merge(['name' => $role, 'email' => $role.'@example.com', 'password' => 'Password123', 'role' => $role, 'is_active' => true, 'must_change_password' => false], $x));
        $this->admin = $mk('admin_ck');
        $this->ou = $mk('outlet', ['outlet_id' => $this->outlet->id]);
        S::move($this->outlet->id, $this->item, 'masuk', 50, null, $this->admin->id, 'SJ');
    }

    public function test_sales_upload_reduces_stock_and_blocks_duplicates_unless_replaced(): void
    {
        $csv = fn ($qty) => UploadedFile::fake()->createWithContent('esb.csv', "Kode,Jumlah Terjual\nSBK-1,{$qty}\nXXX,3\n");
        $d = today()->toDateString();

        $this->actingAs($this->ou)->post(route('outletstock.sales.store'), ['date' => $d, 'file' => $csv(10)])->assertSessionHasNoErrors();
        $this->assertEquals(40, S::balance($this->outlet->id, $this->item->id));

        $this->post(route('outletstock.sales.store'), ['date' => $d, 'file' => $csv(4)])->assertSessionHasErrors('file');
        $this->assertEquals(40, S::balance($this->outlet->id, $this->item->id));

        $this->post(route('outletstock.sales.store'), ['date' => $d, 'file' => $csv(4), 'replace' => 1])->assertSessionHasNoErrors();
        $this->assertEquals(46, S::balance($this->outlet->id, $this->item->id));
    }

    public function test_outlet_waste_needs_approval_then_reduces_stock(): void
    {
        $this->actingAs($this->ou)->post(route('outletstock.waste.store'), ['reason' => 'rusak', 'qty' => [$this->item->id => 5]])->assertSessionHasNoErrors();
        $req = StockRequest::firstOrFail();
        $this->assertSame('menunggu', $req->status);
        $this->assertEquals(50, S::balance($this->outlet->id, $this->item->id));

        $this->actingAs($this->ou)->post(route('approvals.approve', $req))->assertForbidden();
        $this->actingAs($this->admin)->post(route('approvals.approve', $req))->assertSessionHasNoErrors();
        $this->assertEquals(45, S::balance($this->outlet->id, $this->item->id));
        $this->assertSame('disetujui', $req->fresh()->status);

        $this->post(route('approvals.approve', $req))->assertSessionHasErrors('req');
    }

    public function test_stock_opname_applies_difference_after_approval_and_reject_changes_nothing(): void
    {
        $this->actingAs($this->ou)->post(route('outletstock.count.store'), ['qty' => [$this->item->id => 42]])->assertSessionHasNoErrors();
        $req = StockRequest::where('type', 'so')->firstOrFail();
        $this->actingAs($this->admin)->post(route('approvals.reject', $req), ['note' => 'hitung ulang']);
        $this->assertEquals(50, S::balance($this->outlet->id, $this->item->id));

        $this->actingAs($this->ou)->post(route('outletstock.count.store'), ['qty' => [$this->item->id => 42]]);
        $req2 = StockRequest::where('type', 'so')->where('status', 'menunggu')->firstOrFail();
        $this->actingAs($this->admin)->post(route('approvals.approve', $req2));
        $this->assertEquals(42, S::balance($this->outlet->id, $this->item->id));
    }

    public function test_ck_waste_reduces_warehouse_stock_and_admin_waste_is_immediate(): void
    {
        $this->actingAs($this->admin)->post(route('stock.waste.store'), ['reason' => 'kadaluarsa', 'qty' => [$this->item->id => 6]])->assertSessionHasNoErrors();
        $this->assertEquals(14, (float) $this->item->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', ['item_id' => $this->item->id, 'type' => 'waste']);

        $this->post(route('stock.waste.store'), ['reason' => 'rusak', 'qty' => [$this->item->id => 99]])->assertSessionHasErrors('qty');
        $this->assertEquals(14, (float) $this->item->fresh()->stock);

        $this->post(route('outletstock.waste.store'), ['outlet' => $this->outlet->id, 'reason' => 'tumpah', 'qty' => [$this->item->id => 2]]);
        $this->assertEquals(48, S::balance($this->outlet->id, $this->item->id));
    }

    public function test_pages_render_for_roles(): void
    {
        $this->actingAs($this->ou)->get(route('outletstock.index'))->assertOk()->assertSee('Bumbu');
        $this->get(route('outletstock.sales'))->assertOk();
        $this->get(route('outletstock.count'))->assertOk();
        $this->get(route('outletstock.waste'))->assertOk();
        $this->actingAs($this->admin)->get(route('outletstock.index', ['outlet' => $this->outlet->id]))->assertOk();
        $this->get(route('approvals.index'))->assertOk();
        $this->get(route('stock.waste'))->assertOk();
        $this->actingAs($this->ou)->get(route('stock.waste'))->assertForbidden();
    }

    public function test_initial_outlet_stock_can_be_entered_via_opname_for_fresh_outlet(): void
    {
        $fresh = Outlet::create(['name' => 'Baru', 'payment_term_days' => 7, 'credit_balance' => 0, 'is_active' => true]);
        $this->actingAs($this->admin)->get(route('outletstock.count', ['outlet' => $fresh->id]))->assertOk()->assertSee('Bumbu');
        $this->post(route('outletstock.count.store'), ['outlet' => $fresh->id, 'qty' => [$this->item->id => 30]])->assertSessionHasNoErrors();
        $this->assertEquals(30, S::balance($fresh->id, $this->item->id));
    }

    public function test_opname_recap_values_shortage_and_ignores_initial_stock(): void
    {
        // stok awal (tidak dihitung)
        $fresh = Outlet::create(['name' => 'Baru', 'payment_term_days' => 7, 'credit_balance' => 0, 'is_active' => true]);
        $this->actingAs($this->admin)->post(route('outletstock.count.store'), ['outlet' => $fresh->id, 'initial' => 1, 'qty' => [$this->item->id => 30]]);
        $this->get(route('outletstock.recap', ['outlet' => $fresh->id]))->assertOk()->assertSee('Belum ada minusan');

        // opname outlet A: sistem 50, fisik 42 -> kurang 8 x Rp1.000
        $this->post(route('outletstock.count.store'), ['outlet' => $this->outlet->id, 'qty' => [$this->item->id => 42]])->assertSessionHasNoErrors();
        $r = StockRequest::where('outlet_id', $this->outlet->id)->where('type', 'so')->firstOrFail();
        $this->assertEquals(1000, (int) $r->unit_price);

        $this->get(route('outletstock.recap', ['outlet' => $this->outlet->id]))->assertOk()->assertSee('Rp 8.000')->assertSee('Bumbu');
        $this->actingAs($this->ou)->get(route('outletstock.recap'))->assertOk()->assertSee('Rp 8.000');
    }

    public function test_charge_covers_opname_shortage_and_tumpah_waste_but_not_rusak_or_kadaluarsa(): void
    {
        $this->actingAs($this->admin);
        // SO kurang 8 (sistem 50 -> fisik 42) => Rp 8.000 ditagih
        $this->post(route('outletstock.count.store'), ['outlet' => $this->outlet->id, 'qty' => [$this->item->id => 42]]);
        // waste tumpah 3 => Rp 3.000 ditagih; rusak 5 & kadaluarsa 2 => tidak ditagih
        foreach ([['tumpah', 3], ['rusak', 5], ['kadaluarsa', 2]] as [$reason, $qty]) {
            $this->post(route('outletstock.waste.store'), ['outlet' => $this->outlet->id, 'reason' => $reason, 'qty' => [$this->item->id => $qty]])->assertSessionHasNoErrors();
        }
        $month = now()->format('Y-m');
        $this->get(route('outletstock.recap', ['outlet' => $this->outlet->id]))->assertOk()->assertSee('Rp 11.000')->assertSee('Rp 7.000');

        $this->post(route('charges.store'), ['outlet' => $this->outlet->id, 'bulan' => $month])->assertSessionHasNoErrors();
        $charge = \App\Models\OutletCharge::firstOrFail();
        $this->assertEquals(11000, (int) $charge->total);
        $this->assertSame(2, $charge->items()->count());
        $this->assertStringStartsWith('TM-', $charge->number);

        // tidak bisa ditagih dua kali
        $this->post(route('charges.store'), ['outlet' => $this->outlet->id, 'bulan' => $month])->assertSessionHasErrors('charge');
        $this->assertSame(1, \App\Models\OutletCharge::count());

        // outlet melihat tagihannya, outlet lain tidak
        $this->actingAs($this->ou)->get(route('charges.show', $charge))->assertOk()->assertSee('Rp 11.000');
        $other = User::create(['name' => 'o2', 'email' => 'o2@example.com', 'password' => 'Password123', 'role' => 'outlet', 'outlet_id' => Outlet::create(['name' => 'B', 'payment_term_days' => 7, 'credit_balance' => 0, 'is_active' => true])->id, 'is_active' => true, 'must_change_password' => false]);
        $this->actingAs($other)->get(route('charges.show', $charge))->assertNotFound();
        $this->actingAs($this->ou)->post(route('charges.paid', $charge))->assertForbidden();

        $this->actingAs($this->admin)->post(route('charges.paid', $charge))->assertSessionHasNoErrors();
        $this->assertSame('lunas', $charge->fresh()->status);
    }

    public function test_admin_can_decline_to_charge_tumpah_waste_on_approval(): void
    {
        $this->actingAs($this->ou)->post(route('outletstock.waste.store'), ['reason' => 'tumpah', 'qty' => [$this->item->id => 4]]);
        $req = StockRequest::firstOrFail();
        $this->actingAs($this->admin)->post(route('approvals.approve', $req), ['charge' => 0])->assertSessionHasNoErrors();
        $this->assertFalse((bool) $req->fresh()->charged);
        $this->post(route('charges.store'), ['outlet' => $this->outlet->id, 'bulan' => now()->format('Y-m')])->assertSessionHasErrors('charge');
    }

    public function test_backfill_marks_old_approved_shortages_as_chargeable(): void
    {
        $mk = fn ($type, $q, $sys, $reason) => StockRequest::create(['type' => $type, 'outlet_id' => $this->outlet->id, 'item_id' => $this->item->id, 'qty_base' => $q, 'qty_system' => $sys, 'reason' => $reason, 'status' => 'disetujui', 'charged' => false, 'created_by' => $this->admin->id, 'decided_at' => now()]);
        $so = $mk('so', 8, 10, null);
        $tumpah = $mk('waste', 2, null, 'tumpah');
        $rusak = $mk('waste', 2, null, 'rusak');
        $awal = $mk('so', 5, 10, 'awal');

        (require base_path('database/migrations/2026_11_12_000003_backfill_charged_stock_requests.php'))->up();

        $this->assertTrue((bool) $so->fresh()->charged);
        $this->assertTrue((bool) $tumpah->fresh()->charged);
        $this->assertFalse((bool) $rusak->fresh()->charged);
        $this->assertFalse((bool) $awal->fresh()->charged);

        $this->actingAs($this->admin)->post(route('charges.store'), ['outlet' => $this->outlet->id, 'bulan' => now()->format('Y-m')])->assertSessionHasNoErrors();
        $this->assertEquals(4000, (int) \App\Models\OutletCharge::firstOrFail()->total);
    }
}
