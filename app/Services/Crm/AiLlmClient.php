<?php

namespace App\Services\Crm;

use Illuminate\Support\Facades\Http;

/**
 * Thin format-agnostic tool-calling client — speaks either the Anthropic
 * Messages API or the OpenAI Chat Completions shape. The OpenAI shape is
 * what nearly every non-Anthropic LLM provider speaks today (OpenAI itself,
 * DeepSeek, Groq, Mistral, OpenRouter, xAI, self-hosted Ollama/vLLM, ...),
 * so `$apiFormat` + an optional `$baseUrl` override is enough to add any of
 * them without a code change — only the two genuinely different wire
 * protocols are hardcoded here, not a closed list of providers.
 *
 * Returns a normalized shape:
 * ['text' => ?string, 'tool_calls' => [['id','name','input'], ...], 'usage' => array]
 *
 * Always mocked with Http::fake() in tests — never called live there.
 */
class AiLlmClient
{
    public const DEFAULT_ANTHROPIC_URL = 'https://api.anthropic.com/v1/messages';

    public const DEFAULT_OPENAI_URL = 'https://api.openai.com/v1/chat/completions';

    public function __construct(
        protected string $apiFormat,
        protected string $apiKey,
        protected string $model,
        protected ?string $baseUrl = null,
    ) {}

    /**
     * @param  array  $messages  normalized: [['role' => user|assistant, 'content' => string|array], ...]
     * @param  array  $tools  normalized tool definitions: [['name','description','input_schema'], ...]
     */
    public function chat(string $system, array $messages, array $tools): array
    {
        return $this->apiFormat === 'openai'
            ? $this->chatOpenAiCompatible($this->baseUrl ?: self::DEFAULT_OPENAI_URL, $system, $messages, $tools)
            : $this->chatAnthropic($this->baseUrl ?: self::DEFAULT_ANTHROPIC_URL, $system, $messages, $tools);
    }

    protected function chatAnthropic(string $url, string $system, array $messages, array $tools): array
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
        ])->timeout(60)->post($url, [
            'model' => $this->model,
            'max_tokens' => 1024,
            'system' => $system,
            'messages' => $messages,
            'tools' => $tools,
        ])->throw()->json();

        $text = null;
        $toolCalls = [];

        foreach ($response['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text = trim(($text ?? '').' '.$block['text']);
            } elseif (($block['type'] ?? null) === 'tool_use') {
                $toolCalls[] = [
                    'id' => $block['id'] ?? uniqid('tool_'),
                    'name' => $block['name'] ?? '',
                    'input' => (array) ($block['input'] ?? []),
                ];
            }
        }

        return [
            'text' => $text,
            'tool_calls' => $toolCalls,
            'usage' => (array) ($response['usage'] ?? []),
            'raw_content' => $response['content'] ?? [],
        ];
    }

    protected function chatOpenAiCompatible(string $url, string $system, array $messages, array $tools): array
    {
        $openAiMessages = [['role' => 'system', 'content' => $system]];

        foreach ($messages as $message) {
            // Tool-call rounds are already in OpenAI shape — pass through.
            $openAiMessages[] = ($message['role'] === 'tool' || isset($message['tool_calls']))
                ? $message
                : ['role' => $message['role'], 'content' => $message['content']];
        }

        $response = Http::withToken($this->apiKey)
            ->timeout(60)
            ->post($url, [
                'model' => $this->model,
                'messages' => $openAiMessages,
                'tools' => array_map(fn (array $tool): array => [
                    'type' => 'function',
                    'function' => [
                        'name' => $tool['name'],
                        'description' => $tool['description'],
                        'parameters' => $tool['input_schema'],
                    ],
                ], $tools),
            ])->throw()->json();

        $choice = $response['choices'][0]['message'] ?? [];

        return [
            'text' => $choice['content'] ?? null,
            'tool_calls' => array_map(fn (array $call): array => [
                'id' => $call['id'] ?? uniqid('tool_'),
                'name' => data_get($call, 'function.name', ''),
                'input' => (array) json_decode(data_get($call, 'function.arguments', '{}'), true),
            ], $choice['tool_calls'] ?? []),
            'usage' => (array) ($response['usage'] ?? []),
            'raw_content' => $choice,
        ];
    }
}
