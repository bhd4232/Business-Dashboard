<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual checkout payments used to be identified only by a hardcoded
 * `gateway` value ('manual_bkash'/'manual_nagad'). Now that manual channels
 * are an admin-managed list (storefront_payment_methods), a manual payment
 * points at the exact channel it was made through, so the admin payments
 * list can show its real name (e.g. "Rocket (Send Money)") instead of
 * guessing from a fixed set of literal gateway strings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_payments', function (Blueprint $table) {
            $table->foreignId('storefront_payment_method_id')->nullable()->after('gateway')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('storefront_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('storefront_payment_method_id');
        });
    }
};
