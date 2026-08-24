<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\Actions\ChangeOrderWorkflowAction;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\CourierBooking;
use App\Models\CourierProvider;
use App\Models\CustomerRiskProfile;
use App\Models\Order;
use App\Services\AuditLogService;
use App\Services\CompanyContext;
use App\Services\CourierService;
use App\Services\CustomerRiskService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Nuport's per-order "Logs" tab narrates a "viewed by X" line
        // every time staff open the order; AuditLogService::record()
        // already accepts an arbitrary action string, so this needs no
        // schema change. Deduped per user per order for 5 minutes so a
        // Livewire refresh or accidental double-open doesn't spam the
        // activity feed built from these rows (OrderActivityFeedService).
        $cacheKey = 'order-viewed-log:'.$this->record->getKey().':'.(Auth::id() ?? 'guest');

        if (! Cache::has($cacheKey)) {
            Cache::put($cacheKey, true, now()->addMinutes(5));
            app(AuditLogService::class)->record('viewed', $this->record);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previousOrder')
                ->label('Previous')
                ->icon('heroicon-o-chevron-left')
                ->iconButton()
                ->color('gray')
                ->visible(fn (): bool => $this->previousOrderId() !== null)
                ->url(fn (): string => OrderResource::getUrl('view', ['record' => $this->previousOrderId()])),
            Action::make('nextOrder')
                ->label('Next')
                ->icon('heroicon-o-chevron-right')
                ->iconButton()
                ->color('gray')
                ->visible(fn (): bool => $this->nextOrderId() !== null)
                ->url(fn (): string => OrderResource::getUrl('view', ['record' => $this->nextOrderId()])),
            ChangeOrderWorkflowAction::make(),
            Action::make('bookCourier')
                ->label('Book courier')
                ->icon('heroicon-o-truck')
                ->visible(fn (): bool => $this->canBookCourier())
                ->schema($this->courierBookingForm())
                ->action(function (array $data): void {
                    app(CourierService::class)->createManualBooking($this->record, $data);
                    $this->record->refresh();
                }),
            Action::make('bookSteadfast')
                ->label('Book Steadfast')
                ->icon('heroicon-o-truck')
                ->color('warning')
                ->visible(fn (): bool => $this->canBookCourier() && CourierProvider::query()
                    ->where('driver', CourierProvider::DRIVER_STEADFAST)
                    ->where('is_active', true)
                    ->exists())
                ->schema($this->steadfastBookingForm())
                ->action(function (array $data): void {
                    $provider = CourierProvider::query()->findOrFail($data['courier_provider_id']);
                    app(CourierService::class)->createSteadfastBooking($this->record, $provider, $data);
                    $this->record->refresh();
                }),
            Action::make('markDelivered')
                ->label('Mark delivered')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => filled($this->record->latestCourierBooking) && ! in_array($this->record->delivery_status, [
                    CourierBooking::STATUS_DELIVERED,
                    CourierBooking::STATUS_RETURNED,
                    CourierBooking::STATUS_CANCELLED,
                ], true))
                ->requiresConfirmation()
                ->action(function (): void {
                    app(CourierService::class)->updateStatus($this->record->latestCourierBooking, CourierBooking::STATUS_DELIVERED, 'Marked delivered from order detail.');
                    $this->record->refresh();
                }),
            Action::make('markReturned')
                ->label('Mark returned')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn (): bool => filled($this->record->latestCourierBooking) && ! in_array($this->record->delivery_status, [
                    CourierBooking::STATUS_DELIVERED,
                    CourierBooking::STATUS_RETURNED,
                    CourierBooking::STATUS_CANCELLED,
                ], true))
                ->requiresConfirmation()
                ->action(function (): void {
                    app(CourierService::class)->updateStatus($this->record->latestCourierBooking, CourierBooking::STATUS_RETURNED, 'Marked returned from order detail.');
                    $this->record->refresh();
                }),
            Action::make('print')
                ->url(fn () => route('orders.print', ['order' => $this->record, 'print' => 1]))
                ->openUrlInNewTab(),
            EditAction::make(),
        ];
    }

    protected function courierBookingForm(bool $includeProvider = true): array
    {
        $this->record->loadMissing('customer');

        $schema = [
            Placeholder::make('risk_notice')
                ->label('Customer Risk Check')
                ->content(function (): string {
                    $profile = app(CustomerRiskService::class)->evaluateCustomer($this->record->customer, $this->record);

                    return "{$profile->risk_score}/100 — ".(CustomerRiskProfile::LEVELS[$profile->risk_level] ?? $profile->risk_level);
                }),
            TextInput::make('tracking_id')
                ->label('Tracking ID')
                ->helperText('Leave blank to auto-generate a manual tracking ID.')
                ->maxLength(255),
            TextInput::make('recipient_name')
                ->required()
                ->default($this->record->customer_name ?: $this->record->customer?->name)
                ->maxLength(255),
            TextInput::make('recipient_phone')
                ->tel()
                ->default($this->record->customer?->phone)
                ->maxLength(255),
            Textarea::make('recipient_address')
                ->default($this->record->customer?->address)
                ->rows(3),
            TextInput::make('cod_amount')
                ->numeric()
                ->prefix('৳')
                ->default((float) $this->record->due_amount)
                ->minValue(0),
            Textarea::make('note')
                ->rows(2),
        ];

        if (! $includeProvider) {
            return $schema;
        }

        return [
            Select::make('courier_provider_id')
                ->label('Custom Provider')
                ->options(fn (): array => CourierProvider::query()
                    ->where('driver', CourierProvider::DRIVER_MANUAL)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->placeholder('Default Custom')
                ->native(false),
            ...$schema,
        ];
    }

    protected function steadfastBookingForm(): array
    {
        return [
            Select::make('courier_provider_id')
                ->label('Steadfast Provider')
                ->options(fn (): array => CourierProvider::query()
                    ->where('driver', CourierProvider::DRIVER_STEADFAST)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->required()
                ->native(false),
            ...$this->courierBookingForm(includeProvider: false),
            TextInput::make('alternative_phone')
                ->tel()
                ->maxLength(255),
            TextInput::make('recipient_email')
                ->email()
                ->default($this->record->customer?->email)
                ->maxLength(255),
            TextInput::make('item_description')
                ->maxLength(255),
            TextInput::make('total_lot')
                ->numeric()
                ->minValue(1),
            Select::make('delivery_type')
                ->options([
                    0 => 'Home Delivery',
                    1 => 'Point Delivery / Hub Pickup',
                ])
                ->default(0)
                ->native(false),
        ];
    }

    protected function canBookCourier(): bool
    {
        return $this->record->delivery_status === CourierBooking::STATUS_NOT_BOOKED
            && app(CompanyContext::class)->hasCompany()
            && (int) app(CompanyContext::class)->id() === (int) $this->record->company_id;
    }

    /**
     * Nuport's `< >` arrows next to the order number, so staff can page
     * through orders without going back to the list. Id-based rather than
     * tied to whatever sort/filter the list table happened to have —
     * CompanyScope (BelongsToCompany) already keeps this scoped to the
     * current company like every other Order query.
     */
    protected function previousOrderId(): ?int
    {
        return Order::query()
            ->where('id', '<', $this->record->getKey())
            ->orderByDesc('id')
            ->value('id');
    }

    protected function nextOrderId(): ?int
    {
        return Order::query()
            ->where('id', '>', $this->record->getKey())
            ->orderBy('id')
            ->value('id');
    }
}
