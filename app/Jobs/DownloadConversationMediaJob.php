<?php

namespace App\Jobs;

use App\Models\ConversationChannel;
use App\Models\ConversationMessage;
use App\Services\CompanyContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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

    public function handle(CompanyContext $context): void
    {
        $channel = ConversationChannel::withoutGlobalScopes()->with('company')->findOrFail($this->channelId);
        $message = ConversationMessage::query()->findOrFail($this->messageId);
        $context->set($channel->company);

        try {
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

            $extension = Str::of((string) $mime)->after('/')->before(';')->value() ?: 'bin';
            $path = sprintf(
                'conversations/%d/%s.%s',
                $channel->company_id,
                $message->external_message_id ?: Str::random(20),
                $extension,
            );

            Storage::disk('local')->put($path, $response->body());

            $message->forceFill([
                'media_path' => $path,
                'media_mime' => $mime,
            ])->save();
        } finally {
            $context->clear();
        }
    }
}
