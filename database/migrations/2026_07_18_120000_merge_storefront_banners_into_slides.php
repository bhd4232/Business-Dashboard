<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hero Slides and the Storefront Settings banner repeaters were two parallel
 * homepage-banner systems (slides = full-width scheduled hero, banners = the
 * fallback side card). This merges them: slides gain the banners' per-image
 * product tagging, existing banner images are converted into slides, and the
 * banner columns are removed from storefront_settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_slides', function (Blueprint $table): void {
            $table->foreignId('product_id')->nullable()->after('cta_url')->constrained('products')->nullOnDelete();
        });

        $now = now();

        DB::table('storefront_settings')
            ->where(fn ($q) => $q->whereNotNull('banner_images')->orWhereNotNull('banner_images_mobile'))
            ->orderBy('id')
            ->each(function (object $setting) use ($now): void {
                // Companies that already use hero slides never rendered the
                // banner fallback, so converting their banners would suddenly
                // surface old images — skip them.
                if (DB::table('storefront_slides')->where('company_id', $setting->company_id)->exists()) {
                    return;
                }

                $desktop = $this->normalizeBanners($setting->banner_images);
                $mobile = $this->normalizeBanners($setting->banner_images_mobile);

                foreach ($desktop as $index => $banner) {
                    DB::table('storefront_slides')->insert([
                        'company_id' => $setting->company_id,
                        'image' => $banner['image'],
                        'image_mobile' => $mobile[$index]['image'] ?? null,
                        'product_id' => $this->existingProductId($banner['product_id'] ?? null),
                        'sort_order' => $index,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                // Mobile-only banners beyond the desktop list still deserve a slide.
                foreach (array_slice($mobile, count($desktop), null, true) as $index => $banner) {
                    DB::table('storefront_slides')->insert([
                        'company_id' => $setting->company_id,
                        'image' => $banner['image'],
                        'image_mobile' => $banner['image'],
                        'product_id' => $this->existingProductId($banner['product_id'] ?? null),
                        'sort_order' => $index,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });

        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->dropColumn(['banner_images', 'banner_images_mobile']);
        });
    }

    public function down(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->json('banner_images')->nullable();
            $table->json('banner_images_mobile')->nullable();
        });

        Schema::table('storefront_slides', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_id');
        });
    }

    /**
     * @return array<int, array{image: string, product_id: int|null}>
     */
    private function normalizeBanners(?string $json): array
    {
        $items = json_decode((string) $json, true);

        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->map(fn ($item) => is_array($item)
                ? ['image' => (string) ($item['image'] ?? ''), 'product_id' => $item['product_id'] ?? null]
                : ['image' => (string) $item, 'product_id' => null])
            ->filter(fn (array $item): bool => filled($item['image']))
            ->values()
            ->all();
    }

    private function existingProductId(mixed $productId): ?int
    {
        if (! $productId) {
            return null;
        }

        return DB::table('products')->where('id', $productId)->exists() ? (int) $productId : null;
    }
};
