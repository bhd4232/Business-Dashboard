<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Settings;
use App\Services\StorageSettingsService;
use BackedEnum;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class CloudStorageSettings extends Page
{
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
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Enable R2 for new uploads')
                            ->helperText('Existing local files remain readable during migration. Enabling this does not delete or move them.')
                            ->columnSpanFull(),
                        TextInput::make('access_key_id')
                            ->label('Access Key ID')
                            ->required(fn (Get $get): bool => (bool) $get('enabled'))
                            ->autocomplete(false),
                        TextInput::make('secret_access_key')
                            ->label('Secret Access Key')
                            ->password()
                            ->revealable()
                            ->required(fn (Get $get): bool => (bool) $get('enabled') && ! app(StorageSettingsService::class)->hasSecretAccessKey())
                            ->helperText(fn (): string => $this->hasStoredSecretAccessKey()
                                ? 'A secret is stored encrypted. Leave blank to keep it.'
                                : 'No secret key is stored yet.')
                            ->autocomplete('new-password'),
                        TextInput::make('endpoint')
                            ->label('S3 endpoint')
                            ->url()
                            ->disabled(fn (): bool => $this->isPublicTopologyLocked() || $this->isPrivateTopologyLocked())
                            ->required(fn (Get $get): bool => (bool) $get('enabled'))
                            ->placeholder('https://<account_id>.r2.cloudflarestorage.com')
                            ->helperText('Locked after the first active R2 rollout. Account changes require a staged copy-and-verify migration.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Public storefront media')
                    ->description('Product photos, category images, company/storefront logos, slides, and page covers. Use a production custom domain, not an r2.dev development URL.')
                    ->schema([
                        TextInput::make('public_bucket')
                            ->label('Public bucket')
                            ->disabled(fn (): bool => $this->isPublicTopologyLocked())
                            ->required(fn (Get $get): bool => (bool) $get('enabled'))
                            ->different('private_bucket')
                            ->placeholder('zamzam-public-media'),
                        TextInput::make('public_url')
                            ->label('Public custom-domain URL')
                            ->url()
                            ->required(fn (Get $get): bool => (bool) $get('enabled'))
                            ->placeholder('https://media.example.com'),
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
                            ->label('Private bucket')
                            ->disabled(fn (): bool => $this->isPrivateTopologyLocked())
                            ->different('public_bucket')
                            ->placeholder('zamzam-private-files')
                            ->helperText('Optional during rollout. It must be a dedicated bucket with every public-access option disabled. When blank, private files stay local.'),
                        Toggle::make('private_access_confirmed')
                            ->label('I confirmed all public access is disabled for this private bucket')
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
}
