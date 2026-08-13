<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('courier_provider_id')->constrained()->restrictOnDelete();
            $table->foreignId('courier_booking_id')->constrained()->cascadeOnDelete();
            $table->string('provider_reference')->nullable();
            $table->string('reason')->nullable();
            $table->string('status')->default('requested');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['courier_booking_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_returns');
    }
};
