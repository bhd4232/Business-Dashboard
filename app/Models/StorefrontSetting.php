<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

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
        'trust_strip_delivery',
        'trust_strip_return',
        'trust_strip_payment',
        'offer_title',
        'offer_discount_percent',
        'offer_ends_at',
        'theme_mode',
        'online_payment_enabled',
        'payment_credentials',
        'cod_enabled',
        'delivery_charge_inside',
        'delivery_charge_outside',
        'manual_bkash_number',
        'manual_bkash_instructions',
        'manual_nagad_number',
        'manual_nagad_instructions',
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
        'offer_discount_percent' => 'integer',
        'offer_ends_at' => 'datetime',
        'woocommerce_credentials' => 'encrypted:array',
        'online_payment_enabled' => 'boolean',
        'payment_credentials' => 'encrypted:array',
        'cod_enabled' => 'boolean',
        'delivery_charge_inside' => 'decimal:2',
        'delivery_charge_outside' => 'decimal:2',
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
            $setting->cod_enabled ??= true;
        });

        static::saved(fn (StorefrontSetting $setting) => Cache::forget("storefront-home:{$setting->company_id}"));
        static::deleted(fn (StorefrontSetting $setting) => Cache::forget("storefront-home:{$setting->company_id}"));
    }

    public function hasActiveOffer(): bool
    {
        return filled($this->offer_title) && $this->offer_ends_at && $this->offer_ends_at->isFuture();
    }
}
