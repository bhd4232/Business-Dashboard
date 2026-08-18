<x-filament-panels::page>
    @if ($this->accounts()->isEmpty())
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning">
            <p class="text-sm">
                No Meta Ads accounts configured yet. Add one on
                <a href="{{ \App\Filament\Resources\MetaAdAccounts\MetaAdAccountResource::getUrl() }}" class="underline font-medium">
                    Ads &rarr; Ad Accounts
                </a>
                first.
            </p>
        </x-filament::section>
    @else
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                {{ $this->form }}
            </div>
        </div>

        @if ($this->pixelOptions() === [])
            <x-filament::section icon="heroicon-o-information-circle" icon-color="gray">
                <p class="text-sm">
                    No Pixel/Dataset configured for this company yet. Set one up on
                    <a href="{{ \App\Filament\Pages\MetaCapiSettings::getUrl() }}" class="underline font-medium">
                        Storefront &rarr; Meta CAPI
                    </a>
                    to see Pixel Health here (Audiences below still work without one).
                </p>
            </x-filament::section>
        @else
            <x-filament::section heading="Pixel Health" icon="heroicon-o-signal">
                @if ($this->pixelHealthError)
                    <p class="text-sm text-danger-600 dark:text-danger-400">{{ $this->pixelHealthError }}</p>
                @elseif ($this->pixelIdentity)
                    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Name</div>
                            <div class="text-sm font-medium">{{ $this->pixelIdentity['name'] ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Created</div>
                            <div class="text-sm font-medium">{{ $this->pixelIdentity['creation_time'] ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Last Fired</div>
                            <div class="text-sm font-medium">{{ $this->pixelIdentity['last_fired_time'] ?? 'never' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Status</div>
                            <x-filament::badge :color="($this->pixelIdentity['is_unavailable'] ?? false) ? 'danger' : 'success'">
                                {{ ($this->pixelIdentity['is_unavailable'] ?? false) ? 'Unavailable' : 'Active' }}
                            </x-filament::badge>
                        </div>
                    </div>
                @endif

                <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div>
                        <h4 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">What Meta recorded</h4>
                        @if ($this->pixelStatsNotice)
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $this->pixelStatsNotice }}</p>
                        @elseif ($this->pixelEventStatsRows)
                            <table class="w-full text-sm">
                                <tbody>
                                    @foreach ($this->pixelEventStatsRows as $row)
                                        <tr class="border-b border-gray-100 dark:border-white/5">
                                            <td class="py-1">{{ $row['event'] }}</td>
                                            <td class="py-1 text-right font-medium">{{ number_format($row['count']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <p class="text-sm text-gray-500 dark:text-gray-400">-</p>
                        @endif
                    </div>
                    <div>
                        <h4 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">What we attempted to send</h4>
                        @php $sendLog = $this->sendLogRows(); @endphp
                        @if ($sendLog->isEmpty())
                            <p class="text-sm text-gray-500 dark:text-gray-400">No local delivery attempts in this window.</p>
                        @else
                            <table class="w-full text-sm">
                                <tbody>
                                    @foreach ($sendLog as $row)
                                        <tr class="border-b border-gray-100 dark:border-white/5">
                                            <td class="py-1">{{ $row->event_name }}</td>
                                            <td class="py-1">
                                                <x-filament::badge :color="match ($row->status) {
                                                    'sent' => 'success',
                                                    'failed' => 'danger',
                                                    default => 'warning',
                                                }">
                                                    {{ ucfirst($row->status) }}
                                                </x-filament::badge>
                                            </td>
                                            <td class="py-1 text-right font-medium">{{ number_format($row->total) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>
            </x-filament::section>
        @endif

        {{ $this->table }}
    @endif
</x-filament-panels::page>
