<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class AdminPasswordSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_password_helper_rejects_weak_passwords(): void
    {
        $this->expectException(RuntimeException::class);

        AdminPassword::assertStrong('password', 'ADMIN_PASSWORD');
    }

    public function test_admin_ensure_super_rejects_weak_passwords(): void
    {
        $exitCode = Artisan::call('admin:ensure-super', [
            '--email' => 'weak-admin@example.com',
            '--password' => 'password',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('must be at least 12 characters', Artisan::output());
        $this->assertDatabaseMissing('users', ['email' => 'weak-admin@example.com']);
    }

    public function test_admin_ensure_super_accepts_strong_passwords(): void
    {
        $password = 'Strong-Admin-123!';

        $exitCode = Artisan::call('admin:ensure-super', [
            '--email' => 'secure-admin@example.com',
            '--password' => $password,
            '--name' => 'Secure Admin',
        ]);

        $this->assertSame(0, $exitCode);

        $user = User::query()->where('email', 'secure-admin@example.com')->firstOrFail();

        $this->assertSame('Secure Admin', $user->name);
        $this->assertSame('super_admin', $user->role);
        $this->assertTrue(Hash::check($password, $user->password));
    }
}
