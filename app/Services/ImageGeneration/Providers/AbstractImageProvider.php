<?php

namespace App\Services\ImageGeneration\Providers;

use App\Services\ImageGeneration\ImageGenerationClient;
use App\Services\ImageGeneration\ImageGenerationException;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Shared plumbing for the concrete provider adapters: turning HTTP failures
 * and empty responses into a single, user-readable ImageGenerationException,
 * and decoding base64 image payloads.
 */
abstract class AbstractImageProvider implements ImageGenerationClient
{
    protected int $timeoutSeconds = 120;

    abstract protected function providerName(): string;

    /**
     * @param  callable():Response  $send
     */
    protected function send(callable $send): Response
    {
        try {
            $response = $send();
        } catch (ImageGenerationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ImageGenerationException(
                "{$this->providerName()} could not be reached: {$exception->getMessage()}",
                previous: $exception,
            );
        }

        if ($response->failed()) {
            throw new ImageGenerationException(
                "{$this->providerName()} rejected the request (HTTP {$response->status()}): {$this->summarize($response)}"
            );
        }

        return $response;
    }

    protected function summarize(Response $response): string
    {
        $json = rescue(fn (): mixed => $response->json(), rescue: null, report: false);

        foreach ([['error', 'message'], ['error'], ['message'], ['detail'], ['errors', 0, 'message']] as $path) {
            $value = data_get($json, implode('.', $path));

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return str(trim((string) $response->body()))->limit(300)->toString() ?: 'no error detail returned.';
    }

    protected function decodeBase64(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        // Some providers prefix a data URI.
        if (str_contains($value, ',') && str_starts_with($value, 'data:')) {
            $value = substr($value, strpos($value, ',') + 1);
        }

        $decoded = base64_decode(trim($value), true);

        return $decoded === false || $decoded === '' ? null : $decoded;
    }

    /**
     * @param  array<int, string|null>  $images
     */
    protected function requireImages(array $images): array
    {
        $images = array_values(array_filter($images, fn ($image): bool => is_string($image) && $image !== ''));

        if ($images === []) {
            throw new ImageGenerationException("{$this->providerName()} returned no image data.");
        }

        return $images;
    }
}
