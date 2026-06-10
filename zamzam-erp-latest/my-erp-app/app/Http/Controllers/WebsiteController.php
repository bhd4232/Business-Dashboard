<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\SiteBanner;
use App\Models\SiteMenu;
use App\Models\SitePage;
use App\Models\SiteSection;
use App\Models\SiteSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class WebsiteController extends Controller
{
    public function home(): View
    {
        if (! $this->databaseReady()) {
            return view('website.home', [
                'settings' => null,
                'banners' => collect(),
                'headerMenus' => collect(),
                'footerMenus' => collect(),
                'pages' => collect(),
                'sections' => collect(),
            ]);
        }

        return view('website.home', $this->layoutData() + [
            'banners' => SiteBanner::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderByDesc('id')
                ->get(),
            'pages' => SitePage::query()
                ->where('is_published', true)
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get(),
            'sections' => $this->sections('home'),
        ]);
    }

    public function about(): View
    {
        return $this->page('about');
    }

    public function contact(): RedirectResponse
    {
        return redirect('/#contact');
    }

    public function products(): View
    {
        return view('website.products', $this->layoutData());
    }

    public function page(string $slug): View
    {
        if (! $this->databaseReady()) {
            abort(404);
        }

        $page = SitePage::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        return view('website.page', $this->layoutData() + [
            'page' => $page,
        ]);
    }

    public function storeContact(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('contact_messages')) {
            abort(503, 'Contact messages are not ready yet.');
        }

        ContactMessage::query()->create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]));

        return back()->with('contact_status', 'Thanks. Your message has been sent.');
    }

    public static function mediaUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    public static function absoluteMediaUrl(?string $path): ?string
    {
        $url = static::mediaUrl($path);

        if (! $url) {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return url($url);
    }

    private function menus(string $location)
    {
        return SiteMenu::query()
            ->where('location', $location)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();
    }

    private function sections(string $placement)
    {
        return SiteSection::query()
            ->where('placement', $placement)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
    }

    private function layoutData(): array
    {
        return [
            'settings' => SiteSetting::active(),
            'headerMenus' => $this->menus('header'),
            'footerMenus' => $this->menus('footer'),
        ];
    }

    private function databaseReady(): bool
    {
        return Schema::hasTable('site_settings')
            && Schema::hasTable('site_banners')
            && Schema::hasTable('site_menus')
            && Schema::hasTable('site_pages')
            && Schema::hasTable('site_sections');
    }
}
