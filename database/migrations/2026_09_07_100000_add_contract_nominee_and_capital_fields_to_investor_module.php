<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v3 gap analysis P1.5 / P1.6 / P1.7 — the fields the real Tasneem Knitting
 * deed paper, channel-partner agreement and investor register carry but the
 * original module (2026_07_27) never modelled:
 *
 *  - investors: nominee (deed clause 10, register columns), date of birth,
 *    optional pseudonym/display name (deed clause 3), channel-partner flag.
 *  - investor_security_instruments: contract date + reference, the three
 *    stamp serials per investor, cheque branch/account/holder, guarantor
 *    address/relation, and the "won't cash unless breach" attestation.
 *  - investment_projects: the capital the company itself puts in to close a
 *    funding gap (Project-01: ৳62L target, ৳53L from investors, ৳9L company).
 *  - project_settlements: the "rate per lac" investors actually see on their
 *    report, stored alongside the (now corrected) annualized return.
 *
 * Guarded with hasColumn so a restored/demo database whose schema already
 * has the columns but whose migration row is missing stays a safe no-op
 * (same contract as SchemaDriftMigrationTest).
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
            if (! Schema::hasColumn('investors', 'is_channel_partner')) {
                $table->boolean('is_channel_partner')->default(false)->after('channel_partner_id');
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
        });

        Schema::table('investor_security_instruments', function (Blueprint $table): void {
            if (! Schema::hasColumn('investor_security_instruments', 'contract_date')) {
                $table->date('contract_date')->nullable()->after('id');
            }
            if (! Schema::hasColumn('investor_security_instruments', 'contract_reference')) {
                $table->string('contract_reference')->nullable()->after('contract_date');
            }
            if (! Schema::hasColumn('investor_security_instruments', 'stamp_serial_numbers')) {
                $table->json('stamp_serial_numbers')->nullable()->after('contract_reference');
            }
            if (! Schema::hasColumn('investor_security_instruments', 'cheque_branch')) {
                $table->string('cheque_branch')->nullable()->after('cheque_bank_name');
            }
            if (! Schema::hasColumn('investor_security_instruments', 'cheque_account_number')) {
                $table->string('cheque_account_number')->nullable()->after('cheque_branch');
            }
            if (! Schema::hasColumn('investor_security_instruments', 'cheque_account_holder')) {
                $table->string('cheque_account_holder')->nullable()->after('cheque_account_number');
            }
            if (! Schema::hasColumn('investor_security_instruments', 'guarantor_relation')) {
                $table->string('guarantor_relation')->nullable()->after('guarantor_phone');
            }
            if (! Schema::hasColumn('investor_security_instruments', 'guarantor_address')) {
                $table->text('guarantor_address')->nullable()->after('guarantor_relation');
            }
            if (! Schema::hasColumn('investor_security_instruments', 'investor_signed_cheque_terms')) {
                $table->boolean('investor_signed_cheque_terms')->default(false)->after('guarantor_address');
            }
        });

        Schema::table('investment_projects', function (Blueprint $table): void {
            if (! Schema::hasColumn('investment_projects', 'company_contribution_amount')) {
                $table->decimal('company_contribution_amount', 15, 2)->default(0)->after('target_amount');
            }
            if (! Schema::hasColumn('investment_projects', 'company_contribution_note')) {
                $table->string('company_contribution_note')->nullable()->after('company_contribution_amount');
            }
        });

        Schema::table('project_settlements', function (Blueprint $table): void {
            if (! Schema::hasColumn('project_settlements', 'rate_per_lac')) {
                $table->decimal('rate_per_lac', 15, 2)->nullable()->after('annualized_return_percent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('investors', function (Blueprint $table): void {
            $table->dropColumn([
                'display_name', 'date_of_birth', 'is_channel_partner',
                'nominee_name', 'nominee_nid_or_passport', 'nominee_phone',
                'nominee_relation', 'nominee_address',
            ]);
        });

        Schema::table('investor_security_instruments', function (Blueprint $table): void {
            $table->dropColumn([
                'contract_date', 'contract_reference', 'stamp_serial_numbers',
                'cheque_branch', 'cheque_account_number', 'cheque_account_holder',
                'guarantor_relation', 'guarantor_address', 'investor_signed_cheque_terms',
            ]);
        });

        Schema::table('investment_projects', function (Blueprint $table): void {
            $table->dropColumn(['company_contribution_amount', 'company_contribution_note']);
        });

        Schema::table('project_settlements', function (Blueprint $table): void {
            $table->dropColumn('rate_per_lac');
        });
    }
};
