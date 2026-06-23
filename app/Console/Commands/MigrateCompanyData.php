<?php

namespace App\Console\Commands;

use App\Services\CompanyDataMigrationService;
use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use JsonException;

class MigrateCompanyData extends Command
{
    protected $signature = 'companies:migrate-data
        {company : Target company slug}
        {mapping : JSON mapping file}
        {--dry-run : Validate and display the migration plan only}
        {--no-backup : Skip the mandatory backup (non-production only)}';

    protected $description = 'Safely reassign mapped Main Company records and their children to a target company';

    public function handle(CompanyDataMigrationService $migrator, DatabaseBackupService $backups): int
    {
        $path = base_path((string) $this->argument('mapping'));
        if (! File::isFile($path)) {
            $this->error("Mapping file not found: {$path}");

            return self::FAILURE;
        }

        try {
            $mapping = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->error('Invalid mapping JSON: '.$exception->getMessage());

            return self::FAILURE;
        }

        $plan = $migrator->inspect((string) $this->argument('company'), $mapping);
        $this->table(['Table', 'Records'], collect($plan['counts'])->map(fn ($count, $table) => [$table, $count])->values()->all());

        if ($this->option('dry-run')) {
            $this->info('Dry run complete. No records were changed.');

            return self::SUCCESS;
        }

        if (! $this->option('no-backup')) {
            $backup = $backups->create();
            $this->info("Pre-migration backup created: {$backup['name']}");
        } elseif (app()->isProduction()) {
            $this->error('--no-backup is not allowed in production.');

            return self::FAILURE;
        }

        $migrator->migrate((string) $this->argument('company'), $mapping);
        $this->info('Company data migration completed successfully.');

        return self::SUCCESS;
    }
}
