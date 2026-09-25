<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An order payment can name the account (Cash, bKash, bank…) it was
 * received into, so it posts to that account's ledger automatically.
 * Nullable: older rows and gateway/storefront payments have no account.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_payments', 'account_id')) {
            Schema::table('order_payments', function (Blueprint $table): void {
                $table->foreignId('account_id')->nullable()->after('method')->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_payments', 'account_id')) {
            Schema::table('order_payments', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('account_id');
            });
        }
    }
};
