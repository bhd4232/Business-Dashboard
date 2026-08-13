<x-filament-panels::page>
    <div class="space-y-6">
        @unless ($this->activeProvider())
            <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning">
                <p class="text-sm">
                    No active Steadfast provider with API credentials found for this company. Add the API Key and
                    Secret Key on
                    <a href="{{ \App\Filament\Resources\CourierProviders\CourierProviderResource::getUrl() }}" class="underline font-medium">
                        Courier &rarr; Providers
                    </a>
                    to enable balance, consignment, return, and payment data here.
                </p>
            </x-filament::section>
        @endunless

        <div class="grid gap-6 md:grid-cols-2">
            <x-filament::section
                icon="heroicon-o-banknotes"
                heading="Steadfast Balance"
            >
                <x-slot name="afterHeader">
                    <x-filament::icon-button
                        icon="heroicon-o-arrow-path"
                        label="Refresh"
                        wire:click="refreshBalance"
                    />
                </x-slot>

                @if ($balance !== null)
                    <p class="text-3xl font-semibold tracking-tight">BDT {{ number_format($balance, 2) }}</p>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $balanceError ?? 'Balance unavailable.' }}
                    </p>
                @endif
            </x-filament::section>

            <x-filament::section
                icon="heroicon-o-chart-pie"
                heading="Delivery Performance"
            >
                @forelse ($performance as $row)
                    <div class="grid grid-cols-3 gap-4 text-sm {{ ! $loop->first ? 'mt-4 pt-4 border-t border-gray-200 dark:border-white/10' : '' }}">
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Total Bookings</p>
                            <p class="font-semibold text-base">{{ number_format($row->total) }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Success Rate</p>
                            <p class="font-semibold text-base text-success-600 dark:text-success-400">{{ $row->success_rate }}%</p>
                        </div>
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Return Rate</p>
                            <p class="font-semibold text-base text-danger-600 dark:text-danger-400">{{ $row->return_rate }}%</p>
                        </div>
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Delivered COD</p>
                            <p class="font-semibold text-base">BDT {{ number_format($row->delivered_cod, 2) }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Margin</p>
                            <p class="font-semibold text-base">
                                @if ($row->total_margin !== null)
                                    BDT {{ number_format($row->total_margin, 2) }}
                                @else
                                    &mdash;
                                @endif
                            </p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">No Steadfast bookings yet.</p>
                @endforelse
            </x-filament::section>
        </div>

        <x-filament::section icon="heroicon-o-arrow-uturn-left" heading="Recent Returns">
            <x-slot name="afterHeader">
                <a href="{{ \App\Filament\Resources\CourierReturns\CourierReturnResource::getUrl() }}" class="text-sm underline text-gray-500 dark:text-gray-400">
                    View all
                </a>
            </x-slot>

            @if ($recentReturns->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No return requests yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 dark:text-gray-400">
                                <th class="py-1 pr-4">Tracking ID</th>
                                <th class="py-1 pr-4">Invoice</th>
                                <th class="py-1 pr-4">Status</th>
                                <th class="py-1 pr-4">Reason</th>
                                <th class="py-1">Requested</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentReturns as $return)
                                <tr class="border-t border-gray-100 dark:border-white/5">
                                    <td class="py-1.5 pr-4">{{ $return->booking?->tracking_id ?? '-' }}</td>
                                    <td class="py-1.5 pr-4">{{ $return->booking?->order?->order_number ?? '-' }}</td>
                                    <td class="py-1.5 pr-4">
                                        <x-filament::badge>
                                            {{ \App\Models\CourierReturn::STATUSES[$return->status] ?? $return->status }}
                                        </x-filament::badge>
                                    </td>
                                    <td class="py-1.5 pr-4">{{ $return->reason ?? '-' }}</td>
                                    <td class="py-1.5">{{ $return->requested_at?->format('d M Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        {{ $this->table }}

        <div>
            <h2 class="text-base font-semibold mb-3">Manage</h2>
            @livewire(\App\Filament\Pages\CourierWidgets\CourierQuickLinksWidget::class)
        </div>
    </div>
</x-filament-panels::page>
