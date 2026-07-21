<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VoucherAttachment;
use App\Services\CompanyContext;
use App\Services\CompanyStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VoucherAttachmentDownloadController extends Controller
{
    public function __invoke(
        Request $request,
        int $attachment,
        CompanyContext $context,
        CompanyStorageService $storage,
    ): StreamedResponse {
        $user = $request->user();
        $voucherAttachment = VoucherAttachment::withoutGlobalScopes()->findOrFail($attachment);
        $company = $context->company();

        if (! $company && $context->isAllCompanies() && $user?->isSuperAdmin()) {
            $company = $voucherAttachment->company()->first();
        }

        abort_unless(
            $user?->is_active
                && $company
                && (int) $voucherAttachment->company_id === (int) $company->getKey()
                && $user?->canAccessCompany($company->getKey()),
            404,
        );
        abort_unless($user->hasPermission('voucher.view'), 403);

        $voucherAttachment->voucher()
            ->withoutGlobalScopes()
            ->where('company_id', $company->getKey())
            ->when(
                ! $user->canViewAllVouchers(),
                fn ($query) => $query->where('submitted_by', $user->getKey()),
            )
            ->firstOrFail();

        try {
            $location = $storage->locatePrivate($voucherAttachment->file_path, $company);
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
            'attachment',
        );
    }
}
