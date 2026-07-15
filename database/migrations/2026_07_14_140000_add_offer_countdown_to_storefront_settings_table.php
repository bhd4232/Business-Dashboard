<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->string('offer_title')->nullable()->after('trust_strip_payment');
            $table->unsignedTinyInteger('offer_discount_percent')->nullable()->after('offer_title');
            $table->timestamp('offer_ends_at')->nullable()->after('offer_discount_percent');
        });
    }

    public function down(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->dropColumn(['offer_title', 'offer_discount_percent', 'offer_ends_at']);
        });
    }
};
