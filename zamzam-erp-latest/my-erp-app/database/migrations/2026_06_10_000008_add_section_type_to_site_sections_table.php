<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_sections', function (Blueprint $table) {
            $table->string('section_type')->default('service_block')->after('key');
        });
    }

    public function down(): void
    {
        Schema::table('site_sections', function (Blueprint $table) {
            $table->dropColumn('section_type');
        });
    }
};
