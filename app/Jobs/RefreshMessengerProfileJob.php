<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\ConversationChannel;
use App\Models\Lead;
use App\Services\Meta\MetaGraphService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class RefreshMessengerProfileJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $conversationId) {}

    public function handle(MetaGraphService $meta): void
    {
        $conversation = Conversation::withoutGlobalScopes()->find($this->conversationId);
        if (! $conversation || $conversation->provider !== 'messenger' || $conversation->profileDisplayName() !== 'নাম পাওয়া যায়নি') {
            return;
        }
        $channel = ConversationChannel::withoutGlobalScopes()->where('company_id', $conversation->company_id)->find($conversation->channel_id);
        if (! $channel || ! Cache::add('crm:profile:'.$conversation->id, true, now()->addHour())) {
            return;
        }
        try {
            $name = $meta->messengerProfileName($channel, (string) $conversation->external_contact_id);
            if ($name) {
                // Do not overwrite a name saved by staff while the request was running.
                Conversation::withoutGlobalScopes()->whereKey($conversation->id)->where('company_id', $conversation->company_id)
                    ->where('contact_name', $conversation->contact_name)->update(['contact_name' => $name]);
                if ($conversation->lead_id) {
                    Lead::withoutGlobalScopes()->whereKey($conversation->lead_id)->where('company_id', $conversation->company_id)
                        ->where('name', 'Chat contact '.$conversation->external_contact_id)->update(['name' => $name]);
                }
            }
        } catch (\Throwable $exception) {
            // Profile permission/network failures must never interrupt message ingestion or AI replies.
            report($exception);
        }
    }
}
