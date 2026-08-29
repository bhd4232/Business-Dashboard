<?php

namespace Tests\Feature;

use App\Services\CoolifyDeploymentService;
use App\Support\CoolifySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class CoolifyDeploymentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CoolifySettings::save([
            'enabled' => true,
            'base_url' => 'https://coolify.example.com',
            'api_token' => 'fake-token',
            'application_uuid' => 'jc0k48s',
        ]);
    }

    public function test_rollback_candidates_excludes_the_current_deployment_and_non_finished_ones(): void
    {
        Http::fake([
            'coolify.example.com/api/v1/deployments/applications/jc0k48s*' => Http::response([
                'count' => 4,
                'deployments' => [
                    ['deployment_uuid' => 'd4', 'commit' => 'cccc111', 'commit_message' => 'in progress now', 'status' => 'in_progress', 'created_at' => '2026-08-29T10:00:00Z'],
                    ['deployment_uuid' => 'd3', 'commit' => 'bbbb222', 'commit_message' => 'currently live', 'status' => 'finished', 'created_at' => '2026-08-28T10:00:00Z'],
                    ['deployment_uuid' => 'd2', 'commit' => 'aaaa333', 'commit_message' => 'one before that', 'status' => 'finished', 'created_at' => '2026-08-27T10:00:00Z'],
                    ['deployment_uuid' => 'd1', 'commit' => '999444', 'commit_message' => 'a failed attempt', 'status' => 'failed', 'created_at' => '2026-08-26T10:00:00Z'],
                ],
            ]),
        ]);

        $candidates = app(CoolifyDeploymentService::class)->rollbackCandidates(5);

        $this->assertCount(1, $candidates);
        $this->assertSame('aaaa333', $candidates[0]['commit']);
        $this->assertSame('one before that', $candidates[0]['commit_message']);

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer fake-token')
            && str_contains($request->url(), '/deployments/applications/jc0k48s'));
    }

    public function test_rollback_posts_the_commit_and_returns_the_queued_message(): void
    {
        Http::fake([
            'coolify.example.com/api/v1/applications/jc0k48s/rollback' => Http::response([
                'message' => 'Rollback deployment queued.',
                'deployment_uuid' => 'new-deployment-uuid',
            ]),
        ]);

        $result = app(CoolifyDeploymentService::class)->rollback('aaaa333');

        $this->assertSame('Rollback deployment queued.', $result['message']);
        $this->assertSame('new-deployment-uuid', $result['deployment_uuid']);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://coolify.example.com/api/v1/applications/jc0k48s/rollback'
            && $request['commit'] === 'aaaa333'
            && $request->method() === 'POST');
    }

    public function test_rollback_throws_a_friendly_error_on_failure(): void
    {
        Http::fake([
            'coolify.example.com/api/v1/applications/jc0k48s/rollback' => Http::response([
                'message' => 'Application not found.',
            ], 404),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Application not found.');

        app(CoolifyDeploymentService::class)->rollback('deadbeef');
    }
}
