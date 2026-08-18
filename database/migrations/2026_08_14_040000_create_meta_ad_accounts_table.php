<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ad_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name');
            // Encrypted array casts persist an opaque base64 ciphertext, not
            // JSON — same reasoning as courier_providers.credentials.
            $table->text('credentials')->nullable();
            $table->string('currency', 8)->nullable();
            $table->decimal('exchange_rate_to_company_currency', 12, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->unsignedInteger('sync_failure_count')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ad_accounts');
    }
};
