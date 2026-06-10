<?php

namespace App\Models\Sales;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SoPayment extends Model
{
    protected $table = 'so_payments';

    protected $fillable = [
        'sales_order_id',
        'amount_bdt',
        'method',
        'payment_type',
        'reference',
        'payment_date',
        'notes',
        'received_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_bdt'   => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    public static function methodLabel(string $method): string
    {
        return match ($method) {
            'cash'          => 'Cash',
            'bkash'         => 'bKash',
            'nagad'         => 'Nagad',
            'rocket'        => 'Rocket',
            'bank_transfer' => 'Bank Transfer',
            'cheque'        => 'Cheque',
            'other'         => 'Other',
            default         => ucfirst($method),
        };
    }
}
