<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\Outlet;
use App\Models\PurchaseOrder;
use App\Models\ReturnRequest;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnFlowTest extends TestCase
{
    use RefreshDatabase;

    private Item $item;
    private Outlet $outlet;
    private User $admin;
    private User $gudang;
    private User $outletUser;
    private DeliveryNote $note;
    private Invoice $invoice;

    /** 20 PCS stok, outlet pesan 1 Dus (10 PCS @1000) dan menerimanya penuh -> invoice 10.000, stok sisa 10 */
    protected function setUp(): void
    {
        parent::setUp();

        $pcs = Unit::create(['name' => 'PCS']);
        $dus = Unit::create(['name' => 'Dus']);
        $this->item = Item::create(['name' => 'Kerupuk', 'base_unit_id' => $pcs->id, 'price' => 1000, 'stock' => 0, 'min_stock' => 0, 'is_active' => true]);
        ItemUnit::create(['item_id' => $this->item->id, 'unit_id' => $dus->id, 'factor' => 10, 'is_sellable' => true, 'is_purchasable' => true]);
        $this->outlet = Outlet::create(['name' => 'Outlet A', 'payment_term_days' => 7, 'credit_balance' => 0, 'is_active' => true]);

        $this->admin = $this->mk('admin_ck');
        $this->gudang = $this->mk('gudang');
        $this->outletUser = $this->mk('outlet', ['outlet_id' => $this->outlet->id]);

        $supplier = Supplier::create(['name' => 'Pasar', 'is_active' => true]);
        $this->actingAs($this->admin)->post(route('purchases.store'), [
            'supplier_id' => $supplier->id, 'purchased_at' => today()->toDateString(), 'due_at' => today()->addDays(3)->toDateString(), 'receive_now' => 1,
            'lines' => [['item_id' => $this->item->id, 'unit_id' => $dus->id, 'qty' => 2, 'unit_price' => 8000]],
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->outletUser)->post(route('cart.add'), ['item_id' => $this->item->id, 'unit_id' => $dus->id, 'qty' => 1]);
        $this->post(route('cart.submit'))->assertSessionHasNoErrors();
        $po = PurchaseOrder::firstOrFail();

        $this->actingAs($this->gudang)->post(route('orders.ship', $po), ['ship' => [$po->items->first()->id => 1]])->assertSessionHasNoErrors();
        $this->note = DeliveryNote::firstOrFail();
        $this->actingAs($this->outletUser)->post(route('delivery.receive', $this->note), ['received' => [$this->note->items->first()->id => 1]])->assertSessionHasNoErrors();
        $this->invoice = Invoice::firstOrFail();
    }

    private function mk(string $role, array $extra = []): User
    {
        return User::create(array_merge([
            'name' => 'Tes '.$role, 'email' => $role.'@example.com', 'password' => 'Password123',
            'role' => $role, 'is_active' => true, 'must_change_password' => false,
        ], $extra));
    }

    private function requestReturn(float $dusQty, string $reason = 'rusak'): ReturnRequest
    {
        $itemId = $this->item->id;
        $this->actingAs($this->outletUser)->post(route('returns.store'), [
            'delivery_note_id' => $this->note->id,
            'lines' => [$itemId => ['qty' => $dusQty, 'reason' => $reason]],
        ])->assertSessionHasNoErrors();

        return ReturnRequest::latest('id')->firstOrFail();
    }

    public function test_return_credit_note_reduces_open_invoice(): void
    {
        $ret = $this->requestReturn(0.4); // 4 PCS = 4.000
        $this->assertEquals(4000, $ret->totalValue());

        $row = $ret->items->first();
        $this->actingAs($this->admin)->post(route('returns.approve', $ret), ['resolution' => 'potong_tagihan', 'disposition' => [$row->id => 'dibuang']])->assertSessionHasNoErrors();

        $this->assertSame('selesai', $ret->fresh()->status);
        $this->assertEquals(4000, $this->invoice->fresh()->credit_applied);
        $this->assertEquals(6000, $this->invoice->fresh()->remaining());
        $this->assertEquals(10, (float) $this->item->fresh()->stock); // dibuang: stok tidak berubah
        $this->assertDatabaseHas('credit_notes', ['return_id' => $ret->id, 'amount' => 4000, 'applied_to_invoice' => 4000, 'to_balance' => 0]);

        $this->get(route('returns.nota', $ret))->assertOk()->assertSee('NOTA RETUR');
    }

    public function test_return_on_paid_invoice_becomes_credit_balance_and_restocks(): void
    {
        $this->actingAs($this->admin)->post(route('invoices.pay', $this->invoice), ['amount' => 10000, 'paid_at' => today()->toDateString(), 'method' => 'transfer'])->assertSessionHasNoErrors();
        $this->assertSame('lunas', $this->invoice->fresh()->status);

        $ret = $this->requestReturn(0.5); // 5 PCS = 5.000
        $row = $ret->items->first();
        $this->actingAs($this->admin)->post(route('returns.approve', $ret), ['resolution' => 'potong_tagihan', 'disposition' => [$row->id => 'masuk_stok']])->assertSessionHasNoErrors();

        $this->assertEquals(5000, $this->outlet->fresh()->credit_balance);
        $this->assertEquals(15, (float) $this->item->fresh()->stock);
        $this->assertDatabaseHas('credit_notes', ['return_id' => $ret->id, 'applied_to_invoice' => 0, 'to_balance' => 5000]);
    }

    public function test_replacement_goods_flow_has_no_invoice(): void
    {
        $ret = $this->requestReturn(0.2, 'tidak_sesuai'); // 2 PCS
        $row = $ret->items->first();
        $this->actingAs($this->admin)->post(route('returns.approve', $ret), ['resolution' => 'barang_pengganti', 'disposition' => [$row->id => 'masuk_stok']])->assertSessionHasNoErrors();

        $this->assertSame('disetujui', $ret->fresh()->status);
        $this->assertEquals(10, (float) $this->item->fresh()->stock); // +2 masuk, -2 pengganti

        $replacement = DeliveryNote::where('type', 'pengganti_retur')->firstOrFail();
        $this->actingAs($this->outletUser)->post(route('delivery.receive', $replacement), ['received' => [$replacement->items->first()->id => 0.2]])->assertSessionHasNoErrors();

        $this->assertSame('selesai', $ret->fresh()->status);
        $this->assertSame(1, Invoice::count()); // tidak ada invoice baru
    }

    public function test_cannot_return_more_than_received_and_reject_works(): void
    {
        $this->requestReturn(0.6);

        $this->actingAs($this->outletUser)->post(route('returns.store'), [
            'delivery_note_id' => $this->note->id, 'lines' => [$this->item->id => ['qty' => 0.5, 'reason' => 'rusak']],
        ])->assertSessionHasErrors('lines');

        $ret = ReturnRequest::firstOrFail();
        $this->actingAs($this->admin)->post(route('returns.reject', $ret), ['reason' => 'Foto tidak jelas'])->assertSessionHasNoErrors();
        $this->assertSame('ditolak', $ret->fresh()->status);

        // setelah ditolak, jatah retur kembali
        $this->actingAs($this->outletUser)->post(route('returns.store'), [
            'delivery_note_id' => $this->note->id, 'lines' => [$this->item->id => ['qty' => 0.5, 'reason' => 'rusak']],
        ])->assertSessionHasNoErrors();
    }

    public function test_dashboard_notifications_and_access(): void
    {
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->assertSee('Penjualan bulan ini');
        $this->actingAs($this->gudang)->get(route('dashboard'))->assertForbidden();
        $this->actingAs($this->outletUser)->get(route('dashboard'))->assertForbidden();

        $this->actingAs($this->admin)->get(route('notifications.index'))->assertOk();
        $n = AppNotification::where('user_id', $this->admin->id)->firstOrFail();
        $this->assertNull($n->read_at);
        $this->get(route('notifications.open', $n))->assertRedirect();
        $this->assertNotNull($n->fresh()->read_at);

        // notifikasi milik orang lain tidak bisa dibuka
        $other = AppNotification::where('user_id', $this->outletUser->id)->firstOrFail();
        $this->get(route('notifications.open', $other))->assertNotFound();

        foreach (['returns.index'] as $r) {
            $this->actingAs($this->outletUser)->get(route($r))->assertOk();
            $this->actingAs($this->admin)->get(route($r))->assertOk();
        }
        $this->actingAs($this->gudang)->get(route('returns.index'))->assertForbidden();
        $this->actingAs($this->outletUser)->get(route('returns.create', ['surat_jalan' => $this->note->id]))->assertOk();
    }
}
