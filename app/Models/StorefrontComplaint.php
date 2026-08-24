<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorefrontComplaint extends Model
{
    use BelongsToCompany;

    public const STATUS_NEW = 'new';

    public const STATUS_REVIEWING = 'reviewing';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_NEW => 'New',
        self::STATUS_REVIEWING => 'Reviewing',
        self::STATUS_RESOLVED => 'Resolved',
        self::STATUS_CLOSED => 'Closed',
    ];

    public const ISSUE_TYPES = [
        'damaged' => 'Damaged product',
        'quantity_mismatch' => 'Quantity mismatch',
        'wrong_product' => 'Wrong product',
        'other' => 'Other issue',
    ];

    protected $fillable = [
        'company_id',
        'order_id',
        'complaint_number',
        'order_reference',
        'customer_name',
        'phone',
        'issue_type',
        'details',
        'status',
        'internal_note',
        'telegram_status',
        'telegram_message_id',
        'telegram_error',
        'telegram_sent_at',
    ];

    protected $casts = [
        'telegram_sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (StorefrontComplaint $complaint): void {
            $complaint->status ??= self::STATUS_NEW;
            $complaint->complaint_number ??= static::nextNumber($complaint->company_id);
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public static function nextNumber(?int $companyId): string
    {
        $prefix = 'CMP-'.now()->format('Ymd').'-';
        $last = static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('complaint_number', 'like', $prefix.'%')
            // See Order::nextOrderNumber() — length-first keeps the sort
            // numerically correct once the daily sequence passes 9999.
            ->orderByRaw('LENGTH(complaint_number) desc')
            ->orderByDesc('complaint_number')
            ->value('complaint_number');

        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
