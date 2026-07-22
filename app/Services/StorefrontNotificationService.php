<?php

namespace App\Services;

use App\Models\ConversationChannel;
use App\Models\StorefrontSetting;
use App\Services\Meta\MetaGraphService;
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
    public function __construct(protected MetaGraphService $meta) {}

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
        $template = (string) ($credentials['whatsapp_template_name'] ?? '');
        $selectedChannelId = $credentials['whatsapp_channel_id'] ?? null;
        $channelQuery = ConversationChannel::withoutGlobalScopes()
            ->where('company_id', $setting->company_id)
            ->where('provider', 'whatsapp')
            ->where('is_active', true);
        $channel = filled($selectedChannelId)
            ? $channelQuery->whereKey($selectedChannelId)->first()
            : null;

        if (filled($selectedChannelId) && ! $channel) {
            Log::warning('Storefront WhatsApp reminder channel is unavailable.', [
                'company_id' => $setting->company_id,
                'channel_id' => $selectedChannelId,
            ]);

            return false;
        }

        // Credentials are selected atomically. Never combine one field from a
        // selected channel with another field from the legacy fallback.
        if ($channel) {
            $token = (string) $channel->access_token;
            $phoneNumberId = (string) $channel->external_id;

            if ($token === '' || $phoneNumberId === '') {
                $channel->recordDiagnosticError('The selected WhatsApp channel is missing its access token or Phone Number ID. Complete and test the channel before sending storefront templates.', 'outbound');

                return false;
            }
        } else {
            // Backward-compatible fallback for stores not yet migrated to the
            // centralized Chat Channel credentials.
            $token = (string) ($credentials['whatsapp_token'] ?? '');
            $phoneNumberId = (string) ($credentials['whatsapp_phone_number_id'] ?? '');
        }

        if ($token === '' || $phoneNumberId === '' || $template === '') {
            return false;
        }

        try {
            $messageId = $this->meta->sendWhatsAppTemplate(
                $phoneNumberId,
                $token,
                preg_replace('/\D+/', '', $phone) ?: $phone,
                $template,
                (string) ($credentials['whatsapp_template_language'] ?? 'bn'),
                $bodyParameters,
            );
        } catch (\Throwable $exception) {
            Log::warning('Storefront WhatsApp send failed', ['error' => $exception->getMessage()]);

            try {
                $channel?->recordDiagnosticError($exception->getMessage(), 'outbound');
            } catch (\Throwable $diagnosticException) {
                Log::warning('Storefront WhatsApp diagnostic could not be saved.', [
                    'exception' => $diagnosticException::class,
                ]);
            }

            return false;
        }

        if ($channel) {
            try {
                $channel->markOutboundSent();
            } catch (\Throwable $exception) {
                // Meta already accepted the template. Reporting failure here
                // could make the reminder scheduler send a duplicate.
                Log::warning('Storefront WhatsApp template was accepted but diagnostics could not be updated.', [
                    'company_id' => $setting->company_id,
                    'channel_id' => $channel->getKey(),
                    'exception' => $exception::class,
                ]);
            }
        }

        return true;
    }
}
