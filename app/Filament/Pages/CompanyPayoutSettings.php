<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Investments;
use App\Models\CompanyPayoutSetting;
use App\Services\CompanyContext;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * The company's own disbursing bank account for outbound BEFTN payout
 * batches — see 10_INVESTOR_RESELLER_AUTO_PAYOUT_PLAN.md. Kept strictly
 * super-admin-only (not gated by the general `settings.manage` permission)
 * because it is the account real money moves out of.
 */
class CompanyPayoutSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?string $cluster = Investments::class;

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Payout Bank Account';

    protected static ?string $title = 'Payout Bank Account';

    protected ?string $subheading = 'The account BEFTN payout batches are drawn from. Used only to fill in the exported bank file — no money moves automatically from this page.';

    protected string $view = 'filament.pages.company-payout-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->settingsState());
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
                Section::make('Disbursing bank account')
                    ->description('This company\'s own current/corporate account — the source account for every BEFTN payout batch\'s exported file.')
                    ->schema([
                        TextInput::make('disbursing_bank_details.bank_name')->label('Bank Name')->maxLength(255),
                        TextInput::make('disbursing_bank_details.branch')->label('Branch')->maxLength(255),
                        TextInput::make('disbursing_bank_details.routing_number')->label('Routing Number')->maxLength(20),
                        TextInput::make('disbursing_bank_details.account_number')->label('Account Number')->maxLength(50),
                        TextInput::make('disbursing_bank_details.account_name')->label('Account Name')->maxLength(255),
                    ])
                    ->columns(2),
            ]);
    }

    public function save(): void
    {
        $company = app(CompanyContext::class)->company();
        abort_unless($company, 404);

        $state = $this->form->getState();

        CompanyPayoutSetting::withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $company->id],
            ['disbursing_bank_details' => $state['disbursing_bank_details'] ?? []],
        );

        Notification::make()->success()->title('Payout bank account saved')->send();
    }

    /** @return array<string, mixed> */
    protected function settingsState(): array
    {
        $company = app(CompanyContext::class)->company();
        $setting = $company ? CompanyPayoutSetting::withoutGlobalScopes()->where('company_id', $company->id)->first() : null;

        return [
            'disbursing_bank_details' => $setting?->disbursing_bank_details ?? [],
        ];
    }
}
