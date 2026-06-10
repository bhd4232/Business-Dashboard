<?php

namespace App\Enums;

enum StockTransactionType: string
{
    case In              = 'in';
    case Out             = 'out';
    case TransferIn      = 'transfer_in';
    case TransferOut     = 'transfer_out';
    case AdjustmentAdd   = 'adjustment_add';
    case AdjustmentRemove = 'adjustment_remove';
    case ReturnIn        = 'return_in';

    public function label(): string
    {
        return match($this) {
            self::In               => 'Received',
            self::Out              => 'Sold',
            self::TransferIn       => 'Transfer In',
            self::TransferOut      => 'Transfer Out',
            self::AdjustmentAdd    => 'Adjustment (Increase)',
            self::AdjustmentRemove => 'Adjustment (Decrease)',
            self::ReturnIn         => 'Return',
        };
    }

    public function isIncrease(): bool
    {
        return in_array($this, [self::In, self::TransferIn, self::AdjustmentAdd, self::ReturnIn]);
    }
}
