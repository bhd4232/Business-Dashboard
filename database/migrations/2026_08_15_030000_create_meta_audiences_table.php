<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_audiences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('meta_ad_account_id')->constrained()->cascadeOnDelete();
            $table->string('meta_id');
            $table->string('name');
            $table->string('subtype', 16); // WEBSITE | LOOKALIKE
            $table->text('description')->nullable();
            $table->string('pixel_id', 32)->nullable(); // WEBSITE only
            $table->unsignedSmallInteger('retention_days')->nullable(); // WEBSITE only
            $table->json('rule')->nullable(); // exact rule array sent to Meta, kept for our own record
            $table->string('origin_audience_meta_id')->nullable(); // LOOKALIKE only
            $table->string('lookalike_country', 8)->nullable(); // LOOKALIKE only
            $table->decimal('lookalike_ratio', 5, 2)->nullable(); // LOOKALIKE only, stored as a percent (e.g. 5.00)
            $table->unsignedBigInteger('approximate_count_lower_bound')->nullable();
            $table->unsignedBigInteger('approximate_count_upper_bound')->nullable();
            $table->string('delivery_status')->nullable();
            $table->string('operation_status')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            // 'meta' = pulled in from an account that already had this audience;
            // 'erp' = created from this app's New Audience action (Phase E).
            $table->string('source', 16)->default('meta');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['meta_ad_account_id', 'meta_id']);
            $table->index(['company_id', 'meta_ad_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_audiences');
    }
};
