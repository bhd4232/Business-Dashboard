<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCourierWebhook;
use App\Models\CourierProvider;
use App\Models\CourierWebhookLog;
use App\Services\CourierManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourierWebhookController extends Controller
{
    public function __invoke(Request $request, CourierProvider $provider, CourierManager $couriers): JsonResponse
    {
        abort_unless($provider->is_active, 404);
        $adapter = $couriers->adapter($provider);
        $signatureHeader = (string) ($provider->settings['signature_header'] ?? 'X-Courier-Signature');
        abort_unless($adapter->verifyWebhook($provider, $request->getContent(), $request->header($signatureHeader)), 401);

        $payload = $request->json()->all();
        $deliveryId = (string) ($request->header('X-Webhook-Id')
            ?: ($payload['event_id'] ?? hash('sha256', $request->getContent())));

        $log = CourierWebhookLog::withoutGlobalScopes()->firstOrCreate(
            ['courier_provider_id' => $provider->getKey(), 'delivery_id' => $deliveryId],
            [
                'company_id' => $provider->company_id,
                'event' => $payload['event'] ?? $payload['status'] ?? null,
                'payload' => $payload,
                'status' => 'pending',
            ],
        );

        if ($log->wasRecentlyCreated) {
            ProcessCourierWebhook::dispatch($log->getKey());
        }

        return response()->json(['accepted' => true], 202);
    }
}
