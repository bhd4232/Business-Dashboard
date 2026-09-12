<?php

namespace App\Console\Commands;

use App\Models\CrmSalesFollowUp;
use App\Services\CompanyContext;
use App\Services\Crm\SalesFollowUpService;
use Illuminate\Console\Command;

class ProcessSalesFollowUps extends Command
{
    protected $signature = 'crm:process-sales-follow-ups';

    protected $description = 'Process due CRM sales follow-ups with contact and conversation checks';

    public function handle(CompanyContext $context, SalesFollowUpService $service): int
    {
        CrmSalesFollowUp::withoutGlobalScopes()->where('status', 'pending')->where('due_at', '<=', now())->with('company')->chunkById(50, function ($rows) use ($context, $service): void {
            foreach ($rows as $row) {
                if (! $row->company) {
                    continue;
                }
                $context->set($row->company);
                try {
                    $service->process($row);
                } finally {
                    $context->clear();
                }
            }
        });

        return self::SUCCESS;
    }
}
