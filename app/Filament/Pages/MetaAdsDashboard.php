<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Ads;
use App\Models\MetaAd;
use App\Models\MetaAdAccount;
use App\Models\MetaAdCampaign;
use App\Models\MetaAdSet;
use App\Services\CompanyContext;
use App\Services\MetaAdsSyncService;
use App\Services\MetaMarketingApiClient;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Throwable;

/**
 * Meta Ads Manager dashboard: pick an account, see real synced totals, and
 * drill Campaigns -> Ad Sets -> Ads within one page via #[Url]-bound ids
 * (same approach as CourierMerchantDashboard's providerFilter and
 * BulkUpdateStock's shortageOnly — chosen because this Filament install
 * doesn't apply ?tableFilters=... from a cold GET, per
 * CourierMerchantDashboard's own documented finding).
 *
 * All three levels share identical column names (name/status/daily_budget/
 * spend/impressions/clicks/ctr/cpc/reach — see the meta_ad_campaigns,
 * meta_ad_sets, meta_ads migrations), so one `columns()` definition works
 * for whichever model `currentQuery()` returns.
 *
 * The numbers shown are "as of last sync", not live — Sync Data pulls fresh
 * Campaign/Ad Set/Ad + Insights data for the selected date_preset via
 * MetaAdsSyncService; there is no per-day history, so switching the date
 * preset only changes what the *next* sync fetches; it does not retroactively
 * recompute already-stored rows.
 *
 * Phase B write-back (Pause/Resume + Daily Budget editing): unlike
 * BulkUpdateStock's "stage then save together" pattern, every edit here is
 * its own real Meta API call, so there's no batching benefit to staging —
 * each write goes to Meta first via MetaMarketingApiClient, and the local
 * row is only updated after Meta confirms success; a failed call leaves
 * both Meta and the local row exactly as they were and shows a danger
 * notification. Only Daily Budget is editable (Lifetime Budget stays
 * read-only) — a campaign/ad set uses one or the other in Meta's model,
 * and Daily Budget is the common case; Ads have no budget of their own, so
 * the column is read-only at that level. Campaign/ad-set/ad *creation*
 * (Phase C) and the AI assistant (Phase D) are still not implemented here.
 */
class MetaAdsDashboard extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $cluster = Ads::class;

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Meta Ads Dashboard';

    protected string $view = 'filament.pages.meta-ads-dashboard';

    public const DATE_PRESETS = [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'last_7d' => 'Last 7 days',
        'last_30d' => 'Last 30 days',
        'this_month' => 'This month',
        'last_month' => 'Last month',
    ];

    #[Url]
    public ?int $accountId = null;

    #[Url]
    public string $datePreset = MetaMarketingApiClient::DEFAULT_DATE_PRESET;

    #[Url]
    public ?int $campaignId = null;

    #[Url]
    public ?int $adSetId = null;

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
                ->afterStateUpdated(function (): void {
                    $this->campaignId = null;
                    $this->adSetId = null;
                    $this->resetTable();
                }),
            Select::make('datePreset')
                ->label('Sync Window')
                ->options(self::DATE_PRESETS)
                ->native(false)
                ->live()
                ->maxWidth(Width::ExtraSmall)
                ->selectablePlaceholder(false)
                ->helperText('Applies the next time you click Sync Data.'),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newCampaign')
                ->label('New Campaign')
                ->icon(Heroicon::OutlinedPlusCircle)
                ->url(fn (): string => CreateMetaAdCampaign::getUrl())
                ->visible(fn (): bool => Auth::user()?->hasPermission('marketing.create') ?? false),
            Action::make('syncData')
                ->label('Sync Data')
                ->icon(Heroicon::OutlinedArrowPath)
                ->visible(fn (): bool => $this->selectedAccount() !== null)
                ->action(function (MetaAdsSyncService $syncService): void {
                    $account = $this->selectedAccount();

                    if (! $account) {
                        return;
                    }

                    $result = $syncService->sync($account, $this->datePreset);
                    $account->refresh();

                    if ($account->last_sync_error) {
                        Notification::make()
                            ->title('Sync failed')
                            ->body($account->last_sync_error)
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Synced')
                        ->body("{$result['campaigns']} campaigns, {$result['ad_sets']} ad sets, {$result['ads']} ads.")
                        ->success()
                        ->send();

                    $this->resetTable();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        // Ads have no budget of their own in Meta's model, so Daily Budget
        // is only ever editable at the Campaign/Ad Set levels.
        $isAdLevel = $this->adSetId !== null;

        $dailyBudgetColumn = $isAdLevel
            ? TextColumn::make('daily_budget')->label('Daily Budget')->moneyWithoutTrailingZeroes('USD')->placeholder('-')->toggleable()
            : TextInputColumn::make('daily_budget')
                ->label('Daily Budget')
                ->type('number')
                ->rules(['nullable', 'numeric', 'min:0'])
                ->getStateUsing(fn (Model $record): ?float => $record->daily_budget !== null ? (float) $record->daily_budget : null)
                ->updateStateUsing(fn (Model $record, mixed $state): ?float => $this->updateBudget($record, $state))
                ->toggleable();

        return $table
            ->query($this->currentQuery())
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->limit(40),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'ACTIVE' => 'success',
                        'PAUSED' => 'gray',
                        default => 'warning',
                    })
                    ->placeholder('-'),
                $dailyBudgetColumn,
                TextColumn::make('lifetime_budget')->label('Lifetime Budget')->moneyWithoutTrailingZeroes('USD')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('spend')->moneyWithoutTrailingZeroes('USD')->sortable(),
                TextColumn::make('impressions')->numeric()->sortable()->toggleable(),
                TextColumn::make('clicks')->numeric()->sortable(),
                TextColumn::make('ctr')->label('CTR')->suffix('%')->numeric(2)->sortable(),
                TextColumn::make('cpc')->label('CPC')->moneyWithoutTrailingZeroes('USD', decimalPlaces: 4)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('insights_synced_at')->label('Synced')->since()->placeholder('never')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('toggleStatus')
                    ->label(fn (Model $record): string => $record->status === MetaMarketingApiClient::STATUS_ACTIVE ? 'Pause' : 'Resume')
                    ->icon(fn (Model $record): Heroicon => $record->status === MetaMarketingApiClient::STATUS_ACTIVE ? Heroicon::OutlinedPause : Heroicon::OutlinedPlay)
                    ->color(fn (Model $record): string => $record->status === MetaMarketingApiClient::STATUS_ACTIVE ? 'warning' : 'success')
                    ->visible(fn (Model $record): bool => in_array($record->status, [MetaMarketingApiClient::STATUS_ACTIVE, MetaMarketingApiClient::STATUS_PAUSED], true))
                    ->requiresConfirmation()
                    ->action(fn (Model $record, MetaMarketingApiClient $client) => $this->toggleStatus($record, $client)),
                Action::make('drillDown')
                    ->label(fn (): string => $this->campaignId === null ? 'View Ad Sets' : 'View Ads')
                    ->icon(Heroicon::OutlinedChevronRight)
                    ->visible(fn (): bool => $this->adSetId === null)
                    ->action(fn ($record) => $this->drillInto((int) $record->getKey())),
            ])
            ->searchPlaceholder('Search by name...')
            ->defaultSort('spend', 'desc')
            ->emptyStateHeading($this->selectedAccount() ? 'No data synced yet' : 'No ad account selected')
            ->emptyStateDescription($this->selectedAccount()
                ? 'Click Sync Data to pull campaigns from Meta.'
                : 'Add and select a Meta Ads account to see data here.')
            ->emptyStateIcon(Heroicon::OutlinedMegaphone);
    }

    /**
     * Writes the new Daily Budget to Meta first; the local row is only
     * updated (and the input reflects the new value) once Meta confirms.
     * Blank input is a no-op — never sent to Meta as an accidental clear.
     */
    protected function updateBudget(Model $record, mixed $state): ?float
    {
        if ($state === null || $state === '') {
            return $record->daily_budget !== null ? (float) $record->daily_budget : null;
        }

        $account = $this->selectedAccount();
        $newBudget = (float) $state;

        if (! $account) {
            return $record->daily_budget !== null ? (float) $record->daily_budget : null;
        }

        try {
            app(MetaMarketingApiClient::class)->updateBudget($account, $record->meta_id, ['daily_budget' => $newBudget]);
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Budget update failed')
                ->body($this->exceptionMessage($exception))
                ->danger()
                ->send();

            return $record->daily_budget !== null ? (float) $record->daily_budget : null;
        }

        $record->forceFill(['daily_budget' => $newBudget])->save();

        Notification::make()
            ->title('Budget updated')
            ->success()
            ->send();

        return $newBudget;
    }

    protected function toggleStatus(Model $record, MetaMarketingApiClient $client): void
    {
        $account = $this->selectedAccount();

        if (! $account) {
            return;
        }

        $newStatus = $record->status === MetaMarketingApiClient::STATUS_ACTIVE
            ? MetaMarketingApiClient::STATUS_PAUSED
            : MetaMarketingApiClient::STATUS_ACTIVE;

        try {
            $client->updateStatus($account, $record->meta_id, $newStatus);
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Status update failed')
                ->body($this->exceptionMessage($exception))
                ->danger()
                ->send();

            return;
        }

        $record->forceFill(['status' => $newStatus, 'effective_status' => $newStatus])->save();

        Notification::make()
            ->title($newStatus === MetaMarketingApiClient::STATUS_ACTIVE ? 'Resumed' : 'Paused')
            ->body($record->name)
            ->success()
            ->send();
    }

    protected function exceptionMessage(Throwable $exception): string
    {
        return $exception instanceof ValidationException
            ? collect($exception->errors())->flatten()->implode(' ')
            : $exception->getMessage();
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

    public function selectedCampaign(): ?MetaAdCampaign
    {
        return $this->campaignId ? MetaAdCampaign::query()->find($this->campaignId) : null;
    }

    public function selectedAdSet(): ?MetaAdSet
    {
        return $this->adSetId ? MetaAdSet::query()->find($this->adSetId) : null;
    }

    public function drillInto(int $id): void
    {
        if ($this->adSetId !== null) {
            return;
        }

        if ($this->campaignId === null) {
            $this->campaignId = $id;
        } else {
            $this->adSetId = $id;
        }

        $this->resetTable();
    }

    public function drillBackToCampaigns(): void
    {
        $this->campaignId = null;
        $this->adSetId = null;
        $this->resetTable();
    }

    public function drillBackToAdSets(): void
    {
        $this->adSetId = null;
        $this->resetTable();
    }

    /**
     * Account-wide totals (independent of the current drill level) —
     * summed straight from synced Campaign rows so an Ad Set/Ad drill-down
     * never double-counts. CTR/CPC are recomputed as weighted aggregates
     * (never a naive average of each row's own ratio).
     *
     * @return array<int, array{label: string, value: int|float, icon: string, isCurrency?: bool}>
     */
    public function stats(): array
    {
        $account = $this->selectedAccount();

        $totals = $account
            ? MetaAdCampaign::query()->where('meta_ad_account_id', $account->getKey())
                ->selectRaw('COALESCE(SUM(spend), 0) as spend, COALESCE(SUM(impressions), 0) as impressions, COALESCE(SUM(clicks), 0) as clicks')
                ->first()
            : null;

        $spend = (float) ($totals->spend ?? 0);
        $impressions = (int) ($totals->impressions ?? 0);
        $clicks = (int) ($totals->clicks ?? 0);
        $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0.0;
        $cpc = $clicks > 0 ? round($spend / $clicks, 4) : 0.0;

        return [
            ['label' => 'Spend', 'value' => $spend, 'icon' => 'heroicon-o-banknotes', 'isCurrency' => true],
            ['label' => 'Impressions', 'value' => $impressions, 'icon' => 'heroicon-o-eye'],
            ['label' => 'Clicks', 'value' => $clicks, 'icon' => 'heroicon-o-cursor-arrow-rays'],
            ['label' => 'CTR', 'value' => $ctr, 'icon' => 'heroicon-o-chart-bar', 'isPercent' => true],
            ['label' => 'CPC', 'value' => $cpc, 'icon' => 'heroicon-o-currency-dollar', 'isCurrency' => true],
        ];
    }

    protected function currentQuery(): Builder
    {
        if ($this->adSetId) {
            return MetaAd::query()->where('meta_ad_set_id', $this->adSetId);
        }

        if ($this->campaignId) {
            return MetaAdSet::query()->where('meta_ad_campaign_id', $this->campaignId);
        }

        if ($this->accountId) {
            return MetaAdCampaign::query()->where('meta_ad_account_id', $this->accountId);
        }

        return MetaAdCampaign::query()->whereRaw('1 = 0');
    }
}
