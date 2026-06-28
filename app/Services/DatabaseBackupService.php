<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;

class DatabaseBackupService
{
    public const DIRECTORY = 'backups/database';

    public function create(?string $connection = null): array
    {
        $connection ??= config('database.default');
        $config = config("database.connections.{$connection}");

        if (! is_array($config)) {
            throw new RuntimeException("Database connection [{$connection}] is not configured.");
        }

        $driver = $config['driver'] ?? null;
        $extension = $driver === 'sqlite' ? 'sqlite' : 'sql';
        $filename = sprintf(
            'database-backup-%s-%s.%s',
            now()->format('Ymd-His'),
            str($connection)->slug(),
            $extension,
        );
        $relativePath = self::DIRECTORY.'/'.$filename;
        $absolutePath = Storage::disk('local')->path($relativePath);

        File::ensureDirectoryExists(dirname($absolutePath));

        match ($driver) {
            'sqlite' => $this->backupSqlite($config, $absolutePath),
            'mysql', 'mariadb' => $this->backupMysql($config, $absolutePath),
            default => throw new RuntimeException("Database backups are not supported for [{$driver}] connections yet."),
        };

        $this->cleanup();

        return $this->fileDetails($relativePath);
    }

    public function all(): array
    {
        return collect(Storage::disk('local')->files(self::DIRECTORY))
            ->filter(fn (string $path): bool => str($path)->endsWith(['.sqlite', '.sql']))
            ->map(fn (string $path): array => $this->fileDetails($path))
            ->sortByDesc('modified_at')
            ->values()
            ->all();
    }

    public function find(string $filename): ?array
    {
        $filename = basename($filename);
        $path = self::DIRECTORY.'/'.$filename;

        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        return $this->fileDetails($path);
    }

    public function cleanup(int $keep = 10): void
    {
        collect($this->all())
            ->skip($keep)
            ->each(fn (array $file): bool => Storage::disk('local')->delete($file['relative_path']));
    }

    /**
     * Restore a SQLite backup into a disposable file and verify its integrity.
     * The configured/live database is never modified.
     */
    public function verifyRestore(string $filename): array
    {
        $backup = $this->find($filename);

        if (! $backup || ! str_ends_with($backup['name'], '.sqlite')) {
            throw new RuntimeException('A valid SQLite database backup is required for the restore drill.');
        }

        $temporaryPath = storage_path('framework/testing/restore-drill-'.str()->uuid().'.sqlite');
        File::ensureDirectoryExists(dirname($temporaryPath));

        try {
            File::copy($backup['path'], $temporaryPath);
            $pdo = new PDO('sqlite:'.$temporaryPath);
            $integrity = $pdo->query('PRAGMA integrity_check')->fetchColumn();

            if ($integrity !== 'ok') {
                throw new RuntimeException('SQLite integrity check failed: '.(string) $integrity);
            }

            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")
                ->fetchAll(PDO::FETCH_COLUMN);

            if (! in_array('migrations', $tables, true)) {
                throw new RuntimeException('Restored database does not contain the migrations table.');
            }

            return [
                'backup' => $backup['name'],
                'integrity' => $integrity,
                'table_count' => count($tables),
            ];
        } finally {
            unset($pdo);
            File::delete($temporaryPath);
        }
    }

    protected function backupSqlite(array $config, string $absolutePath): void
    {
        $database = $config['database'] ?? null;

        if (! is_string($database) || $database === ':memory:' || ! File::exists($database)) {
            throw new RuntimeException('SQLite database file could not be found for backup.');
        }

        File::copy($database, $absolutePath);
    }

    protected function backupMysql(array $config, string $absolutePath): void
    {
        $command = [
            'mysqldump',
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.($config['port'] ?? 3306),
            '--user='.($config['username'] ?? ''),
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            $config['database'] ?? '',
        ];

        $password = $config['password'] ?? null;

        if (filled($password)) {
            $command[] = '--password='.$password;
        }

        $process = new Process($command);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Database backup failed.');
        }

        File::put($absolutePath, $process->getOutput());
    }

    protected function fileDetails(string $relativePath): array
    {
        $disk = Storage::disk('local');
        $modifiedAt = $disk->lastModified($relativePath);

        return [
            'name' => basename($relativePath),
            'relative_path' => $relativePath,
            'path' => $disk->path($relativePath),
            'size' => $disk->size($relativePath),
            'size_human' => $this->humanSize($disk->size($relativePath)),
            'modified_at' => $modifiedAt,
            'modified_label' => now()->createFromTimestamp($modifiedAt)->format('d M Y, h:i A'),
        ];
    }

    protected function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / 1024 / 1024, 1).' MB';
    }
}
