<?php

namespace Tests\Feature;

use App\Filament\Pages\ImageGeneration;
use App\Jobs\GenerateImageJob;
use App\Models\Company;
use App\Models\GeneratedImage;
use App\Models\Offer;
use App\Models\Product;
use App\Models\User;
use App\Services\CompanyContext;
use App\Services\ImageGeneration\ImageProviderSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ImageGenerationPageTest extends TestCase
{
    use RefreshDatabase;

    private string $url = '/admin/ai-tools/image-generation';

    private function company(): Company
    {
        $company = Company::query()->create([
            'name' => 'Pixel Co',
            'slug' => 'pixel-co-'.uniqid(),
            'invoice_prefix' => 'PIX'.random_int(100, 999),
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

    private function configureProvider(Company $company): void
    {
        app(ImageProviderSettingsService::class)->save($company, [
            ['label' => 'OpenAI', 'api_format' => 'openai', 'model' => 'gpt-image-1', 'api_key' => 'sk-live', 'is_default' => true],
        ]);
    }

    private function configureStabilityProvider(Company $company): void
    {
        app(ImageProviderSettingsService::class)->save($company, [
            ['label' => 'Stability', 'api_format' => 'stability', 'model' => 'sd3.5-medium', 'api_key' => 'stab-live', 'is_default' => true],
        ]);
    }

    private function completedRow(Company $company, User $user, array $overrides = []): GeneratedImage
    {
        return GeneratedImage::query()->create(array_merge([
            'user_id' => $user->getKey(),
            'tool' => GeneratedImage::TOOL_IMAGE_GENERATION,
            'context' => 'product_photo',
            'prompt' => 'a teal ceramic mug',
            'provider_label' => 'OpenAI',
            'api_format' => 'openai',
            'aspect_ratio' => '1:1',
            'variations_requested' => 1,
            'output_paths' => ['companies/'.$company->storage_key.'/public/ai-generated-images/src.webp'],
            'status' => GeneratedImage::STATUS_COMPLETED,
            'generated_at' => now(),
        ], $overrides));
    }

    public function test_manager_can_open_the_page_and_staff_cannot(): void
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

    public function test_generate_creates_a_queued_row_and_dispatches_the_job(): void
    {
        Queue::fake();
        $company = $this->company();
        $this->configureProvider($company);
        $user = $this->user($company, 'manager');

        Livewire::actingAs($user)
            ->test(ImageGeneration::class)
            ->fillForm([
                'prompt' => 'a friendly robot watering plants',
                'context' => 'ad_creative',
                'aspect_ratio' => '16:9',
                'variations' => 2,
            ])
            ->call('generate')
            ->assertHasNoErrors()
            ->assertNotified();

        $row = GeneratedImage::query()->firstOrFail();
        $this->assertSame(GeneratedImage::STATUS_QUEUED, $row->status);
        $this->assertSame('ad_creative', $row->context);
        $this->assertSame('16:9', $row->aspect_ratio);
        $this->assertSame(2, $row->variations_requested);
        $this->assertSame($user->getKey(), $row->user_id);
        $this->assertSame('a friendly robot watering plants', $row->prompt);

        Queue::assertPushed(GenerateImageJob::class, fn (GenerateImageJob $job): bool => $job->generatedImageId === $row->getKey());
    }

    public function test_generate_is_blocked_without_a_configured_provider(): void
    {
        Queue::fake();
        $company = $this->company();
        $user = $this->user($company, 'manager');

        Livewire::actingAs($user)
            ->test(ImageGeneration::class)
            ->assertActionDisabled('generate');

        $this->assertSame(0, GeneratedImage::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_video_reference_context_flags_the_row(): void
    {
        Queue::fake();
        $company = $this->company();
        $this->configureProvider($company);
        $user = $this->user($company, 'manager');

        Livewire::actingAs($user)
            ->test(ImageGeneration::class)
            ->fillForm([
                'prompt' => 'reference frame of a product on a turntable',
                'context' => 'video_reference',
                'aspect_ratio' => '1:1',
                'variations' => 1,
            ])
            ->call('generate')
            ->assertHasNoErrors();

        $this->assertTrue(GeneratedImage::query()->firstOrFail()->is_video_reference);
    }

    private function configureEnhancer(Company $company): void
    {
        app(\App\Services\PromptEnhancement\PromptEnhancerConfigService::class)->save($company, [
            'enabled' => true, 'api_format' => 'openai', 'provider' => 'Test',
            'model' => 'mini', 'api_key' => 'sk-enh',
        ]);
    }

    public function test_enhance_action_is_hidden_until_the_enhancer_is_configured(): void
    {
        $company = $this->company();
        $this->configureProvider($company);
        $user = $this->user($company, 'manager');

        Livewire::actingAs($user)
            ->test(ImageGeneration::class)
            ->assertActionHidden('enhancePrompt');

        $this->configureEnhancer($company);

        Livewire::actingAs($user)
            ->test(ImageGeneration::class)
            ->assertActionVisible('enhancePrompt');
    }

    public function test_accepting_an_enhancement_updates_the_prompt_and_records_the_original(): void
    {
        Queue::fake();
        Http::fake(['api.openai.com/*' => Http::response(
            ['choices' => [['message' => ['content' => 'A crisp studio product photo of a matte-black steel bottle on a warm seamless backdrop, soft daylight.']]], 'usage' => []],
            200,
        )]);

        $company = $this->company();
        $this->configureProvider($company);
        $this->configureEnhancer($company);
        $user = $this->user($company, 'manager');

        $component = Livewire::actingAs($user)
            ->test(ImageGeneration::class)
            ->fillForm(['prompt' => 'black bottle', 'context' => 'product_photo', 'aspect_ratio' => '1:1', 'variations' => 1])
            ->callAction('enhancePrompt', ['enhanced' => 'A crisp studio product photo of a matte-black steel bottle on a warm seamless backdrop, soft daylight.']);

        $component->assertSet('data.prompt', 'A crisp studio product photo of a matte-black steel bottle on a warm seamless backdrop, soft daylight.');
        $component->assertSet('data.original_prompt', 'black bottle');

        $component->call('generate')->assertHasNoErrors();

        $row = GeneratedImage::query()->firstOrFail();
        $this->assertSame('black bottle', $row->original_prompt);
        $this->assertStringContainsString('matte-black steel bottle', $row->prompt);
    }

    public function test_a_role_over_its_monthly_cap_cannot_generate(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $company = $this->company();
        $this->configureProvider($company);
        app(\App\Services\ImageGeneration\ImageGovernanceService::class)->save($company, [
            'monthly_caps' => ['manager' => 2],
            'review_roles' => [],
        ]);
        $user = $this->user($company, 'manager');

        Livewire::actingAs($user)
            ->test(ImageGeneration::class)
            ->fillForm(['prompt' => 'a red mug', 'context' => 'general', 'aspect_ratio' => '1:1', 'variations' => 3])
            ->call('generate')
            ->assertHasFormErrors(['variations']);

        $this->assertSame(0, GeneratedImage::query()->count());
    }

    public function test_a_reviewed_role_generates_a_pending_row(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $company = $this->company();
        $this->configureProvider($company);
        app(\App\Services\ImageGeneration\ImageGovernanceService::class)->save($company, [
            'monthly_caps' => [],
            'review_roles' => ['manager'],
        ]);
        $user = $this->user($company, 'manager');

        Livewire::actingAs($user)
            ->test(ImageGeneration::class)
            ->fillForm(['prompt' => 'a blue mug', 'context' => 'general', 'aspect_ratio' => '1:1', 'variations' => 1])
            ->call('generate')
            ->assertHasNoErrors();

        $this->assertSame(GeneratedImage::REVIEW_PENDING, GeneratedImage::query()->firstOrFail()->review_status);
    }

    public function test_regenerate_from_image_action_queues_an_image_to_image_job(): void
    {
        Queue::fake();
        $company = $this->company();
        $this->configureProvider($company); // OpenAI supports image-to-image
        $user = $this->user($company, 'manager');
        $source = $this->completedRow($company, $user);

        Livewire::actingAs($user)
            ->test(ImageGeneration::class)
            ->callAction(
                'regenerateFromImage',
                ['prompt' => 'now a winter scene', 'strength' => 'strong', 'variations' => 2],
                ['generation' => $source->getKey(), 'image' => 0],
            )
            ->assertHasNoActionErrors()
            ->assertNotified();

        $derived = GeneratedImage::query()->where('operation', GeneratedImage::OPERATION_IMAGE_TO_IMAGE)->firstOrFail();
        $this->assertSame('now a winter scene', $derived->prompt);
        $this->assertSame($source->output_paths[0], $derived->reference_image_path);
        $this->assertSame(2, $derived->variations_requested);

        Queue::assertPushed(GenerateImageJob::class, fn (GenerateImageJob $job): bool =>
            $job->generatedImageId === $derived->getKey() && $job->strength === 0.85);
    }

    public function test_background_removal_availability_follows_the_configured_providers(): void
    {
        $company = $this->company();
        $user = $this->user($company, 'manager');

        $this->configureProvider($company); // OpenAI only — no background removal
        $this->assertFalse(Livewire::actingAs($user)->test(ImageGeneration::class)->instance()->canRemoveBackground());

        $this->configureStabilityProvider($company);
        $this->assertTrue(Livewire::actingAs($user)->test(ImageGeneration::class)->instance()->canRemoveBackground());
    }

    public function test_remove_background_action_queues_a_single_image_job(): void
    {
        Queue::fake();
        $company = $this->company();
        $this->configureStabilityProvider($company);
        $user = $this->user($company, 'manager');
        $source = $this->completedRow($company, $user);

        Livewire::actingAs($user)
            ->test(ImageGeneration::class)
            ->callAction('removeBackground', arguments: ['generation' => $source->getKey(), 'image' => 0])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $derived = GeneratedImage::query()->where('operation', GeneratedImage::OPERATION_BACKGROUND_REMOVAL)->firstOrFail();
        $this->assertSame('stability', $derived->api_format);
        $this->assertSame(1, $derived->variations_requested);
        $this->assertSame($source->output_paths[0], $derived->reference_image_path);
        Queue::assertPushed(GenerateImageJob::class);
    }

    public function test_completed_generations_render_their_images(): void
    {
        $company = $this->company();
        $this->configureProvider($company);
        $user = $this->user($company, 'manager');

        $this->completedGeneration($company, $user);

        Livewire::actingAs($user)
            ->test(ImageGeneration::class)
            ->assertSee('a teal ceramic mug')
            ->assertSee('Completed');
    }

    private function completedGeneration(Company $company, User $user): GeneratedImage
    {
        return GeneratedImage::query()->create([
            'user_id' => $user->getKey(),
            'tool' => GeneratedImage::TOOL_IMAGE_GENERATION,
            'context' => 'product_photo',
            'prompt' => 'a teal ceramic mug',
            'provider_profile_id' => 'x',
            'provider_label' => 'OpenAI',
            'api_format' => 'openai',
            'aspect_ratio' => '1:1',
            'variations_requested' => 2,
            'output_paths' => [
                'companies/'.$company->storage_key.'/public/ai-generated-images/demo-1.webp',
                'companies/'.$company->storage_key.'/public/ai-generated-images/demo-2.webp',
            ],
            'status' => GeneratedImage::STATUS_COMPLETED,
            'generated_at' => now(),
        ]);
    }

    public function test_attach_to_product_action_adds_the_image_to_the_gallery(): void
    {
        $company = $this->company();
        $this->configureProvider($company);
        $user = $this->user($company, 'manager');
        $generation = $this->completedGeneration($company, $user);

        $product = Product::query()->create([
            'name' => 'Ceramic Mug', 'sku' => 'CM-1', 'price' => 400, 'sale_price' => 400, 'stock' => 0,
        ]);

        Livewire::actingAs($user)
            ->test(ImageGeneration::class)
            ->callAction(
                'attachToProduct',
                ['product_id' => $product->getKey(), 'slot' => 'gallery'],
                ['generation' => $generation->getKey(), 'image' => 1],
            )
            ->assertHasNoActionErrors()
            ->assertNotified();

        $this->assertSame(
            ['companies/'.$company->storage_key.'/public/ai-generated-images/demo-2.webp'],
            $product->fresh()->gallery_images,
        );
        $this->assertSame($product->getKey(), $generation->fresh()->linked_id);
    }

    public function test_attach_to_offer_action_sets_the_cover_banner(): void
    {
        $company = $this->company();
        $this->configureProvider($company);
        $user = $this->user($company, 'manager');
        $generation = $this->completedGeneration($company, $user);

        $offer = Offer::query()->create(['type' => Offer::TYPE_SINGLE, 'title' => 'Mug Bundle']);

        Livewire::actingAs($user)
            ->test(ImageGeneration::class)
            ->callAction(
                'attachToOffer',
                ['offer_id' => $offer->getKey()],
                ['generation' => $generation->getKey(), 'image' => 0],
            )
            ->assertHasNoActionErrors()
            ->assertNotified();

        $this->assertSame(
            'companies/'.$company->storage_key.'/public/ai-generated-images/demo-1.webp',
            $offer->fresh()->cover_image,
        );
    }

    public function test_use_as_video_reference_action_flags_the_generation(): void
    {
        $company = $this->company();
        $this->configureProvider($company);
        $user = $this->user($company, 'manager');
        $generation = $this->completedGeneration($company, $user);

        Livewire::actingAs($user)
            ->test(ImageGeneration::class)
            ->callAction('useAsVideoReference', arguments: ['generation' => $generation->getKey()])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $this->assertTrue($generation->fresh()->is_video_reference);
    }
}
