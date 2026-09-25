<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remembers the company a user last switched to (a company id, or "all"
 * for Super Admins), so a new session after logout, closing the app or an
 * app update reopens that company instead of the default one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'last_company_selection')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('last_company_selection', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'last_company_selection')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('last_company_selection');
            });
        }
    }
};
