<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\ChatOrderLink;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\LegacyPrivateStoragePath;
use App\Models\Product;
use App\Services\CompanySettingsService;
use App\Services\CompanyStorageMigrator;
use App\Services\CompanyStorageService;
use App\Services\StorageSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompanyStorageMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(StorageSettingsService::class)->save(['enabled' => false]);
        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_dry_run_plans_legacy_public_and_private_files_without_changing_them(): void
    {
        [$company, $product, $message] = $this->legacyRecords('dry-run');

        $stats = app(CompanyStorageMigrator::class)->migrateCompany($company);

        $this->assertSame(3, $stats['planned']);
        $this->assertSame(0, $stats['copied']);
        $this->assertSame(0, $stats['updated']);
        $this->assertSame('products/dry-run.jpg', $product->fresh()->image);
        $this->assertSame(['products/gallery/dry-run.webp'], $product->fresh()->gallery_images);
        $this->assertSame('conversations/dry-run.png', $message->fresh()->media_path);
        Storage::disk('public')->assertMissing($this->publicPath($company, 'products/dry-run.jpg'));
        Storage::disk('local')->assertMissing($this->privatePath($company, 'conversations/dry-run.png'));
        Storage::disk('public')->assertExists('products/dry-run.jpg');
        Storage::disk('local')->assertExists('conversations/dry-run.png');
    }

    public function test_execute_copies_verifies_and_updates_paths_without_deleting_sources(): void
    {
        [$company, $product, $message] = $this->legacyRecords('execute');

        $stats = app(CompanyStorageMigrator::class)->migrateCompany($company, execute: true);

        $product->refresh();
        $message->refresh();

        $this->assertSame(3, $stats['planned']);
        $this->assertSame(3, $stats['copied']);
        $this->assertSame(3, $stats['updated']);
        $this->assertSame($this->publicPath($company, 'products/execute.jpg'), $product->image);
        $this->assertSame(
            [$this->publicPath($company, 'products/gallery/execute.webp')],
            $product->gallery_images,
        );
        $this->assertSame($this->privatePath($company, 'conversations/execute.png'), $message->media_path);
        Storage::disk('public')->assertExists($product->image);
        Storage::disk('public')->assertExists($product->gallery_images[0]);
        Storage::disk('local')->assertExists($message->media_path);
        Storage::disk('public')->assertExists('products/execute.jpg');
        Storage::disk('public')->assertExists('products/gallery/execute.webp');
        Storage::disk('local')->assertExists('conversations/execute.png');

        $secondRun = app(CompanyStorageMigrator::class)->migrateCompany($company, execute: true);

        $this->assertSame(0, $secondRun['planned']);
        $this->assertSame(0, $secondRun['copied']);
        $this->assertSame(3, $secondRun['already_scoped']);
        $this->assertSame(0, $secondRun['errors']);
    }

    public function test_same_legacy_key_is_copied_into_distinct_company_namespaces(): void
    {
        $first = $this->company('First Storage Company', 'first-storage-company');
        $second = $this->company('Second Storage Company', 'second-storage-company');
        Storage::disk('public')->put('products/shared.jpg', 'shared-image');

        $firstProduct = $this->product($first, 'first-shared', 'products/shared.jpg');
        $secondProduct = $this->product($second, 'second-shared', 'products/shared.jpg');
        $migrator = app(CompanyStorageMigrator::class);

        $this->assertSame(1, $migrator->migrateCompany($first, execute: true, scope: 'public')['copied']);
        $this->assertSame(1, $migrator->migrateCompany($second, execute: true, scope: 'public')['copied']);

        $firstPath = $firstProduct->fresh()->image;
        $secondPath = $secondProduct->fresh()->image;

        $this->assertNotSame($firstPath, $secondPath);
        $this->assertStringStartsWith("companies/{$first->storage_key}/public/", $firstPath);
        $this->assertStringStartsWith("companies/{$second->storage_key}/public/", $secondPath);
        Storage::disk('public')->assertExists($firstPath);
        Storage::disk('public')->assertExists($secondPath);
        Storage::disk('public')->assertExists('products/shared.jpg');
    }

    public function test_conflicting_destination_is_never_overwritten_or_written_to_database(): void
    {
        $company = $this->company('Conflict Company', 'conflict-company');
        Storage::disk('public')->put('products/conflict.jpg', 'source-version');
        $product = $this->product($company, 'conflict-product', 'products/conflict.jpg');
        $target = $this->publicPath($company, 'products/conflict.jpg');
        Storage::disk('public')->put($target, 'different-version');

        $stats = app(CompanyStorageMigrator::class)->migrateCompany($company, execute: true, scope: 'public');

        $this->assertSame(1, $stats['conflicts']);
        $this->assertSame(0, $stats['copied']);
        $this->assertSame(0, $stats['updated']);
        $this->assertSame('products/conflict.jpg', $product->fresh()->image);
        $this->assertSame('different-version', Storage::disk('public')->get($target));
        $this->assertSame('source-version', Storage::disk('public')->get('products/conflict.jpg'));
    }

    public function test_scoped_database_path_with_missing_object_is_reported(): void
    {
        $company = $this->company('Missing Company', 'missing-company');
        $path = $this->publicPath($company, 'products/missing.jpg');
        $this->product($company, 'missing-product', $path);

        $stats = app(CompanyStorageMigrator::class)->migrateCompany($company, scope: 'public');

        $this->assertSame(1, $stats['missing']);
        $this->assertSame(0, $stats['already_scoped']);
    }

    public function test_scoped_local_object_is_copied_to_the_active_r2_disk_and_verified(): void
    {
        $settings = app(StorageSettingsService::class);
        $r2 = [
            'enabled' => false,
            'access_key_id' => 'test-access-key',
            'secret_access_key' => 'test-secret-key',
            'public_bucket' => 'test-public-bucket',
            'endpoint' => 'https://example.r2.cloudflarestorage.com',
            'public_url' => 'https://cdn.example.test',
        ];
        $settings->save($r2);
        AppSetting::setValue(StorageSettingsService::PUBLIC_TOPOLOGY_LOCKED, '1');
        $settings->forgetCachedSettings();
        $settings->save([...$r2, 'enabled' => true]);
        Storage::fake('r2_public');

        $company = $this->company('Scoped Local Company', 'scoped-local-company');
        $path = app(CompanyStorageService::class)->publicDirectory($company, 'products').'/scoped.jpg';
        Storage::disk('public')->put($path, 'scoped-local-image');
        $this->product($company, 'scoped-local', $path);

        $stats = app(CompanyStorageMigrator::class)->migrateCompany($company, execute: true, scope: 'public');

        $this->assertSame(1, $stats['planned']);
        $this->assertSame(1, $stats['copied']);
        $this->assertSame(0, $stats['updated']);
        Storage::disk('r2_public')->assertExists($path);
        $this->assertSame('scoped-local-image', Storage::disk('r2_public')->get($path));
        Storage::disk('public')->assertExists($path);
        $this->assertSame(
            Storage::disk('r2_public')->url($path),
            app(CompanyStorageService::class)->publicUrl($path, $company),
        );

        $secondRun = app(CompanyStorageMigrator::class)->migrateCompany($company, execute: true, scope: 'public');
        $this->assertSame(1, $secondRun['already_scoped']);
        $this->assertSame(0, $secondRun['copied']);
    }

    public function test_root_relative_public_reference_is_left_unchanged(): void
    {
        $company = $this->company('Root Relative Company', 'root-relative-company');
        $product = $this->product($company, 'root-relative', '/images/catalog-placeholder.svg');

        $stats = app(CompanyStorageMigrator::class)->migrateCompany($company, execute: true, scope: 'public');

        $this->assertSame(1, $stats['external']);
        $this->assertSame(0, $stats['errors']);
        $this->assertSame('/images/catalog-placeholder.svg', $product->fresh()->image);
    }

    public function test_execute_refuses_unintended_local_destination_without_explicit_override(): void
    {
        [$company, $product] = $this->legacyRecords('local-guard');

        $this->artisan('storage:migrate-company-files', [
            '--company' => [$company->slug],
            '--scope' => 'public',
            '--execute' => true,
        ])
            ->expectsOutputToContain('would target local storage')
            ->assertFailed();

        $this->assertSame('products/local-guard.jpg', $product->fresh()->image);
    }

    public function test_artisan_command_is_dry_run_by_default_and_requires_execute_to_mutate(): void
    {
        [$company, $product] = $this->legacyRecords('command');

        $this->artisan('storage:migrate-company-files', [
            '--company' => [$company->slug],
            '--scope' => 'public',
        ])
            ->expectsOutputToContain('Dry-run only')
            ->assertSuccessful();

        $this->assertSame('products/command.jpg', $product->fresh()->image);

        $this->artisan('storage:migrate-company-files', [
            '--company' => [(string) $company->getKey()],
            '--scope' => 'public',
            '--execute' => true,
            '--allow-local' => true,
        ])
            ->expectsOutputToContain('Executing copy-and-verify migration')
            ->assertSuccessful();

        $this->assertSame(
            $this->publicPath($company, 'products/command.jpg'),
            $product->fresh()->image,
        );
        Storage::disk('public')->assertExists('products/command.jpg');
    }

    public function test_durable_chat_order_and_global_branding_references_are_migrated(): void
    {
        $company = Company::defaultCompany();
        $this->assertNotNull($company);
        Storage::disk('public')->put('products/chat-link.jpg', 'chat-link-image');
        Storage::disk('public')->put('company/global-dark.png', 'global-dark-logo');
        AppSetting::setValue(CompanySettingsService::DARK_LOGO, 'company/global-dark.png');
        $link = ChatOrderLink::withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'prefill' => [
                'items' => [[
                    'product_id' => null,
                    'name' => 'Legacy linked product',
                    'quantity' => 1,
                    'unit_price' => 50,
                    'image' => 'products/chat-link.jpg',
                ]],
                'name' => 'Legacy customer',
            ],
        ]);

        $stats = app(CompanyStorageMigrator::class)->migrateCompany($company, execute: true, scope: 'public');

        $chatImage = data_get($link->fresh()->prefill, 'items.0.image');
        $globalDarkLogo = AppSetting::getValue(CompanySettingsService::DARK_LOGO);

        $this->assertSame(2, $stats['copied']);
        $this->assertStringStartsWith($company->storageRoot().'/public/products/', $chatImage);
        $this->assertStringStartsWith($company->storageRoot().'/public/company/', $globalDarkLogo);
        Storage::disk('public')->assertExists($chatImage);
        Storage::disk('public')->assertExists($globalDarkLogo);
        Storage::disk('public')->assertExists('products/chat-link.jpg');
        Storage::disk('public')->assertExists('company/global-dark.png');
    }

    /** @return array{Company, Product, ConversationMessage} */
    protected function legacyRecords(string $suffix): array
    {
        $company = $this->company(Str::headline($suffix).' Company', $suffix.'-company');
        Storage::disk('public')->put("products/{$suffix}.jpg", 'main-image');
        Storage::disk('public')->put("products/gallery/{$suffix}.webp", 'gallery-image');
        Storage::disk('local')->put("conversations/{$suffix}.png", 'private-image');

        $product = $this->product($company, $suffix, "products/{$suffix}.jpg", ["products/gallery/{$suffix}.webp"]);
        $conversation = Conversation::withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'provider' => 'manual',
            'contact_name' => 'Storage Customer',
        ]);
        LegacyPrivateStoragePath::query()->create([
            'path' => "conversations/{$suffix}.png",
            'company_id' => $company->getKey(),
        ]);
        $message = ConversationMessage::query()->create([
            'conversation_id' => $conversation->getKey(),
            'direction' => 'incoming',
            'type' => 'image',
            'media_path' => "conversations/{$suffix}.png",
            'media_mime' => 'image/png',
            'sent_at' => now(),
        ]);

        return [$company, $product, $message];
    }

    protected function company(string $name, string $slug): Company
    {
        return Company::query()->create([
            'name' => $name,
            'slug' => $slug,
            'invoice_prefix' => Str::upper(Str::substr($slug, 0, 8)),
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
    }

    protected function product(
        Company $company,
        string $suffix,
        string $image,
        array $gallery = [],
    ): Product {
        return Product::withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'name' => Str::headline($suffix).' Product',
            'sku' => 'STORAGE-'.Str::upper($suffix).'-'.Str::random(5),
            'price' => 100,
            'stock' => 1,
            'image' => $image,
            'gallery_images' => $gallery,
        ]);
    }

    protected function publicPath(Company $company, string $relativePath): string
    {
        return app(CompanyStorageService::class)->publicDirectory($company, dirname($relativePath)).'/'.basename($relativePath);
    }

    protected function privatePath(Company $company, string $relativePath): string
    {
        return app(CompanyStorageService::class)->privateDirectory($company, dirname($relativePath)).'/'.basename($relativePath);
    }
}
