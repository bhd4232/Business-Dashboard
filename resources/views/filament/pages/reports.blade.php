<x-filament-panels::page>
    <style>
        .zz-reports {
            display: grid;
            gap: 18px;
        }

        .zz-report-bar,
        .zz-report-card,
        .zz-report-switcher,
        .zz-report-table-card {
            background: #17181c;
            border: 1px solid #2b2d33;
            border-radius: 10px;
            box-shadow: 0 14px 30px rgb(0 0 0 / 0.18);
        }

        .zz-report-bar {
            display: grid;
            grid-template-columns: minmax(220px, 1fr) 170px 170px auto 260px;
            gap: 14px;
            align-items: end;
            padding: 16px;
        }

        .zz-field label,
        .zz-report-label {
            display: block;
            margin-bottom: 7px;
            color: #a8adb8;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .zz-field select,
        .zz-field input {
            width: 100%;
            height: 40px;
            padding: 0 12px;
            color: #f7f8fb;
            background: #222329;
            border: 1px solid #3a3d45;
            border-radius: 8px;
            outline: none;
        }

        .zz-field select:focus,
        .zz-field input:focus {
            border-color: #f59e0b;
            box-shadow: 0 0 0 3px rgb(245 158 11 / 0.16);
        }

        .zz-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 40px;
            padding: 0 14px;
            color: #111827;
            background: #f59e0b;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
        }

        .zz-button:hover {
            background: #fbbf24;
            border-color: #fbbf24;
        }

        .zz-button-secondary {
            color: #f7f8fb;
            background: #222329;
            border-color: #3a3d45;
        }

        .zz-button-secondary:hover {
            color: #f59e0b;
            background: #26282f;
            border-color: #f59e0b;
        }

        .zz-active-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            min-height: 72px;
            padding: 12px 14px;
            background: #101827;
            border: 1px solid #283244;
            border-radius: 10px;
        }

        .zz-active-summary strong {
            display: block;
            color: #f7f8fb;
            font-size: 26px;
            line-height: 1;
        }

        .zz-active-summary span {
            display: block;
            margin-top: 4px;
            color: #a8adb8;
            font-size: 13px;
        }

        .zz-kpis {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 12px;
        }

        .zz-report-card {
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 88px;
            padding: 14px;
        }

        .zz-icon-box {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            flex: 0 0 auto;
            color: #fbbf24;
            background: #262111;
            border: 1px solid #43351a;
            border-radius: 9px;
        }

        .zz-icon-box svg,
        .zz-button svg {
            width: 18px;
            height: 18px;
        }

        .zz-kpi-text {
            min-width: 0;
        }

        .zz-kpi-label {
            color: #a8adb8;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .zz-kpi-value {
            margin-top: 5px;
            overflow: hidden;
            color: #f7f8fb;
            font-size: 17px;
            font-weight: 800;
            line-height: 1.25;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .zz-report-switcher {
            padding: 16px;
        }

        .zz-switch-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .zz-switch-group-title {
            margin-bottom: 9px;
            color: #8f95a3;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .zz-report-link {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 44px;
            margin-top: 8px;
            padding: 9px 10px;
            color: #e5e7eb;
            background: #202127;
            border: 1px solid #30333b;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 800;
            text-decoration: none;
            transition: border-color .16s ease, background .16s ease, transform .16s ease;
        }

        .zz-report-link:hover {
            color: #fbbf24;
            background: #24262d;
            border-color: #f59e0b;
            transform: translateY(-1px);
        }

        .zz-report-link.is-active {
            color: #fbbf24;
            background: #2b210f;
            border-color: #f59e0b;
        }

        .zz-report-table-card {
            overflow: hidden;
        }

        .zz-table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px;
            background: #151d2b;
            border-bottom: 1px solid #2b3445;
        }

        .zz-title-wrap {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            min-width: 0;
        }

        .zz-report-title {
            margin: 0;
            color: #f7f8fb;
            font-size: 18px;
            font-weight: 850;
            line-height: 1.25;
        }

        .zz-report-desc {
            margin: 5px 0 0;
            color: #a8adb8;
            font-size: 13px;
        }

        .zz-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
        }

        .zz-pill {
            display: inline-flex;
            align-items: center;
            min-height: 30px;
            padding: 0 10px;
            color: #d4d7de;
            background: #202733;
            border: 1px solid #354052;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .zz-table-scroll {
            overflow-x: auto;
        }

        .zz-table {
            width: 100%;
            min-width: 820px;
            border-collapse: collapse;
            color: #e5e7eb;
            font-size: 14px;
        }

        .zz-table th {
            padding: 11px 14px;
            color: #9ca3af;
            background: #1d1f25;
            border-bottom: 1px solid #30333b;
            font-size: 11px;
            font-weight: 850;
            letter-spacing: .05em;
            text-align: left;
            text-transform: uppercase;
        }

        .zz-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #25272d;
            vertical-align: middle;
        }

        .zz-table tbody tr:hover {
            background: #1d2027;
        }

        .zz-code {
            color: #f7f8fb;
            font-weight: 800;
        }

        .zz-money {
            font-variant-numeric: tabular-nums;
            font-weight: 800;
            white-space: nowrap;
        }

        .zz-status {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 0 8px;
            background: #063c2c;
            color: #7ee6b8;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 850;
        }

        .zz-status-blue {
            color: #93c5fd;
            background: #172b4d;
        }

        .zz-status-warn {
            color: #fcd34d;
            background: #46320b;
        }

        .zz-status-out {
            color: #fda4af;
            background: #4c1720;
        }

        .zz-empty {
            padding: 28px 14px !important;
            color: #9ca3af;
            text-align: center;
        }

        @media (max-width: 1280px) {
            .zz-report-bar {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .zz-active-summary {
                grid-column: 1 / -1;
            }

            .zz-kpis {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .zz-switch-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px) {
            .zz-report-bar,
            .zz-kpis,
            .zz-switch-grid {
                grid-template-columns: 1fr;
            }

            .zz-table-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .zz-pills {
                justify-content: flex-start;
            }
        }
    </style>

    <div class="zz-reports">
        <form method="GET" class="zz-report-bar">
            <div class="zz-field">
                <label for="report_type">Report</label>
                <select id="report_type" name="report_type">
                    @foreach ($this->reportOptions() as $type => $label)
                        <option value="{{ $type }}" @selected($reportType === $type)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="zz-field">
                <label for="date_from">From</label>
                <input id="date_from" name="date_from" type="date" value="{{ $dateFrom }}" />
            </div>

            <div class="zz-field">
                <label for="date_to">To</label>
                <input id="date_to" name="date_to" type="date" value="{{ $dateTo }}" />
            </div>

            <button class="zz-button">
                <x-filament::icon icon="heroicon-m-funnel" />
                Apply
            </button>

            <div class="zz-active-summary">
                <div>
                    <span>{{ $this->activeReportGroup() }}</span>
                    <strong>{{ number_format($this->activeReportCount()) }}</strong>
                    <span>Rows</span>
                </div>
                <a href="{{ $this->exportUrl($reportType) }}" class="zz-button zz-button-secondary">
                    <x-filament::icon icon="heroicon-m-arrow-down-tray" />
                    Export CSV
                </a>
            </div>
        </form>

        <div class="zz-kpis">
            @foreach ([
                ['label' => 'Today Sales', 'value' => $this->money($summary['sales_today'] ?? 0), 'icon' => 'heroicon-o-shopping-bag'],
                ['label' => 'Purchases', 'value' => $this->money($summary['purchases_today'] ?? 0), 'icon' => 'heroicon-o-truck'],
                ['label' => 'Expenses', 'value' => $this->money($summary['expenses_today'] ?? 0), 'icon' => 'heroicon-o-receipt-percent'],
                ['label' => 'Customer Due', 'value' => $this->money($summary['customer_due'] ?? 0), 'icon' => 'heroicon-o-user-group'],
                ['label' => 'Account Balance', 'value' => $this->money($summary['account_balance'] ?? 0), 'icon' => 'heroicon-o-banknotes'],
                ['label' => 'Low Stock', 'value' => number_format($summary['low_stock_count'] ?? 0), 'icon' => 'heroicon-o-exclamation-triangle'],
            ] as $metric)
                <div class="zz-report-card">
                    <span class="zz-icon-box"><x-filament::icon :icon="$metric['icon']" /></span>
                    <div class="zz-kpi-text">
                        <div class="zz-kpi-label">{{ $metric['label'] }}</div>
                        <div class="zz-kpi-value">{{ $metric['value'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="zz-report-switcher">
            <div class="zz-switch-grid">
                @foreach ($this->reportGroups() as $group => $types)
                    <div>
                        <div class="zz-switch-group-title">{{ $group }}</div>
                        @foreach ($types as $type)
                            <a
                                href="{{ request()->fullUrlWithQuery(['report_type' => $type, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                                class="zz-report-link {{ $reportType === $type ? 'is-active' : '' }}"
                            >
                                <span class="zz-icon-box"><x-filament::icon :icon="$this->reportIcon($type)" /></span>
                                <span>{{ $this->reportOptions()[$type] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>

        <div class="zz-report-table-card">
            <div class="zz-table-header">
                <div class="zz-title-wrap">
                    <span class="zz-icon-box"><x-filament::icon :icon="$this->reportIcon($reportType)" /></span>
                    <div>
                        <h2 class="zz-report-title">{{ $this->activeReportTitle() }}</h2>
                        <p class="zz-report-desc">{{ $this->activeReportDescription() }}</p>
                    </div>
                </div>
                <div class="zz-pills">
                    <span class="zz-pill">{{ $dateFrom }} to {{ $dateTo }}</span>
                    <span class="zz-pill">{{ number_format($this->activeReportCount()) }} rows</span>
                </div>
            </div>

            <div class="zz-table-scroll">
                @switch($reportType)
                    @case('sales')
                        <table class="zz-table">
                            <thead>
                                <tr>
                                    <th>Date</th><th>Invoice</th><th>Customer</th><th>Total</th><th>Paid</th><th>Due</th><th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($sales as $order)
                                    <tr>
                                        <td>{{ optional($order->order_date)->format('d M Y') }}</td>
                                        <td class="zz-code">{{ $order->order_number }}</td>
                                        <td>{{ $order->customer?->name }}</td>
                                        <td class="zz-money">{{ $this->money($order->total_amount) }}</td>
                                        <td class="zz-money">{{ $this->money($order->paid_amount) }}</td>
                                        <td class="zz-money">{{ $this->money($order->due_amount) }}</td>
                                        <td><span class="zz-status">{{ ucfirst($order->status) }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="zz-empty">No sales found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        @break

                    @case('purchases')
                        @php($customCostLabels = $this->purchaseCustomCostLabels())
                        <table class="zz-table">
                            <thead>
                                <tr>
                                    <th>Date</th><th>Purchase</th><th>Supplier</th><th>China to BD Costs</th>
                                    @foreach ($customCostLabels as $label)
                                        <th>{{ $label }}</th>
                                    @endforeach
                                    <th>Total</th><th>Paid</th><th>Due</th><th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($purchases as $purchase)
                                    <tr>
                                        <td>{{ optional($purchase->purchase_date)->format('d M Y') }}</td>
                                        <td class="zz-code">{{ $purchase->purchase_number }}</td>
                                        <td>{{ $purchase->supplier?->name }}</td>
                                        <td class="zz-money">{{ $this->money($purchase->chinaToBdCostTotal()) }}</td>
                                        @foreach ($customCostLabels as $label)
                                            <td class="zz-money">{{ $this->money($purchase->customCostAmountFor($label)) }}</td>
                                        @endforeach
                                        <td class="zz-money">{{ $this->money($purchase->total_amount) }}</td>
                                        <td class="zz-money">{{ $this->money($purchase->paid_amount) }}</td>
                                        <td class="zz-money">{{ $this->money($purchase->due_amount) }}</td>
                                        <td><span class="zz-status zz-status-blue">{{ ucfirst($purchase->status) }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="{{ 8 + count($customCostLabels) }}" class="zz-empty">No purchases found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        @break

                    @case('profit')
                        <table class="zz-table">
                            <thead>
                                <tr>
                                    <th>Date</th><th>Invoice</th><th>Product</th><th>Qty</th><th>Revenue</th><th>Cost</th><th>Profit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($profit as $row)
                                    <tr>
                                        <td>{{ $row['date'] }}</td>
                                        <td class="zz-code">{{ $row['invoice'] }}</td>
                                        <td>{{ $row['product'] }}</td>
                                        <td>{{ $row['quantity'] }}</td>
                                        <td class="zz-money">{{ $this->money($row['revenue']) }}</td>
                                        <td class="zz-money">{{ $this->money($row['cost']) }}</td>
                                        <td class="zz-money">{{ $this->money($row['profit']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="zz-empty">No profit data found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        @break

                    @case('stock')
                    @case('low-stock')
                        <table class="zz-table">
                            <thead>
                                <tr>
                                    <th>SKU</th><th>Product</th><th>Category</th><th>Stock</th><th>Reorder</th><th>Cost</th><th>Sale Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php($products = $reportType === 'stock' ? $stock : $lowStock)
                                @forelse ($products as $product)
                                    <tr>
                                        <td class="zz-code">{{ $product->sku }}</td>
                                        <td>{{ $product->name }}</td>
                                        <td>{{ $product->category?->name }}</td>
                                        <td><span class="{{ $reportType === 'low-stock' ? 'zz-status zz-status-warn' : '' }}">{{ $product->stock }}</span></td>
                                        <td>{{ $product->reorder_level }}</td>
                                        <td class="zz-money">{{ $this->money($product->cost_price) }}</td>
                                        <td class="zz-money">{{ $this->money($product->sale_price ?? $product->price) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="zz-empty">No products found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        @break

                    @case('customer-dues')
                        <table class="zz-table">
                            <thead>
                                <tr>
                                    <th>Customer</th><th>Phone</th><th>Email</th><th>Due</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($customerDues as $customer)
                                    <tr>
                                        <td>{{ $customer->name }}</td>
                                        <td>{{ $customer->phone }}</td>
                                        <td>{{ $customer->email }}</td>
                                        <td class="zz-money">{{ $this->money($customer->current_balance) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="zz-empty">No customer due.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        @break

                    @case('supplier-dues')
                        <table class="zz-table">
                            <thead>
                                <tr>
                                    <th>Supplier</th><th>Phone</th><th>Company</th><th>Payable</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($supplierDues as $supplier)
                                    <tr>
                                        <td>{{ $supplier->name }}</td>
                                        <td>{{ $supplier->phone }}</td>
                                        <td>{{ $supplier->company_name }}</td>
                                        <td class="zz-money">{{ $this->money($supplier->current_balance) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="zz-empty">No supplier payable.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        @break

                    @case('expenses')
                        <table class="zz-table">
                            <thead>
                                <tr>
                                    <th>Date</th><th>Expense</th><th>Category</th><th>Account</th><th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($expenses as $expense)
                                    <tr>
                                        <td>{{ optional($expense->expense_date)->format('d M Y') }}</td>
                                        <td class="zz-code">{{ $expense->expense_number }}</td>
                                        <td>{{ $expense->category?->name }}</td>
                                        <td>{{ $expense->account?->name }}</td>
                                        <td class="zz-money">{{ $this->money($expense->amount) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="zz-empty">No expenses found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        @break

                    @case('ledger')
                        <table class="zz-table">
                            <thead>
                                <tr>
                                    <th>Date</th><th>Account</th><th>Type</th><th>Direction</th><th>Amount</th><th>Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($ledger as $entry)
                                    <tr>
                                        <td>{{ optional($entry->transaction_date)->format('d M Y') }}</td>
                                        <td>{{ $entry->account?->name }}</td>
                                        <td>{{ str($entry->type)->headline() }}</td>
                                        <td><span class="zz-status {{ $entry->direction === 'out' ? 'zz-status-out' : '' }}">{{ ucfirst($entry->direction) }}</span></td>
                                        <td class="zz-money">{{ $this->money($entry->amount) }}</td>
                                        <td>{{ $entry->note }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="zz-empty">No transactions found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        @break
                @endswitch
            </div>
        </div>
    </div>
</x-filament-panels::page>
