<?php

namespace App\Services\Crm;

use App\Models\Conversation;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SalesCartService
{
    public function item(Conversation $conversation, array $input): array
    {
        $data = Validator::make($input, ['product_id' => 'required|integer', 'variant_id' => 'nullable|integer', 'quantity' => 'sometimes|integer|min:1|max:1000'])->validate();
        $product = Product::query()->where('company_id', $conversation->company_id)->where('is_active', true)->findOrFail($data['product_id']);
        $variant = isset($data['variant_id']) ? $product->activeVariants()->where('company_id', $conversation->company_id)->findOrFail($data['variant_id']) : null;
        if ($product->has_variants && ! $variant) {
            throw ValidationException::withMessages(['variant_id' => 'Ask the customer to choose a size/colour first.']);
        }
        $quantity = $data['quantity'] ?? 1;
        if ($product->status !== Product::STATUS_AVAILABLE || (int) ($variant?->stock ?? $product->stock) < $quantity) {
            throw ValidationException::withMessages(['quantity' => 'Requested quantity is not currently available.']);
        }

        return ['product_id' => $product->getKey(), 'product_variant_id' => $variant?->getKey(), 'variant_label' => $variant?->label(),
            'name' => $product->name, 'quantity' => $quantity, 'unit_price' => $variant?->effectiveSalePrice() ?? (float) $product->selling_price];
    }
}
