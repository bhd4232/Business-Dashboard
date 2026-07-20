<?php

namespace App\Services;

use App\Models\StorefrontSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends customer notifications (abandoned-cart reminders etc.) over a
 * generic HTTP SMS gateway and the Meta Cloud WhatsApp API.
 *
 * Credentials live in storefront_settings.notification_credentials:
 * - sms_api_url: full gateway URL template with {api_key}, {sender_id},
 *   {phone}, {message} placeholders (works with BulkSMSBD-style GET APIs)
 * - sms_api_key, sms_sender_id
 * - whatsapp_token, whatsapp_phone_number_id, whatsapp_template_name,
 *   whatsapp_template_language (default "bn")
 */
class StorefrontNotificationService
{
    public function smsConfigured(StorefrontSetting $setting): bool
    {
        return filled(data_get($setting->notification_credentials, 'sms_api_url'));
    }

    public function sendSms(StorefrontSetting $setting, string $phone, string $message): bool
    {
        $credentials = $setting->notification_credentials ?? [];
        $urlTemplate = (string) ($credentials['sms_api_url'] ?? '');

        if ($urlTemplate === '') {
            return false;
        }

        $url = strtr($urlTemplate, [
            '{api_key}' => rawurlencode((string) ($credentials['sms_api_key'] ?? '')),
            '{sender_id}' => rawurlencode((string) ($credentials['sms_sender_id'] ?? '')),
            '{phone}' => rawurlencode(preg_replace('/\D+/', '', $phone) ?: $phone),
            '{message}' => rawurlencode($message),
        ]);

        try {
            return Http::timeout(20)->get($url)->successful();
        } catch (\Throwable $exception) {
            Log::warning('Storefront SMS send failed', ['error' => $exception->getMessage()]);

            return false;
        }
    }

    public function sendWhatsAppTemplate(StorefrontSetting $setting, string $phone, array $bodyParameters = []): bool
    {
        $credentials = $setting->notification_credentials ?? [];
        $token = (string) ($credentials['whatsapp_token'] ?? '');
        $phoneNumberId = (string) ($credentials['whatsapp_phone_number_id'] ?? '');
        $template = (string) ($credentials['whatsapp_template_name'] ?? '');

        if ($token === '' || $phoneNumberId === '' || $template === '') {
            return false;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => preg_replace('/\D+/', '', $phone),
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => (string) ($credentials['whatsapp_template_language'] ?? 'bn')],
            ],
        ];

        if ($bodyParameters !== []) {
            $payload['template']['components'] = [[
                'type' => 'body',
                'parameters' => array_map(
                    fn (string $value): array => ['type' => 'text', 'text' => $value],
                    $bodyParameters,
                ),
            ]];
        }

        try {
            return Http::timeout(20)
                ->withToken($token)
                ->post("https://graph.facebook.com/v19.0/{$phoneNumberId}/messages", $payload)
                ->successful();
        } catch (\Throwable $exception) {
            Log::warning('Storefront WhatsApp send failed', ['error' => $exception->getMessage()]);

            return false;
        }
    }
}
