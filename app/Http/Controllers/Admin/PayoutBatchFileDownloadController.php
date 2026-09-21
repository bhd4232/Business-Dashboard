<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PayoutBatch;
use App\Services\CompanyContext;
use App\Services\CompanyStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayoutBatchFileDownloadController extends Controller
{
    public function __invoke(Request $request, int $batch, CompanyContext $context, CompanyStorageService $storage): StreamedResponse
    {
        $user = $request->user();
        $record = PayoutBatch::withoutGlobalScopes()->findOrFail($batch);
        $company = $context->company();

        if (! $company && $context->isAllCompanies() && $user?->isSuperAdmin()) {
            $company = $record->company()->first();
        }

        abort_unless(
            $user?->is_active
            && $company
            && (int) $record->company_id === (int) $company->getKey()
            && $user->canAccessCompany((int) $company->getKey()),
            404,
        );
        abort_unless($user->hasPermission('investments.settle'), 403);
        abort_if(blank($record->export_file_path), 404);

        try {
            $location = $storage->locatePrivate($record->export_file_path, $company);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        abort_if($location === null, 404);

        return Storage::disk($location['disk'])->response(
            $location['path'],
            "{$record->batch_number}.csv",
            ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'],
            'attachment',
        );
    }
}
