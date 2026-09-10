<?php

namespace Tests\Feature;

use App\Jobs\GenerateImageJob;
use App\Models\Company;
use App\Models\GeneratedImage;
use App\Models\Media;
use App\Models\User;
use App\Notifications\BusinessAlert;
use App\Services\CompanyContext;
use App\Services\ImageGeneration\ImageProviderSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenerateImageJobTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->company = Company::query()->create([
            'name' => 'Gen Co',
            'slug' => 'gen-co-'.uniqid(),
            'invoice_prefix' => 'GEN'.random_int(100, 999),
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        app(CompanyContext::class)->set($this->company);

        $this->user = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $this->user->companies()->syncWithoutDetaching([
            $this->company->getKey() => ['role' => 'manager', 'is_default' => true],
        ]);
    }

    private function pngBytes(): string
    {
        $image = imagecreatetruecolor($w = 40, $w);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 120, 40));
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    private function configureOpenAiProfile(float $costPerImage = 0): string
    {
        app(ImageProviderSettingsService::class)->save($this->company, [
            ['label' => 'OpenAI', 'api_format' => 'openai', 'model' => 'gpt-image-1', 'api_key' => 'sk-live', 'cost_per_image' => $costPerImage, 'is_default' => true],
        ]);

        return app(ImageProviderSettingsService::class)->list($this->company)[0]['id'];
    }

    private function queueRow(array $overrides = []): GeneratedImage
    {
        return GeneratedImage::query()->create(array_merge([
            'user_id' => $this->user->getKey(),
            'tool' => GeneratedImage::TOOL_IMAGE_GENERATION,
            'context' => 'product_photo',
            'prompt' => 'a matte black bottle',
            'provider_profile_id' => 'missing',
            'aspect_ratio' => '1:1',
            'variations_requested' => 2,
            'status' => GeneratedImage::STATUS_QUEUED,
        ], $overrides));
    }

    private function runJob(GeneratedImage $row): void
    {
        app()->call([new GenerateImageJob($row->getKey()), 'handle']);
    }

    public function test_a_successful_run_stores_optimized_webp_and_registers_it_in_the_media_hub(): void
    {
        Notification::fake();
        $png = $this->pngBytes();
        Http::fake(['api.openai.com/*' => Http::response([
            'data' => [
                ['b64_json' => base64_encode($png)],
                ['b64_json' => base64_encode($png)],
            ],
            'usage' => ['total_tokens' => 11],
        ], 200)]);

        $row = $this->queueRow(['provider_profile_id' => $this->configureOpenAiProfile()]);

        $this->runJob($row);

        app(CompanyContext::class)->set($this->company);
        $row->refresh();

        $this->assertSame(GeneratedImage::STATUS_COMPLETED, $row->status);
        $this->assertCount(2, $row->output_paths);
        $this->assertNotNull($row->generated_at);
        $this->assertSame('OpenAI', $row->provider_label);
        $this->assertSame(11, data_get($row->provider_response, 'usage.total_tokens'));

        foreach ($row->output_paths as $path) {
            $this->assertStringEndsWith('.webp', $path);
            Storage::disk('public')->assertExists($path);
        }

        // Each output is also a Media Hub row for this company.
        $this->assertSame(2, Media::withoutGlobalScopes()->where('company_id', $this->company->getKey())->count());

        Notification::assertSentTo($this->user, BusinessAlert::class);
    }

    public function test_it_stamps_the_estimated_cost_from_the_provider_profile(): void
    {
        Notification::fake();
        $png = $this->pngBytes();
        Http::fake(['api.openai.com/*' => Http::response([
            'data' => [['b64_json' => base64_encode($png)], ['b64_json' => base64_encode($png)]],
        ], 200)]);

        $row = $this->queueRow(['provider_profile_id' => $this->configureOpenAiProfile(costPerImage: 0.04)]);

        $this->runJob($row);

        app(CompanyContext::class)->set($this->company);

        // 2 images produced × 0.04
        $this->assertEqualsWithDelta(0.08, (float) $row->fresh()->estimated_cost, 0.0001);
    }

    public function test_a_provider_error_marks_the_row_failed_with_the_reason(): void
    {
        Notification::fake();
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'billing hard limit reached']], 429)]);

        $row = $this->queueRow(['provider_profile_id' => $this->configureOpenAiProfile()]);

        $this->runJob($row);

        app(CompanyContext::class)->set($this->company);
        $row->refresh();

        $this->assertSame(GeneratedImage::STATUS_FAILED, $row->status);
        $this->assertStringContainsString('billing hard limit reached', (string) $row->error_message);
        $this->assertNull($row->output_paths);
        $this->assertSame(0, Media::withoutGlobalScopes()->where('company_id', $this->company->getKey())->count());

        Notification::assertSentTo($this->user, BusinessAlert::class);
    }

    public function test_a_deleted_provider_profile_fails_cleanly(): void
    {
        Notification::fake();
        Http::fake();

        $row = $this->queueRow(['provider_profile_id' => 'does-not-exist']);

        $this->runJob($row);

        app(CompanyContext::class)->set($this->company);
        $row->refresh();

        $this->assertSame(GeneratedImage::STATUS_FAILED, $row->status);
        $this->assertStringContainsString('no longer exists', (string) $row->error_message);
        Http::assertNothingSent();
    }

    public function test_the_job_clears_company_context_afterwards(): void
    {
        Notification::fake();
        Http::fake(['api.openai.com/*' => Http::response(['data' => [['b64_json' => base64_encode($this->pngBytes())]]], 200)]);

        $row = $this->queueRow(['provider_profile_id' => $this->configureOpenAiProfile(), 'variations_requested' => 1]);
        $this->runJob($row);

        $this->assertFalse(app(CompanyContext::class)->hasCompany());
    }
}
