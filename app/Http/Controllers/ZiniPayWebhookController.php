<?php

namespace App\Http\Controllers;

use App\Models\StorefrontPayment;
use App\Services\ZiniPayClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ZiniPayWebhookController extends Controller
{
    public function __invoke(Request $request, StorefrontPayment $payment, ZiniPayClient $zinipay): JsonResponse
    {
        $invoiceId = trim((string) ($request->input('invoice_id') ?: $payment->invoice_id));

        if ($invoiceId === '' || $payment->status === StorefrontPayment::STATUS_COMPLETED) {
            return response()->json(['ok' => true]);
        }

        $setting = $payment->company?->storefrontSetting;

        if (! $setting || ! ZiniPayClient::isConfigured($setting)) {
            return response()->json(['ok' => false], 422);
        }

        // Never trust the webhook body alone; confirm with the verify API.
        try {
            $verified = $zinipay->verifyPayment($setting, $invoiceId);
        } catch (\RuntimeException $exception) {
            Log::warning('ZiniPay webhook verification failed', ['payment' => $payment->getKey(), 'error' => $exception->getMessage()]);

            return response()->json(['ok' => false], 502);
        }

        $status = strtoupper((string) ($verified['status'] ?? ''));
        $amountMatches = abs(((float) ($verified['amount'] ?? 0)) - (float) $payment->amount) < 0.01;

        if ($status === 'COMPLETED' && $amountMatches) {
            $payment->update([
                'status' => StorefrontPayment::STATUS_COMPLETED,
                'invoice_id' => $invoiceId,
                'payment_method' => $verified['payment_method'] ?? null,
                'transaction_id' => $verified['transaction_id'] ?? null,
                'payload' => $verified,
            ]);
        } elseif ($status === 'FAILED') {
            $payment->update([
                'status' => StorefrontPayment::STATUS_FAILED,
                'payload' => $verified,
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
