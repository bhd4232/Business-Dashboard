<?php

namespace App\Services;

use App\Support\CoolifySettings;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Talks to the Coolify instance this app is deployed on (self-hosted on the
 * owner's own Contabo VPS -- see App\Support\CoolifySettings), so a super
 * admin can roll the running app back to a recent previous deployment
 * without leaving the ERP dashboard. Endpoints confirmed directly against
 * Coolify's own source (coollabsio/coolify, routes/api.php +
 * app/Http/Controllers/Api/{ApplicationsController,DeployController}.php):
 *
 *   GET  /api/v1/deployments/applications/{uuid}?skip=&take=
 *        -> {"count": int, "deployments": [{deployment_uuid, commit,
 *           commit_message, status, created_at, ...}, ...]}
 *   POST /api/v1/applications/{uuid}/rollback  body: {"commit": "<sha>"}
 *        -> queues a redeploy of that exact commit (rollback: true
 *           internally) -- the app's code goes back to that version; this
 *           never touches the database or runs any migration in either
 *           direction, by design (see the owner's explicit "code-only
 *           rollback" scoping decision).
 *
 * Deliberately does not attempt a database/migration rollback -- rolling
 * schema back risks real data loss (e.g. a dropped column), which is out of
 * scope here on purpose.
 */
class CoolifyDeploymentService
{
    public const STATUS_FINISHED = 'finished';

    public function isConfigured(): bool
    {
        return CoolifySettings::isConfigured();
    }

    /**
     * The last $limit *finished* deployments, most recent first, excluding
     * whichever finished deployment is currently live -- these are exactly
     * the versions it makes sense to offer a "rollback to this version"
     * button for.
     *
     * @return array<int, array{deployment_uuid: string, commit: string, commit_message: ?string, created_at: ?string}>
     */
    public function rollbackCandidates(int $limit = 5): array
    {
        // Fetched generously past $limit: queued/in-progress/failed entries
        // sort ahead of the finished ones we actually want, and the very
        // first finished entry is the currently-live version, not a
        // candidate -- both get filtered out below before capping to $limit.
        $deployments = $this->fetchDeployments(take: max($limit * 4, 20));

        $finished = collect($deployments)
            ->filter(fn (array $deployment): bool => ($deployment['status'] ?? null) === self::STATUS_FINISHED)
            ->values();

        return $finished
            ->skip(1) // the first finished entry is the currently-live deployment
            ->take($limit)
            ->values()
            ->map(fn (array $deployment): array => [
                'deployment_uuid' => (string) $deployment['deployment_uuid'],
                'commit' => (string) $deployment['commit'],
                'commit_message' => $deployment['commit_message'] ?? null,
                'created_at' => $deployment['created_at'] ?? null,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchDeployments(int $take): array
    {
        $response = $this->client()->get('/deployments/applications/'.CoolifySettings::applicationUuid(), [
            'skip' => 0,
            'take' => $take,
        ]);

        if ($response->failed()) {
            throw new RuntimeException($this->friendlyError($response));
        }

        return (array) $response->json('deployments', []);
    }

    /**
     * Queues a redeploy of $commit -- Coolify's own worker builds and swaps
     * it in; this call only confirms the rollback was *queued*, not that it
     * has finished (there is no synchronous "wait for it" in Coolify's API).
     *
     * @return array{message: string, deployment_uuid: ?string}
     */
    public function rollback(string $commit): array
    {
        $response = $this->client()->post('/applications/'.CoolifySettings::applicationUuid().'/rollback', [
            'commit' => $commit,
        ]);

        if ($response->failed()) {
            throw new RuntimeException($this->friendlyError($response));
        }

        return [
            'message' => (string) $response->json('message', 'Rollback deployment queued.'),
            'deployment_uuid' => $response->json('deployment_uuid'),
        ];
    }

    protected function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken((string) CoolifySettings::apiToken())
            ->baseUrl(rtrim((string) CoolifySettings::baseUrl(), '/').'/api/v1')
            ->acceptJson()
            ->timeout(20);
    }

    protected function friendlyError(\Illuminate\Http\Client\Response $response): string
    {
        $message = $response->json('message');

        if (is_string($message) && $message !== '') {
            return "Coolify returned HTTP {$response->status()}: {$message}";
        }

        return "Coolify returned HTTP {$response->status()} with no message body -- check the base URL, API token, and Application UUID saved in Deployment Settings.";
    }
}
