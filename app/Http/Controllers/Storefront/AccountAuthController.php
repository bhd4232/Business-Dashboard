<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\CompanyContext;
use App\Services\CustomerAccountService;
use App\Services\StorefrontNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountAuthController extends Controller
{
    public function __construct(
        protected CompanyContext $context,
        protected CustomerAccountService $accounts,
        protected StorefrontNotificationService $notifications,
    ) {}

    public function showLogin(Request $request): View|RedirectResponse
    {
        [$company, $setting] = $this->domainStorefront($request);
        abort_if(! $setting->customer_accounts_enabled, 404);

        if (Auth::guard('customer')->check()) {
            return redirect()->route('storefront.account.profile');
        }

        return view('storefront.account.login', [
            'company' => $company,
            'setting' => $setting,
            'activeTab' => 'login',
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        [, $setting] = $this->domainStorefront($request);
        abort_if(! $setting->customer_accounts_enabled, 404);

        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $customer = $this->accounts->attemptLogin($data['identifier'], $data['password']);

        if (! $customer) {
            throw ValidationException::withMessages([
                'identifier' => "Those details don't match an account. Check your phone/email and password.",
            ]);
        }

        Auth::guard('customer')->login($customer, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->route('storefront.account.profile')->with('storefront_status', "Welcome back, {$customer->name}.");
    }

    public function showRegister(Request $request): View|RedirectResponse
    {
        [$company, $setting] = $this->domainStorefront($request);
        abort_if(! $setting->customer_accounts_enabled, 404);

        if (Auth::guard('customer')->check()) {
            return redirect()->route('storefront.account.profile');
        }

        return view('storefront.account.login', [
            'company' => $company,
            'setting' => $setting,
            'activeTab' => 'register',
        ]);
    }

    public function register(Request $request): RedirectResponse
    {
        [, $setting] = $this->domainStorefront($request);
        abort_if(! $setting->customer_accounts_enabled, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $customer = $this->accounts->register($data);

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return redirect()->route('storefront.account.profile')->with('storefront_status', "Welcome, {$customer->name}! Your account has been created.");
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();

        // Regenerate the CSRF token only - invalidating the whole session
        // would also wipe the guest's cart, which is stored in session.
        $request->session()->regenerateToken();

        return redirect()->route('marketing.home')->with('storefront_status', 'You have been logged out.');
    }

    public function showForgotPassword(Request $request): View
    {
        [$company, $setting] = $this->domainStorefront($request);
        abort_if(! $setting->customer_accounts_enabled, 404);

        return view('storefront.account.forgot-password', [
            'company' => $company,
            'setting' => $setting,
            'smsAvailable' => $this->notifications->smsConfigured($setting),
        ]);
    }

    public function forgotPassword(Request $request): RedirectResponse
    {
        [$company, $setting] = $this->domainStorefront($request);
        abort_if(! $setting->customer_accounts_enabled, 404);

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
        ]);

        if (! $this->notifications->smsConfigured($setting)) {
            return back()->withInput()->with('storefront_status', 'Password reset by SMS isn\'t available right now. Please contact support to regain access to your account.');
        }

        $customer = $this->accounts->findRegisteredByPhone($data['phone']);

        if ($customer) {
            $this->accounts->sendPasswordResetCode($company, $setting, $customer);
        }

        // Same message whether or not a match was found, so a phone number
        // can't be used to probe which numbers have accounts.
        return redirect()
            ->route('storefront.account.reset-password', ['phone' => $data['phone']])
            ->with('storefront_status', 'If an account exists for that phone number, a reset code has been sent by SMS.');
    }

    public function showResetPassword(Request $request): View
    {
        [$company, $setting] = $this->domainStorefront($request);
        abort_if(! $setting->customer_accounts_enabled, 404);

        return view('storefront.account.reset-password', [
            'company' => $company,
            'setting' => $setting,
            'phone' => (string) $request->query('phone', ''),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        [, $setting] = $this->domainStorefront($request);
        abort_if(! $setting->customer_accounts_enabled, 404);

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
            'code' => ['required', 'string', 'max:10'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $customer = $this->accounts->findRegisteredByPhone($data['phone']);
        $reset = $customer && $this->accounts->resetPassword($customer, $data['code'], $data['password']);

        if (! $reset) {
            throw ValidationException::withMessages([
                'code' => 'That code is invalid or has expired. Please request a new one.',
            ]);
        }

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return redirect()->route('storefront.account.profile')->with('storefront_status', 'Your password has been reset.');
    }

    protected function domainStorefront(Request $request): array
    {
        $company = $request->attributes->get('storefront_company');

        abort_unless($company instanceof Company && $company->storefrontSetting?->is_published, 404);

        $this->context->set($company);

        return [$company, $company->storefrontSetting];
    }
}
