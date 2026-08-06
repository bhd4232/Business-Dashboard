<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\StorefrontCustomerActivity;
use Illuminate\Support\Facades\Schema;

class StorefrontCustomerActivityService
{
    public function record(
        Customer $customer,
        string $type,
        string $title,
        ?string $description = null,
        array $metadata = [],
    ): ?StorefrontCustomerActivity {
        if (! Schema::hasTable((new StorefrontCustomerActivity)->getTable())) {
            return null;
        }

        return StorefrontCustomerActivity::query()->create([
            'company_id' => $customer->company_id,
            'customer_id' => $customer->getKey(),
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'metadata' => $metadata ?: null,
        ]);
    }
}
