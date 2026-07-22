<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Settings;
use App\Services\StorageSettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class CloudStorageSettings extends Page
{
    protected const R2_DASHBOARD_URL = 'https://dash.cloudflare.com/?to=%2F%3Aaccount%2Fr2%2Foverview';

    protected const R2_AUTH_DOCS_URL = 'https://developers.cloudflare.com/r2/api/tokens/';

    protected const R2_BUCKET_DOCS_URL = 'https://developers.cloudflare.com/r2/buckets/create-buckets/';

    protected const R2_PUBLIC_DOCS_URL = 'https://developers.cloudflare.com/r2/buckets/public-buckets/';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCloudArrowUp;

    protected static ?string $cluster = Settings::class;

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Cloud Storage';

    protected static ?string $title = 'Cloud Storage (Cloudflare R2)';

    protected ?string $subheading = 'One centrally managed R2 connection with company-isolated public and private object paths.';

    protected string $view = 'filament.pages.cloud-storage-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(StorageSettingsService $settings): void
    {
        $this->form->fill($this->settingsState($settings));
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->isSuperAdmin() ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Global R2 connection')
                    ->description('Credentials are infrastructure-wide. Company isolation is enforced by immutable object prefixes, not by duplicating credentials per company.')
                    ->headerActions([
                        $this->r2SetupGuideAction(),
                    ])
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Enable R2 for new uploads')
                            ->hintAction($this->fieldHelpAction(
                                name: 'enableR2Help',
                                heading: 'Enable R2 for New Uploads',
                                description: 'Enable the switch only after the saved configuration passes the required connection tests.',
                                steps: [
                                    'Keep this switch off while entering or changing credentials, bucket names, and the public URL.',
                                    'Save the form, then run Test public bucket. If a private bucket is configured, run Test private bucket too.',
                                    'Turn this switch on only after every configured test succeeds. Existing local files are not moved or deleted.',
                                ],
                                docsUrl: self::R2_AUTH_DOCS_URL,
                            ))
                            ->helperText('Existing local files remain readable during migration. Enabling this does not delete or move them.')
                            ->columnSpanFull(),
                        TextInput::make('access_key_id')
                            ->label('Access Key ID')
                            ->hintAction($this->fieldHelpAction(
                                name: 'accessKeyIdHelp',
                                heading: 'Access Key ID',
                                description: 'Use the Access Key ID generated for an R2 S3 API token, not a general Cloudflare API key.',
                                steps: [
                                    'Open Cloudflare Dashboard > Storage & databases > R2 > Overview.',
                                    'Under Account Details, select Manage next to API Tokens, then create an Account API token.',
                                    'Choose Object Read & Write and scope the token to both the public and private bucket names used on this page.',
                                    'Create the token and copy Access Key ID from the confirmation screen into this field.',
                                ],
                                docsUrl: self::R2_AUTH_DOCS_URL,
                            ))
                            ->required(fn (Get $get): bool => (bool) $get('enabled'))
                            ->autocomplete(false),
                        TextInput::make('secret_access_key')
                            ->label('Secret Access Key')
                            ->hintAction($this->fieldHelpAction(
                                name: 'secretAccessKeyHelp',
                                heading: 'Secret Access Key',
                                description: 'Cloudflare shows the R2 Secret Access Key only once, immediately after the token is created.',
                                steps: [
                                    'Copy Secret Access Key from the same R2 API token confirmation screen as the Access Key ID.',
                                    'Store a recovery copy in a secure password manager before leaving Cloudflare; the secret cannot be viewed again.',
                                    'Paste the secret here. After it is saved encrypted, leave this field blank to keep the stored value.',
                                    'If the secret is lost, create a replacement R2 token and update both credential fields together.',
                                ],
                                docsUrl: self::R2_AUTH_DOCS_URL,
                            ))
                            ->password()
                            ->revealable()
                            ->required(fn (Get $get): bool => (bool) $get('enabled') && ! app(StorageSettingsService::class)->hasSecretAccessKey())
                            ->helperText(fn (): string => $this->hasStoredSecretAccessKey()
                                ? 'A secret is stored encrypted. Leave blank to keep it.'
                                : 'No secret key is stored yet.')
                            ->autocomplete('new-password'),
                        TextInput::make('endpoint')
                            ->label('S3 endpoint')
                            ->hintAction($this->fieldHelpAction(
                                name: 'endpointHelp',
                                heading: 'S3 Endpoint',
                                description: 'Copy the full R2 S3 API endpoint from the token confirmation screen or the R2 Overview page.',
                                steps: [
                                    'For standard buckets, use https://<ACCOUNT_ID>.r2.cloudflarestorage.com.',
                                    'Do not add a bucket name, object path, or public custom domain to this URL.',
                                    'Jurisdictional buckets require their matching endpoint, such as .eu.r2.cloudflarestorage.com. Both buckets on this page must use the same account and endpoint.',
                                    'The endpoint becomes locked after the first successful active rollout; account rotation requires a planned migration.',
                                ],
                                docsUrl: self::R2_AUTH_DOCS_URL,
                            ))
                            ->url()
                            ->disabled(fn (): bool => $this->isPublicTopologyLocked() || $this->isPrivateTopologyLocked())
                            ->required(fn (Get $get): bool => (bool) $get('enabled'))
                            ->placeholder('https://<account_id>.r2.cloudflarestorage.com')
                            ->helperText('Locked after the first active R2 rollout. Account changes require a staged copy-and-verify migration.')
                            ->autocomplete(false)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Public storefront media')
                    ->description('Product photos, category images, company/storefront logos, slides, and page covers. Use a production custom domain, not an r2.dev development URL.')
                    ->schema([
                        TextInput::make('public_bucket')
                            ->label('Public bucket name')
                            ->hintAction($this->fieldHelpAction(
                                name: 'publicBucketHelp',
                                heading: 'Public Bucket Name',
                                description: 'Create a dedicated R2 bucket for storefront and company media, then enter its exact name.',
                                steps: [
                                    'Open Cloudflare R2 Overview and select Create bucket.',
                                    'Create a name such as zamzam-public-media. Use lowercase letters, numbers, and hyphens.',
                                    'Make sure the Object Read & Write token is scoped to this bucket.',
                                    'Enter only the bucket name here, without r2://, a domain, or a slash.',
                                ],
                                docsUrl: self::R2_BUCKET_DOCS_URL,
                            ))
                            ->disabled(fn (): bool => $this->isPublicTopologyLocked())
                            ->required(fn (Get $get): bool => (bool) $get('enabled'))
                            ->different('private_bucket')
                            ->placeholder('zamzam-public-media')
                            ->autocomplete(false),
                        TextInput::make('public_url')
                            ->label('Public custom-domain URL')
                            ->hintAction($this->fieldHelpAction(
                                name: 'publicUrlHelp',
                                heading: 'Public Custom-Domain URL',
                                description: 'Connect a production custom domain to the public bucket and paste its HTTPS origin here.',
                                steps: [
                                    'Open the public bucket in Cloudflare R2, select Settings, then find Custom Domains.',
                                    'Connect a hostname from a domain managed in the same Cloudflare account, such as media.example.com.',
                                    'Wait until the custom-domain status is active, then paste https://media.example.com without an object path.',
                                    'Do not use the rate-limited r2.dev Public Development URL for production. Keep that alternate public URL disabled.',
                                ],
                                docsUrl: self::R2_PUBLIC_DOCS_URL,
                            ))
                            ->url()
                            ->required(fn (Get $get): bool => (bool) $get('enabled'))
                            ->placeholder('https://media.example.com')
                            ->autocomplete(false),
                        Placeholder::make('public_status')
                            ->label('Status')
                            ->content(fn (): string => $this->isPublicConfigured() ? 'Ready for connection test' : 'Configuration incomplete')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Private business files')
                    ->description('Chat media and voucher attachments stay private and are served only after an authenticated company-access check.')
                    ->schema([
                        TextInput::make('private_bucket')
                            ->label('Private bucket name')
                            ->hintAction($this->fieldHelpAction(
                                name: 'privateBucketHelp',
                                heading: 'Private Bucket Name',
                                description: 'Use a second dedicated bucket for chat media and voucher attachments; never reuse the public bucket.',
                                steps: [
                                    'From Cloudflare R2 Overview, create another bucket such as zamzam-private-files.',
                                    'Include this bucket in the same Object Read & Write token scope.',
                                    'Do not connect a custom domain and do not enable the Public Development URL for this bucket.',
                                    'Enter only the exact bucket name. Leave this field blank if private files should remain on local storage for now.',
                                ],
                                docsUrl: self::R2_BUCKET_DOCS_URL,
                            ))
                            ->disabled(fn (): bool => $this->isPrivateTopologyLocked())
                            ->different('public_bucket')
                            ->placeholder('zamzam-private-files')
                            ->autocomplete(false)
                            ->helperText('Optional during rollout. It must be a dedicated bucket with every public-access option disabled. When blank, private files stay local.'),
                        Toggle::make('private_access_confirmed')
                            ->label('I confirmed all public access is disabled for this private bucket')
                            ->hintAction($this->fieldHelpAction(
                                name: 'privateAccessHelp',
                                heading: 'Private Bucket Public-Access Check',
                                description: 'This confirmation is manual because S3 credentials cannot detect every Cloudflare public exposure setting.',
                                steps: [
                                    'Open the private bucket in Cloudflare R2 and select Settings.',
                                    'Confirm Public Development URL is disabled.',
                                    'Confirm there are no enabled Custom Domains connected to the private bucket.',
                                    'Select this confirmation only after both checks pass. The application will still serve private files through authenticated company-authorized routes.',
                                ],
                                docsUrl: self::R2_PUBLIC_DOCS_URL,
                            ))
                            ->accepted(fn (Get $get): bool => filled($get('private_bucket')))
                            ->required(fn (Get $get): bool => filled($get('private_bucket')))
                            ->helperText('R2 S3 credentials cannot verify r2.dev or custom-domain exposure. Confirm this in the Cloudflare dashboard before using the bucket.')
                            ->columnSpanFull(),
                        Placeholder::make('private_status')
                            ->label('Status')
                            ->content(fn (): string => $this->isPrivateConfigured() ? 'Ready for connection test' : 'Using local private storage'),
                    ])
                    ->columns(2),

                Section::make('Company isolation')
                    ->schema([
                        Placeholder::make('path_layout')
                            ->hiddenLabel()
                            ->content(new HtmlString(
                                '<code>companies/{immutable-storage-key}/public/...</code><br>'
                                .'<code>companies/{immutable-storage-key}/private/...</code>'
                            )),
                    ]),
            ]);
    }

    public function save(StorageSettingsService $settings): void
    {
        $settings->save($this->form->getState());
        $this->form->fill($this->settingsState($settings));

        Notification::make()
            ->title('Cloud storage settings saved')
            ->body($settings->enabled()
                ? 'New company media will use the configured R2 disks; legacy local files remain available during migration.'
                : 'R2 is disabled. New files will use stable local public/private disks.')
            ->success()
            ->send();
    }

    public function testPublicConnection(StorageSettingsService $settings): void
    {
        $this->sendConnectionNotification($settings->testPublicConnection(), 'Public bucket');
    }

    public function testPrivateConnection(StorageSettingsService $settings): void
    {
        $this->sendConnectionNotification($settings->testPrivateConnection(), 'Private bucket');
    }

    public function hasStoredSecretAccessKey(): bool
    {
        return app(StorageSettingsService::class)->hasSecretAccessKey();
    }

    public function isPublicConfigured(): bool
    {
        return app(StorageSettingsService::class)->isPublicConfigured();
    }

    public function isPrivateConfigured(): bool
    {
        return app(StorageSettingsService::class)->isPrivateConfigured();
    }

    public function isPublicTopologyLocked(): bool
    {
        return app(StorageSettingsService::class)->publicTopologyLocked();
    }

    public function isPrivateTopologyLocked(): bool
    {
        return app(StorageSettingsService::class)->privateTopologyLocked();
    }

    /** @return array<string, mixed> */
    protected function settingsState(StorageSettingsService $settings): array
    {
        return [
            'enabled' => $settings->enabled(),
            'access_key_id' => $settings->accessKeyId(),
            'secret_access_key' => null,
            'endpoint' => $settings->endpoint(),
            'public_bucket' => $settings->publicBucket(),
            'public_url' => $settings->publicUrl(),
            'private_bucket' => $settings->privateBucket(),
            'private_access_confirmed' => $settings->privateAccessConfirmed(),
        ];
    }

    /** @param array{ok: bool, message: string} $result */
    protected function sendConnectionNotification(array $result, string $label): void
    {
        $notification = Notification::make()
            ->title($result['ok'] ? "{$label} connection successful" : "{$label} connection failed")
            ->body($result['message']);

        $result['ok'] ? $notification->success()->send() : $notification->danger()->send();
    }

    protected function r2SetupGuideAction(): Action
    {
        return Action::make('r2SetupGuide')
            ->label('R2 setup guide')
            ->icon(Heroicon::OutlinedBookOpen)
            ->color('gray')
            ->modalHeading('Cloudflare R2 Setup Guide')
            ->modalDescription('Create the storage topology and scoped S3 credentials before enabling new uploads.')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalWidth(Width::FiveExtraLarge)
            ->schema([
                TextEntry::make('r2_setup_buckets')
                    ->label('1. Create 2 Buckets')
                    ->state([
                        'In Cloudflare Dashboard, open Storage & databases > R2 > Overview and create one public-media bucket and one private-files bucket.',
                        'Use different names, for example zamzam-public-media and zamzam-private-files. Keep both buckets in the same R2 account and jurisdiction.',
                    ])
                    ->bulleted(),
                TextEntry::make('r2_setup_token')
                    ->label('2. Create the R2 S3 Token')
                    ->state([
                        'In R2 Overview, under Account Details, select Manage next to API Tokens and create an Account API token.',
                        'Choose Object Read & Write and scope the token to both bucket names. A general Cloudflare API key is not the required credential.',
                    ])
                    ->bulleted(),
                TextEntry::make('r2_setup_credentials')
                    ->label('3. Copy Credentials')
                    ->state([
                        'From the confirmation screen, copy Access Key ID, Secret Access Key, and the S3 endpoint.',
                        'Save the Secret Access Key securely before leaving; Cloudflare does not show it again.',
                    ])
                    ->bulleted(),
                TextEntry::make('r2_setup_access')
                    ->label('4. Configure Public & Private Access')
                    ->state([
                        'Connect a production custom domain to the public bucket and paste its HTTPS URL into Public custom-domain URL.',
                        'On the private bucket, keep Public Development URL disabled and do not connect an enabled Custom Domain.',
                    ])
                    ->bulleted(),
                TextEntry::make('r2_setup_activate')
                    ->label('5. Save, Test, Then Enable')
                    ->state([
                        'Keep Enable R2 for new uploads off, save the fields, and test the public bucket plus the private bucket when configured.',
                        'Enable R2 only after the configured tests pass. Existing local files remain available and are not moved automatically.',
                    ])
                    ->bulleted(),
                TextEntry::make('r2_dashboard')
                    ->label('Cloudflare Dashboard')
                    ->state('Open R2 Overview')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(self::R2_DASHBOARD_URL)
                    ->openUrlInNewTab(),
                TextEntry::make('r2_documentation')
                    ->label('Official Documentation')
                    ->state('Open R2 authentication guide')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(self::R2_AUTH_DOCS_URL)
                    ->openUrlInNewTab(),
            ]);
    }

    /** @param list<string> $steps */
    protected function fieldHelpAction(
        string $name,
        string $heading,
        string $description,
        array $steps,
        string $docsUrl,
    ): Action {
        return Action::make($name)
            ->label("Help for {$heading}")
            ->icon(Heroicon::OutlinedInformationCircle)
            ->iconButton()
            ->color('gray')
            ->tooltip("How to configure {$heading}")
            ->modalHeading($heading)
            ->modalDescription($description)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalWidth(Width::Large)
            ->schema([
                TextEntry::make("{$name}_steps")
                    ->label('Cloudflare Dashboard Steps')
                    ->state($steps)
                    ->bulleted(),
                TextEntry::make("{$name}_documentation")
                    ->label('Official Documentation')
                    ->state('Open Cloudflare R2 guide')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url($docsUrl)
                    ->openUrlInNewTab(),
            ]);
    }
}
