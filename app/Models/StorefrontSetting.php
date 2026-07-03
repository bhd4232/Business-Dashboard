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
        'banner_images',
        'whatsapp_number',
        'hero_heading',
        'hero_subheading',
        'hero_cta_label',
        'theme_mode',
        'meta_title',
        'meta_description',
        'is_published',
    ];

    protected $casts = [
        'banner_images' => 'array',
        'is_published' => 'boolean',
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
