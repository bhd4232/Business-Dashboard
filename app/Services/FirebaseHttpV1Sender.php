<?php

namespace App\Services;

use App\Support\FirebasePushResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

class FirebaseHttpV1Sender
{
    protected const MESSAGING_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /** @var array<string, mixed>|null */
    protected ?array $credentials = null;

    public function isConfigured(): bool
    {
        if (! config('native_push.firebase.enabled', false)) {
            return false;
        }

        try {
            $credentials = $this->credentials();

            return filled($this->projectId($credentials))
                && filled($credentials['client_email'] ?? null)
                && filled($credentials['private_key'] ?? null);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, scalar|null>  $data
     */
    public function send(
        string $token,
        string $title,
        string $body,
        array $data = [],
    ): FirebasePushResult {
        if (! $this->isConfigured()) {
            return FirebasePushResult::failed(
                'FIREBASE_NOT_CONFIGURED',
                'Firebase push is disabled or its server credentials are incomplete.',
            );
        }

        try {
            $credentials = $this->credentials();
            $projectId = $this->projectId($credentials);
            $accessToken = $this->accessToken($credentials, $projectId);
        } catch (Throwable $exception) {
            return FirebasePushResult::failed(
                'FIREBASE_AUTH_FAILED',
                $this->safeError($exception->getMessage()),
            );
        }

        $endpoint = str_replace(
            '{project}',
            rawurlencode($projectId),
            (string) config('native_push.firebase.messaging_endpoint'),
        );
        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => collect($data)
                    ->mapWithKeys(fn (mixed $value, string $key): array => [
                        $key => is_bool($value)
                            ? ($value ? 'true' : 'false')
                            : (string) ($value ?? ''),
                    ])
                    ->all(),
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => 'app-updates',
                        'sound' => 'default',
                    ],
                ],
            ],
        ];

        $attempts = max(1, (int) config('native_push.firebase.http_attempts', 3));
        $lastResponse = null;
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $lastResponse = Http::acceptJson()
                    ->asJson()
                    ->withToken($accessToken)
                    ->timeout(max(1, (int) config('native_push.firebase.timeout_seconds', 10)))
                    ->post($endpoint, $payload);
                $lastException = null;

                if ($lastResponse->successful() || ! $this->shouldRetry($lastResponse)) {
                    break;
                }
            } catch (ConnectionException $exception) {
                $lastException = $exception;
            }

            if ($attempt < $attempts) {
                $this->pauseBeforeRetry($attempt);
            }
        }

        if ($lastException) {
            return FirebasePushResult::failed(
                'FIREBASE_CONNECTION_FAILED',
                $this->safeError($lastException->getMessage()),
            );
        }

        if (! $lastResponse) {
            return FirebasePushResult::failed(
                'FIREBASE_EMPTY_RESPONSE',
                'Firebase returned no response.',
            );
        }

        if ($lastResponse->successful()) {
            return FirebasePushResult::sent(
                is_string($lastResponse->json('name'))
                    ? $lastResponse->json('name')
                    : null,
            );
        }

        $errorCode = $this->fcmErrorCode($lastResponse);
        $message = $this->safeError(
            (string) ($lastResponse->json('error.message') ?: "Firebase HTTP {$lastResponse->status()} error."),
        );

        if (in_array($errorCode, ['UNREGISTERED', 'SENDER_ID_MISMATCH'], true)) {
            return FirebasePushResult::stale($errorCode, $message);
        }

        return FirebasePushResult::failed(
            $errorCode ?: 'FIREBASE_HTTP_'.$lastResponse->status(),
            $message,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function credentials(): array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }

        $json = null;
        $path = config('native_push.firebase.credentials_path');
        $base64 = config('native_push.firebase.credentials_json_base64');

        if (is_string($path) && trim($path) !== '') {
            $resolvedPath = str_starts_with($path, DIRECTORY_SEPARATOR)
                || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1
                    ? $path
                    : base_path($path);

            if (! is_file($resolvedPath) || ! is_readable($resolvedPath)) {
                throw new RuntimeException('The Firebase credentials file is missing or unreadable.');
            }

            $json = file_get_contents($resolvedPath);
        } elseif (is_string($base64) && trim($base64) !== '') {
            $json = base64_decode($base64, true);

            if ($json === false) {
                throw new RuntimeException('The Firebase base64 credentials are invalid.');
            }
        }

        if (! is_string($json) || trim($json) === '') {
            throw new RuntimeException('Firebase server credentials have not been configured.');
        }

        try {
            $credentials = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The Firebase credentials JSON is invalid.', previous: $exception);
        }

        if (! is_array($credentials)) {
            throw new RuntimeException('The Firebase credentials JSON must contain an object.');
        }

        foreach (['client_email', 'private_key'] as $required) {
            if (blank($credentials[$required] ?? null)) {
                throw new RuntimeException("Firebase credentials are missing {$required}.");
            }
        }

        return $this->credentials = $credentials;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    protected function projectId(array $credentials): string
    {
        return trim((string) (
            config('native_push.firebase.project_id')
            ?: ($credentials['project_id'] ?? '')
        ));
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    protected function accessToken(array $credentials, string $projectId): string
    {
        if ($projectId === '') {
            throw new RuntimeException('Firebase project ID is missing.');
        }

        $cacheKey = 'firebase-fcm-access-token:'.hash(
            'sha256',
            $projectId.'|'.(string) $credentials['client_email'],
        );
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $tokenUri = trim((string) (
            $credentials['token_uri']
            ?? config('native_push.firebase.token_uri')
        ));
        $now = time();
        $header = $this->base64UrlEncode(json_encode(
            ['alg' => 'RS256', 'typ' => 'JWT'],
            JSON_THROW_ON_ERROR,
        ));
        $claims = $this->base64UrlEncode(json_encode([
            'iss' => (string) $credentials['client_email'],
            'scope' => self::MESSAGING_SCOPE,
            'aud' => $tokenUri,
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));
        $unsignedJwt = "{$header}.{$claims}";
        $privateKey = openssl_pkey_get_private((string) $credentials['private_key']);

        if ($privateKey === false) {
            throw new RuntimeException('Firebase credentials contain an invalid private key.');
        }

        $signed = openssl_sign($unsignedJwt, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (! $signed) {
            throw new RuntimeException('Could not sign the Firebase OAuth assertion.');
        }

        $assertion = "{$unsignedJwt}.".$this->base64UrlEncode($signature);
        $attempts = max(1, (int) config('native_push.firebase.http_attempts', 3));
        $response = null;
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = Http::asForm()
                    ->acceptJson()
                    ->timeout(max(1, (int) config('native_push.firebase.timeout_seconds', 10)))
                    ->post($tokenUri, [
                        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                        'assertion' => $assertion,
                    ]);
                $lastException = null;

                if ($response->successful() || ! $this->shouldRetry($response)) {
                    break;
                }
            } catch (ConnectionException $exception) {
                $lastException = $exception;
            }

            if ($attempt < $attempts) {
                $this->pauseBeforeRetry($attempt);
            }
        }

        if ($lastException) {
            throw new RuntimeException(
                $this->safeError($lastException->getMessage()),
                previous: $lastException,
            );
        }

        if (! $response || ! $response->successful() || blank($response->json('access_token'))) {
            throw new RuntimeException($this->safeError(
                (string) ($response?->json('error_description') ?: 'Firebase OAuth token request failed.'),
            ));
        }

        $accessToken = (string) $response->json('access_token');
        $expiresIn = max(120, (int) $response->json('expires_in', 3600));

        Cache::put($cacheKey, $accessToken, now()->addSeconds($expiresIn - 60));

        return $accessToken;
    }

    protected function shouldRetry(Response $response): bool
    {
        if ($response->status() === 408 || $response->status() === 429 || $response->serverError()) {
            return true;
        }

        return in_array(
            $this->fcmErrorCode($response),
            ['INTERNAL', 'QUOTA_EXCEEDED', 'UNAVAILABLE'],
            true,
        );
    }

    protected function fcmErrorCode(Response $response): ?string
    {
        $details = $response->json('error.details', []);

        if (is_array($details)) {
            foreach ($details as $detail) {
                if (is_array($detail) && filled($detail['errorCode'] ?? null)) {
                    return strtoupper((string) $detail['errorCode']);
                }
            }
        }

        $status = $response->json('error.status');

        return is_string($status) && $status !== '' ? strtoupper($status) : null;
    }

    protected function pauseBeforeRetry(int $attempt): void
    {
        $baseDelay = max(0, (int) config('native_push.firebase.retry_delay_ms', 250));

        if ($baseDelay > 0) {
            usleep($baseDelay * $attempt * 1000);
        }
    }

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    protected function safeError(string $message): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', $message) ?: $message), 1000, '');
    }
}
