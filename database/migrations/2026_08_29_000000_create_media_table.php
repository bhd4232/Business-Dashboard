<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // Disk-relative path, in the exact same format every image
            // FileUpload field already stores (see App\Support\CompanyMedia)
            // so a Media row's path can be dropped straight into any of
            // them without conversion.
            $table->string('path');
            $table->string('original_filename')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
            // Guards the backfill command (media:backfill) and the normal
            // upload hook against ever double-registering the same object.
            $table->unique(['company_id', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
