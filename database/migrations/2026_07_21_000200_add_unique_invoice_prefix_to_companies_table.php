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
        if (! Schema::hasTable('companies') || ! Schema::hasColumn('companies', 'invoice_prefix')) {
            return;
        }

        $used = [];

        DB::table('companies')
            ->select(['id', 'invoice_prefix'])
            ->orderBy('id')
            ->chunkById(500, function ($companies) use (&$used): void {
                foreach ($companies as $company) {
                    $base = Str::upper(trim((string) $company->invoice_prefix));
                    $base = preg_replace('/[^A-Z0-9-]+/', '-', $base) ?: 'INV';
                    $base = trim(Str::substr($base, 0, 20), '-') ?: 'INV';
                    $candidate = $base;
                    $attempt = 0;

                    while (isset($used[$candidate])) {
                        $attempt++;
                        $suffix = '-'.$company->id.($attempt > 1 ? '-'.$attempt : '');
                        $candidate = rtrim(Str::substr($base, 0, max(1, 20 - strlen($suffix))), '-').$suffix;
                    }

                    $used[$candidate] = true;

                    if ($candidate !== $company->invoice_prefix) {
                        DB::table('companies')
                            ->where('id', $company->id)
                            ->update(['invoice_prefix' => $candidate]);
                    }
                }
            });

        Schema::table('companies', function (Blueprint $table): void {
            $table->unique('invoice_prefix', 'companies_invoice_prefix_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('companies') || ! Schema::hasColumn('companies', 'invoice_prefix')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropUnique('companies_invoice_prefix_unique');
        });
    }
};
