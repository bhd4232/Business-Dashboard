<?php

namespace Tests\Feature;

use App\Filament\Pages\ImageGeneration;
use App\Filament\Pages\ImageLibrary;
use App\Models\Company;
use App\Models\GeneratedImage;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ImageLibraryTest extends TestCase
{
    use RefreshDatabase;

    private string $url = '/admin/ai-tools/image-library';

    private function company(string $name = 'Lib Co'): Company
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => 'lib-'.uniqid(),
            'invoice_prefix' => 'LIB'.random_int(100, 999),
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

    private function generation(Company $company, User $user, array $overrides = []): GeneratedImage
    {
        return GeneratedImage::query()->create(array_merge([
            'user_id' => $user->getKey(),
            'tool' => GeneratedImage::TOOL_IMAGE_GENERATION,
            'context' => 'product_photo',
            'prompt' => 'a matte black bottle',
            'provider_profile_id' => 'p1',
            'provider_label' => 'OpenAI',
            'api_format' => 'openai',
            'aspect_ratio' => '1:1',
            'variations_requested' => 1,
            'output_paths' => ['companies/'.$company->storage_key.'/public/ai-generated-images/a.webp'],
            'status' => GeneratedImage::STATUS_COMPLETED,
            'generated_at' => now(),
        ], $overrides));
    }

    public function test_manager_can_open_the_library_and_staff_cannot(): void
    {
        $company = $this->company();

        $this->actingAs($this->user($company, 'manager'))
            ->withSession(['current_company_id' => $company->getKey()])
            ->get($this->url)
            ->assertOk();

        $this->actingAs($this->user($company, 'sales_staff'))
            ->withSession(['current_company_id' => $company->getKey()])
            ->get($this->url)
            ->assertForbidden();
    }

    public function test_it_lists_only_this_companys_generations(): void
    {
        $company = $this->company('Mine');
        $user = $this->user($company, 'manager');
        $mine = $this->generation($company, $user, ['prompt' => 'my own teapot']);

        $other = $this->company('Theirs');
        $otherUser = $this->user($other, 'manager');
        $theirs = $this->generation($other, $otherUser, ['prompt' => 'their secret gadget']);

        app(CompanyContext::class)->set($company);

        Livewire::actingAs($user)
            ->test(ImageLibrary::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_the_favourites_filter_narrows_the_list(): void
    {
        $company = $this->company();
        $user = $this->user($company, 'manager');
        $starred = $this->generation($company, $user, ['prompt' => 'starred one', 'is_favorite' => true]);
        $plain = $this->generation($company, $user, ['prompt' => 'plain one']);

        Livewire::actingAs($user)
            ->test(ImageLibrary::class)
            ->filterTable('is_favorite', true)
            ->assertCanSeeTableRecords([$starred])
            ->assertCanNotSeeTableRecords([$plain]);
    }

    public function test_the_toggle_favourite_action_flips_the_flag(): void
    {
        $company = $this->company();
        $user = $this->user($company, 'manager');
        $plain = $this->generation($company, $user, ['prompt' => 'plain one']);
        $starred = $this->generation($company, $user, ['prompt' => 'starred one', 'is_favorite' => true]);

        Livewire::actingAs($user)
            ->test(ImageLibrary::class)
            ->callTableAction('toggleFavorite', $plain)
            ->callTableAction('toggleFavorite', $starred);

        $this->assertTrue($plain->fresh()->is_favorite);
        $this->assertFalse($starred->fresh()->is_favorite);
    }

    public function test_a_reviewer_can_approve_a_pending_image(): void
    {
        $company = $this->company();
        $reviewer = $this->user($company, 'manager'); // manager holds ai_tools.image_generation.review
        $pending = $this->generation($company, $reviewer, [
            'prompt' => 'awaiting sign-off',
            'review_status' => GeneratedImage::REVIEW_PENDING,
        ]);

        Livewire::actingAs($reviewer)
            ->test(ImageLibrary::class)
            ->assertTableActionVisible('approveReview', $pending)
            ->callTableAction('approveReview', $pending);

        $this->assertSame(GeneratedImage::REVIEW_APPROVED, $pending->fresh()->review_status);
        $this->assertSame($reviewer->getKey(), $pending->fresh()->reviewed_by);
    }

    public function test_a_non_reviewer_never_sees_the_approve_action(): void
    {
        $company = $this->company();

        \App\Models\UserRole::query()->create([
            'name' => 'Library Only',
            'slug' => 'library-only',
            'permissions' => ['dashboard.view', 'ai_tools.image_generation'],
            'is_active' => true,
        ]);
        $viewer = $this->user($company, 'library-only');

        $pending = $this->generation($company, $this->user($company, 'manager'), [
            'review_status' => GeneratedImage::REVIEW_PENDING,
        ]);

        Livewire::actingAs($viewer)
            ->test(ImageLibrary::class)
            ->assertTableActionHidden('approveReview', $pending);
    }

    public function test_reuse_prompt_links_to_the_generation_tool_prefilled(): void
    {
        $company = $this->company();
        $user = $this->user($company, 'manager');
        $generation = $this->generation($company, $user);

        Livewire::actingAs($user)
            ->test(ImageLibrary::class)
            ->assertTableActionHasUrl('reusePrompt', ImageGeneration::getUrl(['from' => $generation->getKey()]), $generation);
    }

    public function test_the_generation_tool_prefills_the_prompt_from_a_from_parameter(): void
    {
        $company = $this->company();
        $user = $this->user($company, 'manager');
        $source = $this->generation($company, $user, [
            'prompt' => 'a hand-thrown ceramic mug on a linen cloth',
            'context' => 'ad_creative',
            'aspect_ratio' => '16:9',
        ]);

        Livewire::withQueryParams(['from' => $source->getKey()]);

        Livewire::actingAs($user)
            ->test(ImageGeneration::class)
            ->assertSet('data.prompt', 'a hand-thrown ceramic mug on a linen cloth')
            ->assertSet('data.context', 'ad_creative')
            ->assertSet('data.aspect_ratio', '16:9');
    }
}
