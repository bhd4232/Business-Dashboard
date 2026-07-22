<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Services\CompanyContext;
use App\Services\Crm\ConversationMessengerService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class MarkConversationReadJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public array $backoff = [10];

    public int $uniqueFor = 300;

    public function __construct(public int $conversationId) {}

    public function uniqueId(): string
    {
        return (string) $this->conversationId;
    }

    public function handle(CompanyContext $context, ConversationMessengerService $messenger): void
    {
        $conversation = Conversation::withoutGlobalScopes()
            ->with(['company', 'channel'])
            ->find($this->conversationId);

        if (! $conversation?->company) {
            return;
        }

        $context->set($conversation->company);

        try {
            $messenger->markLatestIncomingRead($conversation);
        } finally {
            $context->clear();
        }
    }
}
