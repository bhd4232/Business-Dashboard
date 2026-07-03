<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Filament\Forms\Components\CustomerSourceSelect;
use App\Filament\Forms\Components\CustomerTypeSelect;
use App\Filament\Forms\Components\EmailInput;
use App\Filament\Forms\Components\PhoneInput;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
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

                                PhoneInput::make(required: true),

                                CustomerTypeSelect::make(),

                                CustomerSourceSelect::make(),

                                EmailInput::make(),

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
                            ->label('Order Status')
                            ->options(Order::STATUSES)
                            ->default('draft')
                            ->required()
                            ->live()
                            ->helperText('Controls invoice, stock, accounts, and sales reporting.'),

                        Select::make('delivery_status')
                            ->label('Delivery Status')
                            ->options(Order::DELIVERY_STATUSES)
                            ->default('not_booked')
                            ->required()
                            ->helperText('Controls courier progress shown on storefront tracking.'),
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
                                TableColumn::make('Variation'),
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

                                        $unitPrice = $product->selling_price;

                                        $set('product_variant_id', null);
                                        $set('variant_label', null);
                                        $set('unit_price', $unitPrice);
                                        $set('subtotal', (int) ($get('quantity') ?? 0) * $unitPrice);
                                        self::setOrderTotalsFromRepeater($get, $set);
                                    }),

                                Select::make('product_variant_id')
                                    ->label('Variation')
                                    ->options(function (Get $get): array {
                                        $product = Product::query()->with('activeVariants')->find($get('product_id'));

                                        if (! $product?->has_variants) {
                                            return [];
                                        }

                                        return $product->activeVariants
                                            ->mapWithKeys(fn (ProductVariant $variant): array => [
                                                $variant->getKey() => $variant->label().' (stock: '.(int) $variant->stock.')',
                                            ])
                                            ->all();
                                    })
                                    ->visible(fn (Get $get): bool => (bool) Product::query()->whereKey($get('product_id'))->value('has_variants'))
                                    ->required(fn (Get $get): bool => (bool) Product::query()->whereKey($get('product_id'))->value('has_variants'))
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, $state): void {
                                        $variant = ProductVariant::query()->find($state);

                                        if (! $variant) {
                                            $set('variant_label', null);

                                            return;
                                        }

                                        $set('variant_label', $variant->label());
                                        $set('unit_price', $variant->effectiveSalePrice());
                                        $set('subtotal', (int) ($get('quantity') ?? 0) * $variant->effectiveSalePrice());
                                        self::setOrderTotalsFromRepeater($get, $set);
                                    }),

                                Hidden::make('variant_label'),

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
        $items = $get($prefix.'items') ?? [];
        $subtotal = collect($items)
            ->sum(fn (array $item): float => (int) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0));
        $total = max($subtotal - (float) ($get($prefix.'discount') ?? 0) + (float) ($get($prefix.'vat') ?? 0), 0);
        $due = max($total - (float) ($get($prefix.'paid_amount') ?? 0), 0);

        $set($prefix.'subtotal', $subtotal);
        $set($prefix.'total_amount', $total);
        $set($prefix.'due_amount', $due);
    }
}
