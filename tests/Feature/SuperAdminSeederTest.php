<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class SuperAdminSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[TestWith([null])]
    #[TestWith([''])]
    #[TestWith(['   '])]
    public function test_missing_admin_password_prevents_account_creation(?string $password): void
    {
        config(['auth.super_admin.password' => $password]);

        try {
            $this->seed(SuperAdminSeeder::class);
            $this->fail('Seeding must require an explicit admin password.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Set SUPER_ADMIN_PASSWORD in your environment before seeding the super admin.', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_seeder_uses_explicit_password_and_hashes_it(): void
    {
        config([
            'auth.super_admin.name' => 'Test Admin',
            'auth.super_admin.email' => 'admin@example.test',
            'auth.super_admin.password' => 'test-only-admin-password',
        ]);

        $this->seed(SuperAdminSeeder::class);

        $user = User::query()->where('email', 'admin@example.test')->firstOrFail();
        $this->assertSame('super_admin', $user->role);
        $this->assertTrue(Hash::check('test-only-admin-password', $user->password));
    }
}
