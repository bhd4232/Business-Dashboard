<?php

namespace App\Services\Crm;

use App\Jobs\MarkConversationReadJob;
use App\Models\Conversation;
use App\Models\ConversationChannel;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Services\Meta\MetaGraphException;
use App\Services\Meta\MetaGraphService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Sends replies through the conversation channel and always archives the
 * outgoing attempt before contacting Meta, including a safe failed state.
 */
class ConversationMessengerService
{
    public function __construct(protected MetaGraphService $meta) {}

    public function send(
        Conversation $conversation,
        string $body,
        ?User $sender = null,
        string $type = 'text',
        ?string $mediaUrl = null,
    ): ConversationMessage {
        $message = ConversationMessage::query()->create([
            'conversation_id' => $conversation->getKey(),
            'direction' => 'outgoing',
            'type' => $type,
            'body' => $body,
            'media_path' => $mediaUrl,
            'media_mime' => $mediaUrl ? 'image/*' : null,
            'delivery_status' => 'sending',
            'sent_by' => $sender?->getKey(),
            'generated_by' => 'human',
            'sent_at' => now(),
        ]);

        $this->updateConversationAfterAttempt($conversation, $sender);

        return $this->deliver($message, $conversation);
    }

    public function retry(ConversationMessage $message, ?User $sender = null): ConversationMessage
    {
        $message->loadMissing('conversation.channel');
        $conversation = $message->conversation;

        if (! $conversation || $message->direction !== 'outgoing' || $message->delivery_status !== 'failed') {
            throw ValidationException::withMessages([
                'message' => 'Only a failed outgoing message can be retried.',
            ]);
        }

        $claimed = ConversationMessage::query()
            ->whereKey($message->getKey())
            ->where('direction', 'outgoing')
            ->where('delivery_status', 'failed')
            ->update([
                'delivery_status' => 'sending',
                'external_message_id' => null,
                'raw_payload' => null,
                'sent_by' => $sender?->getKey() ?? $message->sent_by,
                'sent_at' => now(),
                'updated_at' => now(),
            ]);

        if ($claimed !== 1) {
            throw ValidationException::withMessages([
                'message' => 'This message is already being retried or is no longer failed.',
            ]);
        }

        $message->refresh();

        $this->updateConversationAfterAttempt($conversation, $sender);

        return $this->deliver($message, $conversation);
    }

    public function dispatchLatestIncomingRead(Conversation $conversation): void
    {
        if ($conversation->provider !== 'whatsapp') {
            return;
        }

        MarkConversationReadJob::dispatch($conversation->getKey());
    }

    public function markLatestIncomingRead(Conversation $conversation): bool
    {
        if ($conversation->provider !== 'whatsapp') {
            return false;
        }

        $conversation->loadMissing('channel');
        $channel = $conversation->channel;

        if ($channel && (int) $channel->company_id !== (int) $conversation->company_id) {
            return false;
        }

        $message = $conversation->messages()
            ->where('direction', 'incoming')
            ->whereNotNull('external_message_id')
            ->latest('sent_at')
            ->latest('id')
            ->first();

        if (! $channel?->is_active || ! $message) {
            return false;
        }

        if (data_get($message->raw_payload, '_local.marked_read_at')) {
            return true;
        }

        try {
            $this->meta->markWhatsAppRead($channel, (string) $message->external_message_id);
            $raw = is_array($message->raw_payload) ? $message->raw_payload : [];
            data_set($raw, '_local.marked_read_at', now()->toIso8601String());
            $message->forceFill(['raw_payload' => $raw])->saveQuietly();

            return true;
        } catch (Throwable $exception) {
            $channel->recordDiagnosticError($this->safeMessage($exception), 'read_receipt');

            return false;
        }
    }

    protected function deliver(ConversationMessage $message, Conversation $conversation): ConversationMessage
    {
        if (! in_array($conversation->provider, ['whatsapp', 'messenger'], true)) {
            $message->forceFill(['delivery_status' => 'internal'])->save();

            return $message->refresh();
        }

        $channel = null;

        try {
            $conversation->loadMissing('channel');
            $channel = $conversation->channel;
            $this->validateDelivery($conversation, $channel);

            $contactId = trim((string) $conversation->external_contact_id);
            $externalId = $conversation->provider === 'whatsapp'
                ? $this->meta->sendWhatsApp(
                    $channel,
                    preg_replace('/\D+/', '', $contactId) ?: $contactId,
                    (string) $message->body,
                    $message->media_path,
                )
                : $this->meta->sendMessenger(
                    $channel,
                    $contactId,
                    (string) $message->body,
                    $message->media_path,
                );

        } catch (Throwable $exception) {
            $safeMessage = $this->safeMessage($exception);
            $message->forceFill([
                'delivery_status' => 'failed',
                'raw_payload' => [
                    'error' => array_filter([
                        'message' => $safeMessage,
                        'code' => $exception instanceof MetaGraphException ? $exception->graphCode : null,
                    ], fn (mixed $value): bool => $value !== null),
                    'failed_at' => now()->toIso8601String(),
                ],
            ])->save();
            if ($channel && (int) $channel->company_id === (int) $conversation->company_id) {
                $channel->recordDiagnosticError($safeMessage, 'outbound');
            }

            return $message->refresh();
        }

        // Once Meta returns a message ID the customer may already have the
        // message. Never turn later local bookkeeping failures into a
        // retryable bubble that could send a duplicate.
        $message->forceFill([
            'external_message_id' => $externalId,
            'delivery_status' => 'sent',
            'raw_payload' => null,
        ])->save();

        try {
            $channel->markOutboundSent();
        } catch (Throwable $exception) {
            Log::warning('Meta message was accepted but channel diagnostics could not be updated.', [
                'conversation_id' => $conversation->getKey(),
                'message_id' => $message->getKey(),
                'exception' => $exception::class,
            ]);
        }

        return $message->refresh();
    }

    protected function validateDelivery(Conversation $conversation, ?ConversationChannel $channel): void
    {
        if (! $channel || ! $channel->is_active) {
            throw ValidationException::withMessages([
                'body' => 'This conversation has no active Meta channel. Activate and test the channel before sending.',
            ]);
        }

        if ((int) $channel->company_id !== (int) $conversation->company_id) {
            throw ValidationException::withMessages([
                'body' => 'This conversation is linked to a channel owned by another company. Correct the channel before sending.',
            ]);
        }

        if ($channel->provider !== $conversation->provider) {
            throw ValidationException::withMessages([
                'body' => 'The conversation provider does not match its configured channel.',
            ]);
        }

        if (blank($channel->external_id)) {
            throw ValidationException::withMessages([
                'body' => 'The channel Phone Number ID or Page ID is missing.',
            ]);
        }

        if (blank($channel->access_token)) {
            throw ValidationException::withMessages([
                'body' => 'The Meta access token is missing. Add a permanent token, then run Test & Subscribe.',
            ]);
        }

        if (blank($conversation->external_contact_id)) {
            throw ValidationException::withMessages([
                'body' => 'This conversation has no Meta contact ID, so the message cannot be delivered.',
            ]);
        }

        if (! $conversation->withinReplyWindow()) {
            throw ValidationException::withMessages([
                'body' => 'The customer-service reply window is closed. Send an approved template or wait for the customer to message again.',
            ]);
        }
    }

    protected function updateConversationAfterAttempt(Conversation $conversation, ?User $sender): void
    {
        $updates = ['last_message_at' => now()];

        if ($sender !== null) {
            $updates['human_handled_until'] = now()->addHours(24);

            if ($conversation->status === 'pending') {
                $updates['status'] = 'open';
            }
        }

        $conversation->forceFill($updates)->saveQuietly();
    }

    protected function safeMessage(Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            return (string) (collect($exception->errors())->flatten()->first() ?: 'Message validation failed.');
        }

        if ($exception instanceof MetaGraphException) {
            return $exception->getMessage();
        }

        return Str::limit('The message could not be delivered to Meta. Check the channel diagnostics and retry.', 500, '');
    }
}
