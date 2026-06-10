<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Share global props with all Inertia pages
     */
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $user ? [
                    'id'                => $user->id,
                    'name'              => $user->name,
                    'email'             => $user->email,
                    'phone'             => $user->phone,
                    'profile_photo_url' => $user->profile_photo_path
                        ? asset('storage/' . $user->profile_photo_path)
                        : null,
                    'roles'             => $user->getRoleNames(),
                    'permissions'       => $user->getAllPermissions()->pluck('name'),
                ] : null,
            ],

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info'    => fn () => $request->session()->get('info'),
            ],

            'modules' => fn () => cache()->remember('module_settings', 300, function () {
                return \Illuminate\Support\Facades\DB::table('module_settings')
                    ->pluck('is_active', 'module');
            }),

            'app' => [
                'name'     => config('app.name'),
                'timezone' => config('app.timezone'),
            ],

            'userPreferences' => fn () => $user
                ? ($user->preferences?->only(['theme_color', 'accent_hex', 'dark_mode']) ?? [
                    'theme_color' => 'indigo',
                    'accent_hex'  => null,
                    'dark_mode'   => false,
                ])
                : [
                    'theme_color' => 'indigo',
                    'accent_hex'  => null,
                    'dark_mode'   => false,
                ],
        ];
    }
}
