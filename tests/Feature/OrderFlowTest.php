<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\Outlet;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    private Item $item;
    private Unit $pcs;
    private Unit $dus;
    private Outlet $outlet;
    private User $admin;
    private User $gudang;
    private User $outletUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pcs = Unit::create(['name' => 'PCS']);
        $this->dus = Unit::create(['name' => 'Dus']);
        $this->item = Item::create(['name' => 'Kerupuk', 'base_unit_id' => $this->pcs->id, 'price' => 1000, 'stock' => 0, 'min_stock' => 5, 'is_active' => true]);
        ItemUnit::create(['item_id' => $this->item->id, 'unit_id' => $this->dus->id, 'factor' => 10, 'is_sellable' => true, 'is_purchasable' => true]);

        $this->outlet = Outlet::create(['name' => 'Outlet A', 'payment_term_days' => 7, 'credit_balance' => 0, 'is_active' => true]);

        $this->admin = $this->user('admin_ck');
        $this->gudang = $this->user('gudang');
        $this->outletUser = $this->user('outlet', ['outlet_id' => $this->outlet->id]);
    }

    private function user(string $role, array $extra = []): User
    {
        return User::create(array_merge([
            'name' => 'Tes '.$role, 'email' => $role.'@example.com', 'password' => 'Password123',
            'role' => $role, 'is_active' => true, 'must_change_password' => false,
        ], $extra));
    }

    private function stockIn(float $dusQty): void
    {
        $supplier = Supplier::create(['name' => 'Pasar', 'is_active' => true]);

        $this->actingAs($this->admin)->post(route('purchases.store'), [
            'supplier_id' => $supplier->id,
            'purchased_at' => today()->toDateString(),
            'due_at' => today()->addDays(3)->toDateString(),
            'receive_now' => 1,
            'lines' => [['item_id' => $this->item->id, 'unit_id' => $this->dus->id, 'qty' => $dusQty, 'unit_price' => 8000]],
        ])->assertSessionHasNoErrors();
    }

    private function orderFromOutlet(float $dusQty): PurchaseOrder
    {
        $this->actingAs($this->outletUser)
            ->post(route('cart.add'), ['item_id' => $this->item->id, 'unit_id' => $this->dus->id, 'qty' => $dusQty])
            ->assertSessionHasNoErrors();

        $this->post(route('cart.submit'))->assertSessionHasNoErrors();

        return PurchaseOrder::firstOrFail();
    }

    public function test_purchase_receive_increases_stock_and_payment_settles(): void
    {
        $this->stockIn(2);

        $this->assertEquals(20, (float) $this->item->fresh()->stock);

        $purchase = \App\Models\SupplierPurchase::firstOrFail();
        $this->assertEquals(16000, $purchase->total);

        $this->actingAs($this->admin)->post(route('purchases.pay', $purchase), ['amount' => 16000, 'paid_at' => today()->toDateString(), 'method' => 'transfer'])
            ->assertSessionHasNoErrors();
        $this->assertSame('lunas', $purchase->fresh()->payment_status);
    }

    public function test_purchases_grouped_by_supplier_for_due_date_range(): void
    {
        $a = Supplier::create(['name' => 'Toko A', 'bank_name' => 'BCA', 'account_name' => 'BUDI', 'account_number' => '123456', 'is_active' => true]);
        $b = Supplier::create(['name' => 'Toko B', 'is_active' => true]);
        $mk = fn ($sup, $due, $price) => $this->actingAs($this->admin)->post(route('purchases.store'), [
            'supplier_id' => $sup->id, 'purchased_at' => today()->toDateString(), 'due_at' => $due,
            'lines' => [['item_id' => $this->item->id, 'unit_id' => $this->dus->id, 'qty' => 1, 'unit_price' => $price]],
        ])->assertSessionHasNoErrors();
        $mk($a, '2026-11-12', 10000);
        $mk($a, '2026-11-12', 5000);
        $mk($b, '2026-11-12', 7000);
        $mk($b, '2026-11-20', 99000);

        $res = $this->get(route('purchases.index', ['dari' => '2026-11-12', 'sampai' => '2026-11-12']))->assertOk();
        $res->assertSeeText('Toko A')->assertSeeText('BCA 123456 a.n. BUDI')->assertSeeText('Rp 15.000')->assertSeeText('Rp 22.000');
        $res->assertDontSeeText('99.000');

        $this->get(route('purchases.index', ['supplier' => $b->id, 'dari' => '2026-11-01', 'sampai' => '2026-11-30']))
            ->assertSeeText('Rp 106.000')->assertSeeText('Total per tanggal jatuh tempo');
    }

    public function test_full_flow_partial_shipment_receipt_invoice_payment_and_rekap(): void
    {
        $this->stockIn(2); // 20 PCS

        $po = $this->orderFromOutlet(5); // 5 Dus = 50 PCS
        $this->assertSame('diajukan', $po->status);

        // admin kirim 2 Dus (sebagian)
        $row = $po->items->first();
        $this->actingAs($this->gudang)->post(route('orders.ship', $po), ['ship' => [$row->id => 2]])->assertSessionHasNoErrors();

        $po->refresh();
        $this->assertSame('dikirim_sebagian', $po->status);
        $this->assertEquals(0, (float) $this->item->fresh()->stock);

        $note = \App\Models\DeliveryNote::firstOrFail();

        $this->actingAs($this->gudang)->get(route('delivery.show', $note))->assertOk()->assertSee($note->number);
        $this->actingAs($this->outletUser)->get(route('delivery.show', $note))->assertOk()->assertSee('Terima');

        // surat jalan tidak memuat harga
        $this->actingAs($this->gudang)->get(route('delivery.print', $note))->assertOk()->assertDontSeeText('Rp');

        // outlet terima 1,8 Dus = 18 PCS
        $dnItem = $note->items->first();
        $this->actingAs($this->outletUser)->post(route('delivery.receive', $note), ['received' => [$dnItem->id => 1.8]])->assertSessionHasNoErrors();

        $this->assertEquals(18, \App\Services\OutletStockService::balance($this->outlet->id, $this->item->id));
        $invoice = Invoice::firstOrFail();
        $this->assertEquals(18000, $invoice->total);
        $this->assertTrue($invoice->due_at->isSameDay(today()->addDays(7)));
        $this->assertSame('belum_dibayar', $invoice->status);

        // daftar invoice dikelompokkan per tanggal terbit
        $this->actingAs($this->admin)->get(route('invoices.index'))->assertOk()->assertSee('dayhead', false)->assertSee('1 invoice')->assertSee($invoice->number);

        // pembayaran bertahap
        $this->actingAs($this->admin)->post(route('invoices.pay', $invoice), ['amount' => 10000, 'paid_at' => today()->toDateString(), 'method' => 'tunai'])->assertSessionHasNoErrors();
        $this->assertSame('sebagian', $invoice->fresh()->status);

        $this->post(route('invoices.pay', $invoice), ['amount' => 8000, 'paid_at' => today()->toDateString(), 'method' => 'transfer'])->assertSessionHasNoErrors();
        $this->assertSame('lunas', $invoice->fresh()->status);

        // tidak boleh bayar lebih dari sisa
        $this->post(route('invoices.pay', $invoice), ['amount' => 1, 'paid_at' => today()->toDateString(), 'method' => 'tunai'])->assertSessionHasErrors('amount');

        // rekap
        $this->get(route('rekap.index', ['outlet' => $this->outlet->id, 'bulan' => now()->format('Y-m')]))->assertOk()->assertSee($invoice->number);
        $this->get(route('rekap.print', ['outlet' => $this->outlet->id]))->assertOk()->assertSee($invoice->number);

        // backorder dibatalkan -> PO selesai
        $this->actingAs($this->gudang)->post(route('orders.backorder.cancel', $po), ['reason' => 'stok habis'])->assertSessionHasNoErrors();
        $this->assertSame('selesai', $po->fresh()->status);
    }

    public function test_cannot_ship_more_than_stock_and_zero_shipment_becomes_backorder(): void
    {
        $this->stockIn(1); // 10 PCS
        $po = $this->orderFromOutlet(3);
        $row = $po->items->first();

        $this->actingAs($this->gudang)->post(route('orders.ship', $po), ['ship' => [$row->id => 3]])->assertSessionHasErrors('ship');
        $this->assertEquals(10, (float) $this->item->fresh()->stock);

        $this->post(route('orders.ship', $po), ['ship' => [$row->id => 0]])->assertSessionHasNoErrors();
        $this->assertSame('menunggu_backorder', $po->fresh()->status);
    }

    public function test_credit_balance_reduces_next_invoice(): void
    {
        $this->outlet->update(['credit_balance' => 5000]);
        $this->stockIn(1);
        $po = $this->orderFromOutlet(1);

        $this->actingAs($this->gudang)->post(route('orders.ship', $po), ['ship' => [$po->items->first()->id => 1]]);
        $note = \App\Models\DeliveryNote::firstOrFail();
        $this->actingAs($this->outletUser)->post(route('delivery.receive', $note), ['received' => [$note->items->first()->id => 1]]);

        $invoice = Invoice::firstOrFail();
        $this->assertEquals(10000, $invoice->total);
        $this->assertEquals(5000, $invoice->credit_applied);
        $this->assertEquals(5000, $invoice->remaining());
        $this->assertEquals(0, $this->outlet->fresh()->credit_balance);
    }

    public function test_stock_adjustment_records_difference(): void
    {
        $this->stockIn(1);

        $this->actingAs($this->gudang)->post(route('stock.adjust.store'), ['reason' => 'opname', 'physical' => [$this->item->id => 7]])->assertSessionHasNoErrors();
        $this->assertEquals(7, (float) $this->item->fresh()->stock);
        $this->assertDatabaseHas('stock_adjustments', ['item_id' => $this->item->id, 'difference' => -3]);
    }

    public function test_item_form_saves_conversion(): void
    {
        $this->actingAs($this->admin)->post(route('items.store'), [
            'name' => 'Makaroni', 'base_unit_id' => $this->pcs->id, 'price' => 500, 'min_stock' => 0,
            'units' => [['unit_id' => $this->dus->id, 'factor' => 24, 'is_sellable' => 1, 'is_purchasable' => 1]],
        ])->assertSessionHasNoErrors();

        $item = Item::where('name', 'Makaroni')->firstOrFail();
        $this->assertEquals(24, (float) $item->factorFor($this->dus->id));
    }

    public function test_role_and_outlet_isolation(): void
    {
        $other = Outlet::create(['name' => 'Outlet B', 'payment_term_days' => 0, 'credit_balance' => 0, 'is_active' => true]);
        $po = PurchaseOrder::create(['number' => 'PO-X', 'outlet_id' => $other->id, 'created_by' => $this->admin->id, 'status' => 'diajukan', 'submitted_at' => now()]);

        $this->actingAs($this->outletUser)->get(route('orders.show', $po))->assertNotFound();
        $this->get(route('items.index'))->assertForbidden();
        $this->get(route('stock.index'))->assertForbidden();

        $this->actingAs($this->gudang)->get(route('invoices.index'))->assertForbidden();
        $this->get(route('items.index'))->assertForbidden();
        $this->get(route('orders.index'))->assertOk();

        $this->actingAs($this->admin)->get(route('items.index'))->assertOk();
        $this->get(route('catalog.index'))->assertForbidden();
    }

    public function test_every_main_page_renders_for_its_role(): void
    {
        $this->stockIn(1);
        $po = $this->orderFromOutlet(1);

        foreach (['home', 'catalog.index', 'cart.show', 'orders.index', 'delivery.index', 'invoices.index', 'rekap.index'] as $r) {
            $this->actingAs($this->outletUser)->get(route($r))->assertOk();
        }
        $this->actingAs($this->outletUser)->get(route('orders.show', $po))->assertOk();

        foreach (['home', 'orders.index', 'delivery.index', 'stock.index', 'stock.adjust', 'purchases.index'] as $r) {
            $this->actingAs($this->gudang)->get(route($r))->assertOk();
        }
        $this->actingAs($this->gudang)->get(route('orders.show', $po))->assertOk();

        foreach (['home', 'units.index', 'items.index', 'items.create', 'suppliers.index', 'suppliers.create', 'purchases.index', 'purchases.create', 'invoices.index', 'rekap.index'] as $r) {
            $this->actingAs($this->admin)->get(route($r))->assertOk();
        }
        $this->actingAs($this->admin)->get(route('items.edit', $this->item))->assertOk();
        $this->get(route('purchases.show', \App\Models\SupplierPurchase::firstOrFail()))->assertOk();
    }
}
