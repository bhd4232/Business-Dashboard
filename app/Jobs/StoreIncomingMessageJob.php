<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\ConversationChannel;
use App\Models\ConversationMessage;
use App\Models\Customer;
use App\Models\Lead;
use App\Services\CompanyContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class StoreIncomingMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [10, 30, 120, 300];

    public function __construct(public int $channelId, public array $payload) {}

    public function handle(CompanyContext $context): void
    {
        $channel = ConversationChannel::withoutGlobalScopes()->with('company')->findOrFail($this->channelId);
        $context->set($channel->company);

        try {
            if (($this->payload['object'] ?? null) === 'whatsapp_business_account') {
                $this->handleWhatsApp($channel);
            } elseif (($this->payload['object'] ?? null) === 'page') {
                $this->handleMessenger($channel);
            }
        } finally {
            $context->clear();
        }
    }

    protected function handleWhatsApp(ConversationChannel $channel): void
    {
        foreach ($this->payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                $contacts = collect($value['contacts'] ?? [])->keyBy('wa_id');

                foreach ($value['messages'] ?? [] as $message) {
                    $waId = (string) ($message['from'] ?? '');
                    $contact = $contacts->get($waId, []);

                    $conversation = $this->conversationFor(
                        $channel,
                        $waId,
                        data_get($contact, 'profile.name'),
                        $waId, // WhatsApp contact id IS the phone number
                        $message['referral'] ?? null,
                    );

                    $this->storeMessage($conversation, $channel, [
                        'external_message_id' => (string) ($message['id'] ?? ''),
                        'type' => $this->normalizeType((string) ($message['type'] ?? 'text')),
                        'body' => data_get($message, 'text.body')
                            ?? data_get($message, 'button.text')
                            ?? data_get($message, 'interactive.button_reply.title')
                            ?? data_get($message, 'interactive.list_reply.title'),
                        'media_id' => data_get($message, 'image.id')
                            ?? data_get($message, 'audio.id')
                            ?? data_get($message, 'video.id')
                            ?? data_get($message, 'document.id')
                            ?? data_get($message, 'sticker.id'),
                        'media_mime' => data_get($message, 'image.mime_type')
                            ?? data_get($message, 'audio.mime_type')
                            ?? data_get($message, 'video.mime_type')
                            ?? data_get($message, 'document.mime_type')
                            ?? data_get($message, 'sticker.mime_type'),
                        'sent_at' => isset($message['timestamp'])
                            ? Carbon::createFromTimestamp((int) $message['timestamp'])
                            : now(),
                        'raw' => $message,
                    ]);
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    $this->applyDeliveryStatus((string) ($status['id'] ?? ''), (string) ($status['status'] ?? ''));
                }
            }
        }
    }

    protected function handleMessenger(ConversationChannel $channel): void
    {
        foreach ($this->payload['entry'] ?? [] as $entry) {
            foreach ($entry['messaging'] ?? [] as $event) {
                $psid = (string) data_get($event, 'sender.id', '');

                if ($psid === '' || $psid === $channel->external_id) {
                    continue; // echoes of our own page messages
                }

                if (isset($event['message'])) {
                    $message = $event['message'];
                    $attachment = data_get($message, 'attachments.0');

                    $conversation = $this->conversationFor($channel, $psid, null, null);

                    $this->storeMessage($conversation, $channel, [
                        'external_message_id' => (string) ($message['mid'] ?? ''),
                        'type' => $attachment ? $this->normalizeType((string) ($attachment['type'] ?? 'text')) : 'text',
                        'body' => $message['text'] ?? null,
                        'media_url' => data_get($attachment, 'payload.url'),
                        'media_mime' => null,
                        'sent_at' => isset($event['timestamp'])
                            ? Carbon::createFromTimestampMs((int) $event['timestamp'])
                            : now(),
                        'raw' => $event,
                    ]);
                }

                if (isset($event['delivery']['mids'])) {
                    foreach ((array) $event['delivery']['mids'] as $mid) {
                        $this->applyDeliveryStatus((string) $mid, 'delivered');
                    }
                }
            }
        }
    }

    protected function conversationFor(
        ConversationChannel $channel,
        string $externalContactId,
        ?string $contactName,
        ?string $contactPhone,
        ?array $referral = null,
    ): Conversation {
        // Meta sends `referral` only on the first message that arrives from a
        // Click-to-WhatsApp ad — that conversation gets the 72h FEP window.
        $fromAd = ($referral['source_type'] ?? null) === 'ad';

        $conversation = Conversation::query()->firstOrCreate(
            [
                'channel_id' => $channel->getKey(),
                'external_contact_id' => $externalContactId,
            ],
            [
                'company_id' => $channel->company_id,
                'provider' => $channel->provider,
                'entry_point' => $fromAd ? 'ctwa_ad' : null,
                'ad_referral_id' => $fromAd ? ($referral['source_id'] ?? null) : null,
                'contact_name' => $contactName,
                'contact_phone' => $contactPhone,
                'status' => 'open',
            ],
        );

        if ($fromAd && $conversation->entry_point !== 'ctwa_ad') {
            $conversation->forceFill([
                'entry_point' => 'ctwa_ad',
                'ad_referral_id' => $referral['source_id'] ?? null,
            ])->saveQuietly();
        }

        if ($contactName && $conversation->contact_name !== $contactName) {
            $conversation->forceFill(['contact_name' => $contactName])->saveQuietly();
        }

        if ($conversation->wasRecentlyCreated || (! $conversation->customer_id && ! $conversation->lead_id)) {
            $this->autoLink($conversation, $channel);
        }

        return $conversation;
    }

    protected function autoLink(Conversation $conversation, ConversationChannel $channel): void
    {
        $phone = $conversation->contact_phone;

        if ($phone) {
            // Match on the local significant digits so '8801812345678'
            // (WhatsApp) finds a customer saved as '01812345678'.
            $normalized = substr(preg_replace('/\D/', '', $phone), -10);

            $customer = Customer::query()
                ->where(fn ($query) => $query
                    ->where('phone', 'like', "%{$normalized}")
                    ->orWhere('phone', $phone))
                ->first();

            if ($customer) {
                $conversation->forceFill(['customer_id' => $customer->getKey()])->saveQuietly();

                return;
            }

            $lead = Lead::query()
                ->where(fn ($query) => $query
                    ->where('phone', 'like', "%{$normalized}")
                    ->orWhere('phone', $phone))
                ->first();

            if ($lead) {
                $conversation->forceFill(['lead_id' => $lead->getKey()])->saveQuietly();

                return;
            }
        }

        if ($channel->auto_create_leads) {
            $lead = Lead::query()->create([
                'company_id' => $channel->company_id,
                'name' => $conversation->contact_name ?: 'Chat contact '.$conversation->external_contact_id,
                'phone' => $phone ?: ($conversation->external_contact_id ?? 'unknown'),
                'source' => $channel->provider === 'whatsapp' ? 'whatsapp' : 'facebook',
                'status' => 'new',
            ]);

            $conversation->forceFill(['lead_id' => $lead->getKey()])->saveQuietly();
        }
    }

    protected function storeMessage(Conversation $conversation, ConversationChannel $channel, array $data): void
    {
        $externalId = $data['external_message_id'];

        if ($externalId === '' || ConversationMessage::query()->where('external_message_id', $externalId)->exists()) {
            return; // Meta retries webhooks — dedupe on the platform message id.
        }

        $message = ConversationMessage::query()->create([
            'conversation_id' => $conversation->getKey(),
            'direction' => 'incoming',
            'type' => $data['type'],
            'body' => $data['body'],
            'media_mime' => $data['media_mime'] ?? null,
            'external_message_id' => $externalId,
            'delivery_status' => 'received',
            'raw_payload' => $data['raw'],
            'sent_at' => $data['sent_at'],
        ]);

        $conversation->forceFill([
            'last_message_at' => $data['sent_at'],
            'unread_count' => $conversation->unread_count + 1,
            'status' => $conversation->status === 'closed' ? 'open' : $conversation->status,
        ])->saveQuietly();

        if (! empty($data['media_id']) || ! empty($data['media_url'])) {
            DownloadConversationMediaJob::dispatch(
                $message->getKey(),
                $channel->getKey(),
                $data['media_id'] ?? null,
                $data['media_url'] ?? null,
            );
        }

        if ($data['type'] === 'text' && filled($data['body'])) {
            AiAutoReplyJob::dispatch($conversation->getKey());
        }
    }

    protected function applyDeliveryStatus(string $externalMessageId, string $status): void
    {
        if ($externalMessageId === '' || ! in_array($status, ['sent', 'delivered', 'read', 'failed'], true)) {
            return;
        }

        ConversationMessage::query()
            ->where('external_message_id', $externalMessageId)
            ->where('direction', 'outgoing')
            ->update(['delivery_status' => $status]);
    }

    protected function normalizeType(string $type): string
    {
        return array_key_exists($type, ConversationMessage::TYPES) ? $type : 'text';
    }
}
