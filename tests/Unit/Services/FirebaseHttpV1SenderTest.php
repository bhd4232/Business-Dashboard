<?php

namespace Tests\Unit\Services;

use App\Services\FirebaseHttpV1Sender;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FirebaseHttpV1SenderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Config::set([
            'native_push.firebase.enabled' => true,
            'native_push.firebase.project_id' => 'firebase-project',
            'native_push.firebase.credentials_path' => null,
            'native_push.firebase.credentials_json_base64' => $this->credentials(),
            'native_push.firebase.token_uri' => 'https://oauth.example.test/token',
            'native_push.firebase.messaging_endpoint' => 'https://fcm.googleapis.com/v1/projects/{project}/messages:send',
            'native_push.firebase.timeout_seconds' => 2,
            'native_push.firebase.http_attempts' => 3,
            'native_push.firebase.retry_delay_ms' => 0,
        ]);
    }

    public function test_it_authenticates_and_sends_an_http_v1_notification(): void
    {
        Http::fake([
            'https://oauth.example.test/token' => Http::response([
                'access_token' => 'oauth-access-token',
                'expires_in' => 3600,
            ]),
            'https://fcm.googleapis.com/*' => Http::response([
                'name' => 'projects/firebase-project/messages/message-1',
            ]),
        ]);

        $result = app(FirebaseHttpV1Sender::class)->send(
            'device-token',
            'Update available',
            'Tap Upgrade App when ready.',
            [
                'kind' => 'app-update',
                'deployment_id' => 'deployment-2',
            ],
        );

        $this->assertTrue($result->wasSent());
        $this->assertSame('projects/firebase-project/messages/message-1', $result->messageId);

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), '/messages:send')) {
                return false;
            }

            return $request->hasHeader('Authorization', 'Bearer oauth-access-token')
                && $request['message']['token'] === 'device-token'
                && $request['message']['data']['deployment_id'] === 'deployment-2'
                && $request['message']['android']['priority'] === 'high'
                && $request['message']['android']['notification']['channel_id'] === 'app-updates';
        });
    }

    public function test_it_retries_transient_provider_failures(): void
    {
        Http::fake([
            'https://oauth.example.test/token' => Http::response([
                'access_token' => 'oauth-access-token',
                'expires_in' => 3600,
            ]),
            'https://fcm.googleapis.com/*' => Http::sequence()
                ->push([
                    'error' => [
                        'status' => 'UNAVAILABLE',
                        'message' => 'Temporarily unavailable.',
                    ],
                ], 503)
                ->push(['name' => 'message-after-retry']),
        ]);

        $result = app(FirebaseHttpV1Sender::class)->send(
            'device-token',
            'Update available',
            'Tap when ready.',
        );

        $this->assertTrue($result->wasSent());
        Http::assertSentCount(3);
    }

    public function test_it_classifies_only_provider_confirmed_stale_tokens_as_stale(): void
    {
        Http::fake([
            'https://oauth.example.test/token' => Http::response([
                'access_token' => 'oauth-access-token',
                'expires_in' => 3600,
            ]),
            'https://fcm.googleapis.com/*' => Http::response([
                'error' => [
                    'status' => 'NOT_FOUND',
                    'message' => 'Requested entity was not found.',
                    'details' => [[
                        '@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError',
                        'errorCode' => 'UNREGISTERED',
                    ]],
                ],
            ], 404),
        ]);

        $result = app(FirebaseHttpV1Sender::class)->send(
            'stale-token',
            'Update available',
            'Tap when ready.',
        );

        $this->assertTrue($result->isStale());
        $this->assertSame('UNREGISTERED', $result->errorCode);
    }

    public function test_it_fails_closed_when_credentials_are_missing(): void
    {
        Config::set([
            'native_push.firebase.credentials_json_base64' => null,
            'native_push.firebase.credentials_path' => null,
        ]);

        $sender = app(FirebaseHttpV1Sender::class);

        $this->assertFalse($sender->isConfigured());
        $this->assertSame(
            'FIREBASE_NOT_CONFIGURED',
            $sender->send('token', 'Title', 'Body')->errorCode,
        );
        Http::assertNothingSent();
    }

    protected function credentials(): string
    {
        $privateKey = <<<'PEM'
            -----BEGIN PRIVATE KEY-----
            MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQClMYFRZKZhjzNl
            a+hGhoyCrq+2KdmXOx9FmeYbVTbbIeHKuX0yEZ6szHtUuXgSMnOJFi0TmEc+T6ZK
            8+lqMbBuTCg49g3vA8YGp9vwU+wh96w4MqygdZfjuVe1V2ofqPV9zhDciTgc0Yam
            zidNK37y1xAdkxq4qJC13yqitKSHQAcaWsb/losxKiwXPhd2QAIdLI8ABMXGhDZT
            2fzhtsrpZ74vwVh7vzOux+nmnewLwQjGNXPjCekJmkQaJkudR1NpU+4RgKqSen3u
            VmVXH3AnGWNs9bxshBgGpTAZshXyBw6GJV/BdlpuraUKTy1YOVYr7Z3Hdr/Teiuv
            Ihx5Bj6dAgMBAAECggEAH4bMqtd+J3VYdj07VaZdD52+HBJtm/6lD7f44fOH5hdX
            y8RNv+377feSfA9vem2Vfi60yJ4RzrMNxhU50VINXWd8b66J5zk9pEyJ3ZpuoObv
            P8RwDKmUpNndAtddghBRVcKrliM8Ccf1HkWO25v2/OqNgU9vVJmbEJK4RNgb5FHF
            uQk32a7fLyUtOhNZS/BQnde5WZIG2WMol42RsCGcxqZCoPmpR13FyfKaTnBkLLDE
            LOqqAiU07ppZgisGZqp86M6+Ma/eDh16ABXZLssTfGkT4QGhwUwHWweZ+l3092UD
            H9QF/bMWOX73p2oYENqVjsTU2CY5U9uKB7AT4bf+AwKBgQDcoaBq7rC1uKqoOk0b
            Emvv7fHwl9B3bHm8hyj2qgjq9xJ3lSUd2cu1vD3+Uq35mjPFXUwfJfMBL0P7IVRD
            nM2Sc/+gD1bbhWzsoikyWn4BmzcWB/GJNEAPUBjSBCL8Hk/UKug5hTULUnHkpBF9
            kO9K5pnD1f9TrADEn1fma9bZqwKBgQC/rMnwGc6TNMNWZipmhPk9QW2UV1TKO/6A
            qYOxjxSYDGNFIB2CGFh5/h2PFVnDSMVmFq+IHdNkLX3bawo0GHyLyZDzVn/RxgeE
            WbgTz8Uxha5UMCslEVxtrdxTEr76cVvJvG1DLrGTdeYHmUhKELGSUeBj/5Z7kzA2
            WqX7WrBQ1wKBgQDb53jFvByDM4ldXabGqejNXqO7wwU0UvlPUIQivr1evF46tHwn
            MZKaFALSP0RKOUkKmYAqt59qedrPLpwXO+2l4FpUBZXz/RvhC4v2NT3MihEws0Aq
            GdsTqCjlAtx/4BJ/DTkjQ8LoAwej9We9eL0ZMZjnGZ+AnTqyv4NUFf4yOwKBgHW/
            a/53nBOKYkV9VabNIV+kBfEPBSOHX30ipag6QWh2k7UvWXFGC3RFy1rOvunclod0
            gDiaOfZci+hzBT2jnT8ygD08ciEoCg4gH5jsFjOp78IAJUEMPT+Tgrn11iR75usz
            Odv/n077KmiYnXQCdVNxwOstZABeF0wMu9KBj7bZAoGAN4NUH3kR3oj5+GTH4UXs
            C/iyH5mf6RtODrA5GIuvcGNZbV0Kt4Ylm4MfJgveRlV8HEvpj/xDAa+ndaJloO05
            ZhxBZyTwyvHUsbsNGukNkKDc1phETCq4vRz0OKrqB7ysZ8Hx2G+MwOT6BxpsTk8X
            9nYEG37wG01YBnkkQNkv5sE=
            -----END PRIVATE KEY-----
            PEM;

        return base64_encode(json_encode([
            'type' => 'service_account',
            'project_id' => 'firebase-project',
            'client_email' => 'firebase-adminsdk@example.test',
            'private_key' => $privateKey,
            'token_uri' => 'https://oauth.example.test/token',
        ], JSON_THROW_ON_ERROR));
    }
}
