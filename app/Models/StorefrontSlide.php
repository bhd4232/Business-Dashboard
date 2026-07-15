<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class StorefrontSlide extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'image',
        'image_mobile',
        'heading',
        'subheading',
        'cta_label',
        'cta_url',
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

    public function scopeActiveNow(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public static function forCompany(int $companyId): \Illuminate\Support\Collection
    {
        return Cache::remember("storefront-home:{$companyId}", now()->addMinutes(10), fn () => static::query()
            ->where('company_id', $companyId)
            ->activeNow()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get());
    }
}
