<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The original orders table defined status as
 * enum('pending','processing','completed','cancelled'), but the application
 * has long since moved to draft/confirmed/completed/cancelled. SQLite does
 * not enforce enums so this went unnoticed locally, while MySQL (production)
 * rejects 'draft'/'confirmed' inserts with a strict-mode error — breaking
 * every order-creating flow (admin, storefront checkout, chat order links).
 * Convert the column to a plain string; valid values are enforced in code
 * via Order::STATUSES.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('status', 20)->default('draft')->change();
        });
    }

    public function down(): void
    {
        // Intentionally left as a string: reverting to the old enum would
        // reject the draft/confirmed rows that now legitimately exist.
    }
};
