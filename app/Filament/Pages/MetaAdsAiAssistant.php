<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Ads;
use App\Models\MetaAdAccount;
use App\Models\MetaAdProposal;
use App\Services\CompanyContext;
use App\Services\MetaAdsAiAssistantService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Throwable;

/**
 * Phase D: lists AI-drafted campaign recommendations
 * (App\Services\MetaAdsAiAssistantService) for the selected ad account, and
 * lets the owner review/launch or dismiss each one. Generating never
 * spends money or touches Meta at all — the service only ever writes
 * MetaAdProposal rows with status "draft". "Review & Launch" hands off to
 * CreateMetaAdCampaign (Phase C), which always creates PAUSED objects on
 * Meta; only Phase B's explicit Resume ever starts real spending.
 */
class MetaAdsAiAssistant extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $cluster = Ads::class;

    protected static ?string $navigationLabel = 'AI Assistant';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'AI Marketing Assistant';

    protected string $view = 'filament.pages.meta-ads-ai-assistant';

    #[Url]
    public ?int $accountId = null;

    public function mount(): void
    {
        if ($this->accountId === null) {
            $this->accountId = $this->accounts()->first()?->getKey();
        }
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->hasPermission('marketing.view') ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('accountId')
                ->label('Ad Account')
                ->options(fn (): array => $this->accounts()->pluck('name', 'id')->all())
                ->native(false)
                ->live()
                ->maxWidth(Width::ExtraSmall)
                ->visible(fn (): bool => $this->accounts()->count() > 1)
                ->selectablePlaceholder(false)
                ->afterStateUpdated(fn () => $this->resetTable()),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('Generate Recommendations')
                ->icon(Heroicon::OutlinedSparkles)
                ->visible(fn (): bool => Auth::user()?->hasPermission('marketing.create') ?? false)
                ->disabled(fn (): bool => $this->selectedAccount() === null)
                ->action(function (MetaAdsAiAssistantService $service): void {
                    $account = $this->selectedAccount();

                    if (! $account) {
                        return;
                    }

                    try {
                        $proposals = $service->generateRecommendations($account);
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title('Could not generate recommendations')
                            ->body($this->exceptionMessage($exception))
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Recommendations ready')
                        ->body($proposals->count().' draft proposal(s) — review before launching any of them.')
                        ->success()
                        ->send();

                    $this->resetTable();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->currentQuery())
            ->columns([
                TextColumn::make('product.name')->label('Product')->searchable()->limit(40)->placeholder('-'),
                IconColumn::make('is_recommended')->label('Recommended')->boolean(),
                TextColumn::make('suggested_budget')
                    ->label('Daily Budget')
                    ->moneyWithoutTrailingZeroes(fn (MetaAdProposal $record): string => $record->account?->currency ?? 'USD'),
                TextColumn::make('suggested_duration_days')->label('Duration (days)')->placeholder('-'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        MetaAdProposal::STATUS_DRAFT => 'warning',
                        MetaAdProposal::STATUS_LAUNCHED => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')->label('Generated')->since()->sortable(),
            ])
            ->recordActions([
                Action::make('reasoning')
                    ->label('Reasoning')
                    ->icon(Heroicon::OutlinedLightBulb)
                    ->color('gray')
                    ->modalHeading('AI Reasoning')
                    ->modalWidth(Width::Large)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->schema(fn (MetaAdProposal $record): array => [
                        TextEntry::make('reasoning')->label('')->state($record->reasoning_text ?: 'No reasoning recorded.'),
                    ]),
                Action::make('reviewAndLaunch')
                    ->label('Review & Launch')
                    ->icon(Heroicon::OutlinedArrowRight)
                    ->visible(fn (MetaAdProposal $record): bool => $record->status === MetaAdProposal::STATUS_DRAFT
                        && (Auth::user()?->hasPermission('marketing.create') ?? false))
                    ->url(fn (MetaAdProposal $record): string => CreateMetaAdCampaign::getUrl(['proposalId' => $record->getKey()])),
                Action::make('dismiss')
                    ->label('Dismiss')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (MetaAdProposal $record): bool => $record->status === MetaAdProposal::STATUS_DRAFT
                        && (Auth::user()?->hasPermission('marketing.create') ?? false))
                    ->action(fn (MetaAdProposal $record) => $record->forceFill(['status' => MetaAdProposal::STATUS_DISMISSED])->save()),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading($this->selectedAccount() ? 'No recommendations yet' : 'No ad account selected')
            ->emptyStateDescription($this->selectedAccount()
                ? ($this->selectedAccount()->aiGuardrailsConfigured()
                    ? 'Click Generate Recommendations to have the AI look at real stock/margin/sales data.'
                    : 'Set a Max Daily Budget under "AI Marketing Assistant Guardrails" on this ad account first.')
                : 'Add and select a Meta Ads account to get started.')
            ->emptyStateIcon(Heroicon::OutlinedSparkles);
    }

    /** @return Collection<int, MetaAdAccount> */
    public function accounts(): Collection
    {
        $companyId = app(CompanyContext::class)->id();

        return MetaAdAccount::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function selectedAccount(): ?MetaAdAccount
    {
        if (! $this->accountId) {
            return null;
        }

        return $this->accounts()->firstWhere('id', $this->accountId);
    }

    protected function currentQuery(): Builder
    {
        if (! $this->accountId) {
            return MetaAdProposal::query()->whereRaw('1 = 0');
        }

        return MetaAdProposal::query()
            ->where('meta_ad_account_id', $this->accountId)
            ->whereIn('status', [MetaAdProposal::STATUS_DRAFT, MetaAdProposal::STATUS_LAUNCHED]);
    }

    protected function exceptionMessage(Throwable $exception): string
    {
        return $exception instanceof ValidationException
            ? collect($exception->errors())->flatten()->implode(' ')
            : $exception->getMessage();
    }
}
