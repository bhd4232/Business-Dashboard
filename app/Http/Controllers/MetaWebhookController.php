<?php

namespace App\Http\Controllers;

use App\Jobs\StoreIncomingMessageJob;
use App\Models\ConversationChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MetaWebhookController extends Controller
{
    /**
     * Meta webhook subscription handshake (hub.challenge).
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = (string) $request->query('hub_verify_token');
        $challenge = (string) $request->query('hub_challenge');

        $matches = $mode === 'subscribe'
            && $token !== ''
            && ConversationChannel::withoutGlobalScopes()
                ->where('is_active', true)
                ->where('verify_token', $token)
                ->exists();

        abort_unless($matches, 403);

        return response($challenge, 200);
    }

    /**
     * Receives WhatsApp Cloud API and Messenger webhooks on one endpoint.
     * Resolves the channel from the payload, verifies the X-Hub-Signature-256
     * against that channel's app secret, then queues the heavy work.
     */
    public function handle(Request $request): Response
    {
        $payload = $request->json()->all();
        $channel = $this->resolveChannel($payload);

        abort_unless($channel !== null, 404);
        abort_unless(
            $channel->verifySignature($request->getContent(), $request->header('X-Hub-Signature-256')),
            403,
        );

        StoreIncomingMessageJob::dispatch($channel->getKey(), $payload);

        return response('EVENT_RECEIVED', 200);
    }

    protected function resolveChannel(array $payload): ?ConversationChannel
    {
        $object = $payload['object'] ?? null;

        if ($object === 'whatsapp_business_account') {
            $phoneNumberId = data_get($payload, 'entry.0.changes.0.value.metadata.phone_number_id');

            return $phoneNumberId ? ConversationChannel::withoutGlobalScopes()
                ->where('provider', 'whatsapp')
                ->where('external_id', (string) $phoneNumberId)
                ->where('is_active', true)
                ->first() : null;
        }

        if ($object === 'page') {
            $pageId = data_get($payload, 'entry.0.id');

            return $pageId ? ConversationChannel::withoutGlobalScopes()
                ->where('provider', 'messenger')
                ->where('external_id', (string) $pageId)
                ->where('is_active', true)
                ->first() : null;
        }

        return null;
    }
}
