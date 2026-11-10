<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemRecipe;
use App\Models\Outlet;
use App\Models\PurchaseOrder;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeTest extends TestCase
{
    use RefreshDatabase;

    private Item $bumbu;
    private Item $kencur;
    private Item $bawang;
    private User $admin;
    private User $outletUser;

    protected function setUp(): void
    {
        parent::setUp();
        $pcs = Unit::create(['name' => 'PCS']);
        $kg = Unit::create(['name' => 'Kg']);
        $this->kencur = Item::create(['name' => 'Kencur', 'base_unit_id' => $kg->id, 'price' => 0, 'stock' => 10, 'min_stock' => 0, 'is_active' => true]);
        $this->bawang = Item::create(['name' => 'Bawang Putih', 'base_unit_id' => $kg->id, 'price' => 0, 'stock' => 3, 'min_stock' => 0, 'is_active' => true]);
        $this->bumbu = Item::create(['name' => 'Bumbu Seblak', 'base_unit_id' => $pcs->id, 'price' => 5000, 'stock' => 0, 'min_stock' => 0, 'is_active' => true]);
        ItemRecipe::create(['item_id' => $this->bumbu->id, 'ingredient_id' => $this->kencur->id, 'qty_base' => 0.05]);
        ItemRecipe::create(['item_id' => $this->bumbu->id, 'ingredient_id' => $this->bawang->id, 'qty_base' => 0.03]);

        $outlet = Outlet::create(['name' => 'Outlet A', 'payment_term_days' => 7, 'credit_balance' => 0, 'is_active' => true]);
        $mk = fn ($role, $x = []) => User::create(array_merge(['name' => $role, 'email' => $role.'@example.com', 'password' => 'Password123', 'role' => $role, 'is_active' => true, 'must_change_password' => false], $x));
        $this->admin = $mk('admin_ck');
        $this->outletUser = $mk('outlet', ['outlet_id' => $outlet->id]);
    }

    private function order(float $qty): PurchaseOrder
    {
        $this->actingAs($this->outletUser)->post(route('cart.add'), ['item_id' => $this->bumbu->id, 'unit_id' => $this->bumbu->base_unit_id, 'qty' => $qty])->assertSessionHasNoErrors();
        $this->post(route('cart.submit'))->assertSessionHasNoErrors();

        return PurchaseOrder::firstOrFail();
    }

    public function test_available_stock_is_limited_by_scarcest_ingredient(): void
    {
        // kencur 10/0.05 = 200, bawang 3/0.03 = 100 -> 100
        $this->assertEquals(100.0, $this->bumbu->fresh()->availableStock());
    }

    public function test_shipping_deducts_ingredients_per_recipe(): void
    {
        $po = $this->order(10);
        $row = $po->items->first();

        $this->actingAs($this->admin)->post(route('orders.ship', $po), ['ship' => [$row->id => 10]])->assertSessionHasNoErrors();

        $this->assertEquals(9.5, (float) $this->kencur->fresh()->stock);
        $this->assertEquals(2.7, (float) $this->bawang->fresh()->stock);
        $this->assertEquals(0.0, (float) $this->bumbu->fresh()->stock);
    }

    public function test_shortage_of_one_ingredient_blocks_and_rolls_back(): void
    {
        $this->bawang->update(['stock' => 0.1]); // cukup untuk 3 bumbu saja
        $po = $this->order(5);
        $row = $po->items->first();

        $this->actingAs($this->admin)->post(route('orders.ship', $po), ['ship' => [$row->id => 5]])->assertSessionHasErrors('ship');

        $this->assertEquals(10.0, (float) $this->kencur->fresh()->stock);
        $this->assertEquals(0.1, (float) $this->bawang->fresh()->stock);
    }

    public function test_item_form_saves_recipe_and_blocks_nested_recipe(): void
    {
        $this->actingAs($this->admin)->put(route('items.update', $this->bumbu), [
            'name' => 'Bumbu Seblak', 'base_unit_id' => $this->bumbu->base_unit_id, 'price' => 5000, 'min_stock' => 0,
            'recipe' => [['ingredient_id' => $this->kencur->id, 'qty' => '0,1']],
        ])->assertSessionHasNoErrors();

        $this->assertCount(1, $this->bumbu->fresh()->recipe);
        $this->assertEquals(0.1, (float) $this->bumbu->fresh()->recipe->first()->qty_base);

        // bahan tidak boleh barang beresep
        $this->put(route('items.update', $this->kencur), [
            'name' => 'Kencur', 'base_unit_id' => $this->kencur->base_unit_id, 'price' => 0, 'min_stock' => 0,
            'recipe' => [['ingredient_id' => $this->bumbu->id, 'qty' => '1']],
        ])->assertSessionHasErrors();
    }
}
