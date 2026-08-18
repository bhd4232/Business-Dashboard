<?php

namespace App\Services;

use App\Contracts\PaymentGatewayClient;
use App\Models\StorefrontSetting;
use Illuminate\Validation\ValidationException;

/**
 * Resolves the right PaymentGatewayClient for a company's selected online
 * payment gateway. Mirrors App\Services\CourierManager's own adapter-map
 * resolver idiom rather than constructor-injecting every concrete client,
 * so adding a third gateway later only means one new map entry here.
 */
class PaymentGatewayResolver
{
    /** @var array<string, class-string<PaymentGatewayClient>> */
    protected array $gateways = [
        'zinipay' => ZiniPayClient::class,
        'paystation' => PayStationClient::class,
    ];

    public function byKey(string $gateway): PaymentGatewayClient
    {
        $class = $this->gateways[$gateway] ?? null;

        if ($class === null) {
            throw ValidationException::withMessages(['payment' => "Unknown payment gateway: {$gateway}."]);
        }

        return app($class);
    }

    /** The gateway currently selected for new payments on this setting. */
    public function forSetting(StorefrontSetting $setting): PaymentGatewayClient
    {
        return $this->byKey($setting->online_payment_gateway ?: 'zinipay');
    }

    /**
     * Whether the currently selected gateway (not just "some" gateway) has
     * real credentials configured. Deliberately does not fall back to
     * checking every known gateway — a company that switched from ZiniPay
     * to PayStation but left stale ZiniPay keys in payment_credentials must
     * not be told payment is available when PayStation itself isn't set up.
     */
    public function isConfigured(StorefrontSetting $setting): bool
    {
        return $this->forSetting($setting)::isConfigured($setting);
    }
}
