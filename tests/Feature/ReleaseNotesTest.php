<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AppDeployment;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ReleaseNotesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set([
            'release.deployment_manifest' => base_path('tests/fixtures/missing-deployment.json'),
            'release.asset_manifest' => base_path('tests/fixtures/missing-vite-manifest.json'),
            'release.deployment_id' => 'release-notes-deployment-1',
            'release.deployment_built_at' => '2026-07-23T01:00:00.000Z',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_health_version_endpoint_exposes_release_metadata(): void
    {
        Config::set('release.version', '9.8.7');
        Config::set('release.type', 'critical_fix');
        Config::set('release.date', '2026-06-21');
        Config::set('release.commit', '1234567890abcdef');

        $this->get('/health/version')
            ->assertOk()
            ->assertHeader('Cache-Control')
            ->assertJsonPath('version', '9.8.7')
            ->assertJsonPath('published_version', '1.22.1')
            ->assertJsonPath('release_type', 'critical_fix')
            ->assertJsonPath('release_label', 'Critical Fix Update')
            ->assertJsonPath('release_date', '2026-06-21')
            ->assertJsonPath('commit', '1234567890abcdef')
            ->assertJsonStructure([
                'deployment_id',
                'deployment_ready',
                'ready',
                'built_at',
                'source_id',
                'assets_id',
            ]);
    }

    public function test_release_notes_page_renders_for_authenticated_admin_user(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/admin/settings/release-notes')
            ->assertOk()
            ->assertSee('Release Notes')
            ->assertSee('v1.22.1')
            ->assertSee('Installed version')
            ->assertSee('Minor Feature')
            ->assertSee('Released 2026-08-01')
            ->assertSee('Super Admin Database & Deployment Notes')
            ->assertSee('Added disposable SQLite backup restore verification')
            ->assertSee('Production Update Rules')
            ->assertSee('class="fi-section', false)
            ->assertSee('class="fi-btn', false)
            ->assertSee('class="fi-badge', false);

        $view = File::get(resource_path('views/filament/pages/release-notes.blade.php'));

        $this->assertStringNotContainsString('<style', $view);
        $this->assertStringNotContainsString('zz-', $view);
    }

    public function test_database_related_release_notes_are_hidden_from_non_super_admin_users(): void
    {
        $user = User::factory()->create([
            'role' => 'manager',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/admin/settings/release-notes')
            ->assertOk()
            ->assertSee('Release Notes')
            ->assertSee('v1.22.0')
            ->assertSee('Android update push notifications')
            ->assertSee('Added Customer and Order risk badges')
            ->assertDontSee('Added disposable SQLite backup restore verification')
            ->assertDontSee('Technical Notes')
            ->assertDontSee('Super Admin Database')
            ->assertDontSee('Production Update Rules')
            ->assertDontSee('Never run');
    }

    public function test_pending_release_is_not_presented_as_the_installed_version_before_upgrade(): void
    {
        $user = User::factory()->create([
            'role' => 'manager',
            'is_active' => true,
        ]);
        $user->forceFill([
            'acknowledged_app_version' => '1.21.0',
        ])->saveQuietly();
        $installedDeploymentId = $user->acknowledged_app_deployment_id;

        Config::set([
            'release.deployment_id' => 'release-notes-deployment-2',
            'release.deployment_built_at' => '2026-07-23T02:00:00.000Z',
        ]);

        $this->actingAs($user)
            ->get('/admin/settings/release-notes')
            ->assertOk()
            ->assertSee('Update available: v1.22.1')
            ->assertSee('Awaiting your approval')
            ->assertSee('Installed version: v1.21.0')
            ->assertDontSee('Android update push notifications');

        $this->assertSame($installedDeploymentId, $user->fresh()->acknowledged_app_deployment_id);

        $this->actingAs($user)
            ->post(route('admin.app-upgrade'), [
                'return_to' => url('/admin/settings/release-notes'),
                'deployment_id' => AppDeployment::id(),
            ])
            ->assertRedirectContains('/admin/settings/release-notes');

        $this->actingAs($user->fresh())
            ->get('/admin/settings/release-notes')
            ->assertOk()
            ->assertDontSee('Awaiting your approval')
            ->assertSee('Installed version: v1.22.1')
            ->assertSee('Android update push notifications');
    }
}
