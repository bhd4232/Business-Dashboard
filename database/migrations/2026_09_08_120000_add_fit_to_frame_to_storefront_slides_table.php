<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A demo/restore database may already carry the column even when its
        // migrations ledger was not copied with the schema. Treat that
        // recoverable drift as already applied instead of blocking every
        // later migration with a duplicate-column error.
        if (Schema::hasColumn('storefront_slides', 'fit_to_frame')) {
            return;
        }

        Schema::table('storefront_slides', function (Blueprint $table): void {
            // "Fit to frame": show the whole banner image, never cropped, even
            // when it isn't the exact banner ratio (letterboxed instead of
            // cover-cropped). Off by default so the standard edge-to-edge
            // crop-to-fit behaviour is unchanged. See image-banner.blade.php
            // and the `.storefront-image-banner img.storefront-image-banner-fit`
            // rule in resources/css/app.css.
            $table->boolean('fit_to_frame')->default(false)->after('image_mobile');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('storefront_slides', 'fit_to_frame')) {
            return;
        }

        Schema::table('storefront_slides', function (Blueprint $table): void {
            $table->dropColumn('fit_to_frame');
        });
    }
};
