<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_super_admin_can_login_access_profile_and_logout(): void
    {
        User::factory()->create([
            'email' => 'admin@mrhomeaudio.com',
            'password' => Hash::make('secret-password'),
            'role' => 'super_admin',
        ]);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'admin@mrhomeaudio.com',
            'password' => 'secret-password',
        ]);

        $login->assertOk()->assertJsonPath('user.role', 'super_admin');
        $token = $login->json('token');

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'admin@mrhomeaudio.com');

        $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_regular_user_cannot_login_to_admin(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('secret-password'),
            'role' => 'user',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'secret-password',
        ])->assertUnprocessable();
    }

    public function test_super_admin_can_update_profile(): void
    {
        $token = 'profile-token';
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'admin@mrhomeaudio.com',
            'role' => 'super_admin',
            'api_token' => hash('sha256', $token),
            'api_token_expires_at' => now()->addHour(),
        ]);

        $this->withToken($token)->patchJson('/api/auth/profile', [
            'name' => 'Super Admin',
            'email' => 'owner@mrhomeaudio.com',
        ])->assertOk()
            ->assertJsonPath('user.name', 'Super Admin')
            ->assertJsonPath('user.email', 'owner@mrhomeaudio.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Super Admin',
            'email' => 'owner@mrhomeaudio.com',
        ]);
    }

    public function test_super_admin_can_change_password_with_current_password(): void
    {
        $token = 'password-token';
        $user = User::factory()->create([
            'password' => Hash::make('OldPassword1'),
            'role' => 'super_admin',
            'api_token' => hash('sha256', $token),
            'api_token_expires_at' => now()->addHour(),
        ]);

        $this->withToken($token)->putJson('/api/auth/password', [
            'current_password' => 'OldPassword1',
            'password' => 'NewPassword2',
            'password_confirmation' => 'NewPassword2',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassword2', $user->fresh()->password));
    }

    public function test_password_change_rejects_incorrect_current_password(): void
    {
        $token = 'incorrect-password-token';
        $user = User::factory()->create([
            'password' => Hash::make('OldPassword1'),
            'role' => 'super_admin',
            'api_token' => hash('sha256', $token),
            'api_token_expires_at' => now()->addHour(),
        ]);

        $this->withToken($token)->putJson('/api/auth/password', [
            'current_password' => 'WrongPassword1',
            'password' => 'NewPassword2',
            'password_confirmation' => 'NewPassword2',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('OldPassword1', $user->fresh()->password));
    }

    public function test_admin_endpoints_require_authentication(): void
    {
        $this->getJson('/api/admin/products')->assertUnauthorized();
        $this->getJson('/api/admin/carousels')->assertUnauthorized();
    }

    public function test_expired_token_is_rejected(): void
    {
        $token = 'expired-token';
        User::factory()->create([
            'role' => 'super_admin',
            'api_token' => hash('sha256', $token),
            'api_token_expires_at' => now()->subMinute(),
        ]);

        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }
}
