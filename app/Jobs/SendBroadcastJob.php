<?php

namespace App\Jobs;

use App\Models\Broadcast;
use App\Services\CompanyContext;
use App\Services\Crm\BroadcastService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendBroadcastJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1000;

    public int $maxExceptions = 1;

    /** Generous ceiling for a large recipient list; send() is safe to re-run. */
    public int $timeout = 70;

    public int $uniqueFor = 1800;

    public function __construct(public int $broadcastId) {}

    public function uniqueId(): string
    {
        return (string) $this->broadcastId;
    }

    public function handle(CompanyContext $context, BroadcastService $broadcasts): void
    {
        $broadcast = Broadcast::withoutGlobalScopes()->with('company')->find($this->broadcastId);

        if (! $broadcast?->company) {
            return;
        }

        $context->set($broadcast->company);

        try {
            $broadcasts->send($broadcast);
            if ($broadcast->fresh()->status === 'sending' && $broadcast->recipients()->where('status', 'pending')->exists()) {
                $this->release(1);
            }
        } finally {
            $context->clear();
        }
    }
}
