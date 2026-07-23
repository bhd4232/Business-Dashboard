<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('acknowledged_app_deployment_id', 128)->nullable()->index();
            $table->timestamp('app_upgrade_acknowledged_at')->nullable();
        });

        Schema::create('app_update_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('deployment_id', 128);
            $table->string('release_version')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'deployment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_update_deliveries');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['acknowledged_app_deployment_id']);
            $table->dropColumn([
                'acknowledged_app_deployment_id',
                'app_upgrade_acknowledged_at',
            ]);
        });
    }
};
