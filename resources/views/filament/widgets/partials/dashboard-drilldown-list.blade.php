{{--
    Generic drilldown-list partial reused by every BusinessOverview stat-card
    modal (App\Filament\Widgets\BusinessOverview). Expects:
    - $rows: Illuminate\Support\Collection of already-limited records
    - $total: total matching record count (used for the "showing X of Y" note)
    - $columns: ['Column label' => fn ($row) => 'cell html/text', ...]
    - $emptyMessage: string shown when $rows is empty
    - $seeAllUrl / $seeAllLabel: optional link to the full filtered list
--}}
<div class="space-y-3">
    @if ($rows->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $emptyMessage }}</p>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        @foreach (array_keys($columns) as $label)
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                {{ $label }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($rows as $row)
                        <tr>
                            @foreach ($columns as $formatter)
                                <td class="px-3 py-2 text-gray-700 dark:text-gray-200">{{ $formatter($row) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($total > $rows->count())
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Showing {{ $rows->count() }} of {{ $total }}.
            </p>
        @endif
    @endif

    @if ($seeAllUrl)
        <a
            href="{{ $seeAllUrl }}"
            class="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
        >
            {{ $seeAllLabel ?? 'See all' }} →
        </a>
    @endif
</div>
