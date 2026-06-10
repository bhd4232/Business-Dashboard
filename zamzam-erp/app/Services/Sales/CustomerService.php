<?php

namespace App\Services\Sales;

use App\Models\Sales\Customer;
use App\Models\Sales\CustomerTag;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    private function generateCode(): string
    {
        $year   = now()->format('Y');
        $prefix = 'C-' . $year . '-';

        $last = Customer::withTrashed()
            ->where('customer_code', 'like', $prefix . '%')
            ->orderByDesc('customer_code')
            ->value('customer_code');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    public function create(array $data, int $createdBy): Customer
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $tagIds = $data['tag_ids'] ?? [];
            unset($data['tag_ids']);

            $data['created_by']    = $createdBy;
            $data['customer_code'] = $this->generateCode();

            // Auto-assign tags that have is_auto_assign = true for new customers
            $autoTags = CustomerTag::where('is_auto_assign', true)
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            $customer = Customer::create($data);

            $allTagIds = array_unique(array_merge($tagIds, $autoTags));
            if ($allTagIds) {
                $customer->tags()->sync($allTagIds);
                // Update customers_count on each tag
                CustomerTag::whereIn('id', $allTagIds)->each(function ($tag) {
                    $tag->update(['customers_count' => $tag->customers()->count()]);
                });
            }

            return $customer->load('tags');
        });
    }

    public function update(Customer $customer, array $data): Customer
    {
        return DB::transaction(function () use ($customer, $data) {
            $tagIds = $data['tag_ids'] ?? null;
            unset($data['tag_ids']);

            $customer->update($data);

            if ($tagIds !== null) {
                $oldTagIds = $customer->tags->pluck('id')->toArray();
                $customer->tags()->sync($tagIds);

                // Recalculate customers_count for affected tags
                $affectedIds = array_unique(array_merge($oldTagIds, $tagIds));
                CustomerTag::whereIn('id', $affectedIds)->each(function ($tag) {
                    $tag->update(['customers_count' => $tag->customers()->count()]);
                });
            }

            return $customer->load('tags');
        });
    }

    public function toggleActive(Customer $customer): Customer
    {
        $customer->update(['is_active' => !$customer->is_active]);
        return $customer->fresh();
    }
}
