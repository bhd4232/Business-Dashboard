<x-filament-panels::page>
    <style>
        .zz-reports {
            --zz-card-bg: #ffffff;
            --zz-card-border: #e5e7eb;
            --zz-card-shadow: 0 14px 30px rgb(15 23 42 / 0.08);
            --zz-title: #111827;
            --zz-text: #374151;
            --zz-muted: #64748b;
            --zz-field-bg: #ffffff;
            --zz-field-border: #cbd5e1;
            --zz-secondary-bg: #f8fafc;
            --zz-secondary-hover: #fff7ed;
            --zz-summary-bg: #f8fafc;
            --zz-summary-border: #e5e7eb;
            --zz-icon-bg: #fffbeb;
            --zz-icon-border: #fde68a;
            --zz-switch-title: #64748b;
            --zz-link-bg: #ffffff;
            --zz-link-border: #e5e7eb;
            --zz-link-hover-bg: #fff7ed;
            --zz-link-active-bg: #fffbeb;
            --zz-table-header-bg: #f8fafc;
            --zz-pill-bg: #f8fafc;
            --zz-pill-border: #e2e8f0;
            --zz-table-head-bg: #f8fafc;
            --zz-table-border: #e5e7eb;
            --zz-row-hover: #f8fafc;
            --zz-status-text: #047857;
            --zz-status-bg: #d1fae5;
            --zz-blue-text: #1d4ed8;
            --zz-blue-bg: #dbeafe;
            --zz-warn-text: #92400e;
            --zz-warn-bg: #fef3c7;
            --zz-out-text: #be123c;
            --zz-out-bg: #ffe4e6;

            display: grid;
            gap: 18px;
        }

        .dark .zz-reports {
            --zz-card-bg: #17181c;
            --zz-card-border: #2b2d33;
            --zz-card-shadow: 0 14px 30px rgb(0 0 0 / 0.18);
            --zz-title: #f7f8fb;
            --zz-text: #e5e7eb;
            --zz-muted: #a8adb8;
            --zz-field-bg: #222329;
            --zz-field-border: #3a3d45;
            --zz-secondary-bg: #222329;
            --zz-secondary-hover: #26282f;
            --zz-summary-bg: #101827;
            --zz-summary-border: #283244;
            --zz-icon-bg: #262111;
            --zz-icon-border: #43351a;
            --zz-switch-title: #8f95a3;
            --zz-link-bg: #202127;
            --zz-link-border: #30333b;
            --zz-link-hover-bg: #24262d;
            --zz-link-active-bg: #2b210f;
            --zz-table-header-bg: #151d2b;
            --zz-pill-bg: #202733;
            --zz-pill-border: #354052;
            --zz-table-head-bg: #1d1f25;
            --zz-table-border: #25272d;
            --zz-row-hover: #1d2027;
            --zz-status-text: #7ee6b8;
            --zz-status-bg: #063c2c;
            --zz-blue-text: #93c5fd;
            --zz-blue-bg: #172b4d;
            --zz-warn-text: #fcd34d;
            --zz-warn-bg: #46320b;
            --zz-out-text: #fda4af;
            --zz-out-bg: #4c1720;
        }

        .zz-report-bar,
        .zz-report-card,
        .zz-report-switcher,
        .zz-report-table-card {
            background: var(--zz-card-bg);
            border: 1px solid var(--zz-card-border);
            border-radius: 10px;
            box-shadow: var(--zz-card-shadow);
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
            color: var(--zz-muted);
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
            color: var(--zz-title);
            background: var(--zz-field-bg);
            border: 1px solid var(--zz-field-border);
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
            color: var(--zz-title);
            background: var(--zz-secondary-bg);
            border-color: var(--zz-field-border);
        }

        .zz-button-secondary:hover {
            color: #f59e0b;
            background: var(--zz-secondary-hover);
            border-color: #f59e0b;
        }

        .zz-active-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            min-height: 72px;
            padding: 12px 14px;
            background: var(--zz-summary-bg);
            border: 1px solid var(--zz-summary-border);
            border-radius: 10px;
        }

        .zz-export-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
        }

        .zz-active-summary strong {
            display: block;
            color: var(--zz-title);
            font-size: 26px;
            line-height: 1;
        }

        .zz-active-summary span {
            display: block;
            margin-top: 4px;
            color: var(--zz-muted);
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
            background: var(--zz-icon-bg);
            border: 1px solid var(--zz-icon-border);
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
            color: var(--zz-muted);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .zz-kpi-value {
            margin-top: 5px;
            overflow: hidden;
            color: var(--zz-title);
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
            color: var(--zz-switch-title);
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
            color: var(--zz-text);
            background: var(--zz-link-bg);
            border: 1px solid var(--zz-link-border);
            border-radius: 9px;
            font-size: 14px;
            font-weight: 800;
            text-decoration: none;
            transition: border-color .16s ease, background .16s ease, transform .16s ease;
        }

        .zz-report-link:hover {
            color: #fbbf24;
            background: var(--zz-link-hover-bg);
            border-color: #f59e0b;
            transform: translateY(-1px);
        }

        .zz-report-link.is-active {
            color: #fbbf24;
            background: var(--zz-link-active-bg);
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
            background: var(--zz-table-header-bg);
            border-bottom: 1px solid var(--zz-card-border);
        }

        .zz-title-wrap {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            min-width: 0;
        }

        .zz-report-title {
            margin: 0;
            color: var(--zz-title);
            font-size: 18px;
            font-weight: 850;
            line-height: 1.25;
        }

        .zz-report-desc {
            margin: 5px 0 0;
            color: var(--zz-muted);
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
            color: var(--zz-text);
            background: var(--zz-pill-bg);
            border: 1px solid var(--zz-pill-border);
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
            color: var(--zz-text);
            font-size: 14px;
        }

        .zz-table th {
            padding: 11px 14px;
            color: var(--zz-muted);
            background: var(--zz-table-head-bg);
            border-bottom: 1px solid var(--zz-card-border);
            font-size: 11px;
            font-weight: 850;
            letter-spacing: .05em;
            text-align: left;
            text-transform: uppercase;
        }

        .zz-table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--zz-table-border);
            vertical-align: middle;
        }

        .zz-table tbody tr:hover {
            background: var(--zz-row-hover);
        }

        .zz-code {
            color: var(--zz-title);
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
            background: var(--zz-status-bg);
            color: var(--zz-status-text);
            border-radius: 7px;
            font-size: 12px;
            font-weight: 850;
        }

        .zz-status-blue {
            color: var(--zz-blue-text);
            background: var(--zz-blue-bg);
        }

        .zz-status-warn {
            color: var(--zz-warn-text);
            background: var(--zz-warn-bg);
        }

        .zz-status-out {
            color: var(--zz-out-text);
            background: var(--zz-out-bg);
        }

        .zz-empty {
            padding: 28px 14px !important;
            color: var(--zz-muted);
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
                <div class="zz-export-actions">
                    <a href="{{ $this->exportUrl($reportType) }}" class="zz-button zz-button-secondary">
                        <x-filament::icon icon="heroicon-m-arrow-down-tray" />
                        CSV
                    </a>
                    <a href="{{ $this->exportPdfUrl($reportType) }}" class="zz-button zz-button-secondary">
                        <x-filament::icon icon="heroicon-m-document-arrow-down" />
                        PDF
                    </a>
                </div>
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
