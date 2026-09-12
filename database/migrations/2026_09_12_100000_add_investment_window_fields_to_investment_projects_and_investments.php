<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v3 gap analysis P2.3 — a project can optionally declare an investment
 * collection window (owner: "আগামী ১০ দিন পর্যন্ত ... ইনভেস্টমেন্ট নেয়া হবে").
 * Both columns are nullable: a project that never sets them keeps today's
 * unrestricted behaviour. `investments.override_reason` records why a
 * super_admin (or a user separately granted
 * `investments.override_investment_window`) added an investment after the
 * window closed (Q4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investment_projects', function (Blueprint $table): void {
            if (! Schema::hasColumn('investment_projects', 'investment_opens_at')) {
                $table->date('investment_opens_at')->nullable()->after('start_date');
            }
            if (! Schema::hasColumn('investment_projects', 'investment_closes_at')) {
                $table->date('investment_closes_at')->nullable()->after('investment_opens_at');
            }
        });

        Schema::table('investments', function (Blueprint $table): void {
            if (! Schema::hasColumn('investments', 'override_reason')) {
                $table->text('override_reason')->nullable()->after('invested_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('investment_projects', function (Blueprint $table): void {
            $table->dropColumn(['investment_opens_at', 'investment_closes_at']);
        });
        Schema::table('investments', function (Blueprint $table): void {
            $table->dropColumn(['override_reason']);
        });
    }
};
