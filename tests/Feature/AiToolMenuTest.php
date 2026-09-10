<?php

namespace Tests\Feature;

use App\Filament\Clusters\AiTools;
use App\Filament\Pages\ToolMenu;
use App\Models\Company;
use App\Models\User;
use App\Models\UserRole;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiToolMenuTest extends TestCase
{
    use RefreshDatabase;

    private string $toolMenuUrl = '/admin/ai-tools/tool-menu';

    public function test_super_admin_can_open_the_tool_menu(): void
    {
        [$company, $admin] = $this->companyAndUser('super_admin');

        $this->actingAs($admin)
            ->withSession(['current_company_id' => $company->getKey()])
            ->get($this->toolMenuUrl)
            ->assertOk()
            ->assertSee('Image Generation')
            ->assertSee('Video Generation')
            ->assertSee('Content Creation');
    }

    public function test_manager_can_open_the_tool_menu(): void
    {
        [$company, $manager] = $this->companyAndUser('manager');

        $this->actingAs($manager)
            ->withSession(['current_company_id' => $company->getKey()])
            ->get($this->toolMenuUrl)
            ->assertOk();
    }

    public function test_staff_without_ai_permissions_cannot_open_the_tool_menu(): void
    {
        [$company, $staff] = $this->companyAndUser('sales_staff');

        $this->actingAs($staff)
            ->withSession(['current_company_id' => $company->getKey()])
            ->get($this->toolMenuUrl)
            ->assertForbidden();
    }

    public function test_custom_role_with_only_image_generation_permission_can_still_open_the_hub(): void
    {
        [$company, $user] = $this->companyAndUser('image-only');

        UserRole::query()->create([
            'name' => 'Image Only',
            'slug' => 'image-only',
            'permissions' => ['dashboard.view', 'ai_tools.image_generation'],
            'is_active' => true,
        ]);

        $this->actingAs($user);

        // No `ai_tools.menu` — the hub must still open, since it is the only
        // route to the tool this role can use.
        $this->assertTrue(ToolMenu::canAccess());

        $this->withSession(['current_company_id' => $company->getKey()])
            ->get($this->toolMenuUrl)
            ->assertOk();
    }

    public function test_ai_tools_cluster_root_redirects_to_the_tool_menu(): void
    {
        [$company, $admin] = $this->companyAndUser('super_admin');

        $this->actingAs($admin)
            ->withSession(['current_company_id' => $company->getKey()])
            ->get('/admin/ai-tools')
            ->assertRedirect(route('filament.admin.ai-tools.pages.tool-menu'));
    }

    public function test_coming_soon_tools_render_as_disabled_tiles(): void
    {
        [$company, $admin] = $this->companyAndUser('super_admin');
        app(CompanyContext::class)->set($company);

        Livewire::actingAs($admin)
            ->test(ToolMenu::class)
            ->assertOk()
            ->assertSee('Coming soon')
            ->assertSee('Image Generation')
            ->assertSee('Video Generation')
            ->assertSee('Content Creation');
    }

    public function test_permission_keys_are_registered_for_role_configuration(): void
    {
        foreach ([
            'ai_tools.menu',
            'ai_tools.image_generation',
            'ai_tools.video_generation',
            'ai_tools.content_creation',
        ] as $key) {
            $this->assertArrayHasKey($key, User::CUSTOM_PERMISSION_OPTIONS, "{$key} must be selectable on custom roles.");
        }

        $this->assertContains('ai_tools.menu', User::ROLE_PERMISSIONS['manager']);
        $this->assertContains('ai_tools.image_generation', User::ROLE_PERMISSIONS['manager']);

        // Reserved keys are not active on any built-in role yet.
        foreach (User::ROLE_PERMISSIONS as $role => $permissions) {
            if ($permissions === ['*']) {
                continue;
            }

            $this->assertNotContains('ai_tools.video_generation', $permissions);
            $this->assertNotContains('ai_tools.content_creation', $permissions);
        }
    }

    public function test_cluster_is_hidden_from_users_without_any_ai_permission(): void
    {
        [$company, $staff] = $this->companyAndUser('sales_staff');
        $this->actingAs($staff);
        app(CompanyContext::class)->set($company);

        $this->assertFalse(AiTools::canAccess());
        $this->assertFalse(AiTools::shouldRegisterNavigation());
    }

    /**
     * @return array{0: Company, 1: User}
     */
    private function companyAndUser(string $role): array
    {
        $company = Company::query()->create([
            'name' => 'AI Tools Co',
            'slug' => 'ai-tools-co-'.uniqid(),
            'invoice_prefix' => 'AIT'.random_int(100, 999),
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);

        $user = User::factory()->create(['role' => $role, 'is_active' => true]);
        $user->companies()->syncWithoutDetaching([
            $company->getKey() => ['role' => $role, 'is_default' => true],
        ]);

        return [$company, $user];
    }
}
