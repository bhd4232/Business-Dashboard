<?php

use App\Models\Account;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->string('system_key', 50)->nullable()->after('type');
            $table->unique(['company_id', 'system_key'], 'accounts_company_system_key_unique');
        });

        DB::table('companies')
            ->orderBy('id')
            ->each(function (object $company): void {
                foreach (Account::SYSTEM_ACCOUNTS as $key => $definition) {
                    DB::table('accounts')->updateOrInsert(
                        [
                            'company_id' => $company->id,
                            'system_key' => $key,
                        ],
                        [
                            ...$definition,
                            'opening_balance' => 0,
                            'current_balance' => 0,
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );
                }
            });
    }

    public function down(): void
    {
        DB::table('accounts')->whereNotNull('system_key')->delete();

        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropUnique('accounts_company_system_key_unique');
            $table->dropColumn('system_key');
        });
    }
};
