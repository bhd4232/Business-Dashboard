<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\GeneratesSequentialNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FundTransfer extends Model
{
    use BelongsToCompany, GeneratesSequentialNumber;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_REJECTED => 'Rejected',
    ];

    protected $fillable = [
        'company_id',
        'transfer_number',
        'from_account_id',
        'to_account_id',
        'amount',
        'status',
        'remarks',
        'requested_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (FundTransfer $transfer): void {
            $transfer->transfer_number ??= static::nextTransferNumber();
            $transfer->status ??= self::STATUS_PENDING;
        });
    }

    protected function sequentialNumberColumn(): string
    {
        return 'transfer_number';
    }

    public static function nextTransferNumber(): string
    {
        return 'FT-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
