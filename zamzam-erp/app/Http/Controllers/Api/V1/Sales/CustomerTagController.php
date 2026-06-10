<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\CustomerTag;
use App\Models\Sales\PriceTier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerTagController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('customers.view');
        return response()->json(CustomerTag::with('linkedPriceTier')->orderBy('sort_order')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('customers.create');

        $data = $request->validate([
            'name'                   => 'required|string|max:100|unique:customer_tags,name',
            'color'                  => 'required|string|max:20',
            'description'            => 'nullable|string|max:500',
            'is_auto_assign'         => 'nullable|boolean',
            'linked_price_tier_id'   => 'nullable|exists:price_tiers,id',
            'sort_order'             => 'nullable|integer|min:0',
        ]);

        $data['slug'] = \Str::slug($data['name']);
        $tag = CustomerTag::create($data);

        return response()->json($tag->load('linkedPriceTier'), 201);
    }

    public function update(Request $request, CustomerTag $customerTag): JsonResponse
    {
        $this->authorize('customers.edit');

        $data = $request->validate([
            'name'                   => 'sometimes|required|string|max:100|unique:customer_tags,name,'.$customerTag->id,
            'color'                  => 'sometimes|required|string|max:20',
            'description'            => 'nullable|string|max:500',
            'is_auto_assign'         => 'nullable|boolean',
            'is_active'              => 'nullable|boolean',
            'linked_price_tier_id'   => 'nullable|exists:price_tiers,id',
            'sort_order'             => 'nullable|integer|min:0',
        ]);

        if (isset($data['name'])) {
            $data['slug'] = \Str::slug($data['name']);
        }

        $customerTag->update($data);
        return response()->json($customerTag->load('linkedPriceTier'));
    }

    public function destroy(CustomerTag $customerTag): JsonResponse
    {
        $this->authorize('customers.delete');

        if ($customerTag->customers_count > 0) {
            return response()->json([
                'message' => "Cannot delete tag with {$customerTag->customers_count} customer(s). Detach customers first.",
            ], 422);
        }

        $customerTag->delete();
        return response()->json(['message' => 'Tag deleted.']);
    }

    public function priceTiers(): JsonResponse
    {
        return response()->json(PriceTier::active()->get(['id','name','discount_percent']));
    }
}
