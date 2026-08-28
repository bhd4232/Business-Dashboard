<?php

namespace App\Console\Commands;

use App\Support\AppRelease;
use App\Support\ReleaseCutter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Cuts a new dated release from CHANGELOG.md's `[Unreleased]` section.
 *
 * Run unattended by the `cut-release` job in .github/workflows/deploy.yml on
 * every push to `main`, so a release can never again sit un-cut for weeks
 * the way `[Unreleased]` did for 25 commits before v2.2.0 (2026-08-26).
 * Safe to run any time: a no-op whenever there's nothing new to release.
 *
 * Writing changes requires --apply on purpose — without it this only shows
 * what would happen, so this can never be run by habit/muscle-memory
 * (`php artisan release:cut`) and accidentally rewrite CHANGELOG.md.
 */
class CutRelease extends Command
{
    protected $signature = 'release:cut {--apply : Actually write the changes; without this flag, only shows what would happen}';

    protected $description = "Convert CHANGELOG.md's [Unreleased] section into a new dated release entry, and sync config/release.php + .env.example + .env.production.example + docs/deployment.md to match";

    public function handle(): int
    {
        $changelog = File::get(AppRelease::changelogPath());
        $plan = ReleaseCutter::plan($changelog);

        if ($plan === null) {
            $this->info('Nothing to release — [Unreleased] has no bullet content yet.');
            $this->line('changed=false');

            return self::SUCCESS;
        }

        $this->info("Cutting v{$plan['version']} ({$plan['type_label']}) from v{$plan['previous_version']}, dated {$plan['date']}.");

        if (! $this->option('apply')) {
            $this->line('Dry run (pass --apply to write these changes) — no files were touched.');
            $this->line('changed=false');

            return self::SUCCESS;
        }

        ReleaseCutter::apply($plan);

        $this->info('CHANGELOG.md, config/release.php, .env.example, .env.production.example, and docs/deployment.md updated.');
        $this->line('changed=true');
        $this->line("version={$plan['version']}");
        $this->line("type={$plan['type']}");
        $this->line("date={$plan['date']}");

        return self::SUCCESS;
    }
}
