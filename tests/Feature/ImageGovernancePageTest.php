<?php

namespace Tests\Feature;

use App\Filament\Pages\ImageGovernance;
use App\Models\Company;
use App\Models\GeneratedImage;
use App\Models\User;
use App\Services\CompanyContext;
use App\Services\ImageGeneration\ImageGovernanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ImageGovernancePageTest extends TestCase
{
    use RefreshDatabase;

    private string $url = '/admin/ai-tools/image-governance';

    private function company(): Company
    {
        $company = Company::query()->create([
            'name' => 'Gov Page Co',
            'slug' => 'gov-page-'.uniqid(),
            'invoice_prefix' => 'GVP'.random_int(100, 999),
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);

        return $company;
    }

    private function user(Company $company, string $role): User
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);
        $user->companies()->syncWithoutDetaching([$company->getKey() => ['role' => $role, 'is_default' => true]]);

        return $user;
    }

    public function test_only_super_admin_can_open_the_page(): void
    {
        $company = $this->company();

        $this->actingAs($this->user($company, 'super_admin'))
            ->withSession(['current_company_id' => $company->getKey()])
            ->get($this->url)
            ->assertOk();

        $this->actingAs($this->user($company, 'manager'))
            ->withSession(['current_company_id' => $company->getKey()])
            ->get($this->url)
            ->assertForbidden();
    }

    public function test_it_saves_caps_and_review_roles(): void
    {
        $company = $this->company();
        $admin = $this->user($company, 'super_admin');

        Livewire::actingAs($admin)
            ->test(ImageGovernance::class)
            ->fillForm([
                'cap_sales_staff' => 30,
                'cap_manager' => 0,
                'review_roles' => ['sales_staff'],
            ])
            ->call('save')
            ->assertHasNoErrors()
            ->assertNotified();

        $config = app(ImageGovernanceService::class)->all($company->fresh());
        $this->assertSame(30, $config['monthly_caps']['sales_staff']);
        $this->assertSame(['sales_staff'], $config['review_roles']);
    }

    public function test_the_usage_panel_totals_this_months_spend(): void
    {
        $company = $this->company();
        $admin = $this->user($company, 'super_admin');

        GeneratedImage::query()->create([
            'user_id' => $admin->getKey(),
            'tool' => GeneratedImage::TOOL_IMAGE_GENERATION,
            'context' => 'general',
            'prompt' => 'x',
            'provider_label' => 'OpenAI',
            'aspect_ratio' => '1:1',
            'variations_requested' => 2,
            'estimated_cost' => 0.5,
            'status' => GeneratedImage::STATUS_COMPLETED,
        ]);

        $usage = Livewire::actingAs($admin)->test(ImageGovernance::class)->instance()->usage();

        $this->assertSame(2, $usage['images']);
        $this->assertEqualsWithDelta(0.5, $usage['cost'], 0.001);
    }
}
