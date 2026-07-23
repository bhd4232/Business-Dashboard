<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AppUpdateService;
use App\Support\AppDeployment;
use Filament\Notifications\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;

class AppUpgradeController extends Controller
{
    public function synchronize(Request $request, AppUpdateService $appUpdates): JsonResponse
    {
        $user = $request->user();

        abort_unless($user, 401);

        $expectedDeploymentId = $request->validate([
            'deployment_id' => ['required', 'string', 'max:128'],
        ])['deployment_id'];
        $deployment = AppDeployment::current();

        if (
            ! $appUpdates->isDeploymentEligible($deployment)
            || ! hash_equals(
                (string) ($deployment['deployment_id'] ?? ''),
                (string) $expectedDeploymentId,
            )
        ) {
            return response()
                ->json([
                    ...$deployment,
                    'upgrade_available' => false,
                    'message' => 'The requested deployment is not active on this node yet.',
                ], 409)
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }

        $appUpdates->synchronize($user);

        return response()
            ->json([
                ...$deployment,
                'upgrade_available' => $appUpdates->isAvailable($user->fresh()),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function upgrade(Request $request, AppUpdateService $appUpdates): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user, 401);

        $deployment = AppDeployment::current();
        $expectedDeploymentId = $request->validate([
            'deployment_id' => ['required', 'string', 'max:128'],
        ])['deployment_id'];
        $target = $this->safeReturnUrl($request) ?? url('/admin');

        if (
            ! $appUpdates->isDeploymentEligible($deployment)
            || ! hash_equals(
                (string) ($deployment['deployment_id'] ?? ''),
                (string) $expectedDeploymentId,
            )
        ) {
            Notification::make()
                ->title('Upgrade is not ready yet')
                ->body('The deployment changed while you were upgrading. Your current app was kept; please try again shortly.')
                ->warning()
                ->send();

            return $this->uncachedRedirect($target);
        }

        if (! $appUpdates->isAvailable($user->fresh())) {
            Notification::make()
                ->title('App is already up to date')
                ->info()
                ->send();

            return $this->uncachedRedirect($target);
        }

        $appUpdates->synchronize($user);
        $appUpdates->acknowledge($user);

        Notification::make()
            ->title('App upgraded')
            ->body('The latest app files are now loaded.')
            ->success()
            ->send();

        $cacheBuster = Str::limit((string) ($deployment['deployment_id'] ?? now()->timestamp), 24, '');
        $target = Uri::of($target)
            ->withQuery(['_app_upgrade' => $cacheBuster])
            ->value();

        return $this->uncachedRedirect($target)
            ->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Clear-Site-Data' => '"cache"',
                'Expires' => '0',
                'Pragma' => 'no-cache',
            ]);
    }

    protected function safeReturnUrl(Request $request): ?string
    {
        $returnTo = $request->string('return_to')->trim()->toString();

        if ($returnTo === '') {
            return null;
        }

        $parts = parse_url($returnTo);

        if (! is_array($parts)) {
            return null;
        }

        $scheme = $parts['scheme'] ?? $request->getScheme();
        $host = $parts['host'] ?? $request->getHost();
        $port = $parts['port'] ?? $request->getPort();
        $path = $parts['path'] ?? '/admin';

        if (! hash_equals(strtolower($request->getScheme()), strtolower((string) $scheme))) {
            return null;
        }

        if (! hash_equals(strtolower($request->getHost()), strtolower((string) $host))) {
            return null;
        }

        if ((int) $request->getPort() !== (int) $port) {
            return null;
        }

        $path = '/'.ltrim((string) $path, '/');

        if ($path !== '/admin' && ! str_starts_with($path, '/admin/')) {
            return null;
        }

        return $returnTo;
    }

    protected function uncachedRedirect(string $target): RedirectResponse
    {
        return redirect()
            ->to($target)
            ->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Expires' => '0',
                'Pragma' => 'no-cache',
            ]);
    }
}
