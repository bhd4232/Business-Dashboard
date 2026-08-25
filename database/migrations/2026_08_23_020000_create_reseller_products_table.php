<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reseller_products', function (Blueprint $table): void {
            $table->id();
            // The reseller (a Customer row with reseller_status='approved').
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Lets a reseller temporarily hide a picked product from their
            // store without losing the pick (re-enabling later keeps any
            // ordering/notes intact rather than re-adding from scratch).
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['customer_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_products');
    }
};
