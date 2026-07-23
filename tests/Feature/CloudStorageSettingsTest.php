<?php

namespace Tests\Feature;

use App\Filament\Pages\CloudStorageSettings;
use App\Models\AppSetting;
use App\Models\User;
use App\Services\StorageSettingsService;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Support\Icons\Heroicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CloudStorageSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_can_open_global_cloud_storage_settings(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->actingAs($manager)
            ->get('/admin/settings/cloud-storage-settings')
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->get('/admin/settings/cloud-storage-settings')
            ->assertOk()
            ->assertSee('Global R2 connection')
            ->assertSee('R2 setup guide')
            ->assertSee('Public storefront media')
            ->assertSee('Private business files')
            ->assertSee('companies/{immutable-storage-key}/public/...', escape: false);
    }

    public function test_r2_setup_guide_and_field_help_explain_where_every_value_comes_from(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($superAdmin);

        $guide = TestAction::make('r2SetupGuide')->schemaComponent();
        $component = Livewire::test(CloudStorageSettings::class)
            ->assertActionExists($guide)
            ->mountAction($guide)
            ->assertActionMounted($guide)
            ->assertMountedActionModalSee([
                'Cloudflare R2 Setup Guide',
                'Create 2 Buckets',
                'Object Read & Write',
                'Access Key ID, Secret Access Key, and the S3 endpoint',
                'production custom domain',
                'Public Development URL disabled',
                'Save, Test, Then Enable',
                'Open R2 Overview',
                'Open R2 authentication guide',
            ])
            ->unmountAction();

        foreach ([
            'enabled' => 'enableR2Help',
            'access_key_id' => 'accessKeyIdHelp',
            'secret_access_key' => 'secretAccessKeyHelp',
            'endpoint' => 'endpointHelp',
            'public_bucket' => 'publicBucketHelp',
            'public_url' => 'publicUrlHelp',
            'private_bucket' => 'privateBucketHelp',
            'private_access_confirmed' => 'privateAccessHelp',
        ] as $field => $actionName) {
            $component->assertActionExists(
                TestAction::make($actionName)->schemaComponent($field, 'form'),
                fn (Action $action): bool => $action->isIconButton()
                    && $action->getIcon() === Heroicon::OutlinedInformationCircle
                    && str_starts_with($action->getLabel(), 'Help for ')
                    && filled($action->getTooltip()),
            );
        }

        $accessKeyHelp = TestAction::make('accessKeyIdHelp')->schemaComponent('access_key_id', 'form');
        $component
            ->mountAction($accessKeyHelp)
            ->assertActionMounted($accessKeyHelp)
            ->assertMountedActionModalSee([
                'Use the Access Key ID generated for an R2 S3 API token',
                'Account Details',
                'both the public and private bucket names',
            ])
            ->unmountAction();

        $privateAccessHelp = TestAction::make('privateAccessHelp')->schemaComponent('private_access_confirmed', 'form');
        $component
            ->mountAction($privateAccessHelp)
            ->assertActionMounted($privateAccessHelp)
            ->assertMountedActionModalSee([
                'Public Development URL is disabled',
                'no enabled Custom Domains',
                'authenticated company-authorized routes',
            ]);
    }

    public function test_super_admin_can_stage_separate_public_and_private_buckets_without_reentering_secret(): void
    {
        $settings = app(StorageSettingsService::class);
        $settings->save([
            'enabled' => false,
            'access_key_id' => 'old-access-key',
            'secret_access_key' => 'stored-secret',
            'public_bucket' => 'old-public',
            'private_bucket' => 'old-private',
            'endpoint' => 'https://old-account.r2.cloudflarestorage.com',
            'public_url' => 'https://old-media.example.com',
        ]);

        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($superAdmin);

        Livewire::test(CloudStorageSettings::class)
            ->set('data.enabled', false)
            ->set('data.access_key_id', 'new-access-key')
            ->set('data.secret_access_key', null)
            ->set('data.endpoint', 'https://new-account.r2.cloudflarestorage.com')
            ->set('data.public_bucket', 'zamzam-public')
            ->set('data.public_url', 'https://media.example.com')
            ->set('data.private_bucket', 'zamzam-private')
            ->set('data.private_access_confirmed', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse($settings->enabled());
        $this->assertSame('new-access-key', $settings->accessKeyId());
        $this->assertSame('zamzam-public', $settings->publicBucket());
        $this->assertSame('zamzam-private', $settings->privateBucket());
        $this->assertSame('https://media.example.com', $settings->publicUrl());
        $this->assertSame(
            'stored-secret',
            AppSetting::getValue(StorageSettingsService::SECRET_ACCESS_KEY),
        );
        $this->assertSame('zamzam-public', config('filesystems.disks.r2_public.bucket'));
        $this->assertSame('zamzam-private', config('filesystems.disks.r2_private.bucket'));
        $this->assertArrayNotHasKey('visibility', config('filesystems.disks.r2_public'));
        $this->assertArrayNotHasKey('visibility', config('filesystems.disks.r2_private'));
    }

    public function test_public_connection_test_stages_unsaved_form_state_without_enabling_r2(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($superAdmin);

        $testDisk = Storage::fake('r2-public-draft-test');
        Storage::partialMock()
            ->shouldReceive('build')
            ->once()
            ->with(\Mockery::on(fn (array $config): bool => $config['key'] === 'draft-access-key'
                && $config['secret'] === 'draft-secret-key'
                && $config['bucket'] === 'draft-public-bucket'
                && $config['endpoint'] === 'https://draft-account.r2.cloudflarestorage.com'
                && $config['url'] === 'https://draft-media.example.test'))
            ->andReturn($testDisk);
        Http::fake([
            'https://draft-media.example.test/*' => Http::response('ok'),
        ]);

        Livewire::test(CloudStorageSettings::class)
            ->set('data.enabled', true)
            ->set('data.access_key_id', 'draft-access-key')
            ->set('data.secret_access_key', 'draft-secret-key')
            ->set('data.endpoint', 'https://draft-account.r2.cloudflarestorage.com')
            ->set('data.public_bucket', 'draft-public-bucket')
            ->set('data.public_url', 'https://draft-media.example.test')
            ->set('data.private_bucket', null)
            ->set('data.private_access_confirmed', false)
            ->call('testPublicConnection')
            ->assertHasNoErrors()
            ->assertNotified('Public bucket connection successful');

        $settings = app(StorageSettingsService::class);
        $this->assertFalse($settings->enabled());
        $this->assertSame('draft-access-key', $settings->accessKeyId());
        $this->assertSame('draft-public-bucket', $settings->publicBucket());
        $this->assertSame('https://draft-account.r2.cloudflarestorage.com', $settings->endpoint());
        $this->assertSame('https://draft-media.example.test', $settings->publicUrl());
        $this->assertTrue($settings->publicTopologyLocked());
        $this->assertSame([], $testDisk->allFiles());
        Http::assertSent(fn ($request): bool => str_starts_with(
            $request->url(),
            'https://draft-media.example.test/_health/zamzam-r2-public-',
        ));
    }

    public function test_public_connection_test_preserves_the_encrypted_secret_when_the_form_field_is_blank(): void
    {
        $settings = app(StorageSettingsService::class);
        $settings->save([
            'enabled' => false,
            'access_key_id' => 'stored-access-key',
            'secret_access_key' => 'stored-secret-key',
            'public_bucket' => 'stored-public-bucket',
            'endpoint' => 'https://stored-account.r2.cloudflarestorage.com',
            'public_url' => 'https://stored-media.example.test',
        ]);

        $storedSecret = AppSetting::query()
            ->where('key', StorageSettingsService::SECRET_ACCESS_KEY)
            ->firstOrFail();
        $encryptedValue = $storedSecret->value;

        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($superAdmin);

        $testDisk = Storage::fake('r2-public-stored-secret-test');
        Storage::partialMock()
            ->shouldReceive('build')
            ->once()
            ->with(\Mockery::on(fn (array $config): bool => $config['secret'] === 'stored-secret-key'))
            ->andReturn($testDisk);
        Http::fake([
            'https://stored-media.example.test/*' => Http::response('ok'),
        ]);

        Livewire::test(CloudStorageSettings::class)
            ->set('data.secret_access_key', null)
            ->call('testPublicConnection')
            ->assertHasNoErrors()
            ->assertNotified('Public bucket connection successful');

        $storedSecret->refresh();

        $this->assertTrue($storedSecret->is_encrypted);
        $this->assertSame($encryptedValue, $storedSecret->value);
        $this->assertSame(
            'stored-secret-key',
            AppSetting::getValue(StorageSettingsService::SECRET_ACCESS_KEY),
        );
        $this->assertFalse($settings->enabled());
    }

    public function test_public_connection_test_requires_bucket_and_public_url_before_probing(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($superAdmin);

        Storage::partialMock()->shouldNotReceive('build');
        Http::fake();

        Livewire::test(CloudStorageSettings::class)
            ->set('data.enabled', false)
            ->set('data.access_key_id', 'draft-access-key')
            ->set('data.secret_access_key', 'draft-secret-key')
            ->set('data.endpoint', 'https://draft-account.r2.cloudflarestorage.com')
            ->set('data.public_bucket', null)
            ->set('data.public_url', null)
            ->call('testPublicConnection')
            ->assertHasErrors([
                'data.public_bucket',
                'data.public_url',
            ]);

        Http::assertNothingSent();
        $this->assertFalse(app(StorageSettingsService::class)->enabled());
    }

    public function test_private_connection_test_stages_unsaved_configuration_with_public_access_attestation(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($superAdmin);

        $testDisk = Storage::fake('r2-private-draft-test');
        Storage::partialMock()
            ->shouldReceive('build')
            ->once()
            ->with(\Mockery::on(fn (array $config): bool => $config['key'] === 'draft-access-key'
                && $config['secret'] === 'draft-secret-key'
                && $config['bucket'] === 'draft-private-bucket'
                && $config['endpoint'] === 'https://draft-account.r2.cloudflarestorage.com'
                && ! array_key_exists('url', $config)))
            ->andReturn($testDisk);
        Http::fake();

        Livewire::test(CloudStorageSettings::class)
            ->set('data.enabled', false)
            ->set('data.access_key_id', 'draft-access-key')
            ->set('data.secret_access_key', 'draft-secret-key')
            ->set('data.endpoint', 'https://draft-account.r2.cloudflarestorage.com')
            ->set('data.public_bucket', null)
            ->set('data.public_url', null)
            ->set('data.private_bucket', 'draft-private-bucket')
            ->set('data.private_access_confirmed', true)
            ->call('testPrivateConnection')
            ->assertHasNoErrors()
            ->assertNotified('Private bucket connection successful');

        $settings = app(StorageSettingsService::class);
        $this->assertFalse($settings->enabled());
        $this->assertSame('draft-access-key', $settings->accessKeyId());
        $this->assertSame('draft-private-bucket', $settings->privateBucket());
        $this->assertTrue($settings->privateAccessConfirmed());
        $this->assertTrue($settings->privateTopologyLocked());
        $this->assertSame([], $testDisk->allFiles());
        Http::assertNothingSent();
    }

    public function test_enabled_public_storage_requires_complete_public_configuration(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($superAdmin);

        Livewire::test(CloudStorageSettings::class)
            ->set('data.enabled', true)
            ->set('data.access_key_id', 'access-key')
            ->set('data.secret_access_key', 'secret-key')
            ->set('data.endpoint', 'https://account.r2.cloudflarestorage.com')
            ->set('data.public_bucket', null)
            ->set('data.public_url', null)
            ->call('save')
            ->assertHasErrors(['data.public_bucket', 'data.public_url']);

        $this->assertFalse(app(StorageSettingsService::class)->enabled());
    }

    public function test_form_rejects_using_the_public_bucket_for_private_objects(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($superAdmin);

        Livewire::test(CloudStorageSettings::class)
            ->set('data.enabled', true)
            ->set('data.access_key_id', 'access-key')
            ->set('data.secret_access_key', 'secret-key')
            ->set('data.endpoint', 'https://account.r2.cloudflarestorage.com')
            ->set('data.public_bucket', 'shared-bucket')
            ->set('data.public_url', 'https://media.example.com')
            ->set('data.private_bucket', 'shared-bucket')
            ->set('data.private_access_confirmed', true)
            ->call('save')
            ->assertHasErrors(['data.public_bucket', 'data.private_bucket']);

        $this->assertFalse(app(StorageSettingsService::class)->enabled());
    }
}
