<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminManagementTest extends TestCase
{
    use RefreshDatabase;

    private function make(string $role, string $email): User
    {
        return User::create(['name' => 'Tes '.$role, 'email' => $email, 'password' => 'Password123', 'role' => $role, 'is_active' => true, 'must_change_password' => false]);
    }

    public function test_super_admin_can_create_another_super_admin(): void
    {
        $sa = $this->make('super_admin', 'sa1@example.com');

        $this->actingAs($sa)->get(route('users.create'))->assertOk()->assertSee('Super Admin');

        $this->actingAs($sa)->post(route('users.store'), ['name' => 'SA Dua', 'email' => 'sa2@example.com', 'role' => 'super_admin'])
            ->assertRedirect(route('users.index'))->assertSessionHas('temp_password');

        $new = User::where('email', 'sa2@example.com')->first();
        $this->assertTrue($new->isSuperAdmin());
        $this->assertTrue($new->must_change_password);
        $this->assertNull($new->outlet_id);
    }

    public function test_only_super_admin_can_open_user_management(): void
    {
        $admin = $this->make('admin_ck', 'admin@example.com');

        $this->actingAs($admin)->post(route('users.store'), ['name' => 'X', 'email' => 'x@example.com', 'role' => 'super_admin'])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
    }

    public function test_super_admin_role_cannot_be_assigned_by_editing_a_user(): void
    {
        $sa = $this->make('super_admin', 'sa1@example.com');
        $staf = $this->make('gudang', 'g@example.com');

        $this->actingAs($sa)->put(route('users.update', $staf), ['name' => 'G', 'email' => 'g@example.com', 'role' => 'super_admin'])
            ->assertSessionHasErrors('role');
        $this->assertSame('gudang', $staf->fresh()->role);
    }

    public function test_super_admin_can_deactivate_and_reset_the_other_one_but_not_self(): void
    {
        $a = $this->make('super_admin', 'a@example.com');
        $b = $this->make('super_admin', 'b@example.com');

        $this->actingAs($a)->post(route('users.toggle', $b))->assertSessionHasNoErrors();
        $this->assertFalse($b->fresh()->is_active);

        $this->actingAs($a)->post(route('users.reset', $b))->assertSessionHas('temp_password');
        $this->assertTrue($b->fresh()->must_change_password);

        $this->actingAs($a)->post(route('users.toggle', $a))->assertForbidden();
        $this->actingAs($a)->post(route('users.reset', $a))->assertForbidden();
        $this->assertTrue($a->fresh()->is_active);
    }
}
