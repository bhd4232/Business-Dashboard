<?php

namespace App\Services;

use App\Models\CourierProvider;
use App\Models\CustomerRiskEvent;
use App\Services\CourierFraud\CourierFraudClient;
use App\Services\CourierFraud\PathaoFraudClient;
use App\Services\CourierFraud\RedxFraudClient;
use App\Services\CourierFraud\SteadfastFraudClient;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Looks up a phone number's delivery history across Pathao, Steadfast, and
 * RedX merchant panels via our own CourierFraud HTTP clients. Never throws:
 * a failed/unconfigured courier is simply omitted from the result, so this
 * can never block order creation or courier booking.
 */
class ExternalCourierFraudService
{
    public const CACHE_TTL_HOURS = 24;

    protected const DRIVER_CLIENT_MAP = [
        CourierProvider::DRIVER_PATHAO => PathaoFraudClient::class,
        CourierProvider::DRIVER_STEADFAST => SteadfastFraudClient::class,
        CourierProvider::DRIVER_REDX => RedxFraudClient::class,
    ];

    /**
     * $bypassCache is used by the manual staff-facing "Courier Fraud Check"
     * button so newly added/changed courier credentials take effect
     * immediately instead of waiting out a stale cached result. The
     * background checkout job still uses the cache to avoid repeated
     * merchant-panel logins.
     */
    public function checkByPhone(string $phone, int $companyId, ?int $customerId = null, ?int $orderId = null, bool $bypassCache = false): array
    {
        $normalizedPhone = $this->normalizePhone($phone);

        if ($normalizedPhone === '') {
            return [];
        }

        $cacheKey = "external-courier-fraud:{$companyId}:{$normalizedPhone}";

        if (! $bypassCache && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $result = $this->fetch($normalizedPhone, $companyId);

        // Only cache when at least one courier actually answered — otherwise a
        // missing-credentials or temporary-failure result would stick for 24h.
        if ($result['overall_success_ratio'] !== null || count($result) > 1) {
            Cache::put($cacheKey, $result, now()->addHours(self::CACHE_TTL_HOURS));
            $this->logEvent($companyId, $customerId, $orderId, $result);
        }

        return $result;
    }

    /**
     * The courier merchant panels only accept the local format (01XXXXXXXXX),
     * so +880/880-prefixed numbers are converted back to it.
     */
    protected function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '880')) {
            $digits = '0'.substr($digits, 3);
        } elseif ($digits !== '' && ! str_starts_with($digits, '0')) {
            $digits = '0'.$digits;
        }

        return $digits;
    }

    protected function fetch(string $phone, int $companyId): array
    {
        $result = [];

        foreach (self::DRIVER_CLIENT_MAP as $driver => $clientClass) {
            $stats = $this->checkDriver($driver, $clientClass, $phone, $companyId);

            if ($stats !== null) {
                $result[$driver] = $stats;
            }
        }

        $totalSuccess = collect($result)->sum('success');
        $totalAll = collect($result)->sum('total');
        $result['overall_success_ratio'] = $totalAll > 0 ? round(($totalSuccess / $totalAll) * 100, 2) : null;

        return $result;
    }

    protected function checkDriver(string $driver, string $clientClass, string $phone, int $companyId): ?array
    {
        $provider = CourierProvider::query()
            ->where('company_id', $companyId)
            ->where('driver', $driver)
            ->where('is_active', true)
            ->first();

        $credentials = $provider?->credentials['fraud_check'] ?? null;

        if (! is_array($credentials) || blank($credentials['username'] ?? null) || blank($credentials['password'] ?? null)) {
            return null;
        }

        try {
            /** @var CourierFraudClient $client */
            $client = app($clientClass);

            $stats = $client->checkByPhone($phone, $credentials);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        if (! is_array($stats) || ! is_numeric($stats['total'] ?? null)) {
            return null;
        }

        return [
            'success' => (int) ($stats['success'] ?? 0),
            'cancel' => (int) ($stats['cancel'] ?? 0),
            'total' => (int) ($stats['total'] ?? 0),
        ];
    }

    protected function logEvent(int $companyId, ?int $customerId, ?int $orderId, array $result): void
    {
        try {
            CustomerRiskEvent::query()->create([
                'company_id' => $companyId,
                'customer_id' => $customerId,
                'order_id' => $orderId,
                'event_type' => 'external_courier_fraud_check',
                'metadata' => $result,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
