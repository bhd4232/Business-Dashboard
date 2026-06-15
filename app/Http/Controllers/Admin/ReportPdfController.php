<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Services\CompanySettingsService;
use App\Services\ReportExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportPdfController extends Controller
{
    public function __invoke(
        string $type,
        Request $request,
        ReportExportService $exports,
        AuditLogService $auditLogs,
        CompanySettingsService $settings,
    ): Response {
        abort_unless($request->user()?->canExportReports(), 403);

        $auditLogs->record('report_exported', ReportExportService::class, null, [
            'format' => 'pdf',
            'type' => $type,
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ], $request);

        $export = $exports->export(
            $type,
            $request->query('date_from'),
            $request->query('date_to'),
        );

        $filename = str($export['filename'])->replaceEnd('.csv', '.pdf')->toString();

        return Pdf::loadView('reports.pdf', [
            'export' => $export,
            'title' => str($type)->replace('-', ' ')->headline()->toString(),
            'company' => $settings->profile(),
        ])->setPaper('a4', 'landscape')->download($filename);
    }
}
