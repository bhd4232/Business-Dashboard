<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseScan;
use App\Services\CompanyContext;
use App\Services\CompanyStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams one privately stored AI Expense Scan photo inline — to reviewers
 * (`expenses.ai_scan`) and, as the proof behind a published expense, to
 * anyone who may view expenses.
 */
class ExpenseScanImageController extends Controller
{
    public function __invoke(
        Request $request,
        int $scan,
        int $index,
        CompanyContext $context,
        CompanyStorageService $storage,
    ): StreamedResponse {
        $user = $request->user();
        $expenseScan = ExpenseScan::withoutGlobalScopes()->findOrFail($scan);
        $company = $context->company();

        if (! $company && $context->isAllCompanies() && $user?->isSuperAdmin()) {
            $company = $expenseScan->company()->first();
        }

        abort_unless(
            $user?->is_active
                && $company
                && (int) $expenseScan->company_id === (int) $company->getKey()
                && $user->canAccessCompany($company->getKey()),
            404,
        );
        abort_unless($user->canUseExpenseScan() || $user->canPerformModelAbility('view', Expense::class), 403);

        $path = $expenseScan->imagePaths()[$index] ?? null;
        abort_if($path === null, 404);

        try {
            $location = $storage->locatePrivate($path, $company);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        abort_if($location === null, 404);

        return Storage::disk($location['disk'])->response(
            $location['path'],
            basename($location['path']),
            [
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline',
        );
    }
}
