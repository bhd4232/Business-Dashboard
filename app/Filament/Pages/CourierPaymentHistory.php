<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Courier;
use App\Models\CourierProvider;
use App\Services\CompanyContext;
use App\Services\SteadfastCourierClient;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Read-only Steadfast payment/settlement history, fetched live from the
 * official API and cached briefly — this data changes rarely, so there is
 * no local table for it (unlike bookings/returns, which are recorded as
 * they happen). The exact response shape isn't confirmed against Steadfast's
 * official docs yet (see the note on SteadfastCourierClient::payments()),
 * so columns are derived from whatever keys the response actually returns
 * rather than assuming a fixed schema.
 */
class CourierPaymentHistory extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $cluster = Courier::class;

    protected static ?string $navigationLabel = 'Payments';

    protected static ?int $navigationSort = 5;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Payment / Settlement History';

    protected string $view = 'filament.pages.courier-payment-history';

    public ?string $fetchError = null;

    public static function canAccess(): bool
    {
        return Auth::user()?->hasPermission('sales.view') ?? false;
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

    protected function records(): array
    {
        $provider = $this->activeProvider();

        if (! $provider) {
            return [];
        }

        try {
            return Cache::remember(
                "courier-payments:{$provider->getKey()}",
                now()->addMinutes(10),
                function () use ($provider): array {
                    $response = app(SteadfastCourierClient::class)->payments($provider);

                    $rows = data_get($response, 'data') ?? data_get($response, 'payments') ?? $response;

                    return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
                },
            );
        } catch (Throwable $exception) {
            $this->fetchError = $exception->getMessage();

            return [];
        }
    }

    public function table(Table $table): Table
    {
        $records = $this->records();

        $columns = $records === []
            ? [TextColumn::make('note')->label('')]
            : collect(array_keys($records[0]))
                ->map(fn (string $key): TextColumn => TextColumn::make($key)
                    ->label(str($key)->headline())
                    ->placeholder('-')
                    ->wrap())
                ->values()
                ->all();

        return $table
            ->records(fn (): array => collect($records)
                ->mapWithKeys(fn (array $row, int $index): array => ["payment-{$index}" => $row])
                ->all())
            ->columns($columns)
            ->headerActions([
                Action::make('refresh')
                    ->label('Refresh')
                    ->icon(Heroicon::ArrowPath)
                    ->action(function (): void {
                        if ($provider = $this->activeProvider()) {
                            Cache::forget("courier-payments:{$provider->getKey()}");
                        }
                    }),
            ])
            ->paginated(false)
            ->emptyStateHeading($this->activeProvider() ? 'No payment history found' : 'No active Steadfast provider')
            ->emptyStateDescription($this->fetchError
                ?? ($this->activeProvider()
                    ? 'Steadfast has not returned any settlement records yet.'
                    : 'Configure and activate a Steadfast courier provider with API credentials first.'))
            ->emptyStateIcon(Heroicon::OutlinedBanknotes);
    }
}
