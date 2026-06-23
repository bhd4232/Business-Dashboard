<?php

namespace App\Jobs;

use App\Models\CourierBooking;
use App\Models\CourierWebhookLog;
use App\Services\CompanyContext;
use App\Services\CourierManager;
use App\Services\CourierService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessCourierWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [10, 30, 120, 300];

    public function __construct(public int $webhookLogId) {}

    public function handle(CourierManager $couriers, CourierService $service, CompanyContext $context): void
    {
        $log = CourierWebhookLog::withoutGlobalScopes()->with('provider')->findOrFail($this->webhookLogId);
        $context->set($log->provider->company);
        $log->increment('attempts');

        try {
            $payload = $log->payload;
            $trackingId = $payload['tracking_code'] ?? $payload['tracking_id'] ?? $payload['consignment_id'] ?? null;
            $booking = CourierBooking::query()
                ->where('courier_provider_id', $log->courier_provider_id)
                ->where(fn ($query) => $query->where('tracking_id', $trackingId)->orWhere('provider_reference', $trackingId))
                ->firstOrFail();
            $status = $couriers->adapter($log->provider)->webhookStatus($payload);

            if (! $status || ! array_key_exists($status, CourierBooking::STATUSES)) {
                throw new \RuntimeException('Webhook did not contain a recognized delivery status.');
            }

            $service->updateStatus($booking, $status, 'Updated by verified courier webhook.');
            $log->forceFill(['status' => 'processed', 'processed_at' => now(), 'error' => null])->save();
        } catch (Throwable $exception) {
            $log->forceFill(['status' => 'failed', 'error' => str($exception->getMessage())->limit(2000)])->save();
            throw $exception;
        } finally {
            $context->clear();
        }
    }
}
