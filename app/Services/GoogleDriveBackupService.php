<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleDriveBackupService
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const UPLOAD_URL = 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,name,webViewLink';

    private const SCOPE = 'https://www.googleapis.com/auth/drive.file';

    public function isConfigured(): bool
    {
        return app(BackupSettingsService::class)->googleDriveEnabled()
            && filled($this->folderId())
            && $this->credentials() !== [];
    }

    public function upload(string $absolutePath, ?string $filename = null): array
    {
        if (! File::exists($absolutePath)) {
            throw new RuntimeException('Backup file was not found for Google Drive upload.');
        }

        $filename ??= basename($absolutePath);
        $folderId = $this->folderId();

        if (blank($folderId)) {
            throw new RuntimeException('Google Drive backup folder ID is required.');
        }

        $token = $this->accessToken();
        $boundary = 'backup_' . bin2hex(random_bytes(12));
        $metadata = json_encode([
            'name' => $filename,
            'parents' => [$folderId],
        ]);

        $body = "--{$boundary}\r\n"
            . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
            . "{$metadata}\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: application/zip\r\n\r\n"
            . File::get($absolutePath) . "\r\n"
            . "--{$boundary}--";

        $response = Http::withToken($token)
            ->withHeaders(['Content-Type' => "multipart/related; boundary={$boundary}"])
            ->send('POST', self::UPLOAD_URL, ['body' => $body]);

        if (! $response->successful()) {
            throw new RuntimeException($response->json('error.message') ?: 'Google Drive upload failed.');
        }

        return $response->json();
    }

    protected function accessToken(): string
    {
        $credentials = $this->credentials();
        $clientEmail = $credentials['client_email'] ?? null;
        $privateKey = $credentials['private_key'] ?? null;

        if (blank($clientEmail) || blank($privateKey)) {
            throw new RuntimeException('Google Drive service account credentials are invalid.');
        }

        $now = time();
        $jwt = $this->jwt([
            'iss' => $clientEmail,
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ], $privateKey);

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException($response->json('error_description') ?: 'Could not authenticate with Google Drive.');
        }

        return $response->json('access_token');
    }

    protected function credentials(): array
    {
        $settings = app(BackupSettingsService::class);
        $json = $settings->serviceAccountJson();

        if (filled($json)) {
            return json_decode($json, true) ?: [];
        }

        $path = $settings->serviceAccountPath();

        if (filled($path) && File::exists($path)) {
            return json_decode(File::get($path), true) ?: [];
        }

        return [];
    }

    protected function folderId(): ?string
    {
        return app(BackupSettingsService::class)->googleDriveFolderId();
    }

    protected function jwt(array $claims, string $privateKey): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $segments = [
            $this->base64Url(json_encode($header)),
            $this->base64Url(json_encode($claims)),
        ];
        $payload = implode('.', $segments);

        openssl_sign($payload, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        $segments[] = $this->base64Url($signature);

        return implode('.', $segments);
    }

    protected function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
