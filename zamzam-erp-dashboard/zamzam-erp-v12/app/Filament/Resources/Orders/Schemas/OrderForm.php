<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Invoice Details')
                    ->schema([
                        TextInput::make('order_number')
                            ->label('Invoice Number')
                            ->default(fn (): string => Order::nextOrderNumber())
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Select::make('customer_id')
                            ->label('Customer')
                            ->relationship('customer', 'name', fn ($query) => $query->where('is_active', true))
                            ->searchable(['name', 'phone'])
                            ->getOptionLabelFromRecordUsing(fn (Customer $record): string => $record->phone
                                ? "{$record->name} ({$record->phone})"
                                : $record->name)
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('Customer Name')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('phone')
                                    ->label('Phone')
                                    ->tel()
                                    ->required()
                                    ->maxLength(255),

                                Select::make('customer_type')
                                    ->label('Customer Type')
                                    ->options(Customer::TYPES)
                                    ->default('regular')
                                    ->required(),

                                Select::make('customer_source')
                                    ->label('Customer Source')
                                    ->options(Customer::SOURCES)
                                    ->searchable(),

                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(255),

                                Textarea::make('address')
                                    ->label('Address')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                return Customer::query()->create([
                                    'name' => $data['name'],
                                    'phone' => $data['phone'],
                                    'email' => $data['email'] ?? null,
                                    'address' => $data['address'] ?? null,
                                    'customer_type' => $data['customer_type'] ?? 'regular',
                                    'customer_source' => $data['customer_source'] ?? null,
                                    'is_active' => true,
                                ])->getKey();
                            })
                            ->live()
                            ->required()
                            ->afterStateUpdated(function (Set $set, $state): void {
                                $customer = Customer::find($state);

                                if ($customer) {
                                    $set('customer_name', $customer->name);
                                }
                            }),

                        Hidden::make('customer_name'),

                        DatePicker::make('order_date')
                            ->label('Sale Date')
                            ->default(now())
                            ->required(),

                        Select::make('status')
                            ->options(Order::STATUSES)
                            ->default('draft')
                            ->required()
                            ->live()
                            ->helperText('Stock is decreased when the invoice is Confirmed or Completed. Draft and Cancelled invoices do not affect stock.'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->persistCollapsed(),

                Section::make('Items')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->table([
                                TableColumn::make('Product'),
                                TableColumn::make('Quantity'),
                                TableColumn::make('Unit Price'),
                                TableColumn::make('Subtotal'),
                            ])
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->relationship('product', 'name', fn ($query) => $query->where('is_active', true))
                                    ->searchable()
                                    ->live()
                                    ->required()
                                    ->afterStateUpdated(function (Get $get, Set $set, $state): void {
                                        $product = Product::find($state);

                                        if (! $product) {
                                            return;
                                        }

                                        $unitPrice = (float) ($product->sale_price ?? $product->price ?? 0);

                                        $set('unit_price', $unitPrice);
                                        $set('subtotal', (int) ($get('quantity') ?? 0) * $unitPrice);
                                        self::setOrderTotalsFromRepeater($get, $set);
                                    }),

                                TextInput::make('quantity')
                                    ->integer()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set): void {
                                        self::setItemSubtotal($get, $set);
                                        self::setOrderTotalsFromRepeater($get, $set);
                                    }),

                                TextInput::make('unit_price')
                                    ->numeric()
                                    ->prefix('BDT')
                                    ->minValue(0)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set): void {
                                        self::setItemSubtotal($get, $set);
                                        self::setOrderTotalsFromRepeater($get, $set);
                                    }),

                                TextInput::make('subtotal')
                                    ->numeric()
                                    ->prefix('BDT')
                                    ->readOnly(),
                            ])
                            ->columns(4)
                            ->defaultItems(1)
                            ->addActionLabel('Add item')
                            ->reorderable(false)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::setOrderTotals($get, $set))
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->persistCollapsed(),

                Section::make('Totals')
                    ->schema([
                        TextInput::make('subtotal')
                            ->numeric()
                            ->prefix('BDT')
                            ->readOnly(),

                        TextInput::make('discount')
                            ->numeric()
                            ->prefix('BDT')
                            ->default(0)
                            ->minValue(0)
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::setOrderTotals($get, $set)),

                        TextInput::make('vat')
                            ->numeric()
                            ->prefix('BDT')
                            ->default(0)
                            ->minValue(0)
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::setOrderTotals($get, $set)),

                        TextInput::make('total_amount')
                            ->numeric()
                            ->prefix('BDT')
                            ->readOnly(),

                        TextInput::make('paid_amount')
                            ->numeric()
                            ->prefix('BDT')
                            ->default(0)
                            ->minValue(0)
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::setOrderTotals($get, $set)),

                        TextInput::make('due_amount')
                            ->numeric()
                            ->prefix('BDT')
                            ->readOnly(),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->persistCollapsed(),

                Section::make('Note')
                    ->schema([
                        Textarea::make('note')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->persistCollapsed(),
            ]);
    }

    protected static function setItemSubtotal(Get $get, Set $set): void
    {
        $set('subtotal', (int) ($get('quantity') ?? 0) * (float) ($get('unit_price') ?? 0));
    }

    protected static function setOrderTotalsFromRepeater(Get $get, Set $set): void
    {
        self::setOrderTotals($get, $set, '../../');
    }

    protected static function setOrderTotals(Get $get, Set $set, string $prefix = ''): void
    {
        $items = $get($prefix . 'items') ?? [];
        $subtotal = collect($items)
            ->sum(fn (array $item): float => (int) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0));
        $total = max($subtotal - (float) ($get($prefix . 'discount') ?? 0) + (float) ($get($prefix . 'vat') ?? 0), 0);
        $due = max($total - (float) ($get($prefix . 'paid_amount') ?? 0), 0);

        $set($prefix . 'subtotal', $subtotal);
        $set($prefix . 'total_amount', $total);
        $set($prefix . 'due_amount', $due);
    }
}
