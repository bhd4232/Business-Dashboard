<?php

namespace App\Support;

/**
 * Tap-to-call and tap-to-WhatsApp links for a customer's phone number.
 * Numbers are stored in the local 0-prefixed Bangladesh format
 * (01XXXXXXXXX); WhatsApp needs the country code in front (8801XXXXXXXXX).
 */
class PhoneLinks
{
    /**
     * `tel:` link that opens the device's dialer with the number filled in.
     */
    public static function telUrl(?string $phone): ?string
    {
        $phone = trim((string) $phone);
        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === '') {
            return null;
        }

        return 'tel:'.(str_starts_with($phone, '+') ? '+' : '').$digits;
    }

    /**
     * wa.me link that opens a WhatsApp chat with the number directly (in
     * the WhatsApp app on phones, WhatsApp Web on computers).
     */
    public static function whatsappUrl(?string $phone, ?string $text = null): ?string
    {
        $digits = self::internationalDigits($phone);

        if ($digits === null) {
            return null;
        }

        return "https://wa.me/{$digits}".(filled($text) ? '?text='.rawurlencode($text) : '');
    }

    public static function internationalDigits(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        // 01XXXXXXXXX -> 8801XXXXXXXXX, 1XXXXXXXXX -> 8801XXXXXXXXX.
        if (str_starts_with($digits, '0')) {
            return '88'.$digits;
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '1')) {
            return '880'.$digits;
        }

        return $digits;
    }
}
