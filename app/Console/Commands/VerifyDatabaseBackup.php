<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;

class VerifyDatabaseBackup extends Command
{
    protected $signature = 'backup:verify {filename? : Backup filename; defaults to the newest SQLite backup}';

    protected $description = 'Verify a database backup by restoring it into a disposable SQLite database';

    public function handle(DatabaseBackupService $backups): int
    {
        $filename = $this->argument('filename')
            ?: collect($backups->all())->first(fn (array $backup): bool => str_ends_with($backup['name'], '.sqlite'))['name'] ?? null;

        if (! $filename) {
            $this->error('No SQLite backup is available to verify.');

            return self::FAILURE;
        }

        $result = $backups->verifyRestore($filename);
        $this->info("Restore drill passed: {$result['backup']}");
        $this->line("Integrity: {$result['integrity']}; tables: {$result['table_count']}");

        return self::SUCCESS;
    }
}
