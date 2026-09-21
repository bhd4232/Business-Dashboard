<?php

namespace App\Services\Payouts;

use App\Contracts\BeftnFileFormatter;
use App\Models\PayoutBatch;
use Illuminate\Support\Collection;

/**
 * Phase-1 default for every mfs_* method: a plain name/number/amount CSV —
 * no MFS provider's disbursement API is integrated yet (that needs a
 * separate merchant/B2C agreement with bKash/Nagad/Rocket, confirmed with
 * the owner first). Staff hand-send each amount from their own MFS
 * merchant app/wallet, then reconcile it in the batch as usual.
 */
class GenericMfsListFormatter implements BeftnFileFormatter
{
    public function generate(PayoutBatch $batch, Collection $items): string
    {
        $lines = [$this->csvLine(['Recipient Name', 'MFS Number', 'Amount (BDT)', 'Reference'])];

        foreach ($items as $item) {
            $lines[] = $this->csvLine([
                $item->recipient_name,
                $item->recipient_mfs_number,
                number_format((float) $item->amount, 2, '.', ''),
                "{$batch->batch_number}-{$item->id}",
            ]);
        }

        return implode("\r\n", $lines)."\r\n";
    }

    /** @param  list<string|null>  $fields */
    private function csvLine(array $fields): string
    {
        return implode(',', array_map($this->csvField(...), $fields));
    }

    private function csvField(?string $value): string
    {
        $value = (string) $value;

        return str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")
            ? '"'.str_replace('"', '""', $value).'"'
            : $value;
    }
}
