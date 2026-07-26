<?php

return [
    'firebase' => [
        'enabled' => (bool) env('FIREBASE_PUSH_ENABLED', false),
        'project_id' => env('FIREBASE_PROJECT_ID'),

        // Prefer a mounted, read-only secret file in production. Base64 is
        // supported for hosting platforms that expose secrets only as values.
        'credentials_path' => env('FIREBASE_CREDENTIALS'),
        'credentials_json_base64' => env('FIREBASE_CREDENTIALS_JSON_BASE64'),

        'token_uri' => env('FIREBASE_TOKEN_URI', 'https://oauth2.googleapis.com/token'),
        'messaging_endpoint' => env(
            'FIREBASE_MESSAGING_ENDPOINT',
            'https://fcm.googleapis.com/v1/projects/{project}/messages:send',
        ),
        'timeout_seconds' => (int) env('FIREBASE_HTTP_TIMEOUT', 10),
        'http_attempts' => (int) env('FIREBASE_HTTP_ATTEMPTS', 3),
        'retry_delay_ms' => (int) env('FIREBASE_HTTP_RETRY_DELAY_MS', 250),
        'max_delivery_attempts' => (int) env('FIREBASE_MAX_DELIVERY_ATTEMPTS', 12),
    ],
];
