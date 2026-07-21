<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Services\CompanyContext;
use App\Services\StorageSettingsService;
use App\Support\CompanyMedia;
use Filament\Forms\Components\BaseFileUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PublicMediaUploadPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_file_upload_previews_a_legacy_local_image_while_r2_is_enabled(): void
    {
        Storage::fake('public');
        $settings = app(StorageSettingsService::class);
        $r2Settings = [
            'enabled' => false,
            'access_key_id' => 'test-access-key',
            'secret_access_key' => 'test-secret-key',
            'public_bucket' => 'test-public-bucket',
            'endpoint' => 'https://example.r2.cloudflarestorage.com',
            'public_url' => 'https://cdn.example.test',
        ];
        $settings->save($r2Settings);
        AppSetting::setValue(StorageSettingsService::PUBLIC_TOPOLOGY_LOCKED, '1');
        $settings->forgetCachedSettings();
        $settings->save([...$r2Settings, 'enabled' => true]);
        Storage::fake('r2_public');

        $user = User::factory()->create();
        $company = $user->defaultCompany();
        app(CompanyContext::class)->set($company);

        $legacyPath = 'products/legacy-product.jpg';
        $image = UploadedFile::fake()->image('legacy-product.jpg');
        Storage::disk('public')->put($legacyPath, file_get_contents($image->getRealPath()));

        $category = Category::query()->create([
            'name' => 'Legacy Category',
            'slug' => 'legacy-category',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'name' => 'Legacy Product',
            'sku' => 'LEGACY-PRODUCT',
            'category_id' => $category->getKey(),
            'price' => 100,
            'sale_price' => 100,
            'stock' => 1,
            'unit' => 'pcs',
            'image' => $legacyPath,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);

        $this->actingAs($user);

        $page = Livewire::test(EditProduct::class, ['record' => $product->getKey()]);
        $upload = collect($page->instance()->getSchema('form')?->getFlatComponents(withHidden: true))
            ->first(fn ($component): bool => $component instanceof BaseFileUpload && $component->getStatePath() === 'data.image');

        $this->assertInstanceOf(BaseFileUpload::class, $upload);
        $this->assertContains($legacyPath, $upload->getRawState());

        $metadata = collect($upload->getUploadedFiles())->filter()->first();

        $this->assertIsArray($metadata);
        $this->assertSame(Storage::disk('public')->url($legacyPath), $metadata['url']);
        $this->assertSame($metadata['url'], $metadata['openableUrl']);
        $this->assertSame($metadata['url'], $metadata['downloadableUrl']);
        $this->assertSame('legacy-product.jpg', $metadata['name']);
        Storage::disk('r2_public')->assertMissing($legacyPath);
    }

    public function test_public_upload_directory_rejects_an_explicit_inaccessible_company(): void
    {
        $user = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $ownedCompany = $user->defaultCompany();
        $otherCompany = Company::query()->create([
            'name' => 'Other Media Company',
            'slug' => 'other-media-company',
            'invoice_prefix' => 'OMC',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        app(CompanyContext::class)->set($ownedCompany);
        $this->actingAs($user);

        $this->assertStringStartsWith(
            $ownedCompany->storageRoot().'/public/products',
            CompanyMedia::publicDirectory('products', companyId: $ownedCompany->getKey()),
        );

        $this->expectException(ValidationException::class);
        CompanyMedia::publicDirectory('products', companyId: $otherCompany->getKey());
    }
}
