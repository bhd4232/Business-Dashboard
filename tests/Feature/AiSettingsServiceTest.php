<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Services\Crm\AiSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers AiSettingsService's provider flexibility: any OpenAI-compatible
 * provider can be configured via a free-text label + base URL, and settings
 * saved before this became flexible (the old closed 'anthropic'|'openai'|
 * 'deepseek' `provider` enum) keep working unchanged after the upgrade.
 */
class AiSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_freely_named_provider_can_be_saved_with_its_own_base_url(): void
    {
        $company = $this->company();

        app(AiSettingsService::class)->save($company, [
            'enabled' => true,
            'api_format' => 'openai',
            'provider' => 'Groq',
            'base_url' => 'https://api.groq.com/openai/v1/chat/completions',
            'model' => 'llama-3.3-70b-versatile',
            'api_key' => 'gsk-test-key',
        ]);

        $settings = app(AiSettingsService::class)->all($company->fresh());

        $this->assertSame('openai', $settings['api_format']);
        $this->assertSame('Groq', $settings['provider']);
        $this->assertSame('https://api.groq.com/openai/v1/chat/completions', $settings['base_url']);
        $this->assertSame('gsk-test-key', $settings['api_key']);
    }

    public function test_an_invalid_api_format_falls_back_to_the_default(): void
    {
        $company = $this->company();

        app(AiSettingsService::class)->save($company, [
            'enabled' => true,
            'api_format' => 'not-a-real-format',
            'provider' => 'Mystery Provider',
            'model' => 'whatever',
            'api_key' => 'test-key',
        ]);

        $this->assertSame('anthropic', app(AiSettingsService::class)->all($company->fresh())['api_format']);
    }

    public function test_legacy_openai_provider_value_migrates_forward_on_read(): void
    {
        $company = $this->company();
        $this->storeLegacySettings($company, 'openai');

        $settings = app(AiSettingsService::class)->all($company->fresh());

        $this->assertSame('openai', $settings['api_format']);
        $this->assertSame('OpenAI', $settings['provider']);
        $this->assertSame('', $settings['base_url']);
    }

    public function test_legacy_deepseek_provider_value_migrates_forward_with_its_known_base_url(): void
    {
        $company = $this->company();
        $this->storeLegacySettings($company, 'deepseek');

        $settings = app(AiSettingsService::class)->all($company->fresh());

        $this->assertSame('openai', $settings['api_format']);
        $this->assertSame('DeepSeek', $settings['provider']);
        $this->assertSame('https://api.deepseek.com/chat/completions', $settings['base_url']);
    }

    public function test_legacy_anthropic_provider_value_migrates_forward(): void
    {
        $company = $this->company();
        $this->storeLegacySettings($company, 'anthropic');

        $settings = app(AiSettingsService::class)->all($company->fresh());

        $this->assertSame('anthropic', $settings['api_format']);
        $this->assertSame('Anthropic (Claude)', $settings['provider']);
    }

    /** Writes settings directly in the pre-upgrade shape — no api_format/base_url keys at all. */
    protected function storeLegacySettings(Company $company, string $legacyProvider): void
    {
        $settings = (array) $company->settings;
        $settings['ai'] = [
            'enabled' => true,
            'provider' => $legacyProvider,
            'model' => 'test-model',
            'confidence_threshold' => 0.75,
            'max_consecutive_ai_replies' => 3,
            'brand_voice' => '',
            'api_key' => null,
        ];
        $company->forceFill(['settings' => $settings])->save();
    }

    protected function company(): Company
    {
        $company = Company::query()->create([
            'name' => 'AI Settings Co',
            'slug' => 'ai-settings-co-'.uniqid(),
            'invoice_prefix' => 'ASC'.random_int(100, 999),
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        return $company;
    }
}
