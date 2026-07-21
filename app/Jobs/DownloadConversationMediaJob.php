<?php

namespace App\Jobs;

use App\Models\ConversationChannel;
use App\Models\ConversationMessage;
use App\Services\CompanyContext;
use App\Services\CompanyStorageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Meta CDN media URLs expire within 24-48 hours, so incoming media is copied
 * to our own storage as soon as the webhook arrives.
 */
class DownloadConversationMediaJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 300];

    public function __construct(
        public int $messageId,
        public int $channelId,
        public ?string $mediaId = null,
        public ?string $mediaUrl = null,
    ) {}

    public function handle(CompanyContext $context, CompanyStorageService $storage): void
    {
        $channel = ConversationChannel::withoutGlobalScopes()->with('company')->findOrFail($this->channelId);
        $context->set($channel->company);

        try {
            $message = ConversationMessage::query()
                ->whereHas('conversation', fn (Builder $query): Builder => $query
                    ->withoutGlobalScopes()
                    ->where('company_id', $channel->company_id)
                    ->where('channel_id', $channel->getKey()))
                ->findOrFail($this->messageId);

            $url = $this->mediaUrl;
            $mime = $message->media_mime;

            // WhatsApp media ids must first be exchanged for a temporary URL.
            if (! $url && $this->mediaId) {
                $meta = Http::withToken((string) $channel->access_token)
                    ->get("https://graph.facebook.com/v19.0/{$this->mediaId}")
                    ->throw()
                    ->json();

                $url = $meta['url'] ?? null;
                $mime = $mime ?: ($meta['mime_type'] ?? null);
            }

            if (! $url) {
                return;
            }

            $response = Http::withToken((string) $channel->access_token)->get($url)->throw();
            $mime = $mime ?: $response->header('Content-Type');

            $extension = Str::of((string) $mime)
                ->after('/')
                ->before(';')
                ->lower()
                ->replaceMatches('/[^a-z0-9]+/', '')
                ->limit(12, '')
                ->value() ?: 'bin';
            $messageName = Str::of($message->external_message_id ?: Str::random(20))
                ->replaceMatches('/[^A-Za-z0-9._-]+/', '-')
                ->trim('.-_')
                ->limit(160, '')
                ->value() ?: Str::random(20);

            $path = $storage->putPrivate(
                $channel->company,
                'conversation-media',
                "{$messageName}.{$extension}",
                $response->body(),
            );

            $message->forceFill([
                'media_path' => $path,
                'media_mime' => $mime,
            ])->save();
        } finally {
            $context->clear();
        }
    }
}
