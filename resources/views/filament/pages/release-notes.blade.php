<x-filament-panels::page>
    @php($release = $this->release())

    <div class="space-y-6">
        <x-filament::section
            icon="heroicon-o-rocket-launch"
            icon-color="primary"
            :heading="'v'.$release['version']"
            :description="'Released '.($release['date'] ?? 'date not set').($release['short_commit'] ? ' - Commit '.$release['short_commit'] : '')"
        >
            <x-slot name="afterHeader">
                <x-filament::badge color="primary" icon="heroicon-m-tag">
                    {{ $release['type_label'] }}
                </x-filament::badge>
            </x-slot>

            <p class="text-sm text-gray-600 dark:text-gray-400">
                Review the current application version and the changes included in each published release.
            </p>

            <x-slot name="footer">
                <x-filament::button
                    tag="a"
                    :href="route('health.version')"
                    target="_blank"
                    rel="noreferrer"
                    color="gray"
                    icon="heroicon-m-arrow-top-right-on-square"
                >
                    Open Version Endpoint
                </x-filament::button>
            </x-slot>
        </x-filament::section>

        <x-filament::section
            icon="heroicon-o-clock"
            heading="Release History"
            description="User-facing improvements, fixes, and operational changes by version."
        >
            <div class="divide-y divide-gray-200 dark:divide-white/10">
                @forelse ($this->changelogEntries() as $entry)
                    <article class="py-6 first:pt-0 last:pb-0">
                        <header class="mb-4">
                            <div class="min-w-0">
                                <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                                    v{{ $entry['version'] }}
                                </h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $entry['date'] }} - {{ $entry['release_type'] }}
                                </p>
                            </div>
                        </header>

                        <div class="space-y-5 break-words">
                            @foreach ($entry['sections'] as $section)
                                <section>
                                    <h4 class="text-sm font-semibold text-gray-950 dark:text-white">
                                        {{ $section['title'] }}
                                    </h4>
                                    <ul class="mt-2 list-disc space-y-1 ps-5 text-sm text-gray-700 dark:text-gray-300">
                                        @foreach ($section['items'] as $item)
                                            <li>{{ $item }}</li>
                                        @endforeach
                                    </ul>
                                </section>
                            @endforeach
                        </div>
                    </article>
                @empty
                    <x-filament::empty-state
                        heading="No release notes published"
                        description="Published release notes will appear here."
                        icon="heroicon-o-document-text"
                    />
                @endforelse
            </div>
        </x-filament::section>

        @if (auth()->user()?->isSuperAdmin())
            <x-filament::section
                icon="heroicon-o-circle-stack"
                icon-color="warning"
                heading="Super Admin Database & Deployment Notes"
                description="Technical release details intended only for super administrators."
            >
                <div class="divide-y divide-gray-200 dark:divide-white/10">
                    @forelse ($this->technicalChangelogEntries() as $entry)
                        <article class="py-6 first:pt-0 last:pb-0">
                            <header class="mb-4">
                                <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                                    v{{ $entry['version'] }}
                                </h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $entry['date'] }}
                                </p>
                            </header>

                            <div class="space-y-5 break-words">
                                @foreach ($entry['sections'] as $section)
                                    <section>
                                        <h4 class="text-sm font-semibold text-gray-950 dark:text-white">
                                            {{ $section['title'] }}
                                        </h4>
                                        <ul class="mt-2 list-disc space-y-1 ps-5 text-sm text-gray-700 dark:text-gray-300">
                                            @foreach ($section['items'] as $item)
                                                <li>{{ $item }}</li>
                                            @endforeach
                                        </ul>
                                    </section>
                                @endforeach
                            </div>
                        </article>
                    @empty
                        <x-filament::empty-state
                            heading="No technical notes published"
                            description="Technical release notes will appear here."
                            icon="heroicon-o-wrench-screwdriver"
                        />
                    @endforelse
                </div>
            </x-filament::section>

            <x-filament::section
                icon="heroicon-o-shield-exclamation"
                icon-color="danger"
                heading="Production Update Rules"
                description="Follow these safeguards for every live deployment."
            >
                <ul class="list-disc space-y-2 ps-5 text-sm text-gray-700 dark:text-gray-300">
                    <li>Create a database backup before every live update.</li>
                    <li>Use <code>php artisan migrate --force</code> for production migrations.</li>
                    <li>Never run <code>migrate:fresh</code>, <code>migrate:refresh</code>, <code>migrate:reset</code>, or <code>db:wipe</code> against the live database.</li>
                    <li>Update <code>CHANGELOG.md</code>, the GitHub release notes, and the app version for every release.</li>
                </ul>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
