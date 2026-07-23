<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

final class AppDeployment
{
    public static function current(): array
    {
        $manifest = self::deploymentManifest();
        $runtimeCommit = self::normalize(config('release.commit'));
        $manifestCommit = self::normalize($manifest['commit'] ?? null);
        $commit = $runtimeCommit ?? $manifestCommit;
        $sourceId = self::normalize($manifest['source_id'] ?? null);
        $manifestAssetsId = self::normalize($manifest['assets_id'] ?? null);
        $actualAssetsId = self::assetManifestId();
        $assetsId = $manifestAssetsId ?? $actualAssetsId;
        $builtAt = self::normalize(config('release.deployment_built_at'))
            ?? self::normalize($manifest['built_at'] ?? null);
        $configuredId = self::normalize(config('release.deployment_id'));
        $manifestId = self::normalize($manifest['deployment_id'] ?? null);
        $manifestReady = (bool) ($manifest['ready'] ?? false)
            && $manifestId !== null
            && $sourceId !== null
            && $manifestAssetsId !== null
            && $actualAssetsId !== null
            && $builtAt !== null
            && hash_equals(strtolower($manifestAssetsId), strtolower($actualAssetsId));

        if ($configuredId !== null) {
            $deploymentId = self::identity('configured', $configuredId);
            $ready = $builtAt !== null;
        } elseif ($manifestId !== null) {
            $deploymentId = $manifestId;
            $ready = $manifestReady
                && (
                    $runtimeCommit === null
                    || $manifestCommit === null
                    || self::commitsMatch($runtimeCommit, $manifestCommit)
                );
        } elseif ($runtimeCommit !== null) {
            $deploymentId = self::identity('commit', strtolower($runtimeCommit));
            $ready = false;
        } elseif ($assetsId !== null) {
            $deploymentId = self::identity('assets', $assetsId);
            $ready = false;
        } else {
            $deploymentId = self::identity('release', self::releaseFallbackMaterial());
            $ready = false;
        }

        return [
            'deployment_id' => $deploymentId,
            'commit' => $commit,
            'short_commit' => self::shortCommit($commit),
            'built_at' => $builtAt,
            'source_id' => $sourceId,
            'assets_id' => $assetsId,
            'ready' => $ready,
        ];
    }

    public static function id(): string
    {
        return self::current()['deployment_id'];
    }

    public static function isReady(): bool
    {
        return self::current()['ready'];
    }

    public static function identity(string $kind, string $value): string
    {
        return 'deploy_'.hash('sha256', trim($kind)."\0".trim($value));
    }

    protected static function deploymentManifest(): array
    {
        $path = self::normalize(config('release.deployment_manifest'));

        if ($path === null || ! File::isFile($path)) {
            return [];
        }

        $decoded = json_decode(File::get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    protected static function assetManifestId(): ?string
    {
        $path = self::normalize(config('release.asset_manifest'));

        if ($path === null || ! File::isFile($path)) {
            return null;
        }

        $hash = hash_file('sha256', $path);

        return is_string($hash) && $hash !== '' ? $hash : null;
    }

    protected static function releaseFallbackMaterial(): string
    {
        return implode("\0", [
            AppRelease::version(),
            AppRelease::type(),
            AppRelease::date() ?? '',
        ]);
    }

    protected static function shortCommit(?string $commit): ?string
    {
        if ($commit === null) {
            return null;
        }

        return strlen($commit) > 12 ? substr($commit, 0, 12) : $commit;
    }

    protected static function commitsMatch(string $left, string $right): bool
    {
        $left = strtolower($left);
        $right = strtolower($right);

        if ($left === $right) {
            return true;
        }

        $shorterLength = min(strlen($left), strlen($right));

        return $shorterLength >= 7
            && substr($left, 0, $shorterLength) === substr($right, 0, $shorterLength);
    }

    protected static function normalize(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
