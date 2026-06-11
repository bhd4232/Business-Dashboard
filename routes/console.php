<?php

use Illuminate\Foundation\Inspiring;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('admin:ensure-super {--email=} {--password=} {--name=ZamZam Admin}', function () {
    $email = $this->option('email') ?: env('ADMIN_EMAIL', 'admin@zamzamint.com');
    $password = $this->option('password') ?: env('ADMIN_PASSWORD');

    if (! $password) {
        $this->error('Please provide --password or set ADMIN_PASSWORD.');

        return 1;
    }

    User::query()->updateOrCreate(
        ['email' => $email],
        [
            'name' => $this->option('name'),
            'password' => $password,
            'role' => 'super_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ],
    );

    $this->info("Super admin is ready: {$email}");

    return 0;
})->purpose('Create or reset an active Super Admin user');
