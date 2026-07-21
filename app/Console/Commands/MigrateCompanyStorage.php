<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\CompanyStorageMigrator;
use App\Services\CompanyStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class MigrateCompanyStorage extends Command
{
    protected $signature = 'storage:migrate-company-files
        {--company=* : Company ID or slug; repeat to migrate multiple companies}
        {--scope=all : all, public, or private}
        {--execute : Copy verified objects and update database paths}
        {--allow-local : Allow --execute to target local disks instead of configured R2 disks}
        {--force : Allow --execute in production}';

    protected $description = 'Copy legacy files into immutable company storage namespaces without deleting their sources';

    public function handle(CompanyStorageMigrator $migrator, CompanyStorageService $storage): int
    {
        $scope = strtolower(trim((string) $this->option('scope')));

        if (! in_array($scope, CompanyStorageMigrator::SCOPES, true)) {
            $this->error('The --scope option must be all, public, or private.');

            return self::INVALID;
        }

        $execute = (bool) $this->option('execute');

        if ($execute && app()->isProduction() && ! $this->option('force')) {
            $this->error('Production execution requires both --execute and --force. Run without --execute for a dry-run first.');

            return self::FAILURE;
        }

        $publicTarget = $storage->publicDiskName();
        $privateTarget = $storage->privateDiskName();

        $this->line("Destination disks: public={$publicTarget}; private={$privateTarget}");

        if ($execute && ! $this->option('allow-local')) {
            $localTargets = [];

            if ($scope !== 'private' && $publicTarget !== 'r2_public') {
                $localTargets[] = 'public';
            }

            if ($scope !== 'public' && $privateTarget !== 'r2_private') {
                $localTargets[] = 'private';
            }

            if ($localTargets !== []) {
                $this->error('Execution stopped because these scopes would target local storage: '.implode(', ', $localTargets).'. Configure and enable the required R2 buckets, or use --allow-local intentionally.');

                return self::FAILURE;
            }
        }

        $companies = $this->companies();

        if ($companies->isEmpty()) {
            $this->error('No matching companies were found.');

            return self::FAILURE;
        }

        $this->components->info($execute
            ? 'Executing copy-and-verify migration. Source objects will not be deleted.'
            : 'Dry-run only. No objects or database paths will be changed.');

        $failed = false;

        foreach ($companies as $company) {
            $this->newLine();
            $this->components->info("{$company->name} (#{$company->id})");

            $stats = $migrator->migrateCompany(
                $company,
                $execute,
                $scope,
                function (string $level, string $message): void {
                    match ($level) {
                        'error' => $this->error($message),
                        'warning' => $this->warn($message),
                        default => $this->line($message, verbosity: 'v'),
                    };
                },
            );

            $this->table(
                ['planned', 'copied', 'reused', 'updated', 'already scoped', 'external', 'missing', 'conflicts', 'errors'],
                [[
                    $stats['planned'],
                    $stats['copied'],
                    $stats['reused'],
                    $stats['updated'],
                    $stats['already_scoped'],
                    $stats['external'],
                    $stats['missing'],
                    $stats['conflicts'],
                    $stats['errors'],
                ]],
            );

            $failed = $failed || $stats['missing'] > 0 || $stats['conflicts'] > 0 || $stats['errors'] > 0;
        }

        if (! $execute) {
            $this->newLine();
            $this->info('Review the dry-run, create a database backup, then rerun with --execute.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    protected function companies(): Collection
    {
        $filters = array_values(array_filter(array_map('trim', (array) $this->option('company'))));
        $query = Company::query()->orderBy('id');

        if ($filters !== []) {
            $query->where(function ($query) use ($filters): void {
                foreach ($filters as $filter) {
                    $query->orWhere('slug', $filter);

                    if (ctype_digit($filter)) {
                        $query->orWhere('id', (int) $filter);
                    }
                }
            });
        }

        return $query->get();
    }
}
