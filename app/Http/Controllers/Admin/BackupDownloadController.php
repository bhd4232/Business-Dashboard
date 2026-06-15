<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AppBackupService;
use App\Services\AuditLogService;
use App\Services\DatabaseBackupService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupDownloadController extends Controller
{
    public function __invoke(
        string $filename,
        Request $request,
        DatabaseBackupService $databaseBackups,
        AppBackupService $appBackups,
        AuditLogService $auditLogs,
    ): BinaryFileResponse {
        abort_unless($request->user()?->canManageBackups(), 403);

        $backup = $databaseBackups->find($filename) ?: $appBackups->find($filename);

        abort_unless($backup, 404);

        $auditLogs->record('backup_downloaded', self::class, null, [
            'filename' => $backup['name'],
            'relative_path' => $backup['relative_path'],
        ], $request);

        return response()->download($backup['path'], $backup['name']);
    }
}
