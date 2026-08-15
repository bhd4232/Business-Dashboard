<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Courier;
use App\Models\CourierProvider;
use App\Services\CompanyContext;
use App\Services\SteadfastCourierClient;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
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
 * they happen). Columns are still derived from whatever keys the response
 * actually returns rather than a hardcoded schema, but the real shape is now
 * confirmed live (2026-08-14, real merchant account): `/payments` returns a
 * flat list with `payment_id`, `amount`, `method`, `due_bills`, `paid_bills`,
 * `charges`, `total`, `status_label`, `created_at`, `ready_at`, `paid_at`;
 * `/payments/{payment_id}` wraps the same fields plus a `consignments[]`
 * array under a top-level `payment` key (sibling to `status`/`message`).
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
            ->recordActions([$this->viewPaymentDetailsAction()])
            ->paginated(false)
            ->emptyStateHeading($this->activeProvider() ? 'No payment history found' : 'No active Steadfast provider')
            ->emptyStateDescription($this->fetchError
                ?? ($this->activeProvider()
                    ? 'Steadfast has not returned any settlement records yet.'
                    : 'Configure and activate a Steadfast courier provider with API credentials first.'))
            ->emptyStateIcon(Heroicon::OutlinedBanknotes);
    }

    /**
     * Mirrors the Steadfast merchant app's "Payment Details" screen — which
     * consignments were cleared under a given settlement. Confirmed live
     * (2026-08-14) that the list response identifies each row by
     * `payment_id`; a few other candidates are kept as a fallback in case a
     * different Steadfast account ever returns a different key, and the
     * action simply stays hidden on a row where none of them resolve.
     */
    protected function viewPaymentDetailsAction(): Action
    {
        return Action::make('viewPaymentDetails')
            ->label('View')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->visible(fn (array $record): bool => $this->extractPaymentId($record) !== null)
            ->modalHeading('Payment Details')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->schema(fn (array $record): array => $this->paymentDetailEntries($record));
    }

    protected function extractPaymentId(array $record): int|string|null
    {
        foreach (['payment_id', 'id', 'invoice_id', 'invoice', 'reference_id', 'sf_id'] as $key) {
            if (filled($record[$key] ?? null)) {
                return $record[$key];
            }
        }

        return null;
    }

    /**
     * Known Steadfast settlement fields (confirmed live 2026-08-14) and how
     * each should render — money for amounts, dates for timestamps, a badge
     * for the status. Anything Steadfast returns beyond this set is still
     * shown (in a collapsed "Additional Details" section below) rather than
     * silently dropped, in case the response shape changes later.
     */
    protected const SUMMARY_FIELD_TYPES = [
        'payment_id' => 'text',
        'status_label' => 'badge',
        'method' => 'badge',
        'total' => 'money',
        'amount' => 'money',
        'charges' => 'money',
        'due_bills' => 'money',
        'paid_bills' => 'money',
    ];

    protected const TIMELINE_FIELDS = ['created_at', 'ready_at', 'paid_at'];

    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    protected function paymentDetailEntries(array $record): array
    {
        $provider = $this->activeProvider();
        $id = $this->extractPaymentId($record);

        if (! $provider || $id === null) {
            return [TextEntry::make('error')->label('')->state('Unable to determine this payment\'s reference.')];
        }

        try {
            $detail = Cache::remember(
                "courier-payment-detail:{$provider->getKey()}:{$id}",
                now()->addMinutes(10),
                fn (): array => app(SteadfastCourierClient::class)->payment($provider, $id),
            );
        } catch (Throwable $exception) {
            return [TextEntry::make('error')->label('')->state($exception->getMessage())];
        }

        // Confirmed live 2026-08-14: the real response wraps the settlement
        // under a top-level "payment" key (sibling to status/alert_class/
        // message), not "data" — prefer it, falling back for safety.
        $body = data_get($detail, 'payment') ?? data_get($detail, 'data') ?? $detail;

        if (! is_array($body) || $body === []) {
            return [TextEntry::make('empty')->label('')->state('Steadfast returned no details for this payment.')];
        }

        $sections = [];

        $summaryFields = collect(self::SUMMARY_FIELD_TYPES)->only(array_keys($body));

        if ($summaryFields->isNotEmpty()) {
            $sections[] = Section::make('Settlement Summary')
                ->columns(4)
                ->schema($summaryFields
                    ->map(fn (string $type, string $key): TextEntry => $this->summaryEntry($key, $type, $body[$key]))
                    ->values()
                    ->all());
        }

        $timelineFields = collect(self::TIMELINE_FIELDS)->filter(fn (string $key): bool => array_key_exists($key, $body));

        if ($timelineFields->isNotEmpty()) {
            $sections[] = Section::make('Timeline')
                ->columns(3)
                ->schema($timelineFields
                    ->map(fn (string $key): TextEntry => TextEntry::make($key)
                        ->label(str($key)->headline())
                        ->state($body[$key])
                        ->dateTime()
                        ->placeholder('—'))
                    ->values()
                    ->all());
        }

        $consignments = $body['consignments'] ?? null;

        if (is_array($consignments) && $consignments !== []) {
            $sections[] = Section::make('Consignments')
                ->description(count($consignments).' '.str('consignment')->plural(count($consignments)).' cleared under this settlement')
                ->schema([$this->consignmentsRepeater($consignments)]);
        }

        $shownKeys = [...array_keys($summaryFields->all()), ...$timelineFields->all(), 'consignments'];
        $leftover = collect($body)->except($shownKeys);

        if ($leftover->isNotEmpty()) {
            $sections[] = Section::make('Additional Details')
                ->collapsed()
                ->schema($leftover
                    ->map(fn (mixed $value, int|string $key): TextEntry => TextEntry::make((string) $key)
                        ->label(str((string) $key)->headline())
                        ->state($this->formatDetailValue($value))
                        ->columnSpanFull())
                    ->values()
                    ->all());
        }

        return $sections !== [] ? $sections : [TextEntry::make('empty')->label('')->state('Steadfast returned no details for this payment.')];
    }

    protected function summaryEntry(string $key, string $type, mixed $value): TextEntry
    {
        $entry = TextEntry::make($key)->label(str($key)->headline())->state($value)->placeholder('—');

        return match ($type) {
            'money' => $entry->money('BDT')->weight($key === 'total' ? 'bold' : null)->color($key === 'total' ? 'success' : null),
            'badge' => $entry->badge()->color(fn (?string $state): string => match (true) {
                $state === null => 'gray',
                $key === 'status_label' && strtolower($state) === 'paid' => 'success',
                $key === 'status_label' && strtolower($state) === 'pending' => 'warning',
                default => 'gray',
            }),
            default => $entry->weight('bold'),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $consignments
     */
    protected function consignmentsRepeater(array $consignments): RepeatableEntry
    {
        return RepeatableEntry::make('consignments')
            ->hiddenLabel()
            ->state($consignments)
            ->contained(true)
            ->schema([
                TextEntry::make('tracking_code')->label('Tracking Code')->badge()->color('primary')->placeholder('—'),
                TextEntry::make('invoice')->label('Invoice')->placeholder('—'),
                TextEntry::make('status')
                    ->label('Status')
                    ->badge()
                    ->placeholder('—')
                    ->color(fn (?string $state): string => match ($state) {
                        'delivered' => 'success',
                        'cancelled', 'lost', 'damaged' => 'danger',
                        'partial_delivered' => 'warning',
                        default => 'gray',
                    }),
                TextEntry::make('cod_amount')->label('COD Amount')->money('BDT')->placeholder('—'),
                TextEntry::make('recipient_name')->label('Recipient')->placeholder('—'),
                TextEntry::make('recipient_phone')->label('Phone')->placeholder('—'),
                TextEntry::make('recipient_address')->label('Address')->placeholder('—')->wrap()->columnSpanFull(),
            ])
            ->columns(3);
    }

    /**
     * Renders a scalar as-is; a list of objects as one "key: value ·
     * key: value" line per row; and any other nested object (recursively) as
     * "key: value; key: value" — a generic fallback for any field outside
     * the confirmed shape above.
     */
    protected function formatDetailValue(mixed $value): string
    {
        if (! is_array($value)) {
            return (string) $value;
        }

        if (array_is_list($value) && isset($value[0]) && is_array($value[0])) {
            return collect($value)
                ->map(fn (array $row): string => collect($row)
                    ->map(fn ($cell, $cellKey): string => "{$cellKey}: {$this->formatDetailValue($cell)}")
                    ->implode(' · '))
                ->implode("\n");
        }

        return collect($value)
            ->map(fn ($cell, $cellKey): string => "{$cellKey}: {$this->formatDetailValue($cell)}")
            ->implode('; ');
    }
}
