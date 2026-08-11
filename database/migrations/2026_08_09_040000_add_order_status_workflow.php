<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stage', 32)->nullable();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('from_delivery_status', 32)->nullable();
            $table->string('to_delivery_status', 32)->nullable();
            $table->string('source', 32)->default('direct');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'order_id', 'created_at'], 'order_status_history_index');
            $table->index(['company_id', 'to_status', 'created_at'], 'order_status_target_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_transitions');
    }
};
