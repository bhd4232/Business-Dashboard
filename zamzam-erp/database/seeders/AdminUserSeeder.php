<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // NOTE: 'hashed' cast on User model will auto-hash the plain password.
        // Pass plain text here — do NOT use Hash::make().
        $admin = User::firstOrCreate(
            ['email' => 'admin@zamzam.com.bd'],
            [
                'name'      => 'ZamZam Admin',
                'phone'     => '01700000000',
                'password'  => 'Admin@1234',
                'is_active' => true,
            ]
        );

        $admin->assignRole('admin');

        // Seed a test manager
        $manager = User::firstOrCreate(
            ['email' => 'manager@zamzam.com.bd'],
            [
                'name'      => 'ZamZam Manager',
                'phone'     => '01700000001',
                'password'  => 'Manager@1234',
                'is_active' => true,
            ]
        );

        $manager->assignRole('manager');
    }
}
