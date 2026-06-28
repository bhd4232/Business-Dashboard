<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class AppRelease
{
    protected const TECHNICAL_SECTION_TITLES = [
        'database',
        'databases',
        'deployment',
        'deployment notes',
        'migration',
        'migrations',
        'operations',
        'technical',
        'technical notes',
    ];

    protected const TECHNICAL_ITEM_PATTERN = '/\b(artisan|backup|backups|cron|database|databases|db|maria(?:db)?|migrate|migrated|migration|migrations|mysql|queue|restore|restored|schema|schemas|seeder|seeders|sqlite|table|tables)\b/i';

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

    public static function latestPublished(): array
    {
        $latest = self::changelogEntries()[0] ?? null;

        if (! $latest) {
            return self::current();
        }

        return [
            'version' => $latest['version'],
            'type' => strtolower(str_replace([' ', '-'], '_', $latest['release_type'])),
            'type_label' => $latest['release_type'],
            'date' => $latest['date'],
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

    public static function userFacingChangelogEntries(): array
    {
        return self::filterEntriesForAudience(includeTechnicalNotes: false);
    }

    public static function technicalChangelogEntries(): array
    {
        return self::filterEntriesForAudience(includeTechnicalNotes: true, onlyTechnicalNotes: true);
    }

    protected static function filterEntriesForAudience(bool $includeTechnicalNotes, bool $onlyTechnicalNotes = false): array
    {
        return collect(self::changelogEntries())
            ->map(function (array $entry) use ($includeTechnicalNotes, $onlyTechnicalNotes): array {
                $entry['sections'] = collect($entry['sections'])
                    ->map(function (array $section) use ($includeTechnicalNotes, $onlyTechnicalNotes): ?array {
                        $technicalSection = self::isTechnicalSection($section['title']);

                        if ($onlyTechnicalNotes && $technicalSection) {
                            return $section;
                        }

                        if (! $includeTechnicalNotes && $technicalSection) {
                            return null;
                        }

                        $section['items'] = collect($section['items'])
                            ->filter(function (string $item) use ($includeTechnicalNotes, $onlyTechnicalNotes): bool {
                                $technicalItem = self::isTechnicalItem($item);

                                if ($onlyTechnicalNotes) {
                                    return $technicalItem;
                                }

                                return $includeTechnicalNotes || ! $technicalItem;
                            })
                            ->values()
                            ->all();

                        return count($section['items']) > 0 ? $section : null;
                    })
                    ->filter()
                    ->values()
                    ->all();

                return $entry;
            })
            ->filter(fn (array $entry): bool => count($entry['sections']) > 0)
            ->values()
            ->all();
    }

    protected static function isTechnicalSection(string $title): bool
    {
        return in_array(strtolower(trim($title)), self::TECHNICAL_SECTION_TITLES, true);
    }

    protected static function isTechnicalItem(string $item): bool
    {
        return preg_match(self::TECHNICAL_ITEM_PATTERN, $item) === 1;
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
