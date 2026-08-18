<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Offer extends Model
{
    use BelongsToCompany;

    public const TYPE_SINGLE = 'single';

    public const TYPE_COMBO = 'combo';

    public const TYPES = [
        self::TYPE_SINGLE => 'Single Offer',
        self::TYPE_COMBO => 'Combo Offer',
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_PUBLISHED => 'Published',
        self::STATUS_ARCHIVED => 'Archived',
    ];

    public const PRICE_MODE_AUTO_SUM = 'auto_sum';

    public const PRICE_MODE_MANUAL = 'manual';

    public const PRICE_MODES = [
        self::PRICE_MODE_AUTO_SUM => 'Sum of component products',
        self::PRICE_MODE_MANUAL => 'Manual price',
    ];

    public const DISCOUNT_PERCENT = 'percent';

    public const DISCOUNT_FLAT = 'flat';

    public const DISCOUNT_TYPES = [
        self::DISCOUNT_PERCENT => 'Percent',
        self::DISCOUNT_FLAT => 'Flat amount',
    ];

    /** Block types allowed in the `blocks` JSON column (Filament repeater + AI generator + Blade renderer all agree on this list). */
    public const BLOCK_TYPES = [
        'hero' => 'Hero banner',
        'product_gallery' => 'Product gallery',
        'usp_list' => 'Why buy (USP list)',
        'how_to_buy' => 'How to buy',
        'faq' => 'FAQ',
        'testimonials' => 'Testimonials',
        'rich_text' => 'Custom text/HTML',
    ];

    protected $fillable = [
        'company_id', 'type', 'title', 'slug', 'status', 'short_description',
        'price_mode', 'manual_price', 'discount_type', 'discount_value',
        'blocks', 'is_ai_generated', 'ai_generated_at',
        'meta_title', 'meta_description', 'cover_image', 'online_payment_required',
    ];

    protected $casts = [
        'blocks' => 'array',
        'is_ai_generated' => 'boolean',
        'ai_generated_at' => 'datetime',
        'online_payment_required' => 'boolean',
        'manual_price' => 'decimal:2',
        'discount_value' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Offer $offer): void {
            $offer->slug = static::normalizeSlug($offer->slug ?: $offer->title);
            $offer->status ??= self::STATUS_DRAFT;
            $offer->price_mode ??= self::PRICE_MODE_AUTO_SUM;
        });

        static::saving(function (Offer $offer): void {
            $offer->slug = static::normalizeSlug($offer->slug ?: $offer->title);
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(OfferItem::class)->orderBy('sort_order');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * Approved reviews for this offer's own component products. There is no
     * `offer_id` column on `product_reviews` (reviews belong to a Product,
     * not an Offer) — the testimonials block always resolves reviews through
     * the offer's component products instead.
     */
    public function approvedReviews()
    {
        return ProductReview::query()
            ->approved()
            ->whereIn('product_id', $this->items->pluck('product_id'));
    }

    public static function normalizeSlug(?string $slug): string
    {
        return Str::of($slug ?: 'offer')
            ->trim()
            ->slug()
            ->limit(120, '')
            ->toString() ?: 'offer';
    }
}
