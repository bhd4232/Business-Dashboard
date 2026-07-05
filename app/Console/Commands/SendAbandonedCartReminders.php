<?php

namespace App\Console\Commands;

use App\Models\StorefrontCartRecord;
use App\Models\StorefrontSetting;
use App\Services\StorefrontNotificationService;
use Illuminate\Console\Command;

class SendAbandonedCartReminders extends Command
{
    protected $signature = 'storefront:send-abandoned-cart-reminders';

    protected $description = 'Send SMS/WhatsApp reminders for abandoned storefront carts with a known customer phone';

    public function handle(StorefrontNotificationService $notifications): int
    {
        $settings = StorefrontSetting::withoutGlobalScopes()
            ->with('company')
            ->where('is_published', true)
            ->where('abandoned_cart_reminders_enabled', true)
            ->get();

        $sent = 0;

        foreach ($settings as $setting) {
            $delayHours = max(1, (int) ($setting->abandoned_cart_delay_hours ?: 6));

            $carts = StorefrontCartRecord::withoutGlobalScopes()
                ->where('company_id', $setting->company_id)
                ->where('status', StorefrontCartRecord::STATUS_ACTIVE)
                ->whereNotNull('phone')
                ->whereNull('reminded_at')
                ->where('updated_at', '<=', now()->subHours($delayHours))
                ->get();

            foreach ($carts as $cart) {
                $companyName = $setting->company?->name ?? 'our store';
                $message = "Hello".($cart->customer_name ? " {$cart->customer_name}" : '').", you left items in your cart at {$companyName}. Complete your order any time - your cart is saved.";

                $smsSent = $notifications->sendSms($setting, $cart->phone, $message);
                $whatsAppSent = $notifications->sendWhatsAppTemplate($setting, $cart->phone, [
                    $cart->customer_name ?: 'Customer',
                    $companyName,
                ]);

                if ($smsSent || $whatsAppSent) {
                    $cart->update([
                        'status' => StorefrontCartRecord::STATUS_REMINDED,
                        'reminded_at' => now(),
                    ]);
                    $sent++;
                }
            }
        }

        $this->info("Abandoned cart reminders sent: {$sent}.");

        return self::SUCCESS;
    }
}
