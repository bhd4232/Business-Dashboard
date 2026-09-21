<?php

namespace App\Services\Payouts;

use App\Contracts\BeftnFileFormatter;
use App\Models\PayoutBatch;
use Illuminate\Support\Collection;

/**
 * Phase-1 default: a plain, human-readable CSV — beneficiary bank name,
 * branch, routing number, account number, account name, and amount — for
 * the owner to hand-carry (or re-key) into their bank's own corporate
 * internet banking "Bulk Payment" upload, since no bank's exact BEFTN file
 * spec has been confirmed yet. Not a specific bank's required format; see
 * App\Contracts\BeftnFileFormatter.
 */
class GenericBeftnCsvFormatter implements BeftnFileFormatter
{
    public function generate(PayoutBatch $batch, Collection $items): string
    {
        $lines = [
            $this->csvLine([
                'Beneficiary Name', 'Bank Name', 'Branch', 'Routing Number',
                'Account Number', 'Amount (BDT)', 'Reference',
            ]),
        ];

        foreach ($items as $item) {
            $lines[] = $this->csvLine([
                $item->recipient_name,
                $item->recipient_bank_name,
                $item->recipient_branch,
                $item->recipient_routing_number,
                $item->recipient_account_number,
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
