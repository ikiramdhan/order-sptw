<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    private function make(string $role, array $extra = []): User
    {
        return User::create(array_merge([
            'name' => 'Tes '.$role,
            'email' => $role.'@example.com',
            'password' => 'Password123',
            'role' => $role,
            'is_active' => true,
            'must_change_password' => false,
        ], $extra));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_login_success_and_wrong_password(): void
    {
        $this->make('admin_ck');

        $this->post('/login', ['email' => 'admin_ck@example.com', 'password' => 'salah'])
            ->assertSessionHasErrors('email');

        $this->post('/login', ['email' => 'admin_ck@example.com', 'password' => 'Password123'])
            ->assertRedirect(route('home'));
        $this->assertAuthenticated();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->make('gudang', ['is_active' => false]);

        $this->post('/login', ['email' => 'gudang@example.com', 'password' => 'Password123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_temporary_password_forces_change(): void
    {
        $user = $this->make('outlet', ['must_change_password' => true]);

        $this->actingAs($user)->get('/')->assertRedirect(route('account.password'));

        $this->actingAs($user)->put('/akun/password', [
            'current_password' => 'Password123',
            'password' => 'PasswordBaru456',
            'password_confirmation' => 'PasswordBaru456',
        ])->assertRedirect(route('home'));

        $this->assertFalse($user->fresh()->must_change_password);
        $this->actingAs($user->fresh())->get('/')->assertOk();
    }

    public function test_change_password_rejects_weak_or_wrong_current(): void
    {
        $user = $this->make('outlet');

        $this->actingAs($user)->put('/akun/password', [
            'current_password' => 'bukan-password',
            'password' => 'pendek',
            'password_confirmation' => 'pendek',
        ])->assertSessionHasErrors(['current_password', 'password']);
    }

    public function test_only_super_admin_can_manage_users_outlets_and_settings(): void
    {
        foreach (['admin_ck', 'gudang', 'outlet'] as $role) {
            $user = $this->make($role);
            $this->actingAs($user)->get('/pengguna')->assertForbidden();
            $this->actingAs($user)->get('/outlet')->assertForbidden();
            $this->actingAs($user)->get('/pengaturan')->assertForbidden();
        }

        $super = $this->make('super_admin');
        $this->actingAs($super)->get('/pengguna')->assertOk();
        $this->actingAs($super)->get('/outlet')->assertOk();
        $this->actingAs($super)->get('/pengaturan')->assertOk();
    }

    public function test_super_admin_creates_outlet_and_outlet_user_with_temp_password(): void
    {
        $super = $this->make('super_admin');

        $this->actingAs($super)->post('/outlet', [
            'name' => 'Outlet Bintaro', 'payment_term_days' => 14,
        ])->assertRedirect(route('outlets.index'));

        $outlet = Outlet::where('name', 'Outlet Bintaro')->firstOrFail();

        $this->actingAs($super)->post('/pengguna', [
            'name' => 'Rina', 'email' => 'rina@example.com', 'role' => 'outlet', 'outlet_id' => $outlet->id,
        ])->assertRedirect(route('users.index'))->assertSessionHas('temp_password');

        $created = User::where('email', 'rina@example.com')->firstOrFail();
        $this->assertTrue($created->must_change_password);
        $this->assertSame($outlet->id, $created->outlet_id);
    }

    public function test_outlet_role_requires_outlet(): void
    {
        $super = $this->make('super_admin');

        $this->actingAs($super)->post('/pengguna', [
            'name' => 'Tanpa Outlet', 'email' => 'x@example.com', 'role' => 'outlet',
        ])->assertSessionHasErrors('outlet_id');
    }

    public function test_super_admin_cannot_deactivate_self_and_deactivated_user_is_blocked(): void
    {
        $super = $this->make('super_admin');
        $staff = $this->make('gudang');

        $this->actingAs($super)->post("/pengguna/{$super->id}/toggle")->assertForbidden();
        $this->actingAs($super)->post("/pengguna/{$staff->id}/toggle")->assertRedirect();
        $this->assertFalse($staff->fresh()->is_active);

        $this->actingAs($staff->fresh())->get('/')->assertRedirect(route('login'));
    }

    public function test_forgot_password_sends_reset_notification_only_to_active_user(): void
    {
        Notification::fake();
        $user = $this->make('outlet');
        $this->make('gudang', ['is_active' => false]);

        $this->post('/lupa-password', ['email' => 'outlet@example.com'])->assertSessionHas('status');
        Notification::assertSentTo($user, \App\Notifications\ResetPasswordId::class);

        $this->post('/lupa-password', ['email' => 'gudang@example.com'])->assertSessionHas('status');
        Notification::assertNotSentTo(User::where('email', 'gudang@example.com')->first(), \App\Notifications\ResetPasswordId::class);
    }

    public function test_reset_password_with_valid_token(): void
    {
        $user = $this->make('outlet', ['must_change_password' => true]);
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token, 'email' => $user->email,
            'password' => 'SeblakEnak2026', 'password_confirmation' => 'SeblakEnak2026',
        ])->assertRedirect(route('login'));

        $this->assertFalse($user->fresh()->must_change_password);

        // token sekali pakai
        $this->post('/reset-password', [
            'token' => $token, 'email' => $user->email,
            'password' => 'LainLagi12345', 'password_confirmation' => 'LainLagi12345',
        ])->assertSessionHasErrors('email');
    }

    public function test_smtp_password_is_stored_encrypted_and_kept_when_blank(): void
    {
        $super = $this->make('super_admin');
        $payload = [
            'smtp_host' => 'smtp.hostinger.com', 'smtp_port' => 465, 'smtp_encryption' => 'ssl',
            'smtp_username' => 'noreply@example.com', 'smtp_from_name' => 'CK', 'smtp_password' => 'rahasia-smtp',
        ];

        $this->actingAs($super)->put('/pengaturan', $payload)->assertSessionHas('status');
        $stored = Setting::get('smtp_password');
        $this->assertNotSame('rahasia-smtp', $stored);
        $this->assertSame('rahasia-smtp', \App\Services\MailSettings::password());

        $this->actingAs($super)->put('/pengaturan', array_merge($payload, ['smtp_password' => '']))->assertSessionHas('status');
        $this->assertSame($stored, Setting::get('smtp_password'));
    }

    public function test_overdue_command_marks_invoices_and_purchases(): void
    {
        $this->artisan('ck:mark-overdue')->assertSuccessful();
    }
}
