<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_health_version_reports_role_marker(): void
    {
        $this->get('/health/version')
            ->assertOk()
            ->assertJson([
                'app' => 'zamzam-erp',
                'marker' => 'roles-built-in-v2',
                'has_sales_staff_role' => true,
            ])
            ->assertJsonPath('role_option_keys.2', 'sales_staff');
    }
}
