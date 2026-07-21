<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Company;
use App\Models\LegacyPrivateStoragePath;
use App\Services\CompanyContext;
use App\Services\CompanyStorageService;
use App\Services\StorageSettingsService;
use App\Support\StorageUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class CompanyStorageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_storage_keys_are_generated_unique_and_immutable(): void
    {
        $first = $this->company('First Company', 'first-company');
        $second = $this->company('Second Company', 'second-company');

        $this->assertTrue(Str::isUuid($first->storage_key));
        $this->assertTrue(Str::isUuid($second->storage_key));
        $this->assertNotSame($first->storage_key, $second->storage_key);
        $this->assertSame('companies/'.$first->storage_key, $first->storageRoot());
        $this->assertDatabaseMissing('companies', ['storage_key' => null]);

        $first->forceFill(['storage_key' => (string) Str::uuid()]);

        $this->expectException(LogicException::class);
        $first->save();
    }

    public function test_storage_settings_are_cached_encrypted_and_only_configure_named_r2_disks(): void
    {
        $settings = app(StorageSettingsService::class);

        $this->assertSame($settings, app(StorageSettingsService::class));
        $this->assertSame(app(CompanyStorageService::class), app(CompanyStorageService::class));

        $this->enableR2($settings);

        $secret = AppSetting::query()->where('key', StorageSettingsService::SECRET_ACCESS_KEY)->firstOrFail();

        $this->assertTrue($secret->is_encrypted);
        $this->assertNotSame('secret-key', $secret->value);
        $this->assertTrue($settings->hasSecretAccessKey());
        $this->assertTrue($settings->isPublicConfigured());
        $this->assertTrue($settings->isPrivateConfigured());
        $this->assertTrue($settings->publicTopologyLocked());
        $this->assertTrue($settings->privateTopologyLocked());
        $this->assertSame('public-bucket', $settings->bucket());
        $this->assertSame('local', config('filesystems.disks.public.driver'));
        $this->assertSame('public-bucket', config('filesystems.disks.r2_public.bucket'));
        $this->assertSame('private-bucket', config('filesystems.disks.r2_private.bucket'));
        $this->assertArrayNotHasKey('visibility', $settings->publicDiskConfig());
        $this->assertArrayNotHasKey('visibility', $settings->privateDiskConfig());
        $this->assertArrayNotHasKey('visibility', config('filesystems.disks.r2_public'));
        $this->assertArrayNotHasKey('visibility', config('filesystems.disks.r2_private'));

        $settings->forgetCachedSettings();
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(CompanyStorageService::class)->publicDiskName();
        $initialQueryCount = count(DB::getQueryLog());

        app(CompanyStorageService::class)->publicDiskName();
        app(CompanyStorageService::class)->publicDiskName();

        $this->assertGreaterThan(0, $initialQueryCount);
        $this->assertCount($initialQueryCount, DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_public_and_private_buckets_must_be_distinct_and_active_topology_is_locked(): void
    {
        $settings = app(StorageSettingsService::class);

        try {
            $settings->save([
                ...$this->r2Settings(),
                'private_bucket' => 'public-bucket',
            ]);
            $this->fail('A public bucket must never also be used for private objects.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('private_bucket', $exception->errors());
            $this->assertFalse($settings->enabled());
        }

        try {
            $settings->save($this->r2Settings());
            $this->fail('R2 must not activate before both configured storage scopes are verified.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('enabled', $exception->errors());
            $this->assertArrayHasKey('private_bucket', $exception->errors());
            $this->assertFalse($settings->enabled());
        }

        $settings->save([
            ...$this->r2Settings(),
            'enabled' => false,
            'private_access_confirmed' => false,
        ]);
        $this->assertFalse($settings->isPrivateConfigured());

        $this->enableR2($settings);

        try {
            $settings->save([
                ...$this->r2Settings(),
                'public_bucket' => 'replacement-public-bucket',
            ]);
            $this->fail('An active bucket topology must not be switched in place.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('public_bucket', $exception->errors());
            $this->assertSame('public-bucket', $settings->publicBucket());
        }
    }

    public function test_public_connection_check_also_verifies_the_custom_domain(): void
    {
        $settings = app(StorageSettingsService::class);
        $settings->save([...$this->r2Settings(), 'enabled' => false]);
        $testDisk = Storage::fake('r2-connection-test');

        Storage::shouldReceive('build')->once()->andReturn($testDisk);
        Http::fake(fn () => Http::response('ok'));

        $result = $settings->testPublicConnection();

        $this->assertTrue($result['ok']);
        $this->assertTrue($settings->publicTopologyLocked());
        $this->assertSame([], $testDisk->allFiles());
        Http::assertSent(fn ($request): bool => str_starts_with(
            $request->url(),
            'https://cdn.example.com/_health/zamzam-r2-public-',
        ));
    }

    public function test_private_connection_requires_public_access_attestation_before_it_can_be_verified(): void
    {
        $settings = app(StorageSettingsService::class);
        $settings->save([
            ...$this->r2Settings(),
            'enabled' => false,
            'private_access_confirmed' => false,
        ]);

        $unconfirmed = $settings->testPrivateConnection();

        $this->assertFalse($unconfirmed['ok']);
        $this->assertFalse($settings->isPrivateConfigured());

        $settings->save([
            ...$this->r2Settings(),
            'enabled' => false,
            'private_access_confirmed' => true,
        ]);
        $testDisk = Storage::fake('r2-private-connection-test');
        Storage::shouldReceive('build')->once()->andReturn($testDisk);

        $confirmed = $settings->testPrivateConnection();

        $this->assertTrue($confirmed['ok']);
        $this->assertTrue($settings->privateTopologyLocked());
        $this->assertSame([], $testDisk->allFiles());
    }

    public function test_local_puts_are_company_scoped_and_public_url_uses_company_context(): void
    {
        $settings = app(StorageSettingsService::class);
        $settings->save(['enabled' => false]);

        Storage::fake('public');
        Storage::fake('local');

        $company = $this->company('Local Company', 'local-company');
        app(CompanyContext::class)->set($company);
        $storage = app(CompanyStorageService::class);

        $publicPath = $storage->putPublic($company, 'products/gallery', 'photo.jpg', 'public-data');
        $privatePath = $storage->putPrivate($company, 'documents', 'invoice.pdf', 'private-data');

        $this->assertSame('public', $storage->publicDiskName());
        $this->assertSame('local', $storage->privateDiskName());
        $this->assertSame(
            "companies/{$company->storage_key}/public/products/gallery/photo.jpg",
            $publicPath,
        );
        $this->assertSame(
            "companies/{$company->storage_key}/private/documents/invoice.pdf",
            $privatePath,
        );
        Storage::disk('public')->assertExists($publicPath);
        Storage::disk('local')->assertExists($privatePath);
        $this->assertSame('public-data', $storage->readPublic($publicPath, $company));
        $this->assertSame('private-data', $storage->readPrivate($privatePath, $company));
        $this->assertSame(Storage::disk('public')->url($publicPath), StorageUrl::for($publicPath));
    }

    public function test_enabled_r2_uses_separate_public_and_private_disks_without_rebinding_public(): void
    {
        $this->enableR2();

        Storage::fake('public');
        Storage::fake('local');
        Storage::fake('r2_public');
        Storage::fake('r2_private');

        $company = $this->company('Cloud Company', 'cloud-company');
        $storage = app(CompanyStorageService::class);

        $publicPath = $storage->putPublic($company, 'catalog', 'item.webp', 'public-r2');
        $privatePath = $storage->putPrivate($company, 'exports', 'report.csv', 'private-r2');

        $this->assertSame('r2_public', $storage->publicDiskName());
        $this->assertSame('r2_private', $storage->privateDiskName());
        Storage::disk('r2_public')->assertExists($publicPath);
        Storage::disk('r2_private')->assertExists($privatePath);
        Storage::disk('public')->assertMissing($publicPath);
        Storage::disk('local')->assertMissing($privatePath);
        $this->assertSame('local', config('filesystems.disks.public.driver'));
    }

    public function test_legacy_locators_use_exact_unscoped_local_source_and_copy_without_deleting_it(): void
    {
        $this->enableR2();

        Storage::fake('public');
        Storage::fake('local');
        Storage::fake('r2_public');
        Storage::fake('r2_private');

        $company = $this->company('Migration Company', 'migration-company');
        $storage = app(CompanyStorageService::class);
        Storage::disk('public')->put('products/legacy.jpg', 'legacy-public');
        Storage::disk('local')->put('documents/legacy.pdf', 'legacy-private');
        LegacyPrivateStoragePath::query()->create([
            'path' => 'documents/legacy.pdf',
            'company_id' => $company->getKey(),
        ]);

        $publicSource = $storage->locateLegacyPublic('products/legacy.jpg');
        $privateSource = $storage->locateLegacyPrivate('documents/legacy.pdf', $company);

        $this->assertSame(['disk' => 'public', 'path' => 'products/legacy.jpg'], $publicSource);
        $this->assertSame(['disk' => 'local', 'path' => 'documents/legacy.pdf'], $privateSource);
        $this->assertSame('legacy-public', $storage->readLegacyPublic('products/legacy.jpg'));
        $this->assertSame('legacy-private', $storage->readLegacyPrivate('documents/legacy.pdf', $company));

        $publicTarget = $storage->copyPublicToActive($company, 'products/legacy.jpg', 'products');
        $privateTarget = $storage->copyPrivateToActive($company, 'documents/legacy.pdf', 'documents');

        Storage::disk('r2_public')->assertExists($publicTarget);
        Storage::disk('r2_private')->assertExists($privateTarget);
        Storage::disk('public')->assertExists('products/legacy.jpg');
        Storage::disk('local')->assertExists('documents/legacy.pdf');
        $this->assertSame('legacy-public', Storage::disk('r2_public')->get($publicTarget));
        $this->assertSame('legacy-private', Storage::disk('r2_private')->get($privateTarget));
    }

    public function test_disabling_r2_moves_new_writes_local_but_existing_scoped_r2_objects_remain_readable(): void
    {
        $settings = app(StorageSettingsService::class);
        $this->enableR2($settings);

        Storage::fake('public');
        Storage::fake('local');
        Storage::fake('r2_public');
        Storage::fake('r2_private');

        $company = $this->company('Disable R2 Company', 'disable-r2-company');
        $storage = app(CompanyStorageService::class);
        $cloudPublicPath = $storage->putPublic($company, 'products', 'cloud.jpg', 'cloud-public');
        $cloudPrivatePath = $storage->putPrivate($company, 'documents', 'cloud.pdf', 'cloud-private');

        AppSetting::setValue(StorageSettingsService::ENABLED, '0');
        $settings->forgetCachedSettings();
        $storage->forgetLocations();

        $this->assertSame('public', $storage->publicDiskName());
        $this->assertSame('local', $storage->privateDiskName());
        $this->assertSame(
            ['disk' => 'r2_public', 'path' => $cloudPublicPath],
            $storage->locatePublic($cloudPublicPath, $company),
        );
        $this->assertSame(
            ['disk' => 'r2_private', 'path' => $cloudPrivatePath],
            $storage->locatePrivate($cloudPrivatePath, $company),
        );
        $this->assertSame('cloud-public', $storage->readPublic($cloudPublicPath, $company));
        $this->assertSame('cloud-private', $storage->readPrivate($cloudPrivatePath, $company));

        $localPublicPath = $storage->putPublic($company, 'products', 'local.jpg', 'local-public');
        $localPrivatePath = $storage->putPrivate($company, 'documents', 'local.pdf', 'local-private');

        Storage::disk('public')->assertExists($localPublicPath);
        Storage::disk('local')->assertExists($localPrivatePath);
    }

    public function test_copy_retry_reads_legacy_source_but_refuses_to_overwrite_target_by_default(): void
    {
        $this->enableR2();

        Storage::fake('public');
        Storage::fake('r2_public');

        $company = $this->company('Retry Company', 'retry-company');
        $storage = app(CompanyStorageService::class);
        $sourcePath = 'products/retry.jpg';
        $targetPath = $storage->publicDirectory($company, 'products').'/retry.jpg';

        Storage::disk('public')->put($sourcePath, 'source-version');
        Storage::disk('r2_public')->put($targetPath, 'partial-version');

        try {
            $storage->copyPublicToActive($company, $sourcePath, 'products');
            $this->fail('The existing migration target should not be overwritten by default.');
        } catch (RuntimeException) {
            $this->assertSame('source-version', $storage->readLegacyPublic($sourcePath));
            $this->assertSame('partial-version', Storage::disk('r2_public')->get($targetPath));
        }

        $storage->copyPublicToActive($company, $sourcePath, 'products', overwrite: true);
        $this->assertSame('source-version', Storage::disk('r2_public')->get($targetPath));
    }

    public function test_legacy_private_paths_require_the_registered_company_and_conflicts_fail_closed(): void
    {
        app(StorageSettingsService::class)->save(['enabled' => false]);
        Storage::fake('local');

        $first = $this->company('Legacy Owner', 'legacy-owner');
        $second = $this->company('Legacy Stranger', 'legacy-stranger');
        $storage = app(CompanyStorageService::class);
        Storage::disk('local')->put('documents/owned.pdf', 'owned-private-file');
        LegacyPrivateStoragePath::query()->create([
            'path' => 'documents/owned.pdf',
            'company_id' => $first->getKey(),
        ]);
        LegacyPrivateStoragePath::query()->create([
            'path' => 'documents/conflicted.pdf',
            'company_id' => null,
            'is_conflicted' => true,
        ]);

        $this->assertSame('owned-private-file', $storage->readLegacyPrivate('documents/owned.pdf', $first));

        foreach ([
            ['documents/owned.pdf', $second],
            ['documents/conflicted.pdf', $first],
        ] as [$path, $company]) {
            try {
                $storage->locateLegacyPrivate($path, $company);
                $this->fail("Legacy private path [{$path}] should fail ownership validation.");
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_divergent_local_and_r2_legacy_objects_are_rejected_as_ambiguous(): void
    {
        $this->enableR2();
        Storage::fake('public');
        Storage::fake('r2_public');
        Storage::disk('public')->put('products/ambiguous.jpg', 'local-version');
        Storage::disk('r2_public')->put('products/ambiguous.jpg', 'cloud-version');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('different contents');

        app(CompanyStorageService::class)->locateLegacyPublic('products/ambiguous.jpg');
    }

    public function test_paths_reject_traversal_cross_company_access_and_scoped_legacy_lookup(): void
    {
        Storage::fake('public');

        $first = $this->company('First Tenant', 'first-tenant');
        $second = $this->company('Second Tenant', 'second-tenant');
        $storage = app(CompanyStorageService::class);
        $secondPath = $storage->publicDirectory($second, 'products').'/item.jpg';

        try {
            $storage->publicUrl($secondPath, $first);
            $this->fail('Cross-company public storage access should be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        foreach (['../products', 'products/../secret', '/products'] as $unsafeArea) {
            try {
                $storage->publicDirectory($first, $unsafeArea);
                $this->fail("Unsafe area [{$unsafeArea}] should be rejected.");
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }

        $this->expectException(InvalidArgumentException::class);
        $storage->locateLegacyPublic($storage->publicDirectory($first, 'products').'/item.jpg');
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

    protected function enableR2(?StorageSettingsService $settings = null): void
    {
        $settings ??= app(StorageSettingsService::class);
        $settings->save([...$this->r2Settings(), 'enabled' => false]);
        AppSetting::setValue(StorageSettingsService::PUBLIC_TOPOLOGY_LOCKED, '1');
        AppSetting::setValue(StorageSettingsService::PRIVATE_TOPOLOGY_LOCKED, '1');
        $settings->forgetCachedSettings();
        $settings->save($this->r2Settings());
    }

    /** @return array<string, mixed> */
    protected function r2Settings(): array
    {
        return [
            'enabled' => true,
            'access_key_id' => 'access-key',
            'secret_access_key' => 'secret-key',
            'public_bucket' => 'public-bucket',
            'private_bucket' => 'private-bucket',
            'private_access_confirmed' => true,
            'endpoint' => 'https://example.r2.cloudflarestorage.com',
            'public_url' => 'https://cdn.example.com',
        ];
    }
}
