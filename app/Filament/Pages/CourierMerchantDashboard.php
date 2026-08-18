<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Courier;
use App\Filament\Resources\CourierBookings\CourierBookingResource;
use App\Models\CourierBooking;
use App\Models\CourierProvider;
use App\Models\CourierReturn;
use App\Services\CompanyContext;
use App\Services\CourierManager;
use App\Services\CourierReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;
use Throwable;

/**
 * Single-screen "merchant dashboard" so staff never need to open any
 * courier's own website: live balance, recent consignments, delivery/return
 * performance, and recent return requests, with quick links out to the
 * full Providers/Bookings/Returns/Status Logs/Webhook Logs/Payments
 * resources for anything that needs deeper drill-down.
 *
 * Multi-courier aware: every section defaults to "All Couriers" (aggregated
 * across every provider the company has configured) and can be scoped to
 * one specific provider via the Filament `Select` bound to `providerFilter`.
 * The property remains a string (`'all'` or the provider id as a string)
 * so the explicit "All Couriers" sentinel and URL query state stay stable.
 * `selectedProviderId()` is the single place that turns it into a real
 * `?int` for every query below. Every Recent
 * Consignments row and Booking Status Summary entry also shows which
 * courier it belongs to, so "All Couriers" never hides that.
 *
 * Every Booking Status Summary card is clickable and opens a modal listing
 * that status's own bookings (bookingStatusAction()). An earlier version
 * tried deep-linking to CourierBookingResource with ?tableFilters=...; that
 * was abandoned after confirming live in a browser that this Filament
 * install doesn't apply table filters from a cold GET request. Since
 * Filament\Pages\Page already implements HasActions/InteractsWithActions
 * (via BasePage), a modal action defined directly on this page needed no
 * extra scaffolding.
 */
class CourierMerchantDashboard extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?string $cluster = Courier::class;

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Courier Merchant Dashboard';

    protected string $view = 'filament.pages.courier-merchant-dashboard';

    #[Url(as: 'courier')]
    public string $providerFilter = 'all';

    public static function canAccess(): bool
    {
        return Auth::user()?->hasPermission('sales.view') ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('providerFilter')
                    ->label('Courier')
                    ->options(fn (): array => $this->courierProviderOptions())
                    ->native(false)
                    ->live()
                    ->maxWidth(Width::ExtraSmall)
                    ->visible(fn (): bool => $this->hasMultipleActiveProviders())
                    ->selectablePlaceholder(false),
            ]);
    }

    public function hasMultipleActiveProviders(): bool
    {
        return $this->providers()->where('is_active', true)->count() > 1;
    }

    /** @return array<string|int, string> */
    protected function courierProviderOptions(): array
    {
        return ['all' => 'All Couriers'] + $this->providers()
            ->mapWithKeys(fn (CourierProvider $provider): array => [
                $provider->getKey() => $provider->name.' ('.(CourierProvider::DRIVERS[$provider->driver] ?? $provider->driver).')',
            ])
            ->all();
    }

    public function selectedProviderId(): ?int
    {
        return ($this->providerFilter !== 'all' && is_numeric($this->providerFilter))
            ? (int) $this->providerFilter
            : null;
    }

    /**
     * Every courier provider the current company has configured — powers
     * the "Courier" selector (All Couriers + one option per provider) plus
     * every provider lookup on this page.
     */
    public function providers(): Collection
    {
        $companyId = app(CompanyContext::class)->id();

        return CourierProvider::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->orderBy('name')
            ->get();
    }

    /**
     * Computed fresh on every render (not cached in a public property) —
     * confirmed live that storing this in a public Collection property is
     * unsafe: `providerPerformance()`'s rows are raw stdClass results from
     * a manual `->select()`, which Livewire's wire:snapshot hydration
     * doesn't reliably round-trip on a subsequent AJAX request (e.g.
     * closing the Booking Status Summary modal silently reset it to an
     * empty collection even though the underlying data hadn't changed — a
     * fresh full page load then rendered it correctly again). Recomputing
     * per render, the same way `statusCounts()`/`providers()` already do,
     * sidesteps the whole hydration question.
     *
     * @return Collection<int, array{provider: CourierProvider, amount: ?float, error: ?string, supported: bool}>
     */
    public function balances(): Collection
    {
        $selectedId = $this->selectedProviderId();

        $providers = $selectedId
            ? $this->providers()->where('id', $selectedId)
            : $this->providers()->where('is_active', true)->whereIn('driver', CourierProvider::API_DRIVERS);

        return $providers
            ->map(fn (CourierProvider $provider): array => $this->fetchBalance($provider))
            // "not supported by this courier" rows only get hidden in the
            // All Couriers view — a courier the owner explicitly selected
            // should still say so rather than showing nothing at all.
            ->when($selectedId === null, fn (Collection $rows) => $rows->filter(fn (array $row): bool => $row['supported']))
            ->values();
    }

    /**
     * @return array{provider: CourierProvider, amount: ?float, error: ?string, supported: bool}
     */
    protected function fetchBalance(CourierProvider $provider): array
    {
        try {
            $response = Cache::remember(
                "courier-balance:{$provider->getKey()}",
                now()->addMinutes(5),
                fn (): ?array => app(CourierManager::class)->adapter($provider)->balance($provider),
            );
        } catch (Throwable $exception) {
            return ['provider' => $provider, 'amount' => null, 'error' => $exception->getMessage(), 'supported' => true];
        }

        // AbstractCourierAdapter::balance() returns null outright for
        // couriers with no balance endpoint wired up (currently everything
        // except Steadfast) — distinct from a real API error below.
        if ($response === null) {
            return ['provider' => $provider, 'amount' => null, 'error' => null, 'supported' => false];
        }

        $amount = isset($response['current_balance']) ? (float) $response['current_balance'] : null;

        return [
            'provider' => $provider,
            'amount' => $amount,
            'error' => $amount === null ? ($response['message'] ?? 'Balance unavailable.') : null,
            'supported' => true,
        ];
    }

    public function refreshBalance(int $providerId): void
    {
        // No stored property to update — balances() is recomputed on the
        // re-render Livewire triggers after any public method call, so
        // busting the cache here is the only work needed.
        Cache::forget("courier-balance:{$providerId}");
    }

    public function performance(): Collection
    {
        $selectedId = $this->selectedProviderId();

        return app(CourierReportService::class)->providerPerformance()
            ->when($selectedId, fn (Collection $rows) => $rows->filter(fn ($row): bool => (int) $row->id === $selectedId));
    }

    public function recentReturns(): Collection
    {
        return CourierReturn::query()
            ->when($this->selectedProviderId(), fn ($query, int $providerId) => $query->where('courier_provider_id', $providerId))
            ->with(['booking.order', 'provider'])
            ->latest()
            ->limit(5)
            ->get();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent Consignments')
            ->query(CourierBooking::query()->when($this->selectedProviderId(), fn ($query, int $providerId) => $query->where('courier_provider_id', $providerId)))
            ->modifyQueryUsing(fn ($query) => $query->with(['order.customer', 'provider'])->latest('booked_at'))
            ->columns([
                TextColumn::make('tracking_id')->searchable(),
                TextColumn::make('provider.name')->label('Courier')->badge()->color('gray'),
                TextColumn::make('order.order_number')->label('Invoice')->searchable(),
                TextColumn::make('recipient_name')->searchable(),
                TextColumn::make('cod_amount')->moneyWithoutTrailingZeroes('BDT'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => CourierBooking::STATUSES[$state ?? ''] ?? str($state)->headline()->toString())
                    ->color(fn (?string $state): string => match ($state) {
                        CourierBooking::STATUS_DELIVERED => 'success',
                        CourierBooking::STATUS_RETURNED, CourierBooking::STATUS_CANCELLED, CourierBooking::STATUS_FAILED => 'danger',
                        CourierBooking::STATUS_BOOKED, CourierBooking::STATUS_PICKED_UP, CourierBooking::STATUS_IN_TRANSIT => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('booked_at')->dateTime()->sortable(),
            ])
            ->recordUrl(fn (CourierBooking $record): string => CourierBookingResource::getUrl('view', ['record' => $record]))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->headerActions([
                Action::make('viewAllBookings')
                    ->label('View all bookings')
                    ->icon(Heroicon::ArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(CourierBookingResource::getUrl()),
            ])
            ->emptyStateHeading('No consignments yet')
            ->emptyStateDescription('Courier bookings created from Orders will show up here.');
    }

    /**
     * @return array<string, array{label: string, icon: Heroicon, color: string, statuses: array<int, string>|null, description: string}>
     */
    public function statusCardDefinitions(): array
    {
        return [
            'all' => [
                'label' => 'All Bookings',
                'icon' => Heroicon::OutlinedArchiveBox,
                'color' => 'primary',
                'statuses' => null,
                'description' => 'Every consignment booked from ZamZam',
            ],
            'in_progress' => [
                'label' => 'In Progress',
                'icon' => Heroicon::OutlinedTruck,
                'color' => 'info',
                'statuses' => CourierBooking::ACTIVE_STATUSES,
                'description' => 'Pending, booked, picked up, or in transit',
            ],
            'delivered' => [
                'label' => 'Delivered',
                'icon' => Heroicon::OutlinedCheckCircle,
                'color' => 'success',
                'statuses' => [CourierBooking::STATUS_DELIVERED],
                'description' => 'Successfully delivered',
            ],
            'partial_delivered' => [
                'label' => 'Partial Delivered',
                'icon' => Heroicon::OutlinedExclamationCircle,
                'color' => 'warning',
                'statuses' => [CourierBooking::STATUS_PARTIAL_DELIVERED],
                'description' => 'Delivered with a reduced amount',
            ],
            'returned' => [
                'label' => 'Returned',
                'icon' => Heroicon::OutlinedArrowUturnLeft,
                'color' => 'gray',
                'statuses' => [CourierBooking::STATUS_RETURNED],
                'description' => 'Sent back to ZamZam',
            ],
            'cancelled' => [
                'label' => 'Cancelled',
                'icon' => Heroicon::OutlinedXCircle,
                'color' => 'danger',
                'statuses' => [CourierBooking::STATUS_CANCELLED],
                'description' => 'Cancelled before or during delivery',
            ],
            'failed' => [
                'label' => 'Failed',
                'icon' => Heroicon::OutlinedNoSymbol,
                'color' => 'danger',
                'statuses' => [CourierBooking::STATUS_FAILED],
                'description' => 'Delivery attempt failed',
            ],
        ];
    }

    /**
     * Semi-transparent, Filament-palette-based card background tint —
     * [resting classes, hover classes] — shared by this page's hand-built
     * Booking Status Summary cards and `CourierQuickLinksWidget`'s native
     * Manage cards, so every card gets its own distinct color instead of
     * a uniform white/gray surface. Only Filament's default-registered
     * color names are safe to use here (`primary`, `info`, `success`,
     * `warning`, `danger`, `gray` — see `Filament\Support\Colors\
     * ColorManager::$defaultColors`); anything else silently renders with
     * no matching CSS variable. The `!` (Tailwind important) prefix is
     * required because Filament's own `.fi-wi-stats-overview-stat` class
     * already bakes in `bg-white`/`dark:bg-gray-900` at the same utility
     * specificity, so an unmarked override isn't guaranteed to win.
     *
     * @return array{0: string, 1: string}
     */
    public static function statCardTint(string $color): array
    {
        return match ($color) {
            'primary' => ['!bg-primary-50 dark:!bg-primary-400/10', 'hover:!bg-primary-100 dark:hover:!bg-primary-400/20'],
            'info' => ['!bg-info-50 dark:!bg-info-400/10', 'hover:!bg-info-100 dark:hover:!bg-info-400/20'],
            'success' => ['!bg-success-50 dark:!bg-success-400/10', 'hover:!bg-success-100 dark:hover:!bg-success-400/20'],
            'warning' => ['!bg-warning-50 dark:!bg-warning-400/10', 'hover:!bg-warning-100 dark:hover:!bg-warning-400/20'],
            'danger' => ['!bg-danger-50 dark:!bg-danger-400/10', 'hover:!bg-danger-100 dark:hover:!bg-danger-400/20'],
            default => ['!bg-gray-50 dark:!bg-white/5', 'hover:!bg-gray-100 dark:hover:!bg-white/10'],
        };
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $counts = CourierBooking::query()
            ->when($this->selectedProviderId(), fn ($query, int $providerId) => $query->where('courier_provider_id', $providerId))
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $result = ['all' => (int) $counts->sum()];

        foreach ($this->statusCardDefinitions() as $key => $definition) {
            if ($key === 'all') {
                continue;
            }

            $result[$key] = (int) collect($definition['statuses'])->sum(fn (string $status) => $counts->get($status, 0));
        }

        return $result;
    }

    public function bookingStatusAction(): Action
    {
        return Action::make('bookingStatus')
            ->modalHeading(fn (array $arguments): string => $this->statusCardDefinitions()[$arguments['key'] ?? 'all']['label'] ?? 'Bookings')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->schema(fn (array $arguments): array => $this->bookingStatusEntries($arguments['key'] ?? 'all'));
    }

    /**
     * @return array<int, Component>
     */
    protected function bookingStatusEntries(string $key): array
    {
        $statuses = $this->statusCardDefinitions()[$key]['statuses'] ?? null;

        $query = CourierBooking::query()
            ->when($this->selectedProviderId(), fn ($q, int $providerId) => $q->where('courier_provider_id', $providerId))
            ->when($statuses !== null, fn ($q) => $q->whereIn('status', $statuses))
            ->with(['order', 'provider']);

        $total = $query->count();
        $bookings = $query->latest('booked_at')->limit(50)->get();

        if ($bookings->isEmpty()) {
            return [TextEntry::make('empty')->label('')->state('No bookings in this status yet.')];
        }

        $entries = [
            RepeatableEntry::make('bookings')
                ->hiddenLabel()
                ->state($bookings->map(fn (CourierBooking $booking): array => [
                    'tracking_id' => $booking->tracking_id,
                    'courier' => $booking->provider?->name,
                    'invoice' => $booking->order?->order_number,
                    'recipient_name' => $booking->recipient_name,
                    'recipient_phone' => $booking->recipient_phone,
                    'cod_amount' => $booking->cod_amount,
                    'status' => $booking->status,
                    'booked_at' => $booking->booked_at,
                ])->all())
                ->contained(true)
                ->schema([
                    TextEntry::make('tracking_id')->label('Tracking ID')->badge()->color('primary')->placeholder('—'),
                    TextEntry::make('courier')->label('Courier')->badge()->color('gray')->placeholder('—'),
                    TextEntry::make('invoice')->label('Invoice')->placeholder('—'),
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => CourierBooking::STATUSES[$state ?? ''] ?? str($state)->headline()->toString())
                        ->color(fn (?string $state): string => match ($state) {
                            CourierBooking::STATUS_DELIVERED => 'success',
                            CourierBooking::STATUS_RETURNED, CourierBooking::STATUS_CANCELLED, CourierBooking::STATUS_FAILED => 'danger',
                            CourierBooking::STATUS_BOOKED, CourierBooking::STATUS_PICKED_UP, CourierBooking::STATUS_IN_TRANSIT => 'warning',
                            default => 'gray',
                        }),
                    TextEntry::make('cod_amount')->label('COD Amount')->moneyWithoutTrailingZeroes('BDT')->placeholder('—'),
                    TextEntry::make('recipient_name')->label('Recipient')->placeholder('—'),
                    TextEntry::make('recipient_phone')->label('Phone')->placeholder('—'),
                    TextEntry::make('booked_at')->label('Booked At')->dateTime()->placeholder('—'),
                ])
                ->columns(3),
        ];

        if ($total > $bookings->count()) {
            array_unshift($entries, TextEntry::make('note')
                ->label('')
                ->state("Showing the latest {$bookings->count()} of {$total} — open Bookings for the full list.")
                ->color('gray'));
        }

        return $entries;
    }
}
