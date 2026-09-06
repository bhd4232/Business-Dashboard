<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Services\Crm\AiSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers AiSettingsService's per-tool isolation (each of TOOLS gets its own
 * provider/model/API key), the legacy-global-config fallback that copies the
 * pre-per-tool `settings->ai` blob into every tool until it's explicitly
 * saved, and provider flexibility: any OpenAI-compatible provider can be
 * configured via a free-text label + base URL, with settings saved before
 * either upgrade (the old closed 'anthropic'|'openai'|'deepseek' `provider`
 * enum, and the old single shared config) still working unchanged.
 */
class AiSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_freely_named_provider_can_be_saved_with_its_own_base_url(): void
    {
        $company = $this->company();

        app(AiSettingsService::class)->save($company, AiSettingsService::TOOL_MESSAGING, [
            'enabled' => true,
            'api_format' => 'openai',
            'provider' => 'Groq',
            'base_url' => 'https://api.groq.com/openai/v1/chat/completions',
            'model' => 'llama-3.3-70b-versatile',
            'api_key' => 'gsk-test-key',
        ]);

        $settings = app(AiSettingsService::class)->all($company->fresh(), AiSettingsService::TOOL_MESSAGING);

        $this->assertSame('openai', $settings['api_format']);
        $this->assertSame('Groq', $settings['provider']);
        $this->assertSame('https://api.groq.com/openai/v1/chat/completions', $settings['base_url']);
        $this->assertSame('gsk-test-key', $settings['api_key']);
    }

    public function test_an_invalid_api_format_falls_back_to_the_default(): void
    {
        $company = $this->company();

        app(AiSettingsService::class)->save($company, AiSettingsService::TOOL_AD_ASSISTANT, [
            'enabled' => true,
            'api_format' => 'not-a-real-format',
            'provider' => 'Mystery Provider',
            'model' => 'whatever',
            'api_key' => 'test-key',
        ]);

        $this->assertSame(
            'anthropic',
            app(AiSettingsService::class)->all($company->fresh(), AiSettingsService::TOOL_AD_ASSISTANT)['api_format']
        );
    }

    /**
     * AI tool settings aren't a BelongsToCompany model — they're a JSON
     * attribute (`ai_tools`) directly on the Company row itself, so
     * "company scope" here means: saving/reading always operates on the one
     * Company instance passed in, never a shared table a global scope could
     * leak across. This proves that holds per tool, not just for the
     * pre-existing single global config.
     */
    public function test_two_companies_ai_tool_settings_are_fully_isolated(): void
    {
        $companyA = $this->company();
        $companyB = $this->company();
        $service = app(AiSettingsService::class);

        $service->save($companyA, AiSettingsService::TOOL_AD_ASSISTANT, [
            'enabled' => true, 'api_format' => 'anthropic', 'provider' => 'Anthropic (Claude)',
            'model' => 'claude-opus-5', 'api_key' => 'company-a-key',
        ]);
        $service->save($companyB, AiSettingsService::TOOL_AD_ASSISTANT, [
            'enabled' => true, 'api_format' => 'openai', 'provider' => 'OpenAI',
            'model' => 'gpt-5', 'api_key' => 'company-b-key',
        ]);

        $settingsA = $service->all($companyA->fresh(), AiSettingsService::TOOL_AD_ASSISTANT);
        $settingsB = $service->all($companyB->fresh(), AiSettingsService::TOOL_AD_ASSISTANT);

        $this->assertSame('claude-opus-5', $settingsA['model']);
        $this->assertSame('company-a-key', $settingsA['api_key']);
        $this->assertSame('gpt-5', $settingsB['model']);
        $this->assertSame('company-b-key', $settingsB['api_key']);

        // Adding a legacy global blob on company B never leaks into company
        // A, and doesn't disturb company B's already-explicitly-saved
        // ad_assistant tool — only company B's still-unsaved messaging tool
        // falls back to it.
        $this->storeLegacyGlobalSettings($companyB);

        $this->assertSame('claude-opus-5', $service->all($companyA->fresh(), AiSettingsService::TOOL_AD_ASSISTANT)['model']);
        $this->assertSame('gpt-5', $service->all($companyB->fresh(), AiSettingsService::TOOL_AD_ASSISTANT)['model']);
        $this->assertSame('legacy-model', $service->all($companyB->fresh(), AiSettingsService::TOOL_MESSAGING)['model']);
    }

    public function test_each_tool_stores_and_reads_back_its_own_independent_config(): void
    {
        $company = $this->company();
        $service = app(AiSettingsService::class);

        $service->save($company, AiSettingsService::TOOL_MESSAGING, [
            'enabled' => true, 'api_format' => 'anthropic', 'provider' => 'Anthropic (Claude)',
            'model' => 'claude-haiku-4-5-20251001', 'api_key' => 'messaging-key',
        ]);
        $service->save($company, AiSettingsService::TOOL_AD_ASSISTANT, [
            'enabled' => true, 'api_format' => 'openai', 'provider' => 'OpenAI',
            'model' => 'gpt-5', 'api_key' => 'ad-assistant-key',
        ]);
        $service->save($company, AiSettingsService::TOOL_LANDING_PAGE, [
            'enabled' => false, 'api_format' => 'openai', 'provider' => 'DeepSeek',
            'model' => 'deepseek-chat', 'api_key' => 'landing-page-key',
        ]);

        $fresh = $company->fresh();
        $messaging = $service->all($fresh, AiSettingsService::TOOL_MESSAGING);
        $adAssistant = $service->all($fresh, AiSettingsService::TOOL_AD_ASSISTANT);
        $landingPage = $service->all($fresh, AiSettingsService::TOOL_LANDING_PAGE);

        $this->assertSame('claude-haiku-4-5-20251001', $messaging['model']);
        $this->assertSame('messaging-key', $messaging['api_key']);
        $this->assertSame('gpt-5', $adAssistant['model']);
        $this->assertSame('ad-assistant-key', $adAssistant['api_key']);
        $this->assertFalse($landingPage['enabled']);
        $this->assertSame('deepseek-chat', $landingPage['model']);
        $this->assertSame('landing-page-key', $landingPage['api_key']);

        // Saving one tool again never touches the other two.
        $service->save($fresh, AiSettingsService::TOOL_MESSAGING, [
            'enabled' => true, 'api_format' => 'anthropic', 'provider' => 'Anthropic (Claude)',
            'model' => 'claude-opus-5', 'api_key' => 'messaging-key-2',
        ]);

        $refreshed = $company->fresh();
        $this->assertSame('claude-opus-5', $service->all($refreshed, AiSettingsService::TOOL_MESSAGING)['model']);
        $this->assertSame('gpt-5', $service->all($refreshed, AiSettingsService::TOOL_AD_ASSISTANT)['model']);
        $this->assertSame('deepseek-chat', $service->all($refreshed, AiSettingsService::TOOL_LANDING_PAGE)['model']);
    }

    public function test_every_tool_falls_back_to_the_legacy_shared_config_until_saved_directly(): void
    {
        $company = $this->company();
        $this->storeLegacyGlobalSettings($company);
        $service = app(AiSettingsService::class);

        $fresh = $company->fresh();

        foreach (AiSettingsService::TOOLS as $tool => $label) {
            $settings = $service->all($fresh, $tool);
            $this->assertSame('legacy-model', $settings['model']);
            $this->assertSame('legacy-key', $settings['api_key']);
        }

        // Once a tool is explicitly saved, only that tool moves off the
        // legacy fallback — the other two keep reading the legacy blob.
        $service->save($fresh, AiSettingsService::TOOL_AD_ASSISTANT, [
            'enabled' => true, 'api_format' => 'openai', 'provider' => 'OpenAI',
            'model' => 'gpt-5', 'api_key' => 'new-ad-assistant-key',
        ]);

        $refreshed = $company->fresh();
        $this->assertSame('gpt-5', $service->all($refreshed, AiSettingsService::TOOL_AD_ASSISTANT)['model']);
        $this->assertSame('legacy-model', $service->all($refreshed, AiSettingsService::TOOL_MESSAGING)['model']);
        $this->assertSame('legacy-model', $service->all($refreshed, AiSettingsService::TOOL_LANDING_PAGE)['model']);
    }

    public function test_legacy_openai_provider_value_migrates_forward_on_read(): void
    {
        $company = $this->company();
        $this->storeLegacyEnumSettings($company, 'openai');

        $settings = app(AiSettingsService::class)->all($company->fresh(), AiSettingsService::TOOL_MESSAGING);

        $this->assertSame('openai', $settings['api_format']);
        $this->assertSame('OpenAI', $settings['provider']);
        $this->assertSame('', $settings['base_url']);
    }

    public function test_legacy_deepseek_provider_value_migrates_forward_with_its_known_base_url(): void
    {
        $company = $this->company();
        $this->storeLegacyEnumSettings($company, 'deepseek');

        $settings = app(AiSettingsService::class)->all($company->fresh(), AiSettingsService::TOOL_MESSAGING);

        $this->assertSame('openai', $settings['api_format']);
        $this->assertSame('DeepSeek', $settings['provider']);
        $this->assertSame('https://api.deepseek.com/chat/completions', $settings['base_url']);
    }

    public function test_legacy_anthropic_provider_value_migrates_forward(): void
    {
        $company = $this->company();
        $this->storeLegacyEnumSettings($company, 'anthropic');

        $settings = app(AiSettingsService::class)->all($company->fresh(), AiSettingsService::TOOL_MESSAGING);

        $this->assertSame('anthropic', $settings['api_format']);
        $this->assertSame('Anthropic (Claude)', $settings['provider']);
    }

    /** Writes settings directly in the pre-per-tool shape — a single `ai` key, no `ai_tools` at all. */
    protected function storeLegacyGlobalSettings(Company $company): void
    {
        $settings = (array) $company->settings;
        $settings['ai'] = [
            'enabled' => true,
            'provider' => 'Anthropic (Claude)',
            'api_format' => 'anthropic',
            'base_url' => '',
            'model' => 'legacy-model',
            'confidence_threshold' => 0.75,
            'max_consecutive_ai_replies' => 3,
            'brand_voice' => '',
            'api_key' => \Illuminate\Support\Facades\Crypt::encryptString('legacy-key'),
        ];
        $company->forceFill(['settings' => $settings])->save();
    }

    /** Writes settings directly in the pre-flexible-provider shape — no api_format/base_url keys at all. */
    protected function storeLegacyEnumSettings(Company $company, string $legacyProvider): void
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
