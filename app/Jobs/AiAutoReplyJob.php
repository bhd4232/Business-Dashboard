<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\CompanyContext;
use App\Services\Crm\AiReplyService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class AiAutoReplyJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1; // a failed AI reply escalates instead of retrying

    public int $uniqueFor = 3600;

    public function __construct(
        public int $conversationId,
        public ?int $sourceMessageId = null,
    ) {}

    public function uniqueId(): string
    {
        return $this->conversationId.':'.($this->sourceMessageId ?: 'legacy');
    }

    public function handle(CompanyContext $context, AiReplyService $service): void
    {
        $conversation = Conversation::withoutGlobalScopes()->with('company')->find($this->conversationId);

        if (! $conversation || ! $conversation->company) {
            return;
        }

        $context->set($conversation->company);

        try {
            $sourceQuery = $conversation->messages()->where('direction', 'incoming');
            $source = $this->sourceMessageId
                ? $sourceQuery->whereKey($this->sourceMessageId)->first()
                : $sourceQuery->latest('sent_at')->latest('id')->first();

            if (! $source) {
                return;
            }

            if ($this->alreadyProcessed($source)) {
                $this->markProcessed($source);

                return;
            }

            $lock = Cache::lock('ai-auto-reply-processing:'.$source->getKey(), 600);

            if (! $lock->get()) {
                return;
            }

            try {
                $source->refresh();

                if ($this->alreadyProcessed($source)) {
                    $this->markProcessed($source);

                    return;
                }

                $service->maybeReply($conversation, $source);
                $this->markProcessed($source);
            } finally {
                $lock->release();
            }
        } finally {
            $context->clear();
        }
    }

    protected function alreadyProcessed(ConversationMessage $source): bool
    {
        if (data_get($source->raw_payload, '_local.ai_processed_at')) {
            return true;
        }

        return ConversationMessage::query()
            ->where('conversation_id', $source->conversation_id)
            ->where('generated_by', 'ai')
            ->where('ai_meta->source_message_id', $source->getKey())
            ->exists();
    }

    protected function markProcessed(ConversationMessage $source): void
    {
        $source->refresh();
        $raw = is_array($source->raw_payload) ? $source->raw_payload : [];
        data_set($raw, '_local.ai_processed_at', now()->toIso8601String());
        $source->forceFill(['raw_payload' => $raw])->saveQuietly();
    }
}
