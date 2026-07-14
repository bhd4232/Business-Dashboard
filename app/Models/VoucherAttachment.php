<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherAttachment extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'voucher_id',
        'file_path',
        'file_type',
        'label',
    ];

    protected static function booted(): void
    {
        static::creating(function (VoucherAttachment $attachment): void {
            $attachment->company_id ??= $attachment->voucher?->company_id;
        });
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }
}
