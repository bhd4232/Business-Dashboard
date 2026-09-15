<?php

namespace App\Services\Crm;

use App\Models\ConversationMessage;
use App\Services\CompanyStorageService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Transcribes an incoming voice note to plain text via an
 * OpenAI-Whisper-compatible `/audio/transcriptions` endpoint — the one wire
 * shape OpenAI, Groq, and most self-hosted Whisper servers all speak, the
 * same "one format covers nearly every provider" approach AiLlmClient takes
 * for chat. The transcript is written into the message's own `body`
 * (AiReplyService does this) so it flows through the normal text-based reply
 * pipeline — and shows up in the Inbox thread for staff — unchanged.
 *
 * Always mocked with Http::fake() in tests — never called live there.
 */
class AiVoiceTranscriber
{
    public const DEFAULT_URL = 'https://api.openai.com/v1/audio/transcriptions';

    /** Whisper's own upload cap. */
    protected const MAX_BYTES = 25 * 1024 * 1024;

    public function transcribe(ConversationMessage $message, array $settings): ?string
    {
        if (blank($settings['voice_api_key'] ?? null) || blank($message->media_path)) {
            return null;
        }

        $company = $message->conversation->company;
        $storage = app(CompanyStorageService::class);
        $location = $storage->locatePrivate($message->media_path, $company);

        if (! $location || Storage::disk($location['disk'])->size($location['path']) > self::MAX_BYTES) {
            return null;
        }

        $bytes = $storage->readPrivate($message->media_path, $company);

        if (! $bytes) {
            return null;
        }

        try {
            $response = Http::withToken($settings['voice_api_key'])
                ->timeout(60)
                ->connectTimeout(10)
                ->attach('file', $bytes, 'voice-note.'.$this->extension($message->media_mime))
                ->post($settings['voice_base_url'] ?: self::DEFAULT_URL, [
                    'model' => $settings['voice_model'] ?: 'whisper-1',
                ])
                ->throw()
                ->json();
        } catch (\Throwable $exception) {
            Log::warning('CRM voice transcription failed.', ['message_id' => $message->getKey(), 'error' => $exception->getMessage()]);

            return null;
        }

        $text = trim((string) ($response['text'] ?? ''));

        return $text !== '' ? mb_substr($text, 0, 4000) : null;
    }

    protected function extension(?string $mime): string
    {
        return match (true) {
            str_contains((string) $mime, 'ogg') => 'ogg',
            str_contains((string) $mime, 'mpeg'), str_contains((string) $mime, 'mp3') => 'mp3',
            str_contains((string) $mime, 'wav') => 'wav',
            str_contains((string) $mime, 'webm') => 'webm',
            default => 'm4a',
        };
    }
}
