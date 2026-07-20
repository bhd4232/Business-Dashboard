<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\StorefrontSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Storefront customer login/registration, scoped to the active company via
 * the existing CompanyContext/CompanyScope (already set by the controller
 * before any of these methods run, same as checkout/cart/track).
 */
class CustomerAccountService
{
    public function __construct(protected StorefrontNotificationService $notifications) {}

    /**
     * Registers (or upgrades) a Customer. Reuses an existing phone-matched
     * CRM/order record instead of creating a duplicate, so past storefront
     * orders are immediately visible once the account is created.
     */
    public function register(array $data): Customer
    {
        $customer = Customer::query()->tap(fn (Builder $query) => $this->wherePhoneMatches($query, $data['phone']))->first();

        if ($customer?->isRegistered()) {
            throw ValidationException::withMessages([
                'phone' => 'An account with this phone number already exists. Please log in instead.',
            ]);
        }

        if (filled($data['email'] ?? null) && $this->emailTaken($data['email'])) {
            throw ValidationException::withMessages([
                'email' => 'An account with this email address already exists. Please log in instead.',
            ]);
        }

        $customer ??= new Customer;

        $customer->fill([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? $customer->email,
            'address' => $data['address'] ?? $customer->address,
            'customer_type' => $customer->customer_type ?: 'regular',
            'customer_source' => $customer->customer_source ?: 'website',
            'is_active' => true,
        ]);
        $customer->password = Hash::make($data['password']);
        $customer->save();

        return $customer;
    }

    /**
     * @return Customer|null null when the identifier/password pair doesn't match a registered account.
     */
    public function attemptLogin(string $identifier, string $password): ?Customer
    {
        $identifier = trim($identifier);
        $query = Customer::query()->whereNotNull('password');

        if (str_contains($identifier, '@')) {
            $query->where('email', $identifier);
        } else {
            $this->wherePhoneMatches($query, $identifier);
        }

        $customer = $query->first();

        if (! $customer || ! Hash::check($password, $customer->password)) {
            return null;
        }

        return $customer;
    }

    /**
     * Looks up a registered account by phone (used by both the forgot- and
     * reset-password steps). Callers show the same generic message whether
     * or not this returns a match, to avoid leaking which phones have accounts.
     */
    public function findRegisteredByPhone(string $phone): ?Customer
    {
        return Customer::query()
            ->whereNotNull('password')
            ->tap(fn (Builder $query) => $this->wherePhoneMatches($query, $phone))
            ->first();
    }

    /**
     * Sends a 6-digit SMS reset code. Returns false when no SMS gateway is
     * configured for this company - the caller still shows the generic
     * "if an account exists" message either way.
     */
    public function sendPasswordResetCode(Company $company, StorefrontSetting $setting, Customer $customer): bool
    {
        $code = (string) random_int(100000, 999999);

        $customer->forceFill([
            'password_reset_code' => Hash::make($code),
            'password_reset_expires_at' => now()->addMinutes(15),
        ])->save();

        return $this->notifications->sendSms(
            $setting,
            $customer->phone,
            "Your {$company->name} password reset code is {$code}. It expires in 15 minutes.",
        );
    }

    public function resetPassword(Customer $customer, string $code, string $newPassword): bool
    {
        if (
            blank($customer->password_reset_code)
            || ! $customer->password_reset_expires_at
            || $customer->password_reset_expires_at->isPast()
            || ! Hash::check($code, $customer->password_reset_code)
        ) {
            return false;
        }

        $customer->forceFill([
            'password' => Hash::make($newPassword),
            'password_reset_code' => null,
            'password_reset_expires_at' => null,
        ])->save();

        return true;
    }

    public function updatePassword(Customer $customer, string $newPassword): void
    {
        $customer->forceFill(['password' => Hash::make($newPassword)])->save();
    }

    public function emailAvailable(string $email, ?int $ignoreCustomerId = null): bool
    {
        return ! $this->emailTaken($email, $ignoreCustomerId);
    }

    protected function emailTaken(string $email, ?int $ignoreCustomerId = null): bool
    {
        return Customer::query()
            ->whereNotNull('password')
            ->where('email', $email)
            ->when($ignoreCustomerId, fn (Builder $query) => $query->whereKeyNot($ignoreCustomerId))
            ->exists();
    }

    protected function wherePhoneMatches(Builder $query, string $phone): void
    {
        $phone = trim($phone);
        $digits = preg_replace('/\D+/', '', $phone) ?: $phone;

        $query->where(function (Builder $query) use ($phone, $digits): void {
            $query->where('phone', $phone)
                ->orWhere('phone', $digits)
                ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), '-', ''), ' ', ''), '(', '') LIKE ?", ['%'.$digits.'%']);
        });
    }
}
