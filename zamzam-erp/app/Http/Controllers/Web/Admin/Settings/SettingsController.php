<?php

namespace App\Http\Controllers\Web\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\UserPreference;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        $preferences = auth()->user()->preferences
            ?? new UserPreference([
                'theme_color' => 'indigo',
                'accent_hex'  => null,
                'dark_mode'   => false,
            ]);

        return Inertia::render('Settings/Index', [
            'preferences' => $preferences->only(['theme_color', 'accent_hex', 'dark_mode']),
        ]);
    }
}
