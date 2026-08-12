<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\StorefrontSetting;

class StorefrontPaymentEligibilityService
{
    public function __construct(protected ExternalCourierFraudService $courierFraud) {}

    /**
     * Build one server-authoritative advance decision from the existing
     * pre-order/new-customer rules and the optional courier-history rule.
     *
     * @return array{
     *     required_advance: float,
     *     reasons: array<string, float>,
     *     courier_success_ratio: float|null,
     *     courier_lookup_status: string
     * }
     */
    public function decide(
        Company $company,
        StorefrontSetting $setting,
        string $phone,
        float $cartTotal,
        float $preorderAdvance,
        float $deliveryAdvance,
        ?Customer $customer = null,
    ): array {
        $cartTotal = max(0, $cartTotal);
        $reasons = [];

        if ($preorderAdvance > 0) {
            $reasons['preorder'] = $this->clamp($preorderAdvance, $cartTotal);
        }

        if ($deliveryAdvance > 0) {
            $reasons['new_customer_delivery'] = $this->clamp($deliveryAdvance, $cartTotal);
        }

        $ratio = null;
        $lookupStatus = 'disabled';

        if ((bool) $setting->risk_payment_enabled) {
            $result = $this->courierFraud->checkByPhone(
                $phone,
                $company->getKey(),
                $customer?->getKey(),
            );
            $providerResults = collect($result)->except('overall_success_ratio');

            if ($providerResults->isEmpty()) {
                // Missing credentials and temporary provider failures must
                // never turn into a mandatory payment.
                $lookupStatus = 'unavailable';
            } else {
                $historyTotal = (int) $providerResults->sum('total');
                $ratio = is_numeric($result['overall_success_ratio'] ?? null)
                    ? (float) $result['overall_success_ratio']
                    : null;

                if ($historyTotal === 0) {
                    $lookupStatus = 'no_history';

                    if ($setting->risk_payment_zero_history_action === StorefrontSetting::RISK_ZERO_HISTORY_REQUIRE_ADVANCE) {
                        $reasons['courier_no_history'] = $this->riskAdvance($setting, $cartTotal);
                    }
                } elseif ($ratio !== null && $ratio < (int) $setting->risk_payment_success_ratio_threshold) {
                    $lookupStatus = 'low_success_ratio';
                    $reasons['courier_low_success_ratio'] = $this->riskAdvance($setting, $cartTotal);
                } else {
                    $lookupStatus = 'eligible';
                }
            }
        }

        $reasons = array_filter($reasons, fn (float $amount): bool => $amount > 0);

        return [
            // Rules are alternatives, not cumulative charges. Taking the
            // largest required amount prevents double-charging one checkout.
            'required_advance' => $this->clamp($reasons === [] ? 0 : max($reasons), $cartTotal),
            'reasons' => $reasons,
            'courier_success_ratio' => $ratio,
            'courier_lookup_status' => $lookupStatus,
        ];
    }

    protected function riskAdvance(StorefrontSetting $setting, float $cartTotal): float
    {
        $amount = $setting->risk_payment_advance_type === StorefrontSetting::RISK_ADVANCE_PERCENT
            ? $cartTotal * max(0, min(100, (int) $setting->risk_payment_advance_percent)) / 100
            : max(0, (float) $setting->risk_payment_advance_amount);

        return $this->clamp($amount, $cartTotal);
    }

    protected function clamp(float $amount, float $cartTotal): float
    {
        return round(min(max(0, $amount), max(0, $cartTotal)), 2);
    }
}
