<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Services\CompanyContext;
use App\Services\ImageGeneration\ImageProviderSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class ImageProviderSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    private function company(): Company
    {
        $company = Company::query()->create([
            'name' => 'Image Co',
            'slug' => 'image-co-'.uniqid(),
            'invoice_prefix' => 'IMG'.random_int(100, 999),
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);

        return $company;
    }

    public function test_it_saves_encrypts_and_reads_back_profiles(): void
    {
        $company = $this->company();
        $service = app(ImageProviderSettingsService::class);

        $service->save($company, [
            ['label' => 'OpenAI HQ', 'api_format' => 'openai', 'model' => 'gpt-image-1', 'api_key' => 'sk-secret', 'is_default' => true],
            ['label' => 'Imagen', 'api_format' => 'google', 'model' => 'imagen-4.0-generate-001', 'api_key' => 'goog-secret', 'is_default' => false],
        ]);

        $company->refresh();

        // Stored key is ciphertext, never plaintext.
        $rawKey = data_get($company->settings, 'ai_tools.image_generation.0.api_key');
        $this->assertNotSame('sk-secret', $rawKey);
        $this->assertSame('sk-secret', Crypt::decryptString($rawKey));

        $all = $service->all($company);
        $this->assertCount(2, $all);
        $this->assertSame('sk-secret', $all[0]['api_key']);
        $this->assertSame('goog-secret', $all[1]['api_key']);
        $this->assertTrue($all[0]['is_default']);

        // list() never exposes the plaintext key.
        $list = $service->list($company);
        $this->assertArrayNotHasKey('api_key', $list[0]);
        $this->assertTrue($list[0]['has_api_key']);
    }

    public function test_it_stores_a_non_negative_rounded_cost_per_image(): void
    {
        $company = $this->company();
        $service = app(ImageProviderSettingsService::class);

        $service->save($company, [
            ['label' => 'A', 'api_format' => 'openai', 'model' => 'gpt-image-1', 'api_key' => 'k', 'cost_per_image' => 0.0421567],
            ['label' => 'B', 'api_format' => 'openai', 'model' => 'gpt-image-1', 'api_key' => 'k', 'cost_per_image' => -3],
        ]);

        $all = $service->all($company->fresh());
        $this->assertSame(0.0422, $all[0]['cost_per_image']);
        $this->assertSame(0.0, $all[1]['cost_per_image']);
        $this->assertSame(0.0422, $service->list($company->fresh())[0]['cost_per_image']);
    }

    public function test_a_blank_key_on_an_existing_profile_keeps_the_stored_one(): void
    {
        $company = $this->company();
        $service = app(ImageProviderSettingsService::class);

        $service->save($company, [
            ['label' => 'OpenAI', 'api_format' => 'openai', 'model' => 'gpt-image-1', 'api_key' => 'sk-original', 'is_default' => true],
        ]);

        $id = $service->list($company)[0]['id'];

        $service->save($company, [
            ['id' => $id, 'label' => 'OpenAI renamed', 'api_format' => 'openai', 'model' => 'gpt-image-1', 'api_key' => '', 'is_default' => true],
        ]);

        $profile = $service->find($company, $id);
        $this->assertSame('OpenAI renamed', $profile['label']);
        $this->assertSame('sk-original', $profile['api_key']);
    }

    public function test_exactly_one_profile_ends_up_default(): void
    {
        $company = $this->company();
        $service = app(ImageProviderSettingsService::class);

        // None flagged → first becomes default.
        $service->save($company, [
            ['label' => 'A', 'api_format' => 'openai', 'model' => 'm', 'api_key' => 'k'],
            ['label' => 'B', 'api_format' => 'openai', 'model' => 'm', 'api_key' => 'k'],
        ]);
        $this->assertSame('A', $service->default($company)['label']);

        // Two flagged → first flagged wins, others cleared.
        $service->save($company, [
            ['label' => 'A', 'api_format' => 'openai', 'model' => 'm', 'api_key' => 'k', 'is_default' => false],
            ['label' => 'B', 'api_format' => 'openai', 'model' => 'm', 'api_key' => 'k', 'is_default' => true],
            ['label' => 'C', 'api_format' => 'openai', 'model' => 'm', 'api_key' => 'k', 'is_default' => true],
        ]);
        $defaults = collect($service->all($company))->where('is_default', true);
        $this->assertCount(1, $defaults);
        $this->assertSame('B', $defaults->first()['label']);
    }

    public function test_is_configured_and_has_any_configured(): void
    {
        $company = $this->company();
        $service = app(ImageProviderSettingsService::class);

        $this->assertFalse($service->hasAnyConfigured($company));

        $service->save($company, [
            ['label' => 'No key', 'api_format' => 'openai', 'model' => 'gpt-image-1', 'api_key' => ''],
        ]);
        $this->assertFalse($service->hasAnyConfigured($company));

        $service->save($company, [
            ['label' => 'No key', 'api_format' => 'openai', 'model' => 'gpt-image-1', 'api_key' => ''],
            ['label' => 'Custom keyless', 'api_format' => 'custom', 'model' => 'sdxl', 'api_key' => '', 'base_url' => 'https://x.local'],
        ]);
        $this->assertTrue($service->hasAnyConfigured($company));
    }

    public function test_default_returns_null_when_nothing_configured(): void
    {
        $company = $this->company();
        $this->assertNull(app(ImageProviderSettingsService::class)->default($company));
        $this->assertSame([], app(ImageProviderSettingsService::class)->all($company));
    }
}
