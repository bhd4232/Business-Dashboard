<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConversationMessage;
use App\Services\CompanyContext;
use App\Services\CompanyStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConversationMediaController extends Controller
{
    public function __invoke(
        Request $request,
        int $message,
        CompanyContext $context,
        CompanyStorageService $storage,
    ): StreamedResponse {
        $user = $request->user();
        $conversationMessage = ConversationMessage::query()->findOrFail($message);
        $conversation = $conversationMessage->conversation()
            ->withoutGlobalScopes()
            ->with('company')
            ->firstOrFail();
        $company = $context->company();

        if (! $company && $context->isAllCompanies() && $user?->isSuperAdmin()) {
            $company = $conversation->company;
        }

        abort_unless(
            $user?->is_active
                && $company
                && (int) $conversation->company_id === (int) $company->getKey()
                && $user?->canAccessCompany($company->getKey()),
            404,
        );

        try {
            $location = $storage->locatePrivate($conversationMessage->media_path, $company);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        abort_if($location === null, 404);

        return Storage::disk($location['disk'])->response(
            $location['path'],
            basename($location['path']),
            [
                'Cache-Control' => 'private, no-store',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
