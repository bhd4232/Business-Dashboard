<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section
            icon="heroicon-o-funnel"
            heading="Report Filters"
            description="Choose a report and date range, then apply the filters."
        >
            <x-slot name="afterHeader">
                <x-filament::badge color="gray" icon="heroicon-m-list-bullet">
                    {{ number_format($this->activeReportCount()) }} rows
                </x-filament::badge>
            </x-slot>

            <form method="GET" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <x-filament-forms::field-wrapper id="report_type" label="Report">
                    <x-filament::input.wrapper>
                        <x-filament::input.select id="report_type" name="report_type" autocomplete="off">
                            @foreach ($this->reportOptions() as $type => $label)
                                <option value="{{ $type }}" @selected($reportType === $type)>{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </x-filament-forms::field-wrapper>

                <x-filament-forms::field-wrapper id="date_from" label="From">
                    <x-filament::input.wrapper>
                        <x-filament::input
                            id="date_from"
                            name="date_from"
                            type="date"
                            value="{{ $dateFrom }}"
                            autocomplete="off"
                        />
                    </x-filament::input.wrapper>
                </x-filament-forms::field-wrapper>

                <x-filament-forms::field-wrapper id="date_to" label="To">
                    <x-filament::input.wrapper>
                        <x-filament::input
                            id="date_to"
                            name="date_to"
                            type="date"
                            value="{{ $dateTo }}"
                            autocomplete="off"
                        />
                    </x-filament::input.wrapper>
                </x-filament-forms::field-wrapper>

                <div class="flex items-end">
                    <x-filament::button type="submit" icon="heroicon-m-funnel" class="w-full">
                        Apply Filters
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
            @foreach ([
                ['label' => 'Today Sales', 'value' => $this->money($summary['sales_today'] ?? 0), 'icon' => 'heroicon-o-shopping-bag'],
                ['label' => 'Purchases', 'value' => $this->money($summary['purchases_today'] ?? 0), 'icon' => 'heroicon-o-truck'],
                ['label' => 'Expenses', 'value' => $this->money($summary['expenses_today'] ?? 0), 'icon' => 'heroicon-o-receipt-percent'],
                ['label' => 'Customer Due', 'value' => $this->money($summary['customer_due'] ?? 0), 'icon' => 'heroicon-o-user-group'],
                ['label' => 'Account Balance', 'value' => $this->money($summary['account_balance'] ?? 0), 'icon' => 'heroicon-o-banknotes'],
                ['label' => 'Low Stock', 'value' => number_format($summary['low_stock_count'] ?? 0), 'icon' => 'heroicon-o-exclamation-triangle'],
            ] as $metric)
                <x-filament::section compact :icon="$metric['icon']" :heading="$metric['label']">
                    <p class="text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">
                        {{ $metric['value'] }}
                    </p>
                </x-filament::section>
            @endforeach
        </div>

        <x-filament::section
            icon="heroicon-o-rectangle-stack"
            heading="Available Reports"
            description="Switch report types without losing the selected date range."
        >
            <div class="overflow-x-auto">
                <x-filament::tabs contained label="Report type" class="min-w-max">
                    @foreach ($this->reportGroups() as $group => $types)
                        @foreach ($types as $type)
                            <x-filament::tabs.item
                                tag="a"
                                :href="request()->fullUrlWithQuery(['report_type' => $type, 'date_from' => $dateFrom, 'date_to' => $dateTo])"
                                :icon="$this->reportIcon($type)"
                                :active="$reportType === $type"
                                :badge="$group"
                            >
                                {{ $this->reportOptions()[$type] }}
                            </x-filament::tabs.item>
                        @endforeach
                    @endforeach
                </x-filament::tabs>
            </div>
        </x-filament::section>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
