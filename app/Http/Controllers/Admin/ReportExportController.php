<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Services\ReportExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function __invoke(
        string $type,
        Request $request,
        ReportExportService $exports,
        AuditLogService $auditLogs,
    ): StreamedResponse {
        abort_unless($request->user()?->canExportReports(), 403);

        $auditLogs->record('report_exported', ReportExportService::class, null, [
            'format' => 'csv',
            'type' => $type,
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ], $request);

        return $exports->download(
            $type,
            $request->query('date_from'),
            $request->query('date_to'),
        );
    }
}
