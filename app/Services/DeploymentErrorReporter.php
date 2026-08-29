<?php

namespace App\Services;

use App\Models\DeploymentError;
use App\Models\User;
use App\Notifications\DeploymentErrorAlert;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Catches a deploy-time failure (currently: a failed `migrate`, see
 * App\Console\Commands\DeployMigrate) so it never silently leaves the app
 * running against a stale schema. Every call:
 *
 *   1. Always writes to the normal application log first -- this cannot
 *      fail on account of the database being the thing that's broken.
 *   2. Persists a DeploymentError row (the durable copy the Settings ->
 *      Deploy Error Logs page reads, so the log survives even after the
 *      bell notification below is cleared).
 *   3. Notifies every active super admin via DeploymentErrorAlert, with a
 *      one-click "Copy Error Log" button to hand straight to an agent.
 *
 * If step 2 itself fails (e.g. the database is completely unreachable, not
 * just missing a migration), step 3 is skipped -- there's no record to link
 * a notification to -- but step 1's log entry still exists, marked
 * critical, so the failure isn't lost entirely.
 */
class DeploymentErrorReporter
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function report(string $source, Throwable $exception, array $context = []): ?DeploymentError
    {
        Log::error("Deployment error [{$source}]: {$exception->getMessage()}", [
            'exception' => $exception::class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'context' => $context,
        ]);

        try {
            $error = DeploymentError::create([
                'source' => $source,
                'message' => Str::limit($exception->getMessage(), 500, ''),
                'details' => $this->formatDetails($source, $exception, $context),
                'context' => $context,
                'occurred_at' => now(),
            ]);
        } catch (Throwable $storageFailure) {
            Log::critical('DeploymentErrorReporter could not persist a deployment error record -- the failure above only exists in this log file.', [
                'storage_error' => $storageFailure->getMessage(),
            ]);

            return null;
        }

        $this->notifySuperAdmins($error);

        return $error;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function formatDetails(string $source, Throwable $exception, array $context): string
    {
        $lines = [
            "Source: {$source}",
            'Occurred at: '.now()->toDateTimeString(),
            'Exception: '.$exception::class,
            "Message: {$exception->getMessage()}",
            "Location: {$exception->getFile()}:{$exception->getLine()}",
        ];

        if ($context !== []) {
            $lines[] = 'Context: '.json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        $lines[] = '';
        $lines[] = 'Stack trace:';
        $lines[] = $exception->getTraceAsString();

        return implode("\n", $lines);
    }

    protected function notifySuperAdmins(DeploymentError $error): void
    {
        $recipients = User::query()
            ->where('role', 'super_admin')
            ->where('is_active', true)
            ->get();

        foreach ($recipients as $user) {
            $user->notify(new DeploymentErrorAlert($error));
        }
    }
}
