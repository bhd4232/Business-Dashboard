<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Services\CompanyContext;
use App\Services\CustomerAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountProfileController extends Controller
{
    public function __construct(protected CompanyContext $context, protected CustomerAccountService $accounts) {}

    public function show(Request $request): View|RedirectResponse
    {
        [$company, $setting] = $this->domainStorefront($request);
        $customer = Auth::guard('customer')->user();

        if (! $customer) {
            return redirect()->route('storefront.account.login');
        }

        return view('storefront.account.profile', [
            'company' => $company,
            'setting' => $setting,
            'customer' => $customer,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->domainStorefront($request);
        $customer = $this->requireCustomer();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        if (filled($data['email']) && $data['email'] !== $customer->email && ! $this->accounts->emailAvailable($data['email'], $customer->getKey())) {
            throw ValidationException::withMessages(['email' => 'That email address is already in use by another account.']);
        }

        $customer->update($data);

        return redirect()->route('storefront.account.profile')->with('storefront_status', 'Your profile has been updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $this->domainStorefront($request);
        $customer = $this->requireCustomer();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $customer->password)) {
            throw ValidationException::withMessages(['current_password' => 'Your current password is incorrect.']);
        }

        $this->accounts->updatePassword($customer, $data['password']);

        return redirect()->route('storefront.account.profile')->with('storefront_status', 'Your password has been changed.');
    }

    protected function requireCustomer(): Customer
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($customer instanceof Customer, 403);

        return $customer;
    }

    protected function domainStorefront(Request $request): array
    {
        $company = $request->attributes->get('storefront_company');

        abort_unless($company instanceof Company && $company->storefrontSetting?->is_published, 404);

        $this->context->set($company);

        return [$company, $company->storefrontSetting];
    }
}
