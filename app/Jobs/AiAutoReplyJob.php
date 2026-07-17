<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Services\CompanyContext;
use App\Services\Crm\AiReplyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AiAutoReplyJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1; // a failed AI reply escalates instead of retrying

    public function __construct(public int $conversationId) {}

    public function handle(CompanyContext $context, AiReplyService $service): void
    {
        $conversation = Conversation::withoutGlobalScopes()->with('company')->find($this->conversationId);

        if (! $conversation || ! $conversation->company) {
            return;
        }

        $context->set($conversation->company);

        try {
            $service->maybeReply($conversation);
        } finally {
            $context->clear();
        }
    }
}
