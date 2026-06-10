<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\SiteBanner;
use App\Models\User;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteSettingsAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_website_settings_edit_uses_grouped_settings_ui(): void
    {
        $user = $this->superAdminUser();

        $setting = SiteSetting::query()->create([
            'site_name' => 'ZamZam International',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get("/admin/resources/site_settings/{$setting->id}/edit");

        $response->assertOk();
        $response->assertSee('Website Settings & SEO');
        $response->assertSee('Brand identity');
        $response->assertSee('Header logo display');
        $response->assertSee('SEO and social sharing');
        $response->assertSee('Save Settings');
    }

    public function test_other_resource_edit_still_uses_original_resource_form(): void
    {
        $user = $this->superAdminUser();

        $banner = SiteBanner::query()->create([
            'title' => 'Homepage Banner',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get("/admin/resources/site_banners/{$banner->id}/edit");

        $response->assertOk();
        $response->assertSee('Edit Banner');
        $response->assertSee('Homepage Banner');
    }

    private function superAdminUser(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'Super Admin',
            'slug' => 'super_admin',
        ]);
        $user->assignRole($role);

        return $user;
    }
}
