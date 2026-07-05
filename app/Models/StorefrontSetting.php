<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class StorefrontSetting extends Model
{
    use BelongsToCompany;

    public const THEME_MODES = [
        'system' => 'Match visitor system setting',
        'light' => 'Light',
        'dark' => 'Dark',
    ];

    protected $fillable = [
        'company_id',
        'theme_color',
        'logo',
        'logo_dark',
        'banner_images',
        'banner_image_mobile',
        'whatsapp_number',
        'phone_number',
        'hero_heading',
        'hero_subheading',
        'hero_cta_label',
        'theme_mode',
        'online_payment_enabled',
        'payment_credentials',
        'abandoned_cart_reminders_enabled',
        'abandoned_cart_delay_hours',
        'notification_credentials',
        'woocommerce_base_url',
        'woocommerce_credentials',
        'meta_title',
        'meta_description',
        'is_published',
    ];

    protected $casts = [
        'banner_images' => 'array',
        'is_published' => 'boolean',
        'woocommerce_credentials' => 'encrypted:array',
        'online_payment_enabled' => 'boolean',
        'payment_credentials' => 'encrypted:array',
        'abandoned_cart_reminders_enabled' => 'boolean',
        'abandoned_cart_delay_hours' => 'integer',
        'notification_credentials' => 'encrypted:array',
    ];

    protected static function booted(): void
    {
        static::creating(function (StorefrontSetting $setting): void {
            $setting->theme_color ??= '#0F766E';
            $setting->is_published ??= false;
            $setting->theme_mode ??= 'system';
        });
    }
}
