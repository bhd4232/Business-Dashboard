<?php

namespace App\Services;

use App\Models\StorefrontComplaint;
use App\Models\StorefrontSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TelegramComplaintService
{
    public function send(StorefrontComplaint $complaint, StorefrontSetting $setting): bool
    {
        if (! $setting->complaint_telegram_enabled) {
            $complaint->update(['telegram_status' => 'disabled']);

            return false;
        }

        $token = trim((string) data_get($setting->telegram_credentials, 'bot_token'));
        $chatId = trim((string) data_get($setting->telegram_credentials, 'chat_id'));

        if ($token === '' || $chatId === '') {
            $complaint->update([
                'telegram_status' => 'failed',
                'telegram_error' => 'Telegram bot token or chat ID is not configured.',
            ]);

            return false;
        }

        $response = Http::timeout(15)->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $this->message($complaint),
            'disable_web_page_preview' => true,
        ]);

        if ($response->failed() || ! $response->json('ok')) {
            $message = (string) $response->json('description', 'Telegram rejected the complaint notification.');
            $complaint->update(['telegram_status' => 'failed', 'telegram_error' => $message]);

            throw new RuntimeException($message);
        }

        $complaint->update([
            'telegram_status' => 'sent',
            'telegram_message_id' => (string) $response->json('result.message_id'),
            'telegram_error' => null,
            'telegram_sent_at' => now(),
        ]);

        return true;
    }

    protected function message(StorefrontComplaint $complaint): string
    {
        $type = StorefrontComplaint::ISSUE_TYPES[$complaint->issue_type] ?? $complaint->issue_type;

        return implode("\n", [
            "New customer complaint — {$complaint->company?->name}",
            "Complaint: {$complaint->complaint_number}",
            "Order/Invoice/Phone: {$complaint->order_reference}",
            'Matched order: '.($complaint->order?->order_number ?: 'Not matched automatically'),
            'Customer: '.($complaint->customer_name ?: 'Not provided'),
            'Phone: '.($complaint->phone ?: 'Not provided'),
            "Issue: {$type}",
            "Details: {$complaint->details}",
        ]);
    }
}
