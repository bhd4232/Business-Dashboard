<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyAssetUrlTest extends TestCase
{
    public function test_forwarded_https_scheme_is_used_for_lazy_loaded_assets(): void
    {
        Route::get('/_test/asset-url', fn () => asset('js/filament/forms/components/select.js'));

        $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.0.10',
            'HTTP_HOST' => 'app.zamzamint.com',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
            'HTTP_X_FORWARDED_HOST' => 'app.zamzamint.com',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ])->get('/_test/asset-url')
            ->assertOk()
            ->assertSeeText('https://app.zamzamint.com/js/filament/forms/components/select.js');
    }
}
