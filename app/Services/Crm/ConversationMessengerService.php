<?php

namespace App\Services\Crm;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Sends outgoing replies through the conversation's channel (WhatsApp Cloud
 * API or Messenger Graph API) and archives every outgoing message locally.
 * Manual/phone conversations skip the API and only archive.
 */
class ConversationMessengerService
{
    public function send(Conversation $conversation, string $body, ?User $sender = null, string $type = 'text'): ConversationMessage
    {
        $externalId = null;

        if (in_array($conversation->provider, ['whatsapp', 'messenger'], true)) {
            $channel = $conversation->channel;

            if (! $channel?->is_active || blank($channel->access_token)) {
                throw ValidationException::withMessages([
                    'body' => 'This conversation has no active channel with an access token configured.',
                ]);
            }

            $externalId = $conversation->provider === 'whatsapp'
                ? $this->sendWhatsApp($channel->external_id, (string) $channel->access_token, $conversation->external_contact_id, $body)
                : $this->sendMessenger((string) $channel->access_token, $conversation->external_contact_id, $body);
        }

        $message = ConversationMessage::query()->create([
            'conversation_id' => $conversation->getKey(),
            'direction' => 'outgoing',
            'type' => $type,
            'body' => $body,
            'external_message_id' => $externalId,
            'delivery_status' => 'sent',
            'sent_by' => $sender?->getKey(),
            'generated_by' => 'human',
            'sent_at' => now(),
        ]);

        $updates = ['last_message_at' => now()];

        // A human reply pauses the AI assistant for 24 hours (plan 13.5) and
        // takes the conversation out of the "needs review" queue.
        if ($sender !== null) {
            $updates['human_handled_until'] = now()->addHours(24);

            if ($conversation->status === 'pending') {
                $updates['status'] = 'open';
            }
        }

        $conversation->forceFill($updates)->saveQuietly();

        return $message;
    }

    protected function sendWhatsApp(string $phoneNumberId, string $accessToken, ?string $to, string $body): ?string
    {
        $response = Http::withToken($accessToken)
            ->post("https://graph.facebook.com/v19.0/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => ['body' => $body],
            ])
            ->throw()
            ->json();

        return data_get($response, 'messages.0.id');
    }

    protected function sendMessenger(string $accessToken, ?string $psid, string $body): ?string
    {
        $response = Http::post(
            'https://graph.facebook.com/v19.0/me/messages?access_token='.urlencode($accessToken),
            [
                'recipient' => ['id' => $psid],
                'message' => ['text' => $body],
                'messaging_type' => 'RESPONSE',
            ],
        )
            ->throw()
            ->json();

        return $response['message_id'] ?? null;
    }
}
