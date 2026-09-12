<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v3 gap analysis P1.6 — the investor deed paper signature block names two
 * witnesses (name, address, signature, date). One row per witness, tied to
 * the investment the deed covers.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('investment_witnesses')) {
            return;
        }

        Schema::create('investment_witnesses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investment_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->date('signed_date')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'investment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_witnesses');
    }
};
