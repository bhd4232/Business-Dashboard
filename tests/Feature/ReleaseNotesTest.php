<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ReleaseNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_version_endpoint_exposes_release_metadata(): void
    {
        Config::set('release.version', '9.8.7');
        Config::set('release.type', 'critical_fix');
        Config::set('release.date', '2026-06-21');
        Config::set('release.commit', '1234567890abcdef');

        $this->get('/health/version')
            ->assertOk()
            ->assertJsonPath('version', '9.8.7')
            ->assertJsonPath('release_type', 'critical_fix')
            ->assertJsonPath('release_label', 'Critical Fix Update')
            ->assertJsonPath('release_date', '2026-06-21')
            ->assertJsonPath('commit', '1234567890abcdef');
    }

    public function test_release_notes_page_renders_for_authenticated_admin_user(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/admin/release-notes')
            ->assertOk()
            ->assertSee('Release Notes')
            ->assertSee('v1.6.0')
            ->assertSee('Minor Version Update')
            ->assertSee('Released 2026-07-06')
            ->assertSee('Super Admin Database & Deployment Notes', false)
            ->assertSee('Added disposable SQLite backup restore verification')
            ->assertSee('Production Update Rules');
    }

    public function test_database_related_release_notes_are_hidden_from_non_super_admin_users(): void
    {
        $user = User::factory()->create([
            'role' => 'manager',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/admin/release-notes')
            ->assertOk()
            ->assertSee('Release Notes')
            ->assertSee('v1.2.0')
            ->assertSee('Added Customer and Order risk badges')
            ->assertDontSee('Added disposable SQLite backup restore verification')
            ->assertDontSee('Technical Notes')
            ->assertDontSee('Super Admin Database')
            ->assertDontSee('Production Update Rules')
            ->assertDontSee('Never run');
    }
}
