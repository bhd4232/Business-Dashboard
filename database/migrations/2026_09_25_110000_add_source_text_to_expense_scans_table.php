<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI Expense Scan can also read expenses pasted as text (a WhatsApp
 * message, a notes-app list…), alone or alongside photos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('expense_scans', 'source_text')) {
            Schema::table('expense_scans', function (Blueprint $table): void {
                $table->text('source_text')->nullable()->after('image_paths');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('expense_scans', 'source_text')) {
            Schema::table('expense_scans', function (Blueprint $table): void {
                $table->dropColumn('source_text');
            });
        }
    }
};
