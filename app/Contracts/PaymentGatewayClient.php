<?php

namespace App\Contracts;

use App\Models\StorefrontSetting;

/**
 * Shared contract for storefront online-payment gateways (currently ZiniPay
 * and PayStation). Each company selects one gateway (StorefrontSetting::
 * online_payment_gateway) and configures only that gateway's own credentials
 * in the encrypted payment_credentials JSON blob — see App\Services\
 * PaymentGatewayResolver for how a client is resolved for a given setting or
 * an already-created StorefrontPayment.
 */
interface PaymentGatewayClient
{
    public static function isConfigured(StorefrontSetting $setting): bool;

    /**
     * Create a hosted invoice and return the payment URL to redirect the
     * customer to.
     *
     * $merchantReference is a value the caller guarantees is unique (e.g.
     * the StorefrontPayment's own primary key) — gateways that don't echo
     * back their own invoice/payment id (PayStation) use this as the
     * invoice id instead; gateways that do (ZiniPay) simply ignore it.
     *
     * @return array{payment_url: string, invoice_id: ?string}
     */
    public function createPayment(
        StorefrontSetting $setting,
        float $amount,
        string $customerName,
        ?string $customerEmail,
        string $customerPhone,
        string $customerAddress,
        string $redirectUrl,
        string $cancelUrl,
        string $webhookUrl,
        string $merchantReference,
        array $metadata = [],
    ): array;

    /**
     * Server-side verification of an invoice. $transactionId carries a
     * gateway-issued transaction reference when the return/webhook call
     * supplied one (PayStation's retrive-transaction needs it); gateways
     * that don't need it (ZiniPay) ignore it.
     *
     * The returned array is normalized to always include:
     * 'status' (uppercase 'COMPLETED'|'FAILED'|other), 'amount',
     * 'payment_method', 'transaction_id' — regardless of the gateway's own
     * raw field names — so StorefrontPaymentService stays gateway-agnostic.
     *
     * @return array raw (normalized) verify payload
     */
    public function verifyPayment(StorefrontSetting $setting, string $invoiceId, ?string $transactionId = null): array;
}
