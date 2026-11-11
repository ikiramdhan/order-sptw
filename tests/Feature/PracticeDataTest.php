<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Outlet;
use App\Models\PurchaseOrder;
use App\Models\Unit;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PracticeDataTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create(['name' => $role, 'email' => $role.'@example.com', 'password' => 'Password123', 'role' => $role, 'is_active' => true, 'must_change_password' => false]);
    }

    public function test_initial_stock_on_create_and_edit(): void
    {
        $admin = $this->user('admin_ck');
        $unit = Unit::create(['name' => 'Kg']);
        $this->actingAs($admin)->post(route('items.store'), ['name' => 'Kencur', 'base_unit_id' => $unit->id, 'price' => 1000, 'stock' => '12.5'])->assertSessionHasNoErrors();
        $item = Item::where('name', 'Kencur')->first();
        $this->assertEquals(12.5, (float) $item->stock);
        $this->assertDatabaseHas('stock_movements', ['item_id' => $item->id, 'notes' => 'Stok awal']);

        $this->actingAs($admin)->put(route('items.update', $item), ['name' => 'Kencur', 'base_unit_id' => $unit->id, 'price' => 1000, 'stock' => '8'])->assertSessionHasNoErrors();
        $this->assertEquals(8, (float) $item->fresh()->stock);
        $this->assertDatabaseHas('stock_adjustments', ['item_id' => $item->id, 'notes' => 'Koreksi stok awal']);
    }

    public function test_reset_requires_super_admin_and_phrase(): void
    {
        $this->actingAs($this->user('admin_ck'))->post(route('settings.reset'), ['scope' => 'semua', 'confirm' => 'HAPUS DATA'])->assertForbidden();

        $sa = $this->user('super_admin');
        $unit = Unit::create(['name' => 'Kg']);
        Item::create(['name' => 'X', 'base_unit_id' => $unit->id, 'price' => 1, 'stock' => 5, 'min_stock' => 0, 'is_active' => true]);
        $this->actingAs($sa)->post(route('settings.reset'), ['scope' => 'semua', 'confirm' => 'salah'])->assertSessionHasErrors('confirm');
        $this->assertSame(1, Item::count());
    }

    public function test_reset_transactions_keeps_master_and_all_clears_everything(): void
    {
        $sa = $this->user('super_admin');
        $unit = Unit::create(['name' => 'PCS']);
        $outlet = Outlet::create(['name' => 'O', 'payment_term_days' => 7, 'credit_balance' => 500, 'is_active' => true]);
        $ou = User::create(['name' => 'o', 'email' => 'o@example.com', 'password' => 'Password123', 'role' => 'outlet', 'outlet_id' => $outlet->id, 'is_active' => true, 'must_change_password' => false]);
        $item = Item::create(['name' => 'Y', 'base_unit_id' => $unit->id, 'price' => 1000, 'stock' => 0, 'min_stock' => 0, 'is_active' => true]);
        \App\Services\StockService::move($item, 'penyesuaian', 10, null, $sa->id, 'Stok awal');
        $po = PurchaseOrder::create(['number' => 'PO-1', 'outlet_id' => $outlet->id, 'created_by' => $ou->id, 'status' => 'draft', 'total' => 0]);

        $this->actingAs($sa)->post(route('settings.reset'), ['scope' => 'transaksi', 'confirm' => 'HAPUS DATA'])->assertSessionHasNoErrors();
        $this->assertSame(0, PurchaseOrder::count());
        $this->assertSame(0, \DB::table('stock_movements')->count());
        $this->assertSame(1, Item::count());
        $this->assertEquals(0, (float) $item->fresh()->stock);
        $this->assertEquals(0, $outlet->fresh()->credit_balance);

        $this->actingAs($sa)->post(route('settings.reset'), ['scope' => 'semua', 'confirm' => 'HAPUS DATA'])->assertSessionHasNoErrors();
        $this->assertSame(0, Item::count());
        $this->assertSame(0, Outlet::count());
        $this->assertNull(User::find($ou->id));
        $this->assertNotNull(User::find($sa->id));
    }

    public function test_reset_can_be_locked_and_unlocked_by_super_admin_only(): void
    {
        $this->actingAs($this->user('admin_ck'))->post(route('settings.reset.lock'), ['lock_confirm' => 'SUDAH LIVE'])->assertForbidden();

        $sa = $this->user('super_admin');
        $unit = Unit::create(['name' => 'Kg']);
        Item::create(['name' => 'X', 'base_unit_id' => $unit->id, 'price' => 1, 'stock' => 0, 'min_stock' => 0, 'is_active' => true]);

        $this->actingAs($sa)->post(route('settings.reset.lock'), ['lock_confirm' => 'salah'])->assertSessionHasErrors('lock_confirm');
        $this->post(route('settings.reset.lock'), ['lock_confirm' => 'SUDAH LIVE'])->assertSessionHasNoErrors();

        $this->post(route('settings.reset'), ['scope' => 'semua', 'confirm' => 'HAPUS DATA'])->assertSessionHasErrors('confirm');
        $this->assertSame(1, Item::count());
        $this->get(route('settings.edit'))->assertOk()->assertSee('dikunci');

        $this->post(route('settings.reset.lock'), ['lock_confirm' => 'BUKA KUNCI'])->assertSessionHasNoErrors();
        $this->post(route('settings.reset'), ['scope' => 'semua', 'confirm' => 'HAPUS DATA'])->assertSessionHasNoErrors();
        $this->assertSame(0, Item::count());
    }
}
