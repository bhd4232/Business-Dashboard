<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Converts CHANGELOG.md's `[Unreleased]` section into a new, dated,
 * versioned release entry, and syncs the repo's version-default files to
 * match — the same mechanical steps a release commit has always required by
 * hand (see docs/release-policy.md), now safe to run unattended on every
 * push to `main` (see .github/workflows/deploy.yml's `cut-release` job).
 *
 * Every method here is pure (string in, string/array out) except `apply()`
 * and its file-writing helpers, which are the only place this class touches
 * disk — kept separate so the decision logic (`plan()`) stays trivially unit
 * testable against in-memory changelog content, never the real project files.
 */
class ReleaseCutter
{
    /**
     * Release types this class will infer on its own from which changelog
     * sections carry content. Deliberately excludes `major`, `critical_fix`,
     * `hotfix`, and `initial` — those signal something a human/agent should
     * decide on purpose, never something that should happen by accident just
     * because a commit's bullets matched a pattern. Write an explicit
     * `**Release type:** Major Version Update` (or Critical Fix / Hotfix)
     * line as the first line under `[Unreleased]` to request one of those
     * instead; any recognized `docs/release-policy.md` type name works.
     */
    protected const AUTO_INFERRED_TYPES = ['security', 'minor', 'patch', 'maintenance'];

    /**
     * Build a plan for cutting a release from the given changelog content.
     * Returns null when there's nothing to release yet (no `[Unreleased]`
     * header, or the section has no actual bullet content) — a safe no-op
     * the caller can rely on to mean "don't touch anything".
     *
     * @return array{version: string, type: string, type_label: string, date: string, previous_version: string, changelog: string}|null
     */
    public static function plan(string $changelog, ?string $today = null): ?array
    {
        $section = self::unreleasedSection($changelog);

        if ($section === null) {
            return null;
        }

        $body = trim($section['body']);

        if (! self::hasReleasableContent($body)) {
            return null;
        }

        [$explicitType, $sections] = AppRelease::parseChangelogBody($body);

        $typeKey = self::resolveTypeKey($explicitType, $sections);
        $typeLabel = AppRelease::TYPE_LABELS[$typeKey] ?? $explicitType;

        $previousVersion = self::currentVersion($changelog) ?? '0.0.0';
        $newVersion = self::bump($previousVersion, $typeKey);
        $date = $today ?? gmdate('Y-m-d');

        $cleanBody = self::stripReleaseTypeMarker($body);
        $newEntry = "## [{$newVersion}] - {$date}\n\n**Release type:** {$typeLabel}\n\n{$cleanBody}\n";

        $updatedChangelog = substr($changelog, 0, $section['start'])
            ."## [Unreleased]\n\n"
            .$newEntry
            .substr($changelog, $section['end']);

        return [
            'version' => $newVersion,
            'type' => $typeKey,
            'type_label' => $typeLabel,
            'date' => $date,
            'previous_version' => $previousVersion,
            'changelog' => $updatedChangelog,
        ];
    }

    /**
     * The most recent already-dated version in the changelog (i.e. the
     * version the new release should bump from). Null if none exists yet
     * (a brand-new project with only an `[Unreleased]` section).
     */
    public static function currentVersion(string $changelog): ?string
    {
        return preg_match('/^## \[(\d+\.\d+\.\d+)\] - /m', $changelog, $match) === 1
            ? $match[1]
            : null;
    }

    /**
     * Write a plan's changelog content and mirror its version/type/date into
     * every file `docs/release-policy.md` says a release must update
     * (excluding the production server's own env values, which stay a
     * separate manual/owner step — this can't reach a live server).
     *
     * @param  array{version: string, type: string, type_label: string, date: string, previous_version: string, changelog: string}  $plan
     * @param  array{changelog: string, config: string, env_files: list<string>, deployment_doc: string}|null  $paths  Override for testing; defaults to the real project files.
     */
    public static function apply(array $plan, ?array $paths = null): void
    {
        $paths ??= [
            'changelog' => AppRelease::changelogPath(),
            'config' => config_path('release.php'),
            'env_files' => [base_path('.env.example'), base_path('.env.production.example')],
            'deployment_doc' => base_path('docs/deployment.md'),
        ];

        File::put($paths['changelog'], $plan['changelog']);

        self::updateConfigDefaults($paths['config'], $plan);

        foreach ($paths['env_files'] as $envPath) {
            self::updateEnvFile($envPath, $plan);
        }

        self::updateEnvFile($paths['deployment_doc'], $plan);
    }

    /**
     * @param  array{version: string, type: string, date: string}  $plan
     */
    public static function updateConfigDefaults(string $path, array $plan): void
    {
        if (! File::exists($path)) {
            return;
        }

        $content = File::get($path);

        $content = preg_replace(
            "/'version' => env\('APP_VERSION', '[^']*'\)/",
            "'version' => env('APP_VERSION', '{$plan['version']}')",
            $content,
            1,
        );
        $content = preg_replace(
            "/'type' => env\('APP_RELEASE_TYPE', '[^']*'\)/",
            "'type' => env('APP_RELEASE_TYPE', '{$plan['type']}')",
            $content,
            1,
        );
        $content = preg_replace(
            "/'date' => env\('APP_RELEASE_DATE', '[^']*'\)/",
            "'date' => env('APP_RELEASE_DATE', '{$plan['date']}')",
            $content,
            1,
        );

        File::put($path, $content);
    }

    /**
     * Updates plain `KEY=value` lines — used for `.env.example`,
     * `.env.production.example`, and `docs/deployment.md`'s embedded env
     * block, which all share this exact line format. Leaves any file that
     * doesn't have these keys (or doesn't exist) untouched.
     *
     * @param  array{version: string, type: string, date: string}  $plan
     */
    public static function updateEnvFile(string $path, array $plan): void
    {
        if (! File::exists($path)) {
            return;
        }

        $content = File::get($path);
        $content = preg_replace('/^APP_VERSION=.*/m', 'APP_VERSION='.$plan['version'], $content, 1);
        $content = preg_replace('/^APP_RELEASE_TYPE=.*/m', 'APP_RELEASE_TYPE='.$plan['type'], $content, 1);
        $content = preg_replace('/^APP_RELEASE_DATE=.*/m', 'APP_RELEASE_DATE='.$plan['date'], $content, 1);

        File::put($path, $content);
    }

    /**
     * @return array{start: int, end: int, body: string}|null
     */
    protected static function unreleasedSection(string $changelog): ?array
    {
        if (preg_match('/^## \[Unreleased\][ \t]*\R/m', $changelog, $headerMatch, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $start = $headerMatch[0][1];
        $bodyStart = $start + strlen($headerMatch[0][0]);

        $bodyEnd = preg_match('/^## \[/m', $changelog, $nextMatch, PREG_OFFSET_CAPTURE, $bodyStart) === 1
            ? $nextMatch[0][1]
            : strlen($changelog);

        return [
            'start' => $start,
            'end' => $bodyEnd,
            'body' => substr($changelog, $bodyStart, $bodyEnd - $bodyStart),
        ];
    }

    protected static function hasReleasableContent(string $body): bool
    {
        return preg_match('/^-\s+\S/m', $body) === 1;
    }

    /**
     * @param  array<int, array{title: string, items: list<string>}>  $sections
     */
    protected static function resolveTypeKey(string $explicitType, array $sections): string
    {
        if ($explicitType !== 'Unspecified') {
            $normalized = str_replace([' ', '-'], '_', strtolower(trim($explicitType)));

            foreach (AppRelease::TYPE_LABELS as $key => $label) {
                $normalizedLabel = str_replace([' ', '-'], '_', strtolower($label));

                if ($normalized === $key || $normalized === $normalizedLabel) {
                    return $key;
                }
            }
        }

        $sectionHasItems = function (string $title) use ($sections): bool {
            foreach ($sections as $section) {
                if (strtolower(trim($section['title'])) === $title && count($section['items']) > 0) {
                    return true;
                }
            }

            return false;
        };

        // 'Added' wins even when a 'Security' section is also present: this
        // project's own past releases ([2.2.0], [2.1.0], ...) both shipped
        // real security fixes alongside new features and were still cut as
        // "Minor Feature Update", not "Security Update" — that label is for
        // when a security fix is the release's whole point, not merely
        // present in it. See docs/release-policy.md's version table: every
        // non-major/non-minor type bumps the version number identically
        // (PATCH+1) anyway, so this only changes the label, not the number,
        // for a Fixed/Security-only release.
        if ($sectionHasItems('added')) {
            return 'minor';
        }

        if ($sectionHasItems('security')) {
            return 'security';
        }

        if ($sectionHasItems('changed') || $sectionHasItems('fixed')) {
            return 'patch';
        }

        return 'maintenance';
    }

    protected static function bump(string $version, string $typeKey): string
    {
        [$major, $minor, $patch] = array_pad(
            array_map('intval', explode('.', $version)),
            3,
            0,
        );

        return match ($typeKey) {
            'major' => ($major + 1).'.0.0',
            'minor' => $major.'.'.($minor + 1).'.0',
            default => $major.'.'.$minor.'.'.($patch + 1),
        };
    }

    protected static function stripReleaseTypeMarker(string $body): string
    {
        return trim(preg_replace('/^\*\*Release type:\*\*.*\R+/i', '', $body, 1));
    }
}
