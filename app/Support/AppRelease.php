<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class AppRelease
{
    public const TYPE_LABELS = [
        'initial' => 'Initial Release',
        'major' => 'Major Version Update',
        'minor' => 'Minor Feature Update',
        'patch' => 'Patch/Fix Update',
        'critical_fix' => 'Critical Fix Update',
        'security' => 'Security Update',
        'hotfix' => 'Hotfix Update',
        'maintenance' => 'Maintenance Update',
    ];

    public static function current(): array
    {
        $type = self::type();

        return [
            'version' => self::version(),
            'type' => $type,
            'type_label' => self::typeLabel($type),
            'date' => self::date(),
            'commit' => self::commit(),
            'short_commit' => self::shortCommit(),
        ];
    }

    public static function version(): string
    {
        $version = trim((string) config('release.version', '1.0.0'));

        return $version !== '' ? $version : '1.0.0';
    }

    public static function type(): string
    {
        $type = trim(strtolower((string) config('release.type', 'major')));
        $type = str_replace(['-', ' '], '_', $type);

        return $type !== '' ? $type : 'major';
    }

    public static function typeLabel(?string $type = null): string
    {
        $type ??= self::type();

        return self::TYPE_LABELS[$type] ?? ucwords(str_replace('_', ' ', $type));
    }

    public static function date(): ?string
    {
        $date = trim((string) config('release.date', ''));

        return $date !== '' ? $date : null;
    }

    public static function commit(): ?string
    {
        $commit = trim((string) config('release.commit', ''));

        return $commit !== '' ? $commit : null;
    }

    public static function shortCommit(): ?string
    {
        $commit = self::commit();

        if (! $commit) {
            return null;
        }

        return strlen($commit) > 12 ? substr($commit, 0, 12) : $commit;
    }

    public static function changelogPath(): string
    {
        return base_path('CHANGELOG.md');
    }

    public static function changelog(): string
    {
        $path = self::changelogPath();

        return File::exists($path) ? File::get($path) : '';
    }

    public static function changelogEntries(): array
    {
        $content = self::changelog();

        if (trim($content) === '') {
            return [];
        }

        preg_match_all(
            '/^## \[([^\]]+)\] - ([^\r\n]+)\R(.*?)(?=^## \[|\z)/ms',
            $content,
            $matches,
            PREG_SET_ORDER,
        );

        return array_map(function (array $match): array {
            [$releaseType, $sections] = self::parseChangelogBody($match[3]);

            return [
                'version' => trim($match[1]),
                'date' => trim($match[2]),
                'release_type' => $releaseType,
                'sections' => $sections,
            ];
        }, $matches);
    }

    protected static function parseChangelogBody(string $body): array
    {
        $releaseType = null;
        $sections = [];
        $currentSection = null;

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (preg_match('/^\*\*Release type:\*\*\s*(.+)$/i', $line, $match)) {
                $releaseType = trim($match[1]);

                continue;
            }

            if (preg_match('/^###\s+(.+)$/', $line, $match)) {
                $sections[] = [
                    'title' => trim($match[1]),
                    'items' => [],
                ];
                $currentSection = array_key_last($sections);

                continue;
            }

            if ($currentSection === null) {
                $sections[] = [
                    'title' => 'Notes',
                    'items' => [],
                ];
                $currentSection = array_key_last($sections);
            }

            $sections[$currentSection]['items'][] = str_starts_with($line, '- ')
                ? substr($line, 2)
                : $line;
        }

        return [$releaseType ?: 'Unspecified', $sections];
    }
}
