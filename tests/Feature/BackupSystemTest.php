<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserRole;
use App\Services\AppBackupService;
use App\Services\BackupSettingsService;
use App\Services\DatabaseBackupService;
use App\Services\GoogleDriveBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class BackupSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_backup_service_creates_private_sqlite_backup(): void
    {
        Storage::fake('local');
        $databasePath = storage_path('framework/testing/backup-test.sqlite');
        File::ensureDirectoryExists(dirname($databasePath));
        File::put($databasePath, 'sqlite backup contents');
        Config::set('database.connections.backup_test', [
            'driver' => 'sqlite',
            'database' => $databasePath,
        ]);

        $backup = app(DatabaseBackupService::class)->create('backup_test');

        Storage::disk('local')->assertExists($backup['relative_path']);
        $this->assertStringEndsWith('.sqlite', $backup['name']);
        $this->assertSame('sqlite backup contents', File::get($backup['path']));
    }

    public function test_database_backup_cleanup_keeps_latest_ten_files(): void
    {
        Storage::fake('local');

        foreach (range(1, 12) as $index) {
            $path = DatabaseBackupService::DIRECTORY . "/database-backup-20260613-120{$index}-testing.sqlite";
            Storage::disk('local')->put($path, "backup {$index}");
            touch(Storage::disk('local')->path($path), now()->subMinutes(12 - $index)->timestamp);
        }

        app(DatabaseBackupService::class)->cleanup();

        $this->assertCount(10, app(DatabaseBackupService::class)->all());
    }

    public function test_backup_page_is_limited_to_backup_permission(): void
    {
        $accountant = User::factory()->create([
            'role' => 'accountant',
            'is_active' => true,
        ]);
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->actingAs($accountant)->get('/admin/backups')->assertForbidden();

        $this->actingAs($admin)
            ->get('/admin/backups')
            ->assertOk()
            ->assertSee('Configure')
            ->assertSee('Google Drive Backup Settings')
            ->assertSee('Where to get Service Account JSON and Folder ID')
            ->assertSee('Full App Backups')
            ->assertSee('Database Backups');
    }

    public function test_custom_backup_permission_can_access_backup_page(): void
    {
        UserRole::query()->create([
            'name' => 'Backup Manager',
            'slug' => 'backup_manager',
            'permissions' => ['backups.manage'],
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'role' => 'backup_manager',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/admin/backups')
            ->assertOk()
            ->assertSee('Full App Backups')
            ->assertSee('Database Backups');
    }

    public function test_backup_download_requires_backup_permission(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put(DatabaseBackupService::DIRECTORY . '/database-backup-20260613-120000-testing.sqlite', 'backup');

        $accountant = User::factory()->create([
            'role' => 'accountant',
            'is_active' => true,
        ]);
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->actingAs($accountant)
            ->get('/admin/backups/download/database-backup-20260613-120000-testing.sqlite')
            ->assertForbidden();

        $this->actingAs($admin)
            ->get('/admin/backups/download/database-backup-20260613-120000-testing.sqlite')
            ->assertOk();
    }

    public function test_full_app_backup_creates_zip_with_app_files_and_database_backup(): void
    {
        Storage::fake('local');
        $databasePath = storage_path('framework/testing/app-backup-test.sqlite');
        $fixturePath = storage_path('framework/testing/app-backup-fixture');
        File::ensureDirectoryExists(dirname($databasePath));
        File::ensureDirectoryExists($fixturePath);
        File::put($databasePath, 'sqlite database contents');
        File::put($fixturePath . '/marker.txt', 'app file contents');

        Config::set('database.connections.app_backup_test', [
            'driver' => 'sqlite',
            'database' => $databasePath,
        ]);
        Config::set('backup.app.database_connection', 'app_backup_test');
        Config::set('backup.app.include_paths', ['storage/framework/testing/app-backup-fixture']);
        Config::set('backup.app.exclude_paths', []);

        $backup = app(AppBackupService::class)->create();

        Storage::disk('local')->assertExists($backup['relative_path']);
        $this->assertStringEndsWith('.zip', $backup['name']);

        $zip = new ZipArchive();
        $zip->open($backup['path']);

        $this->assertNotFalse($zip->locateName('storage/framework/testing/app-backup-fixture/marker.txt'));
        $this->assertSame('app file contents', $zip->getFromName('storage/framework/testing/app-backup-fixture/marker.txt'));
        $this->assertNotFalse($zip->locateName('database/' . basename(app(DatabaseBackupService::class)->all()[0]['name'])));

        $zip->close();
    }

    public function test_google_drive_backup_service_uploads_file_with_service_account(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('backups/app/app-backup-test.zip', 'zip contents');

        Config::set('backup.google_drive.folder_id', 'drive-folder-id');

        Http::fake([
            'www.googleapis.com/upload/drive/v3/files*' => Http::response([
                'id' => 'drive-file-id',
                'name' => 'app-backup-test.zip',
            ], 200),
        ]);

        $service = new class extends GoogleDriveBackupService
        {
            protected function accessToken(): string
            {
                return 'test-token';
            }
        };

        $result = $service->upload(
            Storage::disk('local')->path('backups/app/app-backup-test.zip'),
            'app-backup-test.zip',
        );

        $this->assertSame('drive-file-id', $result['id']);
        Http::assertSentCount(1);
    }

    public function test_google_drive_backup_settings_are_saved_securely(): void
    {
        app(BackupSettingsService::class)->saveGoogleDrive([
            'enabled' => true,
            'auto_upload' => true,
            'folder_id' => 'drive-folder-id',
            'service_account_path' => '',
            'service_account_json' => '{"client_email":"backup@example.com","private_key":"secret"}',
        ]);

        $settings = app(BackupSettingsService::class);

        $this->assertTrue($settings->googleDriveEnabled());
        $this->assertTrue($settings->googleDriveAutoUpload());
        $this->assertSame('drive-folder-id', $settings->googleDriveFolderId());
        $this->assertSame('{"client_email":"backup@example.com","private_key":"secret"}', $settings->serviceAccountJson());
        $this->assertDatabaseMissing('app_settings', [
            'key' => BackupSettingsService::DRIVE_SERVICE_ACCOUNT_JSON,
            'value' => '{"client_email":"backup@example.com","private_key":"secret"}',
        ]);
    }

    public function test_google_drive_configuration_can_come_from_admin_settings(): void
    {
        app(BackupSettingsService::class)->saveGoogleDrive([
            'enabled' => true,
            'auto_upload' => false,
            'folder_id' => 'drive-folder-id',
            'service_account_path' => '',
            'service_account_json' => '{"client_email":"backup@example.com","private_key":"secret"}',
        ]);

        $this->assertTrue(app(GoogleDriveBackupService::class)->isConfigured());
    }
}
