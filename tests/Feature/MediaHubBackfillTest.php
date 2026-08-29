<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Media;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * `php artisan media:backfill` — registers images that were already
 * uploaded before the Media Hub existed, so they show up there too (not
 * just new uploads going forward). See App\Console\Commands\BackfillMediaHub.
 */
class MediaHubBackfillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_backfill_registers_existing_product_and_category_images_scoped_per_company(): void
    {
        $companyA = $this->createCompany('backfill-a.example.test');
        $companyB = $this->createCompany('backfill-b.example.test');

        $productImage = $this->putFakeImage($companyA, 'products', 'featured.webp');
        $galleryImage = $this->putFakeImage($companyA, 'products/gallery', 'gallery-1.webp');
        $categoryImage = $this->putFakeImage($companyB, 'categories', 'category.webp');

        Product::query()->create([
            'company_id' => $companyA->getKey(),
            'name' => 'Solar Fan',
            'sku' => 'BACKFILL-FAN-1',
            'price' => 1500,
            'cost_price' => 900,
            'stock' => 5,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
            'image' => $productImage,
            'gallery_images' => [$galleryImage],
        ]);

        Category::query()->create([
            'company_id' => $companyB->getKey(),
            'name' => 'Kitchen',
            'slug' => 'kitchen',
            'is_active' => true,
            'image' => $categoryImage,
        ]);

        $this->artisan('media:backfill')->assertSuccessful();

        $mediaA = Media::withoutGlobalScopes()->where('company_id', $companyA->getKey())->pluck('path')->all();
        $mediaB = Media::withoutGlobalScopes()->where('company_id', $companyB->getKey())->pluck('path')->all();

        $this->assertEqualsCanonicalizing([$productImage, $galleryImage], $mediaA);
        $this->assertEqualsCanonicalizing([$categoryImage], $mediaB);
    }

    public function test_backfill_is_safe_to_run_twice(): void
    {
        $company = $this->createCompany('backfill-idempotent.example.test');
        $image = $this->putFakeImage($company, 'categories', 'idempotent.webp');

        Category::query()->create([
            'company_id' => $company->getKey(),
            'name' => 'Bathroom',
            'slug' => 'bathroom',
            'is_active' => true,
            'image' => $image,
        ]);

        $this->artisan('media:backfill')->assertSuccessful();
        $this->artisan('media:backfill')->assertSuccessful();

        $this->assertSame(1, Media::withoutGlobalScopes()->where('company_id', $company->getKey())->count());
    }

    public function test_backfill_skips_a_path_whose_file_no_longer_exists(): void
    {
        $company = $this->createCompany('backfill-missing.example.test');

        Category::query()->create([
            'company_id' => $company->getKey(),
            'name' => 'Deleted Photo',
            'slug' => 'deleted-photo',
            'is_active' => true,
            'image' => $company->storageRoot().'/public/categories/gone.webp',
        ]);

        $this->artisan('media:backfill')->assertSuccessful();

        $this->assertSame(0, Media::withoutGlobalScopes()->where('company_id', $company->getKey())->count());
    }

    private function putFakeImage(Company $company, string $area, string $filename): string
    {
        $path = $company->storageRoot().'/public/'.$area.'/'.$filename;

        Storage::disk('public')->put($path, 'fake-image-bytes');

        return $path;
    }

    private function createCompany(string $domain): Company
    {
        return Company::query()->create([
            'name' => 'Backfill Store',
            'slug' => str($domain)->before('.')->slug()->value(),
            'domain' => $domain,
            'domain_verified' => true,
            'invoice_prefix' => 'BF'.random_int(100, 999),
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
    }
}
