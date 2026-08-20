<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deliberately no company_id: these are native Android crashes, which
        // can happen before login (no authenticated user, no company context
        // at all) -- see App\Models\MobileCrashReport's doc comment.
        Schema::create('mobile_crash_reports', function (Blueprint $table) {
            $table->id();
            $table->string('exception_class');
            $table->text('message')->nullable();
            $table->text('stack_trace');
            $table->string('app_version_name')->nullable();
            $table->unsignedInteger('app_version_code')->nullable();
            $table->string('os_version')->nullable();
            $table->string('device_manufacturer')->nullable();
            $table->string('device_model')->nullable();
            $table->timestamp('occurred_at')->nullable(); // device clock, informational only
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_crash_reports');
    }
};
