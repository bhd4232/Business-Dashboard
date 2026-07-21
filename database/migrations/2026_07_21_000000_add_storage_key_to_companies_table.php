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
        Schema::table('companies', function (Blueprint $table): void {
            $table->uuid('storage_key')->nullable()->unique()->after('id');
        });

        DB::table('companies')
            ->select('id')
            ->orderBy('id')
            ->eachById(function ($company): void {
                DB::table('companies')
                    ->where('id', $company->id)
                    ->update(['storage_key' => (string) Str::uuid()]);
            });

        Schema::table('companies', function (Blueprint $table): void {
            $table->uuid('storage_key')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropUnique(['storage_key']);
            $table->dropColumn('storage_key');
        });
    }
};
