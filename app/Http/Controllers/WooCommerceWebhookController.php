<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\WooCommerceOrderSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives WooCommerce's native order.created/order.updated/order.deleted
 * webhooks (WordPress admin: WooCommerce → Settings → Advanced → Webhooks)
 * and hands the payload to WooCommerceOrderSyncService. See
 * 09_DASHBOARD_WOOCOMMERCE_SECURITY_PLAN.md step 2 for the design.
 */
class WooCommerceWebhookController extends Controller
{
    public function __invoke(Request $request, Company $company, WooCommerceOrderSyncService $sync): JsonResponse
    {
        $setting = $company->storefrontSetting;
        $secret = (string) data_get($setting?->woocommerce_credentials, 'webhook_secret');

        abort_if($secret === '', 404);

        $signature = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));

        abort_unless(hash_equals($signature, (string) $request->header('X-WC-Webhook-Signature')), 403);

        $topic = (string) $request->header('X-WC-Webhook-Topic');
        $payload = (array) $request->json()->all();

        // WooCommerce sends a harmless ping payload (no "id") when a webhook
        // is first created — nothing to sync, just acknowledge it.
        if (blank($payload)) {
            return response()->json(['ok' => true]);
        }

        try {
            match (true) {
                str_starts_with($topic, 'order.') => $sync->handleOrderEvent($company, $topic, $payload),
                default => Log::info('WooCommerce webhook received for an unhandled topic.', [
                    'company' => $company->getKey(),
                    'topic' => $topic,
                ]),
            };
        } catch (\Throwable $exception) {
            Log::warning('WooCommerce webhook processing failed', [
                'company' => $company->getKey(),
                'topic' => $topic,
                'error' => $exception->getMessage(),
            ]);

            // Non-2xx makes WooCommerce retry the delivery later.
            return response()->json(['ok' => false], 500);
        }

        return response()->json(['ok' => true]);
    }
}
