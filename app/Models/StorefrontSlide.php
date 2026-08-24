<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\StorefrontThemeRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class StorefrontSlide extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'theme',
        'template',
        'image',
        'image_mobile',
        'heading',
        'subheading',
        'cta_label',
        'cta_url',
        'product_id',
        'sort_order',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(fn (StorefrontSlide $slide) => Cache::forget("storefront-home:{$slide->company_id}"));
        static::deleted(fn (StorefrontSlide $slide) => Cache::forget("storefront-home:{$slide->company_id}"));
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class)->withoutGlobalScopes();
    }

    public function scopeActiveNow(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public static function forCompany(int $companyId): Collection
    {
        return Cache::remember("storefront-home:{$companyId}", now()->addMinutes(10), fn () => static::query()
            ->where('company_id', $companyId)
            ->activeNow()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get());
    }

    /**
     * Slides tagged for the company's currently active theme/template, so a
     * banner built for one theme's ratio never shows under another. Slides
     * saved before this tagging existed have a null theme/template and stay
     * a fallback for every theme until re-tagged.
     */
    public static function forCompanyTheme(int $companyId, ?string $theme, ?string $template): Collection
    {
        $theme = StorefrontThemeRegistry::normalizeTheme($theme);
        $template = StorefrontThemeRegistry::normalizeTemplate($theme, $template);

        $all = static::forCompany($companyId);

        $matched = $all->where('theme', $theme)->where('template', $template)->values();

        return $matched->isNotEmpty() ? $matched : $all->whereNull('theme')->values();
    }
}
