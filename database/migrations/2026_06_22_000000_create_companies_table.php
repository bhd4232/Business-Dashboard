<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('business_type')->nullable();
            $table->string('logo')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('currency', 12)->default('BDT');
            $table->string('timezone')->default('Asia/Dhaka');
            $table->string('invoice_prefix', 20)->default('MAIN');
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'name']);
        });

        Schema::create('company_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'user_id']);
            $table->index(['user_id', 'is_default']);
        });

        $now = now();
        $profile = $this->companyProfileFromSettings();

        DB::table('companies')->insert([
            'name' => $profile['name'],
            'slug' => 'main-company',
            'business_type' => 'general',
            'logo' => $profile['logo'],
            'phone' => $profile['phone'],
            'email' => $profile['email'],
            'address' => $profile['address'],
            'currency' => $profile['currency'],
            'timezone' => $profile['timezone'],
            'invoice_prefix' => 'MAIN',
            'is_active' => true,
            'settings' => json_encode([]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $companyId = (int) DB::table('companies')->where('slug', 'main-company')->value('id');

        DB::table('users')
            ->orderBy('id')
            ->get(['id', 'role'])
            ->each(function ($user) use ($companyId, $now): void {
                DB::table('company_user')->insert([
                    'company_id' => $companyId,
                    'user_id' => $user->id,
                    'role' => $user->role ?? null,
                    'is_default' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_user');
        Schema::dropIfExists('companies');
    }

    protected function companyProfileFromSettings(): array
    {
        if (! Schema::hasTable('app_settings')) {
            return $this->fallbackProfile();
        }

        $settings = DB::table('app_settings')
            ->whereIn('key', [
                'company.name',
                'company.logo',
                'company.phone',
                'company.email',
                'company.address',
                'company.currency',
                'company.timezone',
            ])
            ->pluck('value', 'key');

        $name = trim((string) ($settings['company.name'] ?? '')) ?: 'Main Company';

        return [
            'name' => Str::limit($name, 255, ''),
            'logo' => $settings['company.logo'] ?? null,
            'phone' => $settings['company.phone'] ?? null,
            'email' => $settings['company.email'] ?? null,
            'address' => $settings['company.address'] ?? null,
            'currency' => trim((string) ($settings['company.currency'] ?? '')) ?: 'BDT',
            'timezone' => trim((string) ($settings['company.timezone'] ?? '')) ?: config('app.timezone', 'Asia/Dhaka'),
        ];
    }

    protected function fallbackProfile(): array
    {
        return [
            'name' => 'Main Company',
            'logo' => null,
            'phone' => null,
            'email' => null,
            'address' => null,
            'currency' => 'BDT',
            'timezone' => config('app.timezone', 'Asia/Dhaka'),
        ];
    }
};
