<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\GeneratedImage;
use App\Models\User;
use App\Services\CompanyContext;
use App\Services\ImageGeneration\ImageGovernanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImageGovernanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'Gov Co',
            'slug' => 'gov-co-'.uniqid(),
            'invoice_prefix' => 'GOV'.random_int(100, 999),
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        app(CompanyContext::class)->set($this->company);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);
        $user->companies()->syncWithoutDetaching([$this->company->getKey() => ['role' => $role, 'is_default' => true]]);

        return $user;
    }

    private function generation(User $user, array $overrides = []): GeneratedImage
    {
        return GeneratedImage::query()->create(array_merge([
            'user_id' => $user->getKey(),
            'tool' => GeneratedImage::TOOL_IMAGE_GENERATION,
            'context' => 'general',
            'prompt' => 'x',
            'aspect_ratio' => '1:1',
            'variations_requested' => 2,
            'status' => GeneratedImage::STATUS_COMPLETED,
            'estimated_cost' => 0.10,
        ], $overrides));
    }

    public function test_defaults_are_inert(): void
    {
        $service = app(ImageGovernanceService::class);

        $this->assertSame(0, $service->monthlyCapForRole($this->company, 'sales_staff'));
        $this->assertFalse($service->roleRequiresReview($this->company, 'sales_staff'));
        $this->assertNull($service->remainingThisMonth($this->company, $this->user('sales_staff')));
    }

    public function test_save_persists_caps_and_review_roles_and_drops_zeros(): void
    {
        $service = app(ImageGovernanceService::class);

        $service->save($this->company, [
            'monthly_caps' => ['sales_staff' => 40, 'manager' => 0, 'accountant' => -5],
            'review_roles' => ['sales_staff', 'not_a_role'],
        ]);

        $config = $service->all($this->company->fresh());

        $this->assertSame(40, $config['monthly_caps']['sales_staff']);
        $this->assertSame(0, $config['monthly_caps']['manager']);
        $this->assertSame(0, $config['monthly_caps']['accountant']);
        $this->assertSame(['sales_staff'], $config['review_roles']);

        // Nothing configured -> the whole key is removed again.
        $service->save($this->company->fresh(), ['monthly_caps' => [], 'review_roles' => []]);
        $this->assertNull(data_get($this->company->fresh()->settings, 'ai_tools.image_governance'));
    }

    public function test_super_admin_is_always_unlimited_and_never_reviewed(): void
    {
        $service = app(ImageGovernanceService::class);
        $service->save($this->company, ['monthly_caps' => ['super_admin' => 1], 'review_roles' => ['super_admin']]);

        $this->assertSame(0, $service->monthlyCapForRole($this->company->fresh(), 'super_admin'));
        $this->assertFalse($service->roleRequiresReview($this->company->fresh(), 'super_admin'));
    }

    public function test_usage_and_cap_math_count_variations_and_ignore_failures(): void
    {
        $service = app(ImageGovernanceService::class);
        $service->save($this->company, ['monthly_caps' => ['sales_staff' => 10], 'review_roles' => []]);

        $company = $this->company->fresh();
        $staff = $this->user('sales_staff');

        $this->generation($staff, ['variations_requested' => 3]);
        $this->generation($staff, ['variations_requested' => 2, 'status' => GeneratedImage::STATUS_FAILED]);
        $lastMonth = $this->generation($staff, ['variations_requested' => 1]);
        $lastMonth->forceFill(['created_at' => now()->subMonthNoOverflow()->startOfMonth()])->saveQuietly();

        $this->assertSame(3, $service->imagesUsedThisMonth($company, $staff));
        $this->assertSame(7, $service->remainingThisMonth($company, $staff));
        $this->assertFalse($service->wouldExceedCap($company, $staff, 7));
        $this->assertTrue($service->wouldExceedCap($company, $staff, 8));
    }

    public function test_usage_summary_breaks_down_by_user_and_provider(): void
    {
        $a = $this->user('manager');
        $b = $this->user('sales_staff');

        $this->generation($a, ['provider_label' => 'OpenAI', 'variations_requested' => 2, 'estimated_cost' => 0.20]);
        $this->generation($b, ['provider_label' => 'Stability', 'variations_requested' => 1, 'estimated_cost' => 0.05]);

        $summary = app(ImageGovernanceService::class)->usageSummary($this->company);

        $this->assertSame(3, $summary['images']);
        $this->assertEqualsWithDelta(0.25, $summary['cost'], 0.001);
        $this->assertCount(2, $summary['by_user']);
        $this->assertCount(2, $summary['by_provider']);
        $this->assertSame('OpenAI', $summary['by_provider'][0]['label']); // sorted by cost desc
    }

    public function test_review_flips_a_pending_row_and_is_a_noop_otherwise(): void
    {
        $service = app(ImageGovernanceService::class);
        $reviewer = $this->user('manager');
        $author = $this->user('sales_staff');

        $pending = $this->generation($author, ['review_status' => GeneratedImage::REVIEW_PENDING]);
        $service->review($pending, $reviewer, approved: true);

        $pending->refresh();
        $this->assertSame(GeneratedImage::REVIEW_APPROVED, $pending->review_status);
        $this->assertSame($reviewer->getKey(), $pending->reviewed_by);
        $this->assertNotNull($pending->reviewed_at);

        // Re-reviewing an already-decided row does nothing.
        $service->review($pending, $author, approved: false, note: 'nope');
        $this->assertSame(GeneratedImage::REVIEW_APPROVED, $pending->fresh()->review_status);
    }
}
