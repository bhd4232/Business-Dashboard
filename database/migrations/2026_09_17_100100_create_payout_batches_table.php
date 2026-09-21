<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A batch of PayoutItem rows exported together for one bank/MFS run.
     * State machine (enforced in App\Services\Payouts\PayoutBatchService, not
     * the database): draft -> approved -> file_generated -> submitted_to_bank
     * -> completed | partially_completed | failed; draft -> cancelled.
     * 'pending_approval' from the original plan doc was dropped as its own
     * status — a draft batch already means "not yet approved", so a separate
     * pending_approval step added a click without a distinct state.
     *
     * See 10_INVESTOR_RESELLER_AUTO_PAYOUT_PLAN.md — Phase 1 only ever
     * creates method='beftn_bank' batches from Investor/Channel-Partner
     * payouts. The other method values are reserved for the Reseller
     * commission phase.
     */
    public function up(): void
    {
        Schema::create('payout_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number');
            $table->enum('method', ['beftn_bank', 'mfs_bkash', 'mfs_nagad', 'mfs_rocket', 'manual_other'])->default('beftn_bank');
            $table->enum('status', [
                'draft', 'approved', 'file_generated', 'submitted_to_bank',
                'partially_completed', 'completed', 'failed', 'cancelled',
            ])->default('draft');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->unsignedInteger('total_items')->default(0);
            $table->foreignId('generated_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('export_file_path')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->string('bank_batch_reference')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'batch_number']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_batches');
    }
};
