<?php

namespace Tests\Feature;

use App\Support\StorefrontErrorPages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    public function test_every_response_carries_the_baseline_security_headers(): void
    {
        Route::get('/_test/security-headers', fn () => 'ok')->middleware('web');

        $this->get('/_test/security-headers')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
    }

    public function test_robots_txt_disallows_admin_and_livewire(): void
    {
        // public/robots.txt is served directly by the web server in
        // production, not routed through Laravel — read it as a static
        // asset rather than an HTTP round trip (same pattern as other tests
        // that assert on a published view/asset file's raw content).
        $robots = File::get(public_path('robots.txt'));

        $this->assertStringContainsString('Disallow: /admin', $robots);
        $this->assertStringContainsString('Disallow: /livewire', $robots);
    }

    public function test_an_unhandled_storefront_exception_in_production_shows_a_generic_page_not_a_stack_trace(): void
    {
        $this->app['env'] = 'production';
        config(['app.debug' => false]);

        Route::get('/_test/boom', function (): never {
            throw new \RuntimeException('Something exploded with a secret file path.');
        });

        $response = $this->get('/_test/boom');

        $response->assertStatus(500);
        $response->assertSeeText('Something went wrong');
        $response->assertDontSeeText('Something exploded with a secret file path.');
        $response->assertDontSeeText(__FILE__);
    }

    public function test_a_404_in_production_shows_the_generic_storefront_page(): void
    {
        $this->app['env'] = 'production';
        config(['app.debug' => false]);

        $response = $this->get('/this-route-does-not-exist-at-all');

        $response->assertStatus(404);
        $response->assertSeeText('Page not found');
    }

    /**
     * Everything the production exception handler must leave completely
     * alone (Filament's own error handling, Livewire's AJAX updates,
     * API/webhook JSON responses) — tested directly against the
     * classification logic rather than through a real thrown-exception HTTP
     * round trip, since Laravel's own default exception rendering behaves
     * differently under PHPUnit than in a real request and is not what this
     * is meant to verify.
     */
    public function test_storefront_error_page_does_not_apply_to_admin_livewire_api_or_webhook_requests(): void
    {
        $this->assertFalse(StorefrontErrorPages::appliesTo(Request::create('/admin')));
        $this->assertFalse(StorefrontErrorPages::appliesTo(Request::create('/admin/orders')));
        $this->assertFalse(StorefrontErrorPages::appliesTo(Request::create('/api/whatever')));
        $this->assertFalse(StorefrontErrorPages::appliesTo(Request::create('/webhooks/woocommerce/1')));

        $livewireRequest = Request::create('/livewire-abc123/update');
        $livewireRequest->headers->set('X-Livewire', '1');
        $this->assertFalse(StorefrontErrorPages::appliesTo($livewireRequest));

        $jsonRequest = Request::create('/some-storefront-route');
        $jsonRequest->headers->set('Accept', 'application/json');
        $this->assertFalse(StorefrontErrorPages::appliesTo($jsonRequest));
    }

    public function test_storefront_error_page_applies_to_a_plain_storefront_page_request(): void
    {
        $this->assertTrue(StorefrontErrorPages::appliesTo(Request::create('/some-company-storefront-page')));
        $this->assertTrue(StorefrontErrorPages::appliesTo(Request::create('/cart')));
    }

    public function test_storefront_error_page_status_falls_back_to_500_for_anything_not_in_the_covered_list(): void
    {
        $this->assertSame(404, StorefrontErrorPages::statusFor(new NotFoundHttpException()));
        $this->assertSame(500, StorefrontErrorPages::statusFor(new \RuntimeException('whatever')));
    }
}
