<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = config('auth.super_admin.password');
        if (! is_string($password) || trim($password) === '') {
            throw new RuntimeException('Set SUPER_ADMIN_PASSWORD in your environment before seeding the super admin.');
        }

        User::query()->updateOrCreate(
            ['email' => config('auth.super_admin.email')],
            [
                'name' => config('auth.super_admin.name'),
                'password' => $password,
                'role' => 'super_admin',
                'email_verified_at' => now(),
            ],
        );
    }
}
