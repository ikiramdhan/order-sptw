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
}
