<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ingests WooCommerce order.created/order.updated/order.deleted webhooks
 * (see WooCommerceWebhookController) into company-scoped ERP Orders.
 *
 * Upserts are keyed on `orders.external_reference` ("woo-{id}"), so a
 * redelivered webhook (WooCommerce guarantees at-least-once, not
 * exactly-once delivery) updates the same order instead of duplicating it.
 *
 * Status mapping is a reasonable default, not a confirmed business rule —
 * flagged in 09_DASHBOARD_WOOCOMMERCE_SECURITY_PLAN.md step 2.5 as needing
 * the owner's confirmation before go-live. Adjust STATUS_MAP if they want
 * different equivalents (e.g. WooCommerce "processing" vs ERP "confirmed").
 */
class WooCommerceOrderSyncService
{
    /** @var array<string, string> WooCommerce order status => Order::STATUS_* */
    public const STATUS_MAP = [
        'pending' => Order::STATUS_DRAFT,
        'on-hold' => Order::STATUS_DRAFT,
        'processing' => Order::STATUS_PROCESSING,
        'completed' => Order::STATUS_COMPLETED,
        'cancelled' => Order::STATUS_CANCELLED,
        'refunded' => Order::STATUS_REFUNDED,
        'failed' => Order::STATUS_CANCELLED,
        'trash' => Order::STATUS_CANCELLED,
    ];

    public function __construct(
        protected CompanyContext $context,
        protected StorefrontCheckoutPolicyService $checkoutPolicies,
    ) {}

    public function handleOrderEvent(Company $company, string $topic, array $payload): void
    {
        // Every BelongsToCompany read/write below relies on this being set —
        // this route is unauthenticated, so SetCurrentCompany has cleared
        // the context for the request (see CompanyScope's docblock).
        $this->context->set($company);

        if (str_contains($topic, 'deleted')) {
            $this->markCancelled($company, $payload);

            return;
        }

        $this->upsertOrder($company, $payload);
    }

    protected function markCancelled(Company $company, array $payload): void
    {
        $wooOrderId = (int) ($payload['id'] ?? 0);

        if ($wooOrderId <= 0) {
            return;
        }

        Order::query()
            ->where('company_id', $company->getKey())
            ->where('external_reference', "woo-{$wooOrderId}")
            ->first()
            ?->update(['status' => Order::STATUS_CANCELLED]);
    }

    protected function upsertOrder(Company $company, array $payload): Order
    {
        $wooOrderId = (int) ($payload['id'] ?? 0);
        abort_if($wooOrderId <= 0, 422, 'WooCommerce order payload is missing an order id.');

        return DB::transaction(function () use ($company, $payload, $wooOrderId): Order {
            $customer = $this->resolveCustomer($company, $payload);

            $order = Order::query()
                ->where('company_id', $company->getKey())
                ->where('external_reference', "woo-{$wooOrderId}")
                ->first() ?? new Order([
                    'company_id' => $company->getKey(),
                    'source' => Order::SOURCE_WOOCOMMERCE,
                    'external_reference' => "woo-{$wooOrderId}",
                ]);

            $orderDate = $order->exists
                ? $order->order_date
                : $this->orderDate($payload);

            $order->fill([
                'customer_id' => $customer->getKey(),
                'customer_name' => $customer->name,
                'order_date' => $orderDate,
                'discount' => (float) ($payload['discount_total'] ?? 0),
                'vat' => (float) ($payload['total_tax'] ?? 0),
                'shipping_fee' => (float) ($payload['shipping_total'] ?? 0),
                'status' => $this->mapStatus((string) ($payload['status'] ?? 'pending')),
                'note' => 'Synced from WooCommerce order #'.($payload['number'] ?? $wooOrderId).'.',
            ]);
            $order->save();

            $this->syncItems($order, (array) ($payload['line_items'] ?? []));

            return $order->refresh();
        });
    }

    protected function orderDate(array $payload): string
    {
        $created = $payload['date_created'] ?? null;

        if (blank($created)) {
            return now()->toDateString();
        }

        try {
            return Carbon::parse($created)->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }

    protected function resolveCustomer(Company $company, array $payload): Customer
    {
        $billing = (array) ($payload['billing'] ?? []);
        $phone = trim((string) ($billing['phone'] ?? ''));
        $name = trim(collect([$billing['first_name'] ?? null, $billing['last_name'] ?? null])->filter()->implode(' '));
        $email = trim((string) ($billing['email'] ?? '')) ?: null;
        $address = trim(collect([
            $billing['address_1'] ?? null,
            $billing['address_2'] ?? null,
            $billing['city'] ?? null,
            $billing['state'] ?? null,
        ])->filter()->implode(', '));

        // Find-or-create by phone, same convention as the storefront checkout
        // (CheckoutController::createOrder()) — phone is the natural key
        // customers are actually deduplicated on across this app.
        $customer = $phone !== ''
            ? Customer::query()->whereIn('phone', $this->checkoutPolicies->phoneVariants($phone))->first()
            : null;

        $customer ??= new Customer(['phone' => $phone !== '' ? $phone : null]);

        $customer->fill([
            'name' => $name !== '' ? $name : ($customer->name ?: 'WooCommerce Customer'),
            'email' => $email ?? $customer->email,
            'address' => $address !== '' ? $address : $customer->address,
            'customer_type' => $customer->customer_type ?: 'regular',
            'customer_source' => $customer->customer_source ?: 'woocommerce',
            'opening_balance' => $customer->opening_balance ?? 0,
            'is_active' => true,
        ]);
        $customer->save();

        return $customer;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineItems
     */
    protected function syncItems(Order $order, array $lineItems): void
    {
        // Every OrderItem on a WooCommerce-sourced order came from this sync,
        // so a full replace-on-each-delivery is simple and safe — no need to
        // track WooCommerce's own line-item ids to diff against. OrderItem's
        // own saved/deleted hooks keep totals, stock movements, and the
        // customer's balance in sync automatically (see OrderWorkflowService).
        $order->items()->delete();

        foreach ($lineItems as $lineItem) {
            $sku = trim((string) ($lineItem['sku'] ?? ''));
            $product = $sku !== ''
                ? Product::query()->where('company_id', $order->company_id)->where('sku', $sku)->first()
                : null;

            if (! $product) {
                Log::warning('WooCommerce order line item skipped: no matching product SKU.', [
                    'company_id' => $order->company_id,
                    'order_id' => $order->getKey(),
                    'sku' => $sku,
                    'name' => $lineItem['name'] ?? null,
                ]);

                continue;
            }

            $quantity = max(1, (int) ($lineItem['quantity'] ?? 1));
            $lineTotal = (float) ($lineItem['total'] ?? 0);

            $order->items()->create([
                'product_id' => $product->getKey(),
                'quantity' => $quantity,
                'unit_price' => $lineTotal > 0 ? round($lineTotal / $quantity, 2) : (float) $product->sale_price,
            ]);
        }
    }

    protected function mapStatus(string $wooStatus): string
    {
        return self::STATUS_MAP[$wooStatus] ?? Order::STATUS_DRAFT;
    }
}
