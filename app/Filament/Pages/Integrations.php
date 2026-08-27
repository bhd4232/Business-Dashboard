<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Settings;
use App\Models\Company;
use App\Models\StorefrontSetting;
use App\Services\CompanyContext;
use App\Services\Crm\AiSettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

/**
 * Single editable settings page for every third-party integration that is
 * genuinely just a handful of credential fields: AI Assistant, WooCommerce,
 * Payment Gateway, and Meta Pixel/CAPI's connection fields (Pixel ID +
 * access token — the full event-list/testing/log configuration stays on
 * the dedicated Meta CAPI page, linked from that tab). Courier Providers
 * and Meta Ads are NOT merged here — both are full multi-record CRUD areas
 * (many providers/ad accounts, dashboards, campaigns), so they stay on
 * their own pages; IntegrationStatusWidget below still surfaces them as
 * status-card links so this remains the one place staff check first.
 *
 * Three different backing stores get combined into one Livewire form:
 * - AI fields live in `companies.settings->ai` (via AiSettingsService).
 * - WooCommerce/Payment Gateway/Meta connection fields live on the
 *   company's single StorefrontSetting row (`updateOrCreate` with a
 *   partial attribute array only ever touches these columns — the same
 *   pattern MetaCapiSettings::save() already uses — so this never
 *   disturbs the theme/checkout/SEO settings living on that same row).
 */
class Integrations extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?string $cluster = Settings::class;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Integrations';

    protected static ?string $title = 'Integrations';

    protected string $view = 'filament.pages.integrations';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    #[Locked]
    public ?int $companyId = null;

    public function mount(AiSettingsService $aiSettings): void
    {
        if (app(CompanyContext::class)->isAllCompanies()) {
            return;
        }

        $company = $this->selectedCompany();
        $this->companyId = (int) $company->getKey();

        $ai = $aiSettings->all($company);
        $setting = StorefrontSetting::withoutGlobalScopes()->where('company_id', $this->companyId)->first();

        // Every key is filled explicitly (never left for a field's own
        // ->default() to supply) — a brand-new company has no StorefrontSetting
        // row yet, and Filament's Schema::fill() does not fall back to a
        // component's default() for a key simply absent from the given
        // array, so a required Select like online_payment_gateway would
        // fail validation on first save with no visible reason otherwise.
        $this->form->fill([
            'ai_enabled' => $ai['enabled'],
            'ai_api_format' => $ai['api_format'],
            'ai_provider' => $ai['provider'],
            'ai_base_url' => $ai['base_url'],
            'ai_model' => $ai['model'],
            'ai_confidence_threshold' => $ai['confidence_threshold'],
            'ai_max_consecutive_ai_replies' => $ai['max_consecutive_ai_replies'],
            'ai_brand_voice' => $ai['brand_voice'],
            'ai_api_key' => '', // never round-trip the stored key to the browser
            'ai_has_api_key' => filled($ai['api_key']),
            'woocommerce_base_url' => $setting?->woocommerce_base_url,
            'woocommerce_credentials' => $setting?->woocommerce_credentials ?? [],
            'online_payment_enabled' => $setting?->online_payment_enabled ?? false,
            'online_payment_gateway' => $setting?->online_payment_gateway ?? 'zinipay',
            'payment_credentials' => $setting?->payment_credentials ?? [],
            'meta_tracking_enabled' => $setting?->meta_tracking_enabled ?? false,
            'meta_pixel_id' => $setting?->meta_pixel_id,
            'meta_capi_enabled' => $setting?->meta_capi_enabled ?? false,
            'meta_tracking_credentials' => $setting?->meta_tracking_credentials ?? [],
        ]);
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        $context = app(CompanyContext::class);

        if (! $user?->canManageSettings()) {
            return false;
        }

        if ($context->isAllCompanies()) {
            return $user->isSuperAdmin();
        }

        $company = $context->company();

        return (bool) ($company && $user->canAccessCompany((int) $company->getKey()));
    }

    public function hasSelectedCompany(): bool
    {
        return $this->companyId !== null;
    }

    /** AI settings stay super-admin-only, matching AiAssistantSettings::canAccess() — this merged page must not loosen that. */
    public function canManageAi(): bool
    {
        return Auth::user()?->isSuperAdmin() ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveChanges')
                ->label('Save changes')
                ->icon(Heroicon::OutlinedCheck)
                ->submit('save')
                ->formId('integrations-form')
                ->keyBindings(['mod+s'])
                ->visible(fn (): bool => $this->hasSelectedCompany()),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('integrations')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('AI Assistant')
                            ->icon(Heroicon::OutlinedSparkles)
                            ->visible(fn (): bool => $this->canManageAi())
                            ->schema([
                                Toggle::make('ai_enabled')
                                    ->label('Enable AI auto-reply for this company')
                                    ->columnSpanFull(),
                                Select::make('ai_api_format')
                                    ->label('API format')
                                    ->options([
                                        'anthropic' => 'Anthropic (Claude Messages API)',
                                        'openai' => 'OpenAI-compatible (Chat Completions)',
                                    ])
                                    ->helperText('Almost every non-Anthropic provider (OpenAI, DeepSeek, Groq, Mistral, OpenRouter, xAI, a self-hosted Ollama/vLLM, ...) speaks the "OpenAI-compatible" format — pick that and set the base URL below to add any of them.')
                                    ->required()
                                    ->native(false),
                                TextInput::make('ai_provider')
                                    ->label('Provider name (label only)')
                                    ->placeholder('e.g. DeepSeek, Groq, OpenRouter')
                                    ->helperText("Just for your own reference — doesn't affect the request.")
                                    ->required()
                                    ->maxLength(100),
                                TextInput::make('ai_base_url')
                                    ->label('Base URL (optional)')
                                    ->url()
                                    ->maxLength(500)
                                    ->placeholder("Leave blank for the API format's own default endpoint")
                                    ->helperText('e.g. DeepSeek: https://api.deepseek.com/chat/completions')
                                    ->columnSpanFull(),
                                TextInput::make('ai_model')
                                    ->label('Model')
                                    ->placeholder('claude-haiku-4-5-20251001')
                                    ->required()
                                    ->maxLength(100),
                                TextInput::make('ai_api_key')
                                    ->label('API Key')
                                    ->password()
                                    ->revealable()
                                    ->maxLength(500)
                                    ->placeholder(fn (Get $get): string => $get('ai_has_api_key') ? '••••••••' : 'sk-...')
                                    ->helperText(fn (Get $get): string => $get('ai_has_api_key')
                                        ? 'Already saved and encrypted — leave blank to keep it.'
                                        : 'Stored encrypted per company.'),
                                TextInput::make('ai_confidence_threshold')
                                    ->label('Confidence threshold (0–1)')
                                    ->numeric()
                                    ->step(0.05)
                                    ->minValue(0)
                                    ->maxValue(1)
                                    ->required()
                                    ->helperText('Replies below this confidence are held for a human instead of being sent.'),
                                TextInput::make('ai_max_consecutive_ai_replies')
                                    ->label('Max consecutive AI replies')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(20)
                                    ->required(),
                                Textarea::make('ai_brand_voice')
                                    ->label('Brand voice (optional)')
                                    ->rows(3)
                                    ->maxLength(2000)
                                    ->placeholder("e.g. friendly, uses simple Bengali, addresses customers as 'আপনি'")
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Tab::make('WooCommerce')
                            ->icon(Heroicon::OutlinedShoppingBag)
                            ->schema([
                                TextInput::make('woocommerce_base_url')
                                    ->label('WooCommerce site URL')
                                    ->url()
                                    ->maxLength(255)
                                    ->placeholder('https://zamzamgadgetbd.com')
                                    ->helperText('Root URL of the WooCommerce site. Do not include /wp-json.')
                                    ->columnSpanFull(),
                                TextInput::make('woocommerce_credentials.consumer_key')
                                    ->label('Consumer key')
                                    ->password()
                                    ->revealable()
                                    ->maxLength(255),
                                TextInput::make('woocommerce_credentials.consumer_secret')
                                    ->label('Consumer secret')
                                    ->password()
                                    ->revealable()
                                    ->maxLength(255),
                                Placeholder::make('woocommerce_note')
                                    ->hiddenLabel()
                                    ->content('Save credentials here, then run (or re-run) the actual product import from Storefront Settings → WooCommerce Import.')
                                    ->columnSpanFull(),
                                TextInput::make('woocommerce_credentials.webhook_secret')
                                    ->label('Order webhook secret')
                                    ->password()
                                    ->revealable()
                                    ->maxLength(255)
                                    ->suffixAction(
                                        Action::make('generateWoocommerceWebhookSecret')
                                            ->icon(Heroicon::ArrowPath)
                                            ->action(fn (Set $set) => $set('woocommerce_credentials.webhook_secret', Str::random(40))),
                                    )
                                    ->helperText('Must match exactly what you paste as the Secret when creating the webhook in WooCommerce.')
                                    ->columnSpanFull(),
                                Placeholder::make('woocommerce_webhook_url')
                                    ->label('Webhook delivery URL')
                                    ->content(fn (): string => $this->companyId ? route('woocommerce.webhook', $this->companyId) : 'Save the company first.')
                                    ->columnSpanFull(),
                                Placeholder::make('woocommerce_webhook_note')
                                    ->hiddenLabel()
                                    ->content('Order sync (WooCommerce → ERP) uses a webhook, not the import button above: in WordPress go to WooCommerce → Settings → Advanced → Webhooks → Add webhook. Set Topic to "Order updated" (it covers created/updated/deleted), Delivery URL to the URL above, and Secret to the same secret set above — the two must match exactly.')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Tab::make('Payment Gateway')
                            ->icon(Heroicon::OutlinedCreditCard)
                            ->schema([
                                Toggle::make('online_payment_enabled')
                                    ->label('Enable online payments')
                                    ->helperText('Turn on only after the selected gateway\'s credentials below are set.')
                                    ->columnSpanFull(),
                                Select::make('online_payment_gateway')
                                    ->label('Active gateway')
                                    ->options(['zinipay' => 'ZiniPay', 'paystation' => 'PayStation'])
                                    ->default('zinipay')
                                    ->required()
                                    ->native(false)
                                    ->live(),
                                TextInput::make('payment_credentials.zinipay_api_key')
                                    ->label('ZiniPay API key')
                                    ->password()
                                    ->revealable()
                                    ->maxLength(255)
                                    ->visible(fn (Get $get): bool => $get('online_payment_gateway') === 'zinipay'),
                                TextInput::make('payment_credentials.paystation_merchant_id')
                                    ->label('PayStation Merchant ID')
                                    ->password()
                                    ->revealable()
                                    ->maxLength(255)
                                    ->visible(fn (Get $get): bool => $get('online_payment_gateway') === 'paystation'),
                                TextInput::make('payment_credentials.paystation_password')
                                    ->label('PayStation Password / API key')
                                    ->password()
                                    ->revealable()
                                    ->maxLength(255)
                                    ->visible(fn (Get $get): bool => $get('online_payment_gateway') === 'paystation'),
                                Placeholder::make('payment_note')
                                    ->hiddenLabel()
                                    ->content('More gateway options (base URLs, manual bKash/Nagad numbers) are on Storefront Settings → Online Payments.')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Tab::make('Meta Pixel & CAPI')
                            ->icon(Heroicon::OutlinedSignal)
                            ->schema([
                                Toggle::make('meta_tracking_enabled')
                                    ->label('Enable Meta tracking')
                                    ->columnSpanFull(),
                                TextInput::make('meta_pixel_id')
                                    ->label('Primary Pixel / Dataset ID')
                                    ->maxLength(32)
                                    ->regex('/^\d{5,32}$/'),
                                Toggle::make('meta_capi_enabled')
                                    ->label('Server-side events (Conversions API)'),
                                TextInput::make('meta_tracking_credentials.access_token')
                                    ->label('Conversions API access token')
                                    ->password()
                                    ->revealable()
                                    ->maxLength(1000)
                                    ->helperText('Stored encrypted.')
                                    ->columnSpanFull(),
                                Placeholder::make('meta_note')
                                    ->hiddenLabel()
                                    ->content('Event lists, consent settings, additional Pixels, testing tools, and the event log are on the Meta CAPI page.')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }

    public function save(AiSettingsService $aiSettings): void
    {
        $company = $this->selectedCompany();
        $state = $this->form->getState();

        if ($this->canManageAi()) {
            $aiSettings->save($company, [
                'enabled' => $state['ai_enabled'] ?? false,
                'api_format' => $state['ai_api_format'] ?? null,
                'provider' => $state['ai_provider'] ?? null,
                'base_url' => $state['ai_base_url'] ?? null,
                'model' => $state['ai_model'] ?? null,
                'confidence_threshold' => $state['ai_confidence_threshold'] ?? null,
                'max_consecutive_ai_replies' => $state['ai_max_consecutive_ai_replies'] ?? null,
                'brand_voice' => $state['ai_brand_voice'] ?? null,
                'api_key' => $state['ai_api_key'] ?? null,
            ]);
        }

        StorefrontSetting::withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $company->getKey()],
            Arr::only($state, [
                'woocommerce_base_url',
                'woocommerce_credentials',
                'online_payment_enabled',
                'online_payment_gateway',
                'payment_credentials',
                'meta_tracking_enabled',
                'meta_pixel_id',
                'meta_capi_enabled',
                'meta_tracking_credentials',
            ]),
        );

        // Re-fill from the fresh, now-persisted state (same "never keep the
        // plaintext key in the browser" treatment as AiAssistantSettings).
        $this->mount($aiSettings);

        Notification::make()
            ->title('Integration settings saved')
            ->success()
            ->send();
    }

    protected function selectedCompany(): Company
    {
        $contextCompany = app(CompanyContext::class)->company();

        abort_unless($contextCompany !== null, 404, 'Select a company before opening Integrations.');

        if ($this->companyId !== null) {
            abort_unless(
                (int) $contextCompany->getKey() === $this->companyId,
                409,
                'The selected company changed. Reload Integrations before saving.',
            );
        }

        $user = Auth::user();
        abort_unless($user?->canManageSettings() && $user->canAccessCompany((int) $contextCompany->getKey()), 403);

        return $contextCompany;
    }
}
