<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;
use LogicException;

/**
 * A file kept for transparency against a project, its settlement, an investor
 * payout or a direct-cost line (v3 P1.1). Stored in the owning company's
 * private storage; served only through InvestmentDocumentDownloadController.
 */
class InvestmentDocument extends Model
{
    use BelongsToCompany;

    public const CATEGORIES = [
        'settlement_sheet' => 'Settlement Sheet',
        'investor_report' => 'Investor Report',
        'cost_receipt' => 'Cost Receipt / Invoice',
        'purchase_document' => 'Purchase Document',
        'bank_slip' => 'Bank Slip / Transfer Proof',
        'signed_contract' => 'Signed Contract',
        'other' => 'Other',
    ];

    protected $fillable = ['company_id', 'documentable_type', 'documentable_id', 'file_path', 'file_type', 'category', 'label', 'uploaded_by'];

    protected static function booted(): void
    {
        static::creating(function (self $document): void {
            $document->uploaded_by ??= Auth::id();
        });

        static::saving(function (self $document): void {
            $document->company_id ??= $document->documentable?->company_id;

            if (blank($document->file_path) || ! $document->isDirty('file_path')) {
                return;
            }

            $company = $document->company_id
                ? Company::query()->find($document->company_id)
                : $document->documentable?->company;

            if (! $company || ! str_starts_with((string) $document->file_path, $company->storageRoot().'/private/')) {
                throw new LogicException('Investment documents must use the owning company private storage path.');
            }
        });
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
