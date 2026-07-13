<?php

namespace App\Http\Controllers\Storefront\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared phone matching for storefront self-service lookups (order tracking,
 * order history). A customer's stored phone may carry +880/0 prefixes and
 * formatting, so a supplied number is matched against the raw value, its
 * digits-only form, and a punctuation-stripped LIKE.
 */
trait MatchesCustomerPhone
{
    protected function whereCustomerPhoneMatches(Builder $query, string $phone): void
    {
        $phone = trim($phone);
        $digits = preg_replace('/\D+/', '', $phone) ?: $phone;

        $query->whereHas('customer', function (Builder $customer) use ($phone, $digits): void {
            $customer->where('phone', $phone)
                ->orWhere('phone', $digits)
                ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), '-', ''), ' ', ''), '(', '') LIKE ?", ['%'.$digits.'%']);
        });
    }
}
