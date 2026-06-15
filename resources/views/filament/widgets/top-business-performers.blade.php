@php
    $productMax = max((float) ($products->max('revenue') ?? 0), 1);
    $customerMax = max((float) ($customers->max('total_sales') ?? 0), 1);
    $supplierMax = max((float) ($suppliers->max('total_purchases') ?? 0), 1);

    $groups = [
        [
            'title' => 'Top Products',
            'subtitle' => 'Units sold and revenue',
            'icon' => 'heroicon-o-cube',
            'accent' => 'emerald',
            'items' => $products,
            'max' => $productMax,
            'empty' => 'No product sales yet.',
            'name' => fn ($product) => $product->name,
            'meta' => fn ($product) => number_format((float) $product->quantity) . ' units',
            'value' => fn ($product) => (float) $product->revenue,
            'trail' => fn ($product) => 'Revenue',
        ],
        [
            'title' => 'Top Customers',
            'subtitle' => 'Sales volume and dues',
            'icon' => 'heroicon-o-user-group',
            'accent' => 'sky',
            'items' => $customers,
            'max' => $customerMax,
            'empty' => 'No customer sales yet.',
            'name' => fn ($customer) => $customer->name,
            'meta' => fn ($customer) => 'Due BDT ' . number_format((float) $customer->current_balance, 2),
            'value' => fn ($customer) => (float) $customer->total_sales,
            'trail' => fn ($customer) => 'Sales',
        ],
        [
            'title' => 'Top Suppliers',
            'subtitle' => 'Purchase volume and payable',
            'icon' => 'heroicon-o-building-storefront',
            'accent' => 'amber',
            'items' => $suppliers,
            'max' => $supplierMax,
            'empty' => 'No supplier purchases yet.',
            'name' => fn ($supplier) => $supplier->name,
            'meta' => fn ($supplier) => 'Payable BDT ' . number_format((float) $supplier->current_balance, 2),
            'value' => fn ($supplier) => (float) $supplier->total_purchases,
            'trail' => fn ($supplier) => 'Purchases',
        ],
    ];
@endphp

<x-filament-widgets::widget>
    <style>
        .zz-performers {
            display: grid;
            gap: 16px;
        }

        .zz-performers__grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .zz-performer-card {
            overflow: hidden;
            min-width: 0;
            background: #17181d;
            border: 1px solid #2b2d34;
            border-radius: 12px;
            box-shadow: 0 18px 42px rgb(0 0 0 / 0.18);
        }

        .zz-performer-card__head {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            background: #111827;
            border-bottom: 1px solid #2b3445;
        }

        .zz-performer-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            flex: 0 0 auto;
            border-radius: 10px;
        }

        .zz-performer-icon svg {
            width: 21px;
            height: 21px;
        }

        .zz-performer-icon--emerald {
            color: #6ee7b7;
            background: #063c2c;
            border: 1px solid #0f6b4d;
        }

        .zz-performer-icon--sky {
            color: #93c5fd;
            background: #172b4d;
            border: 1px solid #254a7d;
        }

        .zz-performer-icon--amber {
            color: #fcd34d;
            background: #46320b;
            border: 1px solid #79570e;
        }

        .zz-performer-title {
            margin: 0;
            color: #f8fafc;
            font-size: 15px;
            font-weight: 850;
            line-height: 1.25;
        }

        .zz-performer-subtitle {
            margin-top: 3px;
            color: #9ca3af;
            font-size: 12px;
            line-height: 1.35;
        }

        .zz-performer-list {
            display: grid;
            gap: 10px;
            padding: 14px;
        }

        .zz-performer-row {
            display: grid;
            grid-template-columns: 34px minmax(0, 1fr) auto;
            gap: 10px;
            align-items: center;
            min-height: 70px;
            padding: 10px;
            background: #202127;
            border: 1px solid #30333b;
            border-radius: 10px;
        }

        .zz-performer-rank {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            color: #f8fafc;
            background: #2b2d34;
            border: 1px solid #3b3f49;
            border-radius: 9px;
            font-size: 12px;
            font-weight: 850;
            font-variant-numeric: tabular-nums;
        }

        .zz-performer-name {
            overflow: hidden;
            color: #f8fafc;
            font-size: 13px;
            font-weight: 800;
            line-height: 1.3;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .zz-performer-meta {
            margin-top: 3px;
            color: #a8adb8;
            font-size: 12px;
            line-height: 1.3;
        }

        .zz-performer-bar {
            overflow: hidden;
            height: 5px;
            margin-top: 8px;
            background: #111318;
            border-radius: 999px;
        }

        .zz-performer-bar span {
            display: block;
            height: 100%;
            min-width: 6px;
            border-radius: inherit;
        }

        .zz-performer-bar--emerald span {
            background: #22c55e;
        }

        .zz-performer-bar--sky span {
            background: #38bdf8;
        }

        .zz-performer-bar--amber span {
            background: #f59e0b;
        }

        .zz-performer-value {
            min-width: 112px;
            text-align: right;
        }

        .zz-performer-value strong {
            display: block;
            color: #f8fafc;
            font-size: 13px;
            font-weight: 850;
            line-height: 1.25;
            white-space: nowrap;
        }

        .zz-performer-value span {
            display: block;
            margin-top: 3px;
            color: #8f95a3;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .zz-performer-empty {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 120px;
            color: #9ca3af;
            background: #202127;
            border: 1px dashed #3a3d45;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            text-align: center;
        }

        @media (max-width: 1280px) {
            .zz-performers__grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .zz-performer-row {
                grid-template-columns: 30px minmax(0, 1fr);
            }

            .zz-performer-value {
                grid-column: 2;
                min-width: 0;
                text-align: left;
            }
        }
    </style>

    <x-filament::section>
        <x-slot name="heading">Top Business Performers</x-slot>
        <x-slot name="description">Best products, customers, and suppliers by confirmed business activity.</x-slot>

        <div class="zz-performers">
            <div class="zz-performers__grid">
                @foreach ($groups as $group)
                    <section class="zz-performer-card">
                        <div class="zz-performer-card__head">
                            <span class="zz-performer-icon zz-performer-icon--{{ $group['accent'] }}">
                                <x-filament::icon :icon="$group['icon']" />
                            </span>
                            <div>
                                <h3 class="zz-performer-title">{{ $group['title'] }}</h3>
                                <div class="zz-performer-subtitle">{{ $group['subtitle'] }}</div>
                            </div>
                        </div>

                        <div class="zz-performer-list">
                            @forelse ($group['items'] as $item)
                                @php
                                    $value = $group['value']($item);
                                    $width = max(6, min(100, ($value / $group['max']) * 100));
                                @endphp

                                <div class="zz-performer-row">
                                    <span class="zz-performer-rank">{{ $loop->iteration }}</span>
                                    <div class="min-w-0">
                                        <div class="zz-performer-name" title="{{ $group['name']($item) }}">{{ $group['name']($item) }}</div>
                                        <div class="zz-performer-meta">{{ $group['meta']($item) }}</div>
                                        <div class="zz-performer-bar zz-performer-bar--{{ $group['accent'] }}">
                                            <span style="width: {{ $width }}%"></span>
                                        </div>
                                    </div>
                                    <div class="zz-performer-value">
                                        <strong>BDT {{ number_format($value, 2) }}</strong>
                                        <span>{{ $group['trail']($item) }}</span>
                                    </div>
                                </div>
                            @empty
                                <div class="zz-performer-empty">{{ $group['empty'] }}</div>
                            @endforelse
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
