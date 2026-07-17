<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Desktop and mobile storefront banners become multi-image, product-taggable
 * repeaters instead of a single plain image path. `banner_images` keeps its
 * column (already json) but each entry becomes {image, product_id} instead of
 * a plain path string; `banner_image_mobile` (a single string) is replaced by
 * a new `banner_images_mobile` json column with the same shape, so mobile can
 * also carry multiple banners.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->json('banner_images_mobile')->nullable()->after('banner_image_mobile');
        });

        DB::table('storefront_settings')
            ->whereNotNull('banner_image_mobile')
            ->orderBy('id')
            ->each(function (object $row): void {
                DB::table('storefront_settings')
                    ->where('id', $row->id)
                    ->update([
                        'banner_images_mobile' => json_encode([
                            ['image' => $row->banner_image_mobile, 'product_id' => null],
                        ]),
                    ]);
            });

        DB::table('storefront_settings')
            ->whereNotNull('banner_images')
            ->orderBy('id')
            ->each(function (object $row): void {
                $images = json_decode((string) $row->banner_images, true);

                if (! is_array($images) || $images === []) {
                    return;
                }

                $normalized = collect($images)
                    ->map(fn ($item) => is_array($item) ? $item : ['image' => $item, 'product_id' => null])
                    ->values()
                    ->all();

                DB::table('storefront_settings')
                    ->where('id', $row->id)
                    ->update(['banner_images' => json_encode($normalized)]);
            });
    }

    public function down(): void
    {
        DB::table('storefront_settings')
            ->whereNotNull('banner_images_mobile')
            ->orderBy('id')
            ->each(function (object $row): void {
                $images = json_decode((string) $row->banner_images_mobile, true);
                $first = is_array($images) ? ($images[0]['image'] ?? null) : null;

                DB::table('storefront_settings')
                    ->where('id', $row->id)
                    ->update(['banner_image_mobile' => $first]);
            });

        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->dropColumn('banner_images_mobile');
        });
    }
};
