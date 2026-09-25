<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One AI Expense Scan upload: the photographed note(s) and/or pasted text,
 * plus the draft lines the AI read out of them. Nothing here touches the ledger — lines only
 * become real Expenses when a reviewer publishes the scan
 * (ExpenseScanPublisher).
 */
class ExpenseScan extends Model
{
    use BelongsToCompany;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    public const STATUS_PUBLISHED = 'published';

    public const STATUSES = [
        self::STATUS_PENDING => 'Queued',
        self::STATUS_PROCESSING => 'Reading',
        self::STATUS_READY => 'Ready for review',
        self::STATUS_FAILED => 'Failed',
        self::STATUS_PUBLISHED => 'Published',
    ];

    public const MAX_IMAGES = 5;

    public const MAX_TEXT_LENGTH = 10000;

    protected $fillable = [
        'company_id',
        'user_id',
        'default_account_id',
        'image_paths',
        'source_text',
        'status',
        'error_message',
        'api_format',
        'model',
        'ai_response',
        'processed_at',
        'published_at',
        'published_by',
    ];

    protected $casts = [
        'image_paths' => 'array',
        'ai_response' => 'array',
        'processed_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function defaultAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_account_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ExpenseScanItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            self::STATUS_PUBLISHED => 'success',
            self::STATUS_READY => 'warning',
            self::STATUS_FAILED => 'danger',
            default => 'info',
        };
    }

    /**
     * Queued or being read. A read that has not finished within 10 minutes
     * (worker down, job killed) stops counting as busy so it can be retried.
     */
    public function isBusy(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true)
            && ($this->updated_at === null || $this->updated_at->gt(now()->subMinutes(10)));
    }

    /** @return array<int, string> */
    public function imagePaths(): array
    {
        return array_values(array_filter((array) $this->image_paths, fn ($path): bool => is_string($path) && $path !== ''));
    }
}
