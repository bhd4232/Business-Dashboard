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
