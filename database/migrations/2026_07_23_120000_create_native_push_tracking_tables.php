<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('acknowledged_app_version', 64)->nullable();
            $table->string('acknowledged_app_commit', 64)->nullable();
            $table->timestamp('acknowledged_app_built_at')->nullable();
        });

        Schema::create('push_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('token');
            $table->char('token_hash', 64)->unique();
            $table->string('device_id', 191)->nullable()->unique();
            $table->string('platform', 32)->default('android');
            $table->string('app_version', 64)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->string('last_error_code', 120)->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        Schema::create('app_update_push_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('push_device_id')->constrained()->cascadeOnDelete();
            $table->string('deployment_id', 128);
            $table->string('release_version')->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error_code', 120)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['push_device_id', 'deployment_id'],
                'app_update_push_device_deployment_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_update_push_deliveries');
        Schema::dropIfExists('push_devices');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'acknowledged_app_version',
                'acknowledged_app_commit',
                'acknowledged_app_built_at',
            ]);
        });
    }
};
