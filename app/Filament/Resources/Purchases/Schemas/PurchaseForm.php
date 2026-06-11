<?php

namespace App\Filament\Resources\Purchases\Schemas;

use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PurchaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Purchase Details')
                    ->schema([
                        TextInput::make('purchase_number')
                            ->label('Purchase Number')
                            ->default(fn (): string => Purchase::nextPurchaseNumber())
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Select::make('supplier_id')
                            ->label('Supplier')
                            ->relationship('supplier', 'name', fn ($query) => $query->where('is_active', true))
                            ->searchable(['name', 'phone', 'company_name'])
                            ->preload()
                            ->getOptionLabelFromRecordUsing(fn (Supplier $record): string => collect([
                                $record->name,
                                $record->phone ? "Phone: {$record->phone}" : null,
                                $record->company_name ? "Company: {$record->company_name}" : null,
                            ])->filter()->join(' | '))
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('Supplier Name')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('company_name')
                                    ->label('Company Name')
                                    ->maxLength(255),

                                TextInput::make('phone')
                                    ->label('Phone')
                                    ->tel()
                                    ->maxLength(255),

                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(255),

                                TextInput::make('opening_balance')
                                    ->numeric()
                                    ->prefix('BDT')
                                    ->default(0)
                                    ->required(),

                                Textarea::make('address')
                                    ->label('Address')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                return Supplier::query()->create([
                                    'name' => $data['name'],
                                    'company_name' => $data['company_name'] ?? null,
                                    'phone' => $data['phone'] ?? null,
                                    'email' => $data['email'] ?? null,
                                    'address' => $data['address'] ?? null,
                                    'opening_balance' => $data['opening_balance'] ?? 0,
                                    'current_balance' => $data['opening_balance'] ?? 0,
                                    'is_active' => true,
                                ])->getKey();
                            })
                            ->required(),

                        DatePicker::make('purchase_date')
                            ->label('Purchase Date')
                            ->default(now())
                            ->required(),

                        Select::make('status')
                            ->options(Purchase::STATUSES)
                            ->default('draft')
                            ->required()
                            ->live()
                            ->helperText('Stock is increased only when the status is Received. Cancelled purchases remove related stock movements.'),

                        Toggle::make('update_cost_price')
                            ->label('Update product cost price')
                            ->default(false),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Items')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->table([
                                TableColumn::make('Product'),
                                TableColumn::make('Quantity'),
                                TableColumn::make('Unit Cost'),
                                TableColumn::make('Subtotal'),
                            ])
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->relationship('product', 'name', fn ($query) => $query->where('is_active', true))
                                    ->searchable(['name', 'sku', 'barcode', 'brand'])
                                    ->preload()
                                    ->getOptionLabelFromRecordUsing(fn (Product $record): string => collect([
                                        $record->name,
                                        $record->sku ? "SKU: {$record->sku}" : null,
                                        $record->brand ? "Brand: {$record->brand}" : null,
                                    ])->filter()->join(' | '))
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->label('Product Name')
                                            ->required()
                                            ->maxLength(255),

                                        Select::make('category_id')
                                            ->label('Category')
                                            ->relationship('category', 'name', fn ($query) => $query->where('is_active', true))
                                            ->searchable()
                                            ->preload()
                                            ->createOptionForm([
                                                TextInput::make('name')
                                                    ->label('Category Name')
                                                    ->required()
                                                    ->maxLength(255),

                                                Textarea::make('description')
                                                    ->rows(3)
                                                    ->columnSpanFull(),
                                            ])
                                            ->createOptionUsing(function (array $data): int {
                                                $slug = Str::slug($data['name']);
                                                $originalSlug = $slug;
                                                $suffix = 2;

                                                while (Category::query()->where('slug', $slug)->exists()) {
                                                    $slug = "{$originalSlug}-{$suffix}";
                                                    $suffix++;
                                                }

                                                return Category::query()->create([
                                                    'name' => $data['name'],
                                                    'slug' => $slug,
                                                    'description' => $data['description'] ?? null,
                                                    'is_active' => true,
                                                ])->getKey();
                                            })
                                            ->required(),

                                        TextInput::make('sku')
                                            ->label('SKU')
                                            ->required()
                                            ->unique(Product::class, 'sku')
                                            ->maxLength(255),

                                        TextInput::make('barcode')
                                            ->label('Barcode')
                                            ->unique(Product::class, 'barcode')
                                            ->maxLength(255),

                                        TextInput::make('brand')
                                            ->label('Brand')
                                            ->maxLength(255),

                                        TextInput::make('unit')
                                            ->label('Unit')
                                            ->default('pcs')
                                            ->required()
                                            ->maxLength(50),

                                        TextInput::make('cost_price')
                                            ->label('Cost Price')
                                            ->numeric()
                                            ->prefix('BDT')
                                            ->default(0)
                                            ->minValue(0)
                                            ->required(),

                                        TextInput::make('sale_price')
                                            ->label('Sale Price')
                                            ->numeric()
                                            ->prefix('BDT')
                                            ->default(0)
                                            ->minValue(0)
                                            ->required(),

                                        TextInput::make('stock')
                                            ->label('Opening Stock')
                                            ->integer()
                                            ->default(0)
                                            ->minValue(0)
                                            ->required(),
                                    ])
                                    ->createOptionUsing(function (array $data): int {
                                        $openingStock = (int) ($data['stock'] ?? 0);

                                        $product = Product::query()->create([
                                            'name' => $data['name'],
                                            'category_id' => $data['category_id'],
                                            'sku' => $data['sku'],
                                            'barcode' => $data['barcode'] ?? null,
                                            'brand' => $data['brand'] ?? null,
                                            'unit' => $data['unit'] ?? 'pcs',
                                            'cost_price' => $data['cost_price'] ?? 0,
                                            'sale_price' => $data['sale_price'] ?? 0,
                                            'price' => $data['sale_price'] ?? 0,
                                            'stock' => 0,
                                            'reorder_level' => 0,
                                            'vat_rate' => 0,
                                            'is_active' => true,
                                            'status' => Product::STATUS_AVAILABLE,
                                        ]);

                                        $product->setStockFromProductForm($openingStock);

                                        return $product->getKey();
                                    })
                                    ->live()
                                    ->required()
                                    ->afterStateUpdated(function (Get $get, Set $set, $state): void {
                                        $product = Product::find($state);

                                        if (! $product) {
                                            return;
                                        }

                                        $unitCost = (float) ($product->cost_price ?? 0);

                                        $set('unit_cost', $unitCost);
                                        $set('subtotal', (int) ($get('quantity') ?? 0) * $unitCost);
                                        self::setPurchaseTotalsFromRepeater($get, $set);
                                    }),

                                TextInput::make('quantity')
                                    ->integer()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set): void {
                                        self::setItemSubtotal($get, $set);
                                        self::setPurchaseTotalsFromRepeater($get, $set);
                                    }),

                                TextInput::make('unit_cost')
                                    ->numeric()
                                    ->prefix('BDT')
                                    ->minValue(0)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set): void {
                                        self::setItemSubtotal($get, $set);
                                        self::setPurchaseTotalsFromRepeater($get, $set);
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
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::setPurchaseTotals($get, $set))
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('China to BD Costs')
                    ->schema([
                        ...self::chinaToBdCostFields(),
                        Actions::make([
                            self::addCustomCostFieldAction(),
                        ])
                            ->columnSpanFull(),
                        Repeater::make('custom_costs')
                            ->label('Custom Fields')
                            ->hidden(fn (Get $get): bool => blank($get('custom_costs')))
                            ->table([
                                TableColumn::make('Field Name'),
                                TableColumn::make('Amount'),
                            ])
                            ->schema([
                                TextInput::make('label')
                                    ->label('Field Name')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('amount')
                                    ->label('Amount')
                                    ->numeric()
                                    ->prefix('BDT')
                                    ->minValue(0)
                                    ->default(0)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::setPurchaseTotalsFromRepeater($get, $set)),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addable(false)
                            ->reorderable(false)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::setPurchaseTotals($get, $set))
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->collapsible(),

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
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::setPurchaseTotals($get, $set)),

                        TextInput::make('vat')
                            ->numeric()
                            ->prefix('BDT')
                            ->default(0)
                            ->minValue(0)
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::setPurchaseTotals($get, $set)),

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
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::setPurchaseTotals($get, $set)),

                        TextInput::make('due_amount')
                            ->numeric()
                            ->prefix('BDT')
                            ->readOnly(),
                    ])
                    ->columns(3)
                    ->collapsible(),

                Section::make('Note')
                    ->schema([
                        Textarea::make('note')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    protected static function setItemSubtotal(Get $get, Set $set): void
    {
        $set('subtotal', (int) ($get('quantity') ?? 0) * (float) ($get('unit_cost') ?? 0));
    }

    protected static function setPurchaseTotalsFromRepeater(Get $get, Set $set): void
    {
        self::setPurchaseTotals($get, $set, '../../');
    }

    protected static function chinaToBdCostFields(): array
    {
        return collect(Purchase::CHINA_TO_BD_COST_FIELDS)
            ->map(fn (string $label, string $field): TextInput => TextInput::make($field)
                ->label($label)
                ->numeric()
                ->prefix('BDT')
                ->minValue(0)
                ->default(0)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Get $get, Set $set) => self::setPurchaseTotals($get, $set)))
            ->all();
    }

    protected static function addCustomCostFieldAction(): Action
    {
        return Action::make('add_custom_cost_field')
            ->label('Add new field')
            ->icon('heroicon-o-plus')
            ->modalHeading('Create custom cost field')
            ->schema([
                TextInput::make('label')
                    ->label('Field Name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('amount')
                    ->label('Amount')
                    ->numeric()
                    ->prefix('BDT')
                    ->minValue(0)
                    ->default(0)
                    ->required(),
            ])
            ->action(function (array $data, Get $get, Set $set): void {
                $customCosts = $get('custom_costs') ?? [];
                $customCosts[] = [
                    'label' => $data['label'],
                    'amount' => (float) ($data['amount'] ?? 0),
                ];

                $set('custom_costs', $customCosts);
                self::setPurchaseTotals($get, $set);
            });
    }

    protected static function setPurchaseTotals(Get $get, Set $set, string $prefix = ''): void
    {
        $items = $get($prefix . 'items') ?? [];
        $subtotal = collect($items)
            ->sum(fn (array $item): float => (int) ($item['quantity'] ?? 0) * (float) ($item['unit_cost'] ?? 0));
        $chinaToBdCostTotal = collect(Purchase::CHINA_TO_BD_COST_FIELDS)
            ->keys()
            ->sum(fn (string $field): float => (float) ($get($prefix . $field) ?? 0));
        $customCostTotal = collect($get($prefix . 'custom_costs') ?? [])
            ->sum(fn (array $cost): float => (float) ($cost['amount'] ?? 0));
        $total = max($subtotal + $chinaToBdCostTotal + $customCostTotal - (float) ($get($prefix . 'discount') ?? 0) + (float) ($get($prefix . 'vat') ?? 0), 0);
        $due = max($total - (float) ($get($prefix . 'paid_amount') ?? 0), 0);

        $set($prefix . 'subtotal', $subtotal);
        $set($prefix . 'total_amount', $total);
        $set($prefix . 'due_amount', $due);
    }
}
