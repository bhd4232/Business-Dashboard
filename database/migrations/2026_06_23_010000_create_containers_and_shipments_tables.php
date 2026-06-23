<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('containers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('container_number');
            $table->string('shipping_line')->nullable();
            $table->string('origin')->nullable();
            $table->string('destination')->nullable();
            $table->string('status')->default('planned');
            $table->date('estimated_departure')->nullable();
            $table->date('actual_departure')->nullable();
            $table->date('estimated_arrival')->nullable();
            $table->date('actual_arrival')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'container_number']);
            $table->index(['company_id', 'status', 'estimated_arrival']);
        });

        Schema::create('shipments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('container_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tracking_number');
            $table->string('carrier')->nullable();
            $table->string('transport_mode')->default('sea');
            $table->string('status')->default('planned');
            $table->date('shipped_at')->nullable();
            $table->date('estimated_arrival')->nullable();
            $table->date('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'tracking_number']);
            $table->index(['company_id', 'status', 'estimated_arrival']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('containers');
    }
};
