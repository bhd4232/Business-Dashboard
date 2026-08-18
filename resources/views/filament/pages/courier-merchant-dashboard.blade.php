<x-filament-panels::page>
    <div class="space-y-6">
        @php
            $providers = $this->providers();
        @endphp

        @if ($providers->isEmpty())
            <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning">
                <p class="text-sm">
                    No courier providers configured for this company yet. Add one on
                    <a href="{{ \App\Filament\Resources\CourierProviders\CourierProviderResource::getUrl() }}" class="underline font-medium">
                        Courier &rarr; Providers
                    </a>
                    to enable balance, consignment, return, and payment data here.
                </p>
            </x-filament::section>
        @elseif ($this->hasMultipleActiveProviders())
            <div>
                {{ $this->form }}
            </div>
        @endif

        <div class="grid gap-6 md:grid-cols-2">
            <x-filament::section
                icon="heroicon-o-banknotes"
                :heading="$this->selectedProviderId() ? 'Balance' : 'Courier Balances'"
            >
                @forelse ($this->balances() as $row)
                    <div class="flex items-start justify-between gap-4 {{ ! $loop->first ? 'mt-4 pt-4 border-t border-gray-200 dark:border-white/10' : '' }}">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $row['provider']->name }}</p>

                            @if (! $row['supported'])
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    This courier doesn't expose a balance check.
                                </p>
                            @elseif ($row['amount'] !== null)
                                <p class="mt-1 text-3xl font-semibold tracking-tight">BDT {{ \App\Support\MoneyFormatter::number($row['amount']) }}</p>
                            @else
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $row['error'] ?? 'Balance unavailable.' }}
                                </p>
                            @endif
                        </div>

                        @if ($row['supported'])
                            <x-filament::icon-button
                                icon="heroicon-o-arrow-path"
                                label="Refresh {{ $row['provider']->name }} balance"
                                wire:click="refreshBalance({{ $row['provider']->id }})"
                            />
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        @if ($providers->isEmpty())
                            No courier providers configured yet.
                        @else
                            No active courier with a balance check is configured.
                        @endif
                    </p>
                @endforelse
            </x-filament::section>

            <x-filament::section
                icon="heroicon-o-chart-pie"
                heading="Delivery Performance"
            >
                @forelse ($this->performance() as $row)
                    <div class="{{ ! $loop->first ? 'mt-4 pt-4 border-t border-gray-200 dark:border-white/10' : '' }}">
                        @unless ($this->selectedProviderId())
                            <p class="mb-2 text-sm font-medium">
                                {{ $row->name }}
                                <span class="text-gray-400 dark:text-gray-500 font-normal">({{ \App\Models\CourierProvider::DRIVERS[$row->driver] ?? $row->driver }})</span>
                            </p>
                        @endunless

                        <div class="grid grid-cols-3 gap-4 text-sm">
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
                                <p class="font-semibold text-base">BDT {{ \App\Support\MoneyFormatter::number($row['amount']) }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Margin</p>
                                <p class="font-semibold text-base">
                                    @if ($row->total_margin !== null)
                                        BDT {{ \App\Support\MoneyFormatter::number($row['amount']) }}
                                    @else
                                        &mdash;
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">No bookings yet.</p>
                @endforelse
            </x-filament::section>
        </div>

        <div>
            <h2 class="text-base font-semibold mb-3">Booking Status Summary</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Click a card to see those bookings.</p>

            @php
                $statusCounts = $this->statusCounts();
            @endphp

            {{-- Reuses Filament's own native stat-card CSS classes (shipped in
                 vendor/filament/filament/dist/theme.css, loaded on every panel
                 page) so these hand-built, clickable <button> cards render
                 pixel-identical to the Manage section's native
                 StatsOverviewWidget cards below — same rounded-xl/shadow/ring
                 surface, same text-3xl value and text-sm label sizes — rather
                 than an approximated Tailwind card that drifts from Filament's
                 real design. A real <x-filament::widgets::stat> can't be used
                 here because Stat's ->url() only supports plain navigation,
                 not a wire:click modal action. --}}
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
                @foreach ($this->statusCardDefinitions() as $statusKey => $statusDefinition)
                    @php
                        [$statusTint, $statusTintHover] = \App\Filament\Pages\CourierMerchantDashboard::statCardTint($statusDefinition['color']);
                    @endphp
                    <button
                        type="button"
                        wire:click="mountAction('bookingStatus', { key: '{{ $statusKey }}' })"
                        class="fi-wi-stats-overview-stat text-left transition {{ $statusTint }} {{ $statusTintHover }}"
                    >
                        <div class="fi-wi-stats-overview-stat-content">
                            <div class="fi-wi-stats-overview-stat-label-ctn">
                                <x-filament::icon :icon="$statusDefinition['icon']" class="h-5 w-5 shrink-0" />
                                <span class="fi-wi-stats-overview-stat-label">{{ $statusDefinition['label'] }}</span>
                            </div>
                            <div class="fi-wi-stats-overview-stat-value">{{ number_format($statusCounts[$statusKey] ?? 0) }}</div>
                        </div>
                    </button>
                @endforeach
            </div>
        </div>

        <div>
            <h2 class="text-base font-semibold mb-3">Manage</h2>
            @livewire(
                \App\Filament\Pages\CourierWidgets\CourierQuickLinksWidget::class,
                ['providerId' => $this->selectedProviderId()],
                key('courier-quick-links-'.($this->selectedProviderId() ?? 'all'))
            )
        </div>

        <x-filament::section icon="heroicon-o-arrow-uturn-left" heading="Recent Returns">
            <x-slot name="afterHeader">
                <a href="{{ \App\Filament\Resources\CourierReturns\CourierReturnResource::getUrl() }}" class="text-sm underline text-gray-500 dark:text-gray-400">
                    View all
                </a>
            </x-slot>

            @php
                $recentReturns = $this->recentReturns();
            @endphp

            @if ($recentReturns->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No return requests yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 dark:text-gray-400">
                                <th class="py-1 pr-4">Tracking ID</th>
                                <th class="py-1 pr-4">Courier</th>
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
                                    <td class="py-1.5 pr-4">{{ $return->provider?->name ?? '-' }}</td>
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
    </div>
</x-filament-panels::page>
