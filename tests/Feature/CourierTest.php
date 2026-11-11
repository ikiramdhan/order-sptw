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

    public function test_eta_and_progress_from_outlet_coordinates(): void
    {
        \Illuminate\Support\Facades\Http::fake(['router.project-osrm.org/*' => \Illuminate\Support\Facades\Http::response(['routes' => [['distance' => 4000, 'duration' => 600, 'geometry' => ['coordinates' => [[106.65, -6.2], [106.66, -6.21]]]]]])]);
        $this->note->outlet->update(['lat' => -6.21, 'lng' => 106.66]);
        $this->actingAs($this->kurir)->post(route('courier.start', $this->note));
        $this->postJson(route('courier.location', $this->note), ['lat' => -6.2, 'lng' => 106.65])->assertOk();

        $r = $this->actingAs($this->outletUser)->getJson(route('delivery.position', $this->note))->assertOk();
        $r->assertJson(['dist_m' => 4000, 'eta_s' => 600, 'progress' => 0.0]);
        $this->assertCount(2, $r->json('route'));
        $this->get(route('delivery.show', $this->note))->assertOk()->assertSee('etabar');
    }

    public function test_eta_falls_back_to_straight_line_when_router_fails(): void
    {
        \Illuminate\Support\Facades\Http::fake(['router.project-osrm.org/*' => \Illuminate\Support\Facades\Http::response('x', 500)]);
        $this->note->outlet->update(['lat' => -6.21, 'lng' => 106.66]);
        $this->actingAs($this->kurir)->post(route('courier.start', $this->note));
        $this->postJson(route('courier.location', $this->note), ['lat' => -6.2, 'lng' => 106.65]);
        $this->actingAs($this->admin)->getJson(route('delivery.position', $this->note))->assertOk()->assertJsonPath('eta_s', fn ($v) => $v > 0);
    }

    public function test_chat_between_outlet_and_courier_with_notifications(): void
    {
        $this->actingAs($this->outletUser)->postJson(route('delivery.chat.store', $this->note), ['body' => 'Tolong lebih cepat ya'])->assertOk();
        $this->assertDatabaseHas('app_notifications', ['user_id' => $this->kurir->id, 'body' => 'Tolong lebih cepat ya']);

        $this->actingAs($this->kurir)->postJson(route('delivery.chat.store', $this->note), ['body' => 'Siap'])->assertOk();
        $this->assertDatabaseHas('app_notifications', ['user_id' => $this->outletUser->id, 'body' => 'Siap']);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $this->admin->id, 'body' => 'Siap']);

        $r = $this->getJson(route('delivery.chat', $this->note))->assertOk();
        $this->assertCount(2, $r->json('messages'));
        $this->assertTrue($r->json('messages.1.mine'));
        $this->getJson(route('delivery.chat', ['note' => $this->note, 'after' => $r->json('messages.0.id')]))->assertJsonCount(1, 'messages');

        $this->actingAs($this->otherOutletUser)->getJson(route('delivery.chat', $this->note))->assertNotFound();
        $this->actingAs($this->outletUser)->postJson(route('delivery.chat.store', $this->note), ['body' => ''])->assertStatus(422);
        $this->actingAs($this->kurir)->get(route('courier.show', $this->note))->assertOk()->assertSee('Chat dengan outlet');
    }

    public function test_super_admin_can_set_outlet_coordinates(): void
    {
        $sa = $this->user('super_admin');
        $o = $this->outletUser->outlet;
        $this->actingAs($sa)->put(route('outlets.update', $o), ['name' => $o->name, 'payment_term_days' => 7, 'lat' => '-6.2088', 'lng' => '106.8456'])->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(-6.2088, $o->fresh()->lat, 0.0001);
        $this->put(route('outlets.update', $o), ['name' => $o->name, 'payment_term_days' => 7, 'lat' => '-6.2'])->assertSessionHasErrors('lng');
        $this->get(route('outlets.edit', $o))->assertOk()->assertSee('usegps');
    }

    public function test_track_page_for_outlet_admin_and_blocked_for_others(): void
    {
        $this->actingAs($this->outletUser)->get(route('delivery.track', $this->note))->assertOk()->assertSee('tkbanner')->assertSee($this->note->number);
        $this->actingAs($this->admin)->get(route('delivery.track', $this->note))->assertOk();
        $this->actingAs($this->otherOutletUser)->get(route('delivery.track', $this->note))->assertNotFound();
        $this->actingAs($this->outletUser)->get(route('delivery.show', $this->note))->assertOk()->assertDontSee('livemap"></div>');
    }

    public function test_admin_sets_outlet_location_from_maps_link_and_outlet_cannot(): void
    {
        $o = $this->outletUser->outlet;
        $this->actingAs($this->outletUser)->get(route('delivery.show', $this->note))->assertOk()->assertDontSee('Atur titik lokasi outlet');
        $this->post(route('outlet.location', $o), ['lat' => '-6.2', 'lng' => '106.6'])->assertForbidden();

        $this->actingAs($this->admin)->get(route('delivery.show', $this->note))->assertOk()->assertSee('Atur titik lokasi outlet');
        $this->post(route('outlet.location', $o), ['link' => 'https://www.google.com/maps/place/X/@-6.2210,106.6420,17z/data=abc'])->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(-6.2210, $o->fresh()->lat, 0.0001);
        $this->assertEqualsWithDelta(106.642, $o->fresh()->lng, 0.0001);
        $this->post(route('outlet.location', $o), ['lat' => '-6.25', 'lng' => '106.7'])->assertSessionHasNoErrors();
        $this->post(route('outlet.location', $o), ['link' => 'bukan lokasi'])->assertSessionHasErrors('link');
        $this->post(route('outlet.location', $o), ['link' => '-6.3, 106.8'])->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(106.8, $o->fresh()->lng, 0.0001);
        $this->get(route('delivery.show', $this->note))->assertOk()->assertDontSee('Atur titik lokasi outlet');
    }

    public function test_super_admin_outlet_form_accepts_maps_link(): void
    {
        $sa = $this->user('super_admin');
        $o = $this->outletUser->outlet;
        $this->actingAs($sa)->put(route('outlets.update', $o), ['name' => $o->name, 'payment_term_days' => 7, 'maps_link' => 'https://www.google.com/maps/place/Gerendeng/@-6.2105,106.6188,17z'])->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(-6.2105, $o->fresh()->lat, 0.0001);
        $this->assertEqualsWithDelta(106.6188, $o->fresh()->lng, 0.0001);
        $this->put(route('outlets.update', $o), ['name' => $o->name, 'payment_term_days' => 7, 'maps_link' => 'https://example.com/x'])->assertSessionHasErrors('maps_link');

        // link pendek: diikuti sampai ke link panjang yang memuat koordinat
        \Illuminate\Support\Facades\Http::fake([
            'maps.app.goo.gl/*' => \Illuminate\Support\Facades\Http::response('', 302, ['Location' => 'https://www.google.com/maps/place/Gerendeng/@-6.3111,106.7222,17z/data=x']),
        ]);
        $this->put(route('outlets.update', $o), ['name' => $o->name, 'payment_term_days' => 7, 'maps_link' => 'Lokasi: https://maps.app.goo.gl/abc123'])->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(-6.3111, $o->fresh()->lat, 0.0001);
        $this->assertEqualsWithDelta(106.7222, $o->fresh()->lng, 0.0001);
    }

    public function test_short_link_resolves_via_page_body_when_redirect_has_no_coords(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'maps.app.goo.gl/*' => \Illuminate\Support\Facades\Http::response('', 302, ['Location' => 'https://www.google.com/maps/place/Gerendeng/data=!4m2!3m1!1s0x2e69:0xabc']),
            'www.google.com/maps/place/*' => \Illuminate\Support\Facades\Http::response('<meta itemprop="image" content="https://maps.google.com/maps/api/staticmap?center=-6.2105%2C106.6188&zoom=15">', 200),
        ]);
        [$lat, $lng] = \App\Services\MapsLink::resolve('https://maps.app.goo.gl/evVg1z83SQCVthLZ9?g_st=ic');
        $this->assertEqualsWithDelta(-6.2105, $lat, 0.0001);
        $this->assertEqualsWithDelta(106.6188, $lng, 0.0001);
    }
}
