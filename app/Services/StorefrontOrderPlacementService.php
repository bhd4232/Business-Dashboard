<?php

namespace App\Services;

use App\Jobs\CheckExternalCourierFraudJob;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StorefrontPayment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StorefrontOrderPlacementService
{
    public function __construct(protected CompanyContext $context) {}

    public function placePaidCheckout(StorefrontPayment $payment): Order
    {
        $company = $payment->company()->withoutGlobalScopes()->firstOrFail();
        $this->context->set($company);

        return DB::transaction(function () use ($payment): Order {
            $lockedPayment = StorefrontPayment::withoutGlobalScopes()->lockForUpdate()->findOrFail($payment->getKey());

            if ($lockedPayment->order_id) {
                return Order::withoutGlobalScopes()->findOrFail($lockedPayment->order_id);
            }

            if ($lockedPayment->status !== StorefrontPayment::STATUS_COMPLETED) {
                throw new RuntimeException('The delivery advance has not been verified yet.');
            }

            $data = $lockedPayment->checkout_data;

            if (! is_array($data) || empty($data['items']) || empty($data['phone'])) {
                throw new RuntimeException('The pending checkout data is incomplete.');
            }

            $customer = Customer::query()->firstOrNew(['phone' => $data['phone']]);
            $customer->fill([
                'name' => $data['name'],
                'email' => $data['email'] ?: $customer->email,
                'address' => $data['address'],
                'customer_type' => $customer->customer_type ?: 'regular',
                'customer_source' => 'website',
                'opening_balance' => $customer->opening_balance ?? 0,
                'is_active' => true,
            ]);
            $customer->save();

            $order = Order::query()->create([
                'customer_id' => $customer->getKey(),
                'customer_name' => $customer->name,
                'order_date' => now()->toDateString(),
                'discount' => 0,
                'vat' => 0,
                'shipping_zone' => $data['delivery_area'],
                'shipping_fee' => (float) $data['shipping_fee'],
                'paid_amount' => 0,
                'status' => 'draft',
                'source' => Order::SOURCE_STOREFRONT,
                'note' => trim("Storefront checkout\nDelivery address: {$data['address']}\nDelivery advance paid online\n".(($data['note'] ?? null) ? "Customer note: {$data['note']}" : '')),
            ]);

            foreach ($data['items'] as $snapshot) {
                $product = Product::query()->findOrFail($snapshot['product_id']);
                $variant = ! empty($snapshot['product_variant_id'])
                    ? ProductVariant::query()->where('product_id', $product->getKey())->findOrFail($snapshot['product_variant_id'])
                    : null;

                OrderItem::query()->create([
                    'order_id' => $order->getKey(),
                    'product_id' => $product->getKey(),
                    'product_variant_id' => $variant?->getKey(),
                    'variant_label' => $variant?->label() ?: ($snapshot['variant_label'] ?? null),
                    'quantity' => (int) $snapshot['quantity'],
                    'unit_price' => (float) $snapshot['unit_price'],
                    'unit_cost' => (float) $snapshot['unit_cost'],
                ]);
            }

            $order->refresh();
            $order->update(['paid_amount' => min((float) $lockedPayment->amount, (float) $order->total_amount)]);
            $lockedPayment->update(['order_id' => $order->getKey(), 'checkout_data' => null]);

            CheckExternalCourierFraudJob::dispatch($order->getKey())->afterCommit();

            return $order->fresh(['items.product', 'customer', 'storefrontPayments']);
        });
    }
}
