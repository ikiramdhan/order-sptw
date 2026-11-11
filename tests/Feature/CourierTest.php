<?php

namespace Tests\Feature;

use App\Models\DeliveryNote;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\Outlet;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourierTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $kurir;
    private User $outletUser;
    private User $otherOutletUser;
    private DeliveryNote $note;

    private function user(string $role, ?int $outletId = null, string $name = null): User
    {
        return User::create(['name' => $name ?? $role, 'email' => $role.rand(1, 99999).'@x.test', 'password' => 'Password123', 'role' => $role, 'outlet_id' => $outletId, 'is_active' => true, 'must_change_password' => false]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $pcs = Unit::create(['name' => 'PCS']);
        $item = Item::create(['name' => 'Kerupuk', 'base_unit_id' => $pcs->id, 'price' => 1000, 'stock' => 100, 'min_stock' => 5, 'is_active' => true]);
        $o = Outlet::create(['name' => 'Cabang A', 'payment_term_days' => 7, 'credit_balance' => 0, 'is_active' => true]);
        $o2 = Outlet::create(['name' => 'Cabang B', 'payment_term_days' => 7, 'credit_balance' => 0, 'is_active' => true]);
        $this->admin = $this->user('admin_ck');
        $this->kurir = $this->user('kurir', null, 'Budi Kurir');
        $this->outletUser = $this->user('outlet', $o->id);
        $this->otherOutletUser = $this->user('outlet', $o2->id);

        $this->actingAs($this->outletUser)->post(route('cart.add'), ['item_id' => $item->id, 'unit_id' => $pcs->id, 'qty' => 5]);
        $this->post(route('cart.submit'))->assertSessionHasNoErrors();
        $po = \App\Models\PurchaseOrder::first();
        $this->actingAs($this->admin)->post(route('orders.ship', $po), ['ship' => [$po->items->first()->id => 5], 'courier_id' => $this->kurir->id])->assertSessionHasNoErrors();
        $this->note = DeliveryNote::first();
    }

    public function test_courier_assigned_on_ship_and_notified(): void
    {
        $this->assertSame($this->kurir->id, $this->note->courier_id);
        $this->assertSame('ditugaskan', $this->note->courier_status);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $this->kurir->id]);
    }

    public function test_full_delivery_flow_with_notifications_and_position(): void
    {
        $this->actingAs($this->kurir)->get(route('courier.index'))->assertOk()->assertSee($this->note->number);
        $this->get(route('courier.show', $this->note))->assertOk();

        // belum mulai: lokasi ditolak
        $this->post(route('courier.location', $this->note), ['lat' => -6.2, 'lng' => 106.6])->assertStatus(409);

        $this->post(route('courier.start', $this->note))->assertRedirect();
        $this->assertDatabaseHas('app_notifications', ['user_id' => $this->outletUser->id, 'title' => "Kurir menuju lokasi: {$this->note->number}"]);
        $this->get(route('courier.show', $this->note))->assertOk()->assertSee('Sudah sampai');

        $this->postJson(route('courier.location', $this->note), ['lat' => -6.2, 'lng' => 106.65, 'acc' => 12])->assertOk();
        $this->postJson(route('courier.location', $this->note), ['lat' => 999, 'lng' => 106.65])->assertStatus(422);

        // outlet pemilik bisa lihat posisi, outlet lain tidak
        $this->actingAs($this->outletUser)->getJson(route('delivery.position', $this->note))->assertOk()->assertJson(['status' => 'diantar', 'lat' => -6.2, 'lng' => 106.65]);
        $this->get(route('delivery.show', $this->note))->assertOk()->assertSee('livemap');
        $this->actingAs($this->otherOutletUser)->getJson(route('delivery.position', $this->note))->assertNotFound();

        $this->actingAs($this->kurir)->post(route('courier.arrive', $this->note))->assertRedirect(route('courier.index'));
        $this->assertDatabaseHas('app_notifications', ['user_id' => $this->outletUser->id, 'title' => "Kurir sudah sampai: {$this->note->number}"]);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $this->admin->id, 'title' => "{$this->note->number} sudah sampai"]);
    }

    public function test_other_courier_and_roles_cannot_access(): void
    {
        $lain = $this->user('kurir', null, 'Lain');
        $this->actingAs($lain)->get(route('courier.show', $this->note))->assertNotFound();
        $this->actingAs($this->outletUser)->get(route('courier.index'))->assertForbidden();
        $this->actingAs($this->kurir)->get(route('orders.index'))->assertForbidden();
        $this->actingAs($this->kurir)->get(route('home'))->assertRedirect(route('courier.index'));
    }

    public function test_admin_can_reassign_courier_from_detail(): void
    {
        $baru = $this->user('kurir', null, 'Siti Kurir');
        $this->actingAs($this->admin)->get(route('delivery.show', $this->note))->assertOk()->assertSee('Ganti kurir');
        $this->post(route('delivery.courier', $this->note), ['courier_id' => $baru->id])->assertSessionHasNoErrors();
        $this->assertSame($baru->id, $this->note->fresh()->courier_id);
        $this->post(route('delivery.courier', $this->note), ['courier_id' => $this->outletUser->id])->assertSessionHasErrors('courier_id');
    }
}
