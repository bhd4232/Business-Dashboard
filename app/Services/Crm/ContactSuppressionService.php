<?php

namespace App\Services\Crm;

use App\Models\Customer;
use App\Models\Lead;

class ContactSuppressionService
{
    public function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '00880')) {
            $digits = substr($digits, 2);
        }

        return strlen($digits) === 11 && str_starts_with($digits, '01') ? '88'.$digits : $digits;
    }

    public function suppressed(int $companyId, string $phone): bool
    {
        $phone = $this->normalize($phone);
        if ($phone === '') {
            return true;
        }
        foreach ([Lead::class, Customer::class] as $model) {
            foreach ($model::withoutGlobalScopes()->where('company_id', $companyId)->whereNotNull('opted_out_at')->select('phone')->cursor() as $contact) {
                if ($this->normalize((string) $contact->phone) === $phone) {
                    return true;
                }
            }
        }

        return false;
    }
}
