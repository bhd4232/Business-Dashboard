<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Company;
use App\Models\Media;
use App\Models\Offer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StorefrontPage;
use App\Models\StorefrontSetting;
use App\Models\StorefrontSlide;
use App\Services\CompanyStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * One-time backfill: register every image that was already uploaded before
 * the Media Hub existed (Products, Categories, Offers, Storefront
 * Pages/Slides/Settings, Company logos), so it shows up in each company's
 * Media Hub instead of only images uploaded from now on.
 *
 * Safe to re-run — an object already registered for its company is skipped
 * (the `media` table's (company_id, path) unique index also guards this at
 * the database level), and a path whose file no longer exists on disk is
 * skipped without erroring.
 */
class BackfillMediaHub extends Command
{
    protected $signature = 'media:backfill';

    protected $description = 'One-time backfill: register every already-uploaded image (Products, Categories, Offers, Storefront Pages/Slides/Settings, Company logos) into each company\'s Media Hub.';

    public function handle(CompanyStorageService $storage): int
    {
        $pairs = $this->collectCompanyPathPairs();

        $existing = Media::withoutGlobalScopes()
            ->get(['company_id', 'path'])
            ->map(fn (Media $media): string => $media->company_id.'|'.$media->path)
            ->flip();

        $companies = Company::withoutGlobalScopes()->get()->keyBy(fn (Company $company): int => (int) $company->getKey());

        $created = 0;
        $missing = 0;

        foreach ($pairs as [$companyId, $path]) {
            $key = $companyId.'|'.$path;

            if (isset($existing[$key])) {
                continue;
            }

            $company = $companies->get($companyId);

            if (! $company) {
                continue;
            }

            $location = $storage->locatePublic($path, $company);

            if ($location === null) {
                $missing++;

                continue;
            }

            Media::query()->create([
                'company_id' => $companyId,
                'path' => $path,
                'original_filename' => basename($path),
                'mime_type' => rescue(fn (): string|false => Storage::disk($location['disk'])->mimeType($location['path']), rescue: false, report: false) ?: null,
                'size' => rescue(fn (): int => Storage::disk($location['disk'])->size($location['path']), rescue: 0, report: false) ?: null,
            ]);

            $existing[$key] = true;
            $created++;
        }

        $this->info("Backfilled {$created} image(s) into the Media Hub.");

        if ($missing > 0) {
            $this->warn("Skipped {$missing} path(s) whose file no longer exists on disk.");
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{0: int, 1: string}>
     */
    private function collectCompanyPathPairs(): array
    {
        $pairs = [];

        $push = function (mixed $companyId, mixed $path) use (&$pairs): void {
            if (blank($companyId) || blank($path) || ! is_string($path)) {
                return;
            }

            $pairs[] = [(int) $companyId, $path];
        };

        Product::withoutGlobalScopes()->get(['id', 'company_id', 'image', 'gallery_images'])
            ->each(function (Product $product) use ($push): void {
                $push($product->company_id, $product->image);

                foreach ((array) $product->gallery_images as $image) {
                    $push($product->company_id, $image);
                }
            });

        ProductVariant::withoutGlobalScopes()->get(['id', 'company_id', 'images'])
            ->each(function (ProductVariant $variant) use ($push): void {
                foreach ((array) $variant->images as $image) {
                    $push($variant->company_id, $image);
                }
            });

        Category::withoutGlobalScopes()->whereNotNull('image')->get(['id', 'company_id', 'image'])
            ->each(fn (Category $category) => $push($category->company_id, $category->image));

        Offer::withoutGlobalScopes()->whereNotNull('cover_image')->get(['id', 'company_id', 'cover_image'])
            ->each(fn (Offer $offer) => $push($offer->company_id, $offer->cover_image));

        StorefrontPage::withoutGlobalScopes()->whereNotNull('cover_image')->get(['id', 'company_id', 'cover_image'])
            ->each(fn (StorefrontPage $page) => $push($page->company_id, $page->cover_image));

        StorefrontSlide::withoutGlobalScopes()->get(['id', 'company_id', 'image', 'image_mobile'])
            ->each(function (StorefrontSlide $slide) use ($push): void {
                $push($slide->company_id, $slide->image);
                $push($slide->company_id, $slide->image_mobile);
            });

        StorefrontSetting::withoutGlobalScopes()->get(['id', 'company_id', 'logo', 'logo_dark'])
            ->each(function (StorefrontSetting $setting) use ($push): void {
                $push($setting->company_id, $setting->logo);
                $push($setting->company_id, $setting->logo_dark);
            });

        Company::withoutGlobalScopes()->whereNotNull('logo')->get(['id', 'logo'])
            ->each(fn (Company $company) => $push($company->getKey(), $company->logo));

        return $pairs;
    }
}
