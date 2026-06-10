<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Migration order: currencies → exchange_rates → activity_log
return new class extends Migration
{
    public function up(): void
    {
        // ─── Currencies ────────────────────────────────────
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name', 100);
            $table->string('symbol', 5);
            $table->boolean('is_base')->default(false);
            $table->tinyInteger('decimal_places')->default(2);
            $table->timestamps();
        });

        // ─── Exchange Rates ────────────────────────────────
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_currency_id')->constrained('currencies');
            $table->foreignId('to_currency_id')->constrained('currencies');
            $table->decimal('rate', 12, 6);
            $table->date('effective_date');
            $table->string('source', 50)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['from_currency_id', 'to_currency_id', 'effective_date'], 'exchange_rates_unique');
        });

        // ─── Activity Log ──────────────────────────────────
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('event');
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id'], 'idx_activity_log_subject');
            $table->index('user_id', 'idx_activity_log_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('currencies');
    }
};
