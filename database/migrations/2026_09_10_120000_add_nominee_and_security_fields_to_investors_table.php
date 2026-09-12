<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Investor register / deed-paper fields the form never captured:
 *
 *  - display_name  — deed clause 3 pseudonym for shared reports (blank = real name).
 *  - date_of_birth — carried on the register sheet and the deed.
 *  - nominee_*      — deed clause 10: every investor names one nominee
 *                    (name, NID/passport, phone, relation, address).
 *  - stamp_number / cheque_number — the ৳100 deed-paper stamp serial(s) and the
 *                    undated security cheque number recorded against the investor.
 *
 * Each column is added only when missing, so a demo/restored database that
 * already carries some of them (e.g. from the bundled investor-module change
 * that also touched other tables) stays a safe no-op instead of erroring on a
 * duplicate column — same contract as the other drift-guarded migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investors', function (Blueprint $table): void {
            if (! Schema::hasColumn('investors', 'display_name')) {
                $table->string('display_name')->nullable()->after('name');
            }
            if (! Schema::hasColumn('investors', 'date_of_birth')) {
                $table->date('date_of_birth')->nullable()->after('guardian_name');
            }
            if (! Schema::hasColumn('investors', 'nominee_name')) {
                $table->string('nominee_name')->nullable()->after('nid_number');
            }
            if (! Schema::hasColumn('investors', 'nominee_nid_or_passport')) {
                $table->string('nominee_nid_or_passport')->nullable()->after('nominee_name');
            }
            if (! Schema::hasColumn('investors', 'nominee_phone')) {
                $table->string('nominee_phone')->nullable()->after('nominee_nid_or_passport');
            }
            if (! Schema::hasColumn('investors', 'nominee_relation')) {
                $table->string('nominee_relation')->nullable()->after('nominee_phone');
            }
            if (! Schema::hasColumn('investors', 'nominee_address')) {
                $table->text('nominee_address')->nullable()->after('nominee_relation');
            }
            if (! Schema::hasColumn('investors', 'stamp_number')) {
                $table->string('stamp_number')->nullable()->after('nominee_address');
            }
            if (! Schema::hasColumn('investors', 'cheque_number')) {
                $table->string('cheque_number')->nullable()->after('stamp_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('investors', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                'display_name',
                'date_of_birth',
                'nominee_name',
                'nominee_nid_or_passport',
                'nominee_phone',
                'nominee_relation',
                'nominee_address',
                'stamp_number',
                'cheque_number',
            ], fn (string $column): bool => Schema::hasColumn('investors', $column)));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
