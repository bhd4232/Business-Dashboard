<?php

namespace App\Services\Crm;

use App\Models\ConversationMessage;
use Illuminate\Support\Facades\Log;

/**
 * When a company configures a separate image-reading model
 * (`image_model_enabled`), this produces a short plain-text description of
 * the customer's photo via a one-shot call to that model/provider — instead
 * of feeding the raw image inline to the main tool-calling agent loop, which
 * would otherwise require the main chat model itself to be vision-capable.
 * The description then flows through the normal text-based reply pipeline
 * like any other customer message (AiReplyService writes it into the
 * message's body). Grounding rule 0 in AiReplyService's system prompt still
 * applies: the description is untrusted evidence, never a catalog fact.
 *
 * Always mocked with Http::fake() in tests — never called live there.
 */
class AiVisionDescriber
{
    protected const PROMPT = <<<'PROMPT'
Describe this customer photo in one or two short plain-text sentences: the product visible, any readable brand/label/model text, color, and notable condition. Do not guess a price, stock level, or exact catalog match — only describe what is visibly present. If the photo is unclear or not a product photo, say so plainly. Reply with the description only, no preamble.
PROMPT;

    public function describe(ConversationMessage $message, array $settings): ?string
    {
        if (blank($settings['image_api_key'] ?? null)) {
            return null;
        }

        $image = app(AiImageInput::class)->block($message);

        if (! $image) {
            return null;
        }

        try {
            $client = new AiLlmClient(
                $settings['image_api_format'],
                $settings['image_api_key'],
                $settings['image_model'],
                $settings['image_base_url'] ?: null,
                30,
            );

            $response = $client->chat(self::PROMPT, [
                ['role' => 'user', 'content' => [$image, ['type' => 'text', 'text' => 'Describe this image.']]],
            ], []);
        } catch (\Throwable $exception) {
            Log::warning('CRM image description failed.', ['message_id' => $message->getKey(), 'error' => $exception->getMessage()]);

            return null;
        }

        $text = trim((string) ($response['text'] ?? ''));

        return $text !== '' ? mb_substr($text, 0, 1000) : null;
    }
}
