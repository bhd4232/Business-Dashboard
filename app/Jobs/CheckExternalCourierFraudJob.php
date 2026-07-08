<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\CustomerRiskService;
use App\Services\CustomerRiskSettingsService;
use App\Services\ExternalCourierFraudService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs the external courier fraud lookup for a storefront order in the
 * background, so a slow/unavailable courier merchant panel never delays
 * or fails checkout. If the phone's cross-courier success ratio is below
 * the configured threshold, a manager review is requested using the
 * existing courier-booking approval gate (CustomerRiskService).
 */
class CheckExternalCourierFraudJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $orderId) {}

    public function handle(
        ExternalCourierFraudService $fraudService,
        CustomerRiskService $riskService,
        CustomerRiskSettingsService $settings,
    ): void {
        $order = Order::query()->with('customer')->find($this->orderId);

        if (! $order || ! $order->customer?->phone) {
            return;
        }

        $result = $fraudService->checkByPhone(
            $order->customer->phone,
            $order->company_id,
            $order->customer_id,
            $order->getKey(),
        );

        $ratio = $result['overall_success_ratio'] ?? null;

        if ($ratio === null || $ratio >= $settings->int('external_fraud_low_ratio_threshold')) {
            return;
        }

        $profile = $riskService->evaluateCustomer($order->customer, $order);
        $riskService->requestReview($order, $profile);
    }
}
