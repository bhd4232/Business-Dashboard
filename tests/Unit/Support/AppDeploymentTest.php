<?php

namespace Tests\Unit\Support;

use App\Support\AppDeployment;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AppDeploymentTest extends TestCase
{
    protected array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            File::delete($path);
        }

        parent::tearDown();
    }

    public function test_runtime_and_manifest_commit_mismatch_fails_closed(): void
    {
        $assetManifestPath = $this->temporaryFile('{"resources/js/app.js":{"file":"app-123.js"}}');
        $manifestPath = $this->temporaryJsonFile([
            'deployment_id' => 'deploy_from_manifest',
            'commit' => 'abcdef0123456789',
            'built_at' => '2026-07-23T01:02:03.000Z',
            'source_id' => 'source-hash',
            'assets_id' => hash_file('sha256', $assetManifestPath),
            'ready' => true,
        ]);

        Config::set('release.commit', '1234567890abcdef');
        Config::set('release.deployment_manifest', $manifestPath);
        Config::set('release.asset_manifest', $assetManifestPath);

        $deployment = AppDeployment::current();

        $this->assertSame('deploy_from_manifest', $deployment['deployment_id']);
        $this->assertSame('1234567890abcdef', $deployment['commit']);
        $this->assertSame('1234567890ab', $deployment['short_commit']);
        $this->assertFalse($deployment['ready']);
    }

    public function test_generated_manifest_is_used_when_runtime_commit_is_unavailable(): void
    {
        $assetManifestPath = $this->temporaryFile('{"resources/js/app.js":{"file":"app-456.js"}}');
        $assetsId = hash_file('sha256', $assetManifestPath);
        $manifestPath = $this->temporaryJsonFile([
            'deployment_id' => 'deploy_generated123',
            'commit' => 'abcdef0123456789',
            'built_at' => '2026-07-23T01:02:03.000Z',
            'source_id' => 'source-hash',
            'assets_id' => $assetsId,
            'ready' => true,
        ]);

        Config::set('release.commit', null);
        Config::set('release.deployment_id', null);
        Config::set('release.deployment_manifest', $manifestPath);
        Config::set('release.asset_manifest', $assetManifestPath);

        $this->assertSame([
            'deployment_id' => 'deploy_generated123',
            'commit' => 'abcdef0123456789',
            'short_commit' => 'abcdef012345',
            'built_at' => '2026-07-23T01:02:03.000Z',
            'source_id' => 'source-hash',
            'assets_id' => $assetsId,
            'ready' => true,
        ], AppDeployment::current());
    }

    public function test_asset_manifest_alone_provides_a_deterministic_but_unready_fallback(): void
    {
        $assetManifestPath = $this->temporaryFile('{"resources/js/app.js":{"file":"app-123.js"}}');
        $assetsId = hash_file('sha256', $assetManifestPath);

        Config::set('release.commit', null);
        Config::set('release.deployment_id', null);
        Config::set('release.deployment_manifest', $this->missingPath());
        Config::set('release.asset_manifest', $assetManifestPath);

        $first = AppDeployment::current();
        $second = AppDeployment::current();

        $this->assertSame(AppDeployment::identity('assets', $assetsId), $first['deployment_id']);
        $this->assertSame($assetsId, $first['assets_id']);
        $this->assertNull($first['source_id']);
        $this->assertFalse($first['ready']);
        $this->assertSame($first, $second);
    }

    public function test_stale_deployment_metadata_with_different_built_assets_fails_closed(): void
    {
        $assetManifestPath = $this->temporaryFile('{"resources/js/app.js":{"file":"app-current.js"}}');
        $manifestPath = $this->temporaryJsonFile([
            'deployment_id' => 'deploy_stale',
            'commit' => null,
            'built_at' => '2026-07-23T01:02:03.000Z',
            'source_id' => 'source-hash',
            'assets_id' => hash('sha256', 'different-manifest'),
            'ready' => true,
        ]);

        Config::set('release.commit', null);
        Config::set('release.deployment_id', null);
        Config::set('release.deployment_manifest', $manifestPath);
        Config::set('release.asset_manifest', $assetManifestPath);

        $this->assertFalse(AppDeployment::isReady());
    }

    public function test_runtime_commit_without_build_metadata_fails_closed(): void
    {
        Config::set('release.commit', str_repeat('a', 40));
        Config::set('release.deployment_id', null);
        Config::set('release.deployment_manifest', $this->missingPath());
        Config::set('release.asset_manifest', $this->missingPath());

        $deployment = AppDeployment::current();

        $this->assertSame(
            AppDeployment::identity('commit', str_repeat('a', 40)),
            $deployment['deployment_id'],
        );
        $this->assertFalse($deployment['ready']);
    }

    public function test_release_metadata_provides_a_deterministic_unready_last_fallback(): void
    {
        Config::set('release.commit', null);
        Config::set('release.deployment_id', null);
        Config::set('release.deployment_manifest', $this->missingPath());
        Config::set('release.asset_manifest', $this->missingPath());
        Config::set('release.version', '2.5.0');
        Config::set('release.type', 'minor');
        Config::set('release.date', '2026-07-23');

        $first = AppDeployment::current();
        $second = AppDeployment::current();

        $this->assertSame($first['deployment_id'], $second['deployment_id']);
        $this->assertFalse($first['ready']);

        Config::set('release.version', '2.5.1');

        $this->assertNotSame($first['deployment_id'], AppDeployment::id());
    }

    protected function temporaryJsonFile(array $contents): string
    {
        return $this->temporaryFile(json_encode($contents, JSON_THROW_ON_ERROR));
    }

    protected function temporaryFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'app-deployment-');

        File::put($path, $contents);
        $this->temporaryFiles[] = $path;

        return $path;
    }

    protected function missingPath(): string
    {
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.'missing-'.bin2hex(random_bytes(8));
    }
}
