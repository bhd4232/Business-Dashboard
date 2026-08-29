<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deliberately no company_id: a failed deploy/migration step is a
        // platform-level event, not something that happened "inside" any one
        // company's data -- see App\Models\DeploymentError's doc comment.
        Schema::create('deployment_errors', function (Blueprint $table) {
            $table->id();
            $table->string('source'); // e.g. "migration" -- which deploy step failed
            $table->string('message'); // short, human-readable summary
            $table->longText('details'); // full trace -- what the "Copy Log" button copies
            $table->json('context')->nullable(); // e.g. the exact artisan command that was run
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployment_errors');
    }
};
