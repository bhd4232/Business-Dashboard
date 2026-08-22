<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\CompanyContext;
use App\Services\StorefrontMetaTrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MetaConsentController extends Controller
{
    public function __construct(
        protected CompanyContext $context,
        protected StorefrontMetaTrackingService $metaTracking,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'choice' => 'required|in:granted,denied',
            'redirect_to' => 'nullable|string',
        ]);

        $company = $this->context->company();
        abort_unless($company instanceof Company, 404);

        $setting = $company->storefrontSetting;
        abort_unless($setting !== null, 404);

        $redirectTo = (string) ($validated['redirect_to'] ?? '');
        $safeRedirect = $redirectTo !== '' && Str::startsWith($redirectTo, url('/'))
            ? $redirectTo
            : url('/');

        $cookie = cookie(
            name: $this->metaTracking->consentCookieName($setting),
            value: $validated['choice'],
            minutes: 60 * 24 * 180,
            path: '/',
            secure: $request->secure(),
            sameSite: 'lax',
        );

        return redirect()->to($safeRedirect)->withCookie($cookie);
    }
}
