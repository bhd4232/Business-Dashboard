<?php

namespace Tests\Feature;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompanyStorageSchemaMigrationTest extends TestCase
{
    public function test_storage_key_migration_backfills_existing_companies_with_unique_uuids(): void
    {
        $this->withIsolatedDatabase(function (): void {
            Schema::create('companies', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
            });
            DB::table('companies')->insert([
                ['name' => 'Storage Backfill One'],
                ['name' => 'Storage Backfill Two'],
            ]);

            $migration = require database_path('migrations/2026_07_21_000000_add_storage_key_to_companies_table.php');
            $migration->up();

            $keys = DB::table('companies')->orderBy('id')->pluck('storage_key')->all();

            $this->assertCount(2, $keys);
            $this->assertTrue(Str::isUuid($keys[0]));
            $this->assertTrue(Str::isUuid($keys[1]));
            $this->assertNotSame($keys[0], $keys[1]);
        });
    }

    public function test_legacy_private_registry_backfill_is_case_sensitive_and_denies_cross_company_conflicts(): void
    {
        $this->withIsolatedDatabase(function (): void {
            Schema::create('companies', function (Blueprint $table): void {
                $table->id();
            });
            Schema::create('conversations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            });
            Schema::create('conversation_messages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
                $table->string('media_path')->nullable();
            });
            Schema::create('voucher_attachments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('file_path')->nullable();
            });
            DB::table('companies')->insert([['id' => 1], ['id' => 2]]);
            DB::table('conversations')->insert([
                ['id' => 1, 'company_id' => 1],
                ['id' => 2, 'company_id' => 2],
            ]);
            DB::table('conversation_messages')->insert([
                ['conversation_id' => 1, 'media_path' => 'Files/Case.pdf'],
                ['conversation_id' => 1, 'media_path' => 'files/case.pdf'],
                ['conversation_id' => 1, 'media_path' => 'shared/conflict.pdf'],
                ['conversation_id' => 2, 'media_path' => 'shared/conflict.pdf'],
            ]);

            $migration = require database_path('migrations/2026_07_21_000100_create_legacy_private_storage_paths_table.php');
            $migration->up();

            $this->assertSame(3, DB::table('legacy_private_storage_paths')->count());
            $this->assertNotNull(DB::table('legacy_private_storage_paths')
                ->where('path_hash', hash('sha256', 'Files/Case.pdf'))
                ->where('company_id', 1)
                ->where('is_conflicted', false)
                ->first());
            $this->assertNotNull(DB::table('legacy_private_storage_paths')
                ->where('path_hash', hash('sha256', 'files/case.pdf'))
                ->where('company_id', 1)
                ->where('is_conflicted', false)
                ->first());

            $conflict = DB::table('legacy_private_storage_paths')
                ->where('path_hash', hash('sha256', 'shared/conflict.pdf'))
                ->first();

            $this->assertNotNull($conflict);
            $this->assertTrue((bool) $conflict->is_conflicted);
            $this->assertNull($conflict->company_id);
            $this->assertSame(2, (int) $conflict->reference_count);
        });
    }

    public function test_invoice_prefix_migration_normalizes_existing_values_and_enforces_uniqueness(): void
    {
        $this->withIsolatedDatabase(function (): void {
            Schema::create('companies', function (Blueprint $table): void {
                $table->id();
                $table->string('invoice_prefix', 20);
            });
            DB::table('companies')->insert([
                ['invoice_prefix' => 'main'],
                ['invoice_prefix' => 'MAIN'],
                ['invoice_prefix' => ' Acme @ '],
                ['invoice_prefix' => ''],
            ]);

            $migration = require database_path('migrations/2026_07_21_000200_add_unique_invoice_prefix_to_companies_table.php');
            $migration->up();

            $this->assertSame(
                ['MAIN', 'MAIN-2', 'ACME', 'INV'],
                DB::table('companies')->orderBy('id')->pluck('invoice_prefix')->all(),
            );

            try {
                DB::table('companies')->insert(['invoice_prefix' => 'MAIN']);
                $this->fail('The invoice prefix unique index did not reject a duplicate.');
            } catch (QueryException) {
                $this->assertSame(4, DB::table('companies')->count());
            }
        });
    }

    protected function withIsolatedDatabase(Closure $callback): void
    {
        $connection = 'company_storage_migration_test';
        $originalConnection = DB::getDefaultConnection();

        Config::set("database.connections.{$connection}", [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge($connection);
        DB::setDefaultConnection($connection);

        try {
            $callback();
        } finally {
            DB::setDefaultConnection($originalConnection);
            DB::purge($connection);
        }
    }
}
