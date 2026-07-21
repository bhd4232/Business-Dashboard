<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

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

        static::saving(function (VoucherAttachment $attachment): void {
            if (! $attachment->isDirty('file_path') || blank($attachment->file_path)) {
                return;
            }

            $voucher = $attachment->voucher()
                ->withoutGlobalScopes()
                ->with('company')
                ->first();
            $company = $attachment->company_id
                ? Company::query()->find($attachment->company_id)
                : $voucher?->company;
            $path = trim((string) $attachment->file_path);

            if (! $company) {
                throw new LogicException('Voucher attachments require an owning company.');
            }

            if (str_starts_with($path, $company->storageRoot().'/private/')) {
                return;
            }

            if (! str_starts_with($path, 'companies/')
                && LegacyPrivateStoragePath::allows($path, (int) $company->getKey())) {
                return;
            }

            throw new LogicException('Voucher attachments must use a private path owned by their company.');
        });
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }
}
