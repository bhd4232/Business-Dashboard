<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DeployMigrateCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Outside database/migrations on purpose -- a migration dropped there
     * would run for every other test in the suite too, not just this one.
     */
    protected string $tempMigrationPath = 'storage/framework/testing/deploy-migrate-failure';

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path($this->tempMigrationPath));

        parent::tearDown();
    }

    public function test_it_runs_pending_migrations_and_exits_successfully(): void
    {
        // RefreshDatabase has already applied every real migration, so this
        // exercises the genuine "nothing pending" success path end-to-end.
        $exitCode = Artisan::call('deploy:migrate');

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('deployment_errors', 0);
    }

    public function test_it_reports_a_failing_migration_instead_of_failing_the_command(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->writeFailingMigration();

        $exitCode = Artisan::call('deploy:migrate', ['--path' => $this->tempMigrationPath]);

        $this->assertSame(0, $exitCode, 'A broken migration must not prevent the app from starting.');

        $this->assertDatabaseHas('deployment_errors', ['source' => 'migration']);
        $error = \App\Models\DeploymentError::query()->sole();
        $this->assertStringContainsString('simulated migration failure', $error->message);

        $this->assertSame(1, $superAdmin->fresh()->notifications()->count());
    }

    protected function writeFailingMigration(): void
    {
        File::ensureDirectoryExists(base_path($this->tempMigrationPath));

        File::put(
            base_path($this->tempMigrationPath.'/2026_01_01_000000_deploy_migrate_failure_test.php'),
            <<<'PHP'
                <?php

                use Illuminate\Database\Migrations\Migration;

                return new class extends Migration
                {
                    public function up(): void
                    {
                        throw new \RuntimeException('simulated migration failure');
                    }

                    public function down(): void
                    {
                        //
                    }
                };
                PHP,
        );
    }
}
