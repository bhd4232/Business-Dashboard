<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per company — the company's own disbursing bank account used
     * to fund BEFTN payout batches. See 10_INVESTOR_RESELLER_AUTO_PAYOUT_PLAN.md.
     *
     * `beftn_integration_mode` only ever runs as 'file_export_manual' right
     * now (PayoutBatchService always exports a file for the owner to hand to
     * their bank's corporate portal — no bank has confirmed an API/SFTP
     * connection yet). The 'bank_api' value and `beftn_api_credentials`
     * column exist so a future bank-specific adapter can be wired in without
     * another migration; nothing reads `beftn_api_credentials` yet.
     */
    public function up(): void
    {
        Schema::create('company_payout_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('disbursing_bank_details')->nullable();
            $table->enum('beftn_integration_mode', ['file_export_manual', 'bank_api'])->default('file_export_manual');
            $table->text('beftn_api_credentials')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_payout_settings');
    }
};
