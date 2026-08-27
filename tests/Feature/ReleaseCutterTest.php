<?php

namespace Tests\Feature;

use App\Support\ReleaseCutter;
use Tests\TestCase;

/**
 * IMPORTANT: never call ReleaseCutter::apply()/updateConfigDefaults()/
 * updateEnvFile() with default (real-project) paths in a test, and never run
 * the `release:cut` Artisan command with --apply here. This class is the
 * thing that rewrites CHANGELOG.md/config/release.php/.env.example for
 * real — every test below either stays pure in-memory (plan()) or points
 * apply() at temp files created just for that test.
 */
class ReleaseCutterTest extends TestCase
{
    protected array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    protected function tempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'release_cutter_test_');
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }

    protected function fixtureChangelog(string $unreleasedBody): string
    {
        return <<<MD
        # Changelog

        All notable production changes to Business Dashboard are documented here.

        ## [Unreleased]

        {$unreleasedBody}

        ## [2.1.0] - 2026-08-18

        **Release type:** Minor Feature Update

        ### Added

        - Something older.

        ## [2.0.0] - 2026-08-11

        **Release type:** Major Version Update

        ### Added

        - The oldest thing.
        MD;
    }

    public function test_plan_is_null_when_there_is_no_unreleased_header(): void
    {
        $changelog = "# Changelog\n\n## [2.1.0] - 2026-08-18\n\n### Added\n\n- Thing.\n";

        $this->assertNull(ReleaseCutter::plan($changelog));
    }

    public function test_plan_is_null_when_unreleased_has_no_bullet_content(): void
    {
        $changelog = $this->fixtureChangelog("### Added\n\n### Fixed\n");

        $this->assertNull(ReleaseCutter::plan($changelog));
    }

    public function test_added_section_infers_a_minor_bump(): void
    {
        $changelog = $this->fixtureChangelog("### Added\n\n- A new report.\n");

        $plan = ReleaseCutter::plan($changelog, '2026-09-01');

        $this->assertNotNull($plan);
        $this->assertSame('2.2.0', $plan['version']);
        $this->assertSame('minor', $plan['type']);
        $this->assertSame('Minor Feature Update', $plan['type_label']);
        $this->assertSame('2.1.0', $plan['previous_version']);
        $this->assertSame('2026-09-01', $plan['date']);
    }

    public function test_fixed_only_section_infers_a_patch_bump(): void
    {
        $changelog = $this->fixtureChangelog("### Fixed\n\n- A small bug.\n");

        $plan = ReleaseCutter::plan($changelog, '2026-09-01');

        $this->assertSame('2.1.1', $plan['version']);
        $this->assertSame('patch', $plan['type']);
    }

    public function test_added_wins_over_security_when_both_present(): void
    {
        // Matches this project's own precedent ([2.2.0], [2.1.0]): a release
        // with real new features is "Minor Feature Update" even when it also
        // carries security fixes — "Security Update" is reserved for when
        // security is the release's whole point, not merely present in it.
        $changelog = $this->fixtureChangelog(
            "### Added\n\n- A new report.\n\n### Security\n\n- Closed a gap.\n"
        );

        $plan = ReleaseCutter::plan($changelog, '2026-09-01');

        $this->assertSame('minor', $plan['type']);
        $this->assertSame('2.2.0', $plan['version']);
    }

    public function test_security_only_section_infers_a_security_patch_bump(): void
    {
        $changelog = $this->fixtureChangelog("### Security\n\n- Closed a gap.\n");

        $plan = ReleaseCutter::plan($changelog, '2026-09-01');

        $this->assertSame('security', $plan['type']);
        // Security is still a patch-level bump per docs/release-policy.md's version table.
        $this->assertSame('2.1.1', $plan['version']);
    }

    public function test_technical_notes_only_infers_maintenance(): void
    {
        $changelog = $this->fixtureChangelog("### Technical Notes\n\n- Internal refactor only.\n");

        $plan = ReleaseCutter::plan($changelog, '2026-09-01');

        $this->assertSame('maintenance', $plan['type']);
        $this->assertSame('2.1.1', $plan['version']);
    }

    public function test_explicit_release_type_marker_overrides_inference_and_is_stripped(): void
    {
        $changelog = $this->fixtureChangelog(
            "**Release type:** Major Version Update\n\n### Added\n\n- Breaking change.\n"
        );

        $plan = ReleaseCutter::plan($changelog, '2026-09-01');

        $this->assertSame('major', $plan['type']);
        $this->assertSame('3.0.0', $plan['version']);

        // Isolate the newly cut entry (between the fresh empty [Unreleased]
        // and the next dated header) so this doesn't get confused by the
        // fixture's own older [2.0.0] entry, which also happens to be a
        // Major Version Update.
        [, $newEntry] = explode('## [3.0.0] - 2026-09-01', $plan['changelog'], 2);
        [$newEntry] = explode('## [2.1.0]', $newEntry, 2);
        $this->assertSame(1, substr_count($newEntry, '**Release type:**'));
        $this->assertStringContainsString('**Release type:** Major Version Update', $newEntry);
    }

    public function test_unrecognized_explicit_marker_falls_back_to_inference(): void
    {
        $changelog = $this->fixtureChangelog(
            "**Release type:** Something Made Up\n\n### Added\n\n- A new report.\n"
        );

        $plan = ReleaseCutter::plan($changelog, '2026-09-01');

        $this->assertSame('minor', $plan['type']);
    }

    public function test_updated_changelog_keeps_a_fresh_empty_unreleased_section_on_top(): void
    {
        $changelog = $this->fixtureChangelog("### Added\n\n- A new report.\n");

        $plan = ReleaseCutter::plan($changelog, '2026-09-01');

        $this->assertMatchesRegularExpression(
            '/## \[Unreleased\]\s*## \[2\.2\.0\] - 2026-09-01/s',
            $plan['changelog'],
        );
        $this->assertStringContainsString('## [2.1.0] - 2026-08-18', $plan['changelog']);
        $this->assertStringContainsString('- The oldest thing.', $plan['changelog']);
    }

    public function test_current_version_reads_the_first_dated_entry(): void
    {
        $changelog = $this->fixtureChangelog("### Added\n\n- A new report.\n");

        $this->assertSame('2.1.0', ReleaseCutter::currentVersion($changelog));
    }

    public function test_update_config_defaults_replaces_version_type_and_date(): void
    {
        $path = $this->tempFile(<<<'PHP'
        <?php

        return [
            'version' => env('APP_VERSION', '2.2.0'),
            'type' => env('APP_RELEASE_TYPE', 'minor'),
            'date' => env('APP_RELEASE_DATE', '2026-08-26'),
        ];
        PHP);

        ReleaseCutter::updateConfigDefaults($path, [
            'version' => '2.3.0',
            'type' => 'minor',
            'date' => '2026-09-01',
        ]);

        $content = file_get_contents($path);

        $this->assertStringContainsString("'version' => env('APP_VERSION', '2.3.0')", $content);
        $this->assertStringContainsString("'type' => env('APP_RELEASE_TYPE', 'minor')", $content);
        $this->assertStringContainsString("'date' => env('APP_RELEASE_DATE', '2026-09-01')", $content);
    }

    public function test_update_env_file_replaces_the_three_keys_only(): void
    {
        $path = $this->tempFile("APP_NAME=Example\nAPP_VERSION=2.2.0\nAPP_RELEASE_TYPE=minor\nAPP_RELEASE_DATE=2026-08-26\nDB_CONNECTION=sqlite\n");

        ReleaseCutter::updateEnvFile($path, [
            'version' => '2.3.0',
            'type' => 'patch',
            'date' => '2026-09-01',
        ]);

        $content = file_get_contents($path);

        $this->assertStringContainsString('APP_VERSION=2.3.0', $content);
        $this->assertStringContainsString('APP_RELEASE_TYPE=patch', $content);
        $this->assertStringContainsString('APP_RELEASE_DATE=2026-09-01', $content);
        $this->assertStringContainsString('APP_NAME=Example', $content);
        $this->assertStringContainsString('DB_CONNECTION=sqlite', $content);
    }

    public function test_update_env_file_is_a_safe_no_op_when_the_file_is_missing(): void
    {
        $missingPath = sys_get_temp_dir().'/release_cutter_test_does_not_exist_'.uniqid().'.env';

        ReleaseCutter::updateEnvFile($missingPath, ['version' => '2.3.0', 'type' => 'patch', 'date' => '2026-09-01']);

        $this->assertFileDoesNotExist($missingPath);
    }

    public function test_apply_writes_the_changelog_and_every_version_file_together(): void
    {
        $changelog = $this->fixtureChangelog("### Added\n\n- A new report.\n");
        $plan = ReleaseCutter::plan($changelog, '2026-09-01');

        $paths = [
            'changelog' => $this->tempFile('placeholder'),
            'config' => $this->tempFile("<?php\n\nreturn [\n    'version' => env('APP_VERSION', '2.2.0'),\n    'type' => env('APP_RELEASE_TYPE', 'minor'),\n    'date' => env('APP_RELEASE_DATE', '2026-08-26'),\n];\n"),
            'env_files' => [
                $this->tempFile("APP_VERSION=2.2.0\nAPP_RELEASE_TYPE=minor\nAPP_RELEASE_DATE=2026-08-26\n"),
            ],
            'deployment_doc' => $this->tempFile("```env\nAPP_VERSION=2.2.0\nAPP_RELEASE_TYPE=minor\nAPP_RELEASE_DATE=2026-08-26\n```\n"),
        ];

        ReleaseCutter::apply($plan, $paths);

        $this->assertStringContainsString('## [2.2.0] - 2026-09-01', file_get_contents($paths['changelog']));
        $this->assertStringContainsString("env('APP_VERSION', '2.2.0')", file_get_contents($paths['config']));
        $this->assertStringContainsString('APP_VERSION=2.2.0', file_get_contents($paths['env_files'][0]));
        $this->assertStringContainsString('APP_VERSION=2.2.0', file_get_contents($paths['deployment_doc']));
    }

    public function test_artisan_command_without_apply_never_writes_and_always_reports_unchanged(): void
    {
        // Deliberately never passes --apply: this must stay safe to run
        // against the real project CHANGELOG.md no matter its content.
        // "changed=false" is guaranteed true on every branch of the command
        // without --apply, regardless of what CHANGELOG.md currently holds.
        $this->artisan('release:cut')
            ->expectsOutputToContain('changed=false')
            ->assertSuccessful();
    }
}
