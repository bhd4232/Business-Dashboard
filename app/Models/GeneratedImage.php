<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Services\CompanyStorageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One Image Generation request and its result(s). Created with
 * `status = queued` by the ImageGeneration page, advanced by
 * GenerateImageJob. Each finished output file is also registered in the
 * company's Media Hub (App\Models\Media) so it is pickable from every
 * "Select From Media" field — this table keeps the generation-specific
 * metadata the Media row does not (prompt, enhancement trail, provider
 * profile, status, errors).
 *
 * Company-owned (`BelongsToCompany` + `CompanyScope`); registered in
 * MultiCompanyIsolationTest per the CLAUDE.md contract.
 */
class GeneratedImage extends Model
{
    use BelongsToCompany;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /** @var array<string, string> */
    public const CONTEXTS = [
        'product_photo' => 'Product Photo',
        'ad_creative' => 'Ad Creative',
        'landing_banner' => 'Landing / Offer Banner',
        'video_reference' => 'Video Reference',
        'general' => 'General',
    ];

    public const TOOL_IMAGE_GENERATION = 'image_generation';

    public const OPERATION_GENERATE = 'generate';

    public const OPERATION_IMAGE_TO_IMAGE = 'image_to_image';

    public const OPERATION_BACKGROUND_REMOVAL = 'background_removal';

    /** @var array<string, string> */
    public const OPERATIONS = [
        self::OPERATION_GENERATE => 'Generated',
        self::OPERATION_IMAGE_TO_IMAGE => 'Image-to-image',
        self::OPERATION_BACKGROUND_REMOVAL => 'Background removed',
    ];

    public const REVIEW_NOT_REQUIRED = 'not_required';

    public const REVIEW_PENDING = 'pending';

    public const REVIEW_APPROVED = 'approved';

    public const REVIEW_REJECTED = 'rejected';

    /** @var array<string, string> */
    public const REVIEW_STATUSES = [
        self::REVIEW_NOT_REQUIRED => 'No review needed',
        self::REVIEW_PENDING => 'Pending review',
        self::REVIEW_APPROVED => 'Approved',
        self::REVIEW_REJECTED => 'Rejected',
    ];

    /**
     * Aspect-ratio presets offered on the ImageGeneration page. `size` is the
     * pixel size handed to providers that want one (OpenAI); `ratio` is
     * handed to providers that want a ratio (Imagen, Stability). Providers
     * snap anything they do not natively support to their nearest option.
     *
     * @var array<string, array{label: string, size: string}>
     */
    public const ASPECT_RATIOS = [
        '1:1' => ['label' => 'Square · 1:1', 'size' => '1024x1024'],
        '4:5' => ['label' => 'Portrait · 4:5', 'size' => '1024x1280'],
        '16:9' => ['label' => 'Landscape · 16:9', 'size' => '1344x768'],
        '9:16' => ['label' => 'Story · 9:16', 'size' => '768x1344'],
        '3:2' => ['label' => 'Classic · 3:2', 'size' => '1216x832'],
    ];

    public const DEFAULT_ASPECT_RATIO = '1:1';

    public const MAX_VARIATIONS = 4;

    protected $fillable = [
        'company_id',
        'user_id',
        'tool',
        'context',
        'operation',
        'original_prompt',
        'prompt',
        'provider_profile_id',
        'provider_label',
        'api_format',
        'model',
        'reference_image_path',
        'aspect_ratio',
        'variations_requested',
        'output_paths',
        'linked_type',
        'linked_id',
        'is_video_reference',
        'is_favorite',
        'estimated_cost',
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'status',
        'error_message',
        'provider_response',
        'generated_at',
    ];

    protected $attributes = [
        'operation' => self::OPERATION_GENERATE,
        'review_status' => self::REVIEW_NOT_REQUIRED,
    ];

    protected $casts = [
        'output_paths' => 'array',
        'provider_response' => 'array',
        'variations_requested' => 'integer',
        'is_video_reference' => 'boolean',
        'is_favorite' => 'boolean',
        'estimated_cost' => 'decimal:4',
        'reviewed_at' => 'datetime',
        'generated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function linked(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Finished generations flagged (Phase 3) as reference images for the
     * future AI Video Generation tool — the only listing that tool needs.
     */
    public function scopeVideoReferences(Builder $query): Builder
    {
        return $query->where('is_video_reference', true);
    }

    /**
     * Starred generations (Phase 4) — the generation library's favourites,
     * which double as reusable prompt templates.
     */
    public function scopeFavorites(Builder $query): Builder
    {
        return $query->where('is_favorite', true);
    }

    /** A completed generation the library can show a thumbnail for and reuse. */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /** Generations awaiting a reviewer's decision (Phase 5 approval workflow). */
    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where('review_status', self::REVIEW_PENDING);
    }

    /** Rows created in the current calendar month — the usage-cap window. */
    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->where('created_at', '>=', now()->startOfMonth());
    }

    /**
     * True when this image may be attached to a product / offer. A generation
     * a reviewer has not yet cleared (or has rejected) is held back.
     */
    public function isAttachable(): bool
    {
        return in_array($this->review_status ?? self::REVIEW_NOT_REQUIRED, [self::REVIEW_NOT_REQUIRED, self::REVIEW_APPROVED], true);
    }

    public function reviewStatusLabel(): string
    {
        return self::REVIEW_STATUSES[$this->review_status] ?? self::REVIEW_STATUSES[self::REVIEW_NOT_REQUIRED];
    }

    /** True when this row came from an image-to-image or background-removal run. */
    public function isDerived(): bool
    {
        return ($this->operation ?? self::OPERATION_GENERATE) !== self::OPERATION_GENERATE;
    }

    public function operationLabel(): string
    {
        return self::OPERATIONS[$this->operation] ?? self::OPERATIONS[self::OPERATION_GENERATE];
    }

    /**
     * Display name of the record this generation was attached to
     * (Product / Offer), or null if it was never attached.
     */
    public function linkedLabel(): ?string
    {
        $linked = $this->linked;

        return match (true) {
            $linked instanceof Product => $linked->name,
            $linked instanceof Offer => $linked->title,
            default => null,
        };
    }

    /**
     * Public URLs for every stored output, in order. Resolved through
     * CompanyStorageService so an R2-backed company gets CDN URLs and a
     * local one gets local URLs, same as every other image in the app.
     *
     * @return array<int, string>
     */
    public function outputUrls(): array
    {
        $storage = app(CompanyStorageService::class);

        return collect($this->output_paths ?? [])
            ->map(fn (string $path): ?string => $storage->publicUrl($path, $this->company))
            ->filter()
            ->values()
            ->all();
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true);
    }

    public function contextLabel(): string
    {
        return self::CONTEXTS[$this->context] ?? self::CONTEXTS['general'];
    }

    /** Pixel size for the current aspect ratio, for providers that want "WxH". */
    public function pixelSize(): string
    {
        return self::ASPECT_RATIOS[$this->aspect_ratio]['size']
            ?? self::ASPECT_RATIOS[self::DEFAULT_ASPECT_RATIO]['size'];
    }
}
