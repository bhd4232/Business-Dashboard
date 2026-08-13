<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Courier;
use App\Filament\Resources\CourierBookings\CourierBookingResource;
use App\Models\CourierBooking;
use App\Models\CourierProvider;
use App\Models\CourierReturn;
use App\Services\CompanyContext;
use App\Services\CourierReportService;
use App\Services\SteadfastCourierClient;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Single-screen "merchant dashboard" so staff never need to open Steadfast's
 * own website: live balance, recent consignments, delivery/return
 * performance, and recent return requests, with quick links out to the
 * full Providers/Bookings/Returns/Status Logs/Webhook Logs/Payments
 * resources for anything that needs deeper drill-down.
 */
class CourierMerchantDashboard extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $cluster = Courier::class;

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Courier Merchant Dashboard';

    protected string $view = 'filament.pages.courier-merchant-dashboard';

    public ?float $balance = null;

    public ?string $balanceError = null;

    public Collection $performance;

    public Collection $recentReturns;

    public static function canAccess(): bool
    {
        return Auth::user()?->hasPermission('sales.view') ?? false;
    }

    public function mount(): void
    {
        $provider = $this->activeProvider();

        if ($provider) {
            try {
                $response = Cache::remember(
                    "courier-balance:{$provider->getKey()}",
                    now()->addMinutes(5),
                    fn (): array => app(SteadfastCourierClient::class)->balance($provider),
                );

                $this->balance = isset($response['current_balance']) ? (float) $response['current_balance'] : null;

                if ($this->balance === null) {
                    $this->balanceError = $response['message'] ?? 'Steadfast did not return a balance.';
                }
            } catch (Throwable $exception) {
                $this->balanceError = $exception->getMessage();
            }
        }

        $this->performance = app(CourierReportService::class)->providerPerformance()
            ->filter(fn ($row): bool => $row->driver === CourierProvider::DRIVER_STEADFAST);

        $this->recentReturns = CourierReturn::query()
            ->with(['booking.order'])
            ->latest()
            ->limit(5)
            ->get();
    }

    public function activeProvider(): ?CourierProvider
    {
        $companyId = app(CompanyContext::class)->id();

        return CourierProvider::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->where('driver', CourierProvider::DRIVER_STEADFAST)
            ->where('is_active', true)
            ->first();
    }

    public function refreshBalance(): void
    {
        if ($provider = $this->activeProvider()) {
            Cache::forget("courier-balance:{$provider->getKey()}");
        }

        $this->mount();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent Consignments')
            ->query(CourierBooking::query()->whereHas('provider', fn ($query) => $query->where('driver', CourierProvider::DRIVER_STEADFAST)))
            ->modifyQueryUsing(fn ($query) => $query->with(['order.customer', 'provider'])->latest('booked_at'))
            ->columns([
                TextColumn::make('tracking_id')->searchable(),
                TextColumn::make('order.order_number')->label('Invoice')->searchable(),
                TextColumn::make('recipient_name')->searchable(),
                TextColumn::make('cod_amount')->money('BDT'),
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
            ->emptyStateDescription('Steadfast bookings created from Orders will show up here.');
    }
}
