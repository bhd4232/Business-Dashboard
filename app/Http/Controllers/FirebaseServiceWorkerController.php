<?php

namespace App\Http\Controllers;

use App\Support\FirebaseSettings;
use Illuminate\Http\Response;

/**
 * Serves the Firebase Messaging service worker at the site root
 * (`/firebase-messaging-sw.js`) so its scope covers the whole origin --
 * a service worker can only control paths at or below its own path, so it
 * cannot be a normal Vite-built asset served from `/build/...`. Rendered
 * dynamically (not a static file) because the Web SDK config comes from
 * the admin-configurable Push Notification Settings page, not `.env`, so
 * it can change without a redeploy.
 */
class FirebaseServiceWorkerController extends Controller
{
    public function __invoke(): Response
    {
        $script = view('firebase-messaging-sw', [
            'config' => FirebaseSettings::webConfig(),
            'configured' => FirebaseSettings::isWebConfigured(),
        ])->render();

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            // Service workers should be revalidated frequently so a
            // credentials change on the settings page takes effect quickly.
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }
}
