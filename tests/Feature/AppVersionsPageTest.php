<?php

namespace Tests\Feature;

use App\Filament\Pages\AppVersions;
use App\Models\User;
use App\Support\CoolifySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AppVersionsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_a_super_admin_can_access_the_page(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);

        $this->actingAs($superAdmin)
            ->get('/admin/settings/app-versions')
            ->assertOk();

        $this->actingAs($manager)
            ->get('/admin/settings/app-versions')
            ->assertForbidden();
    }

    public function test_it_shows_a_not_configured_state_before_credentials_are_saved(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->actingAs($superAdmin)
            ->get('/admin/settings/app-versions')
            ->assertOk()
            ->assertSee('Not connected to Coolify yet');
    }

    public function test_rollback_action_queues_a_redeploy_of_the_chosen_commit(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        CoolifySettings::save([
            'enabled' => true,
            'base_url' => 'https://coolify.example.com',
            'api_token' => 'fake-token',
            'application_uuid' => 'jc0k48s',
        ]);

        Http::fake([
            'coolify.example.com/api/v1/deployments/applications/jc0k48s*' => Http::response([
                'count' => 2,
                'deployments' => [
                    ['deployment_uuid' => 'd2', 'commit' => 'currentsha', 'commit_message' => 'currently live', 'status' => 'finished', 'created_at' => now()->toIso8601String()],
                    ['deployment_uuid' => 'd1', 'commit' => 'previoussha', 'commit_message' => 'previous release', 'status' => 'finished', 'created_at' => now()->subDay()->toIso8601String()],
                ],
            ]),
            'coolify.example.com/api/v1/applications/jc0k48s/rollback' => Http::response([
                'message' => 'Rollback deployment queued.',
                'deployment_uuid' => 'new-deployment-uuid',
            ]),
        ]);

        Livewire::actingAs($superAdmin)
            ->test(AppVersions::class)
            ->assertSee('previoussha')
            ->assertDontSee('currentsha')
            ->mountAction('rollback', ['commit' => 'previoussha'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        Http::assertSent(fn ($request): bool => $request->url() === 'https://coolify.example.com/api/v1/applications/jc0k48s/rollback'
            && $request['commit'] === 'previoussha');
    }
}
