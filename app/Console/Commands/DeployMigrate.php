<?php

namespace App\Console\Commands;

use App\Services\DeploymentErrorReporter;
use Illuminate\Console\Command;
use Throwable;

/**
 * The container's start command runs this instead of `php artisan migrate`
 * directly (see nixpacks.toml) so a broken migration can never take the
 * whole app down: this command always exits successfully, and reports any
 * failure to super admins via DeploymentErrorReporter instead of letting it
 * fail silently. That gap is exactly what caused two real production 500s
 * (see CHANGELOG) -- the deploy pipeline has no automatic `artisan migrate`
 * step, so a migration added in a feature commit could reach production
 * without ever having actually run.
 */
class DeployMigrate extends Command
{
    /**
     * --path is forwarded to the underlying migrate command. Only ever used
     * by tests, to point at a throwaway migration outside database/
     * migrations that can safely be made to fail.
     */
    protected $signature = 'deploy:migrate {--path= : Migration path to run instead of database/migrations}';

    protected $description = 'Runs pending migrations as part of a deploy; reports failures to super admins instead of failing the deploy.';

    public function handle(DeploymentErrorReporter $reporter): int
    {
        try {
            // --isolated: safe under multiple replicas starting at once --
            // only the first to grab the lock actually runs the migration.
            $options = ['--force' => true, '--isolated' => true];

            if (filled($path = $this->option('path'))) {
                $options['--path'] = $path;
            }

            $this->call('migrate', $options);
            $this->info('Migrations are up to date.');
        } catch (Throwable $exception) {
            $this->error("Migration failed: {$exception->getMessage()}");

            $reporter->report('migration', $exception, [
                'command' => 'migrate --force --isolated',
            ]);
        }

        // Always succeed: the app must still start and serve the "it's
        // broken" notification above, rather than the whole site going down
        // because one migration failed.
        return self::SUCCESS;
    }
}
