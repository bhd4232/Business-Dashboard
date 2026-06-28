<x-filament-panels::page>
    <style>
        .zz-release {
            --zz-card-bg: #ffffff;
            --zz-card-border: #e5e7eb;
            --zz-header-bg: #f8fafc;
            --zz-title: #111827;
            --zz-text: #374151;
            --zz-muted: #64748b;
            --zz-pill-bg: #ecfdf5;
            --zz-pill-text: #047857;
            --zz-warning-bg: #fff7ed;
            --zz-warning-text: #9a3412;

            display: grid;
            gap: 16px;
        }

        .dark .zz-release {
            --zz-card-bg: #17181c;
            --zz-card-border: #2b2d33;
            --zz-header-bg: #101827;
            --zz-title: #f7f8fb;
            --zz-text: #e5e7eb;
            --zz-muted: #a8adb8;
            --zz-pill-bg: #063c2c;
            --zz-pill-text: #7ee6b8;
            --zz-warning-bg: #46320b;
            --zz-warning-text: #fcd34d;
        }

        .zz-release-card {
            color: var(--zz-text);
            background: var(--zz-card-bg);
            border: 1px solid var(--zz-card-border);
            border-radius: 10px;
            overflow: hidden;
        }

        .zz-release-summary {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 18px;
        }

        .zz-release-kicker {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 0 10px;
            color: var(--zz-pill-text);
            background: var(--zz-pill-bg);
            border-radius: 8px;
            font-size: 12px;
            font-weight: 850;
        }

        .zz-release-version {
            margin: 10px 0 4px;
            color: var(--zz-title);
            font-size: 28px;
            font-weight: 900;
            line-height: 1.1;
        }

        .zz-release-meta {
            margin: 0;
            color: var(--zz-muted);
            font-size: 13px;
        }

        .zz-release-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 0 12px;
            color: #111827;
            background: #f59e0b;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 850;
            text-decoration: none;
            white-space: nowrap;
        }

        .zz-release-section-head {
            padding: 14px 18px;
            background: var(--zz-header-bg);
            border-bottom: 1px solid var(--zz-card-border);
        }

        .zz-release-section-head h2 {
            margin: 0;
            color: var(--zz-title);
            font-size: 17px;
            font-weight: 900;
        }

        .zz-release-entry {
            padding: 18px;
            border-bottom: 1px solid var(--zz-card-border);
        }

        .zz-release-entry:last-child {
            border-bottom: 0;
        }

        .zz-release-entry-head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 9px;
            margin-bottom: 14px;
        }

        .zz-release-entry h3 {
            margin: 0;
            color: var(--zz-title);
            font-size: 18px;
            font-weight: 900;
        }

        .zz-release-date {
            color: var(--zz-muted);
            font-size: 13px;
            font-weight: 700;
        }

        .zz-release-body {
            display: grid;
            gap: 12px;
        }

        .zz-release-body h4 {
            margin: 0 0 7px;
            color: var(--zz-title);
            font-size: 13px;
            font-weight: 900;
            text-transform: uppercase;
        }

        .zz-release-body ul {
            display: grid;
            gap: 7px;
            margin: 0;
            padding-left: 18px;
        }

        .zz-release-body li,
        .zz-release-rule li {
            color: var(--zz-text);
            font-size: 14px;
            line-height: 1.5;
        }

        .zz-release-rule {
            padding: 16px 18px;
            color: var(--zz-warning-text);
            background: var(--zz-warning-bg);
        }

        .zz-release-rule h2 {
            margin: 0 0 9px;
            color: var(--zz-title);
            font-size: 17px;
            font-weight: 900;
        }

        .zz-release-rule ul {
            display: grid;
            gap: 7px;
            margin: 0;
            padding-left: 18px;
        }

        .zz-release-empty {
            padding: 28px 18px;
            color: var(--zz-muted);
            text-align: center;
        }

        @media (max-width: 768px) {
            .zz-release-summary {
                flex-direction: column;
            }
        }
    </style>

    @php($release = $this->release())

    <div class="zz-release">
        <section class="zz-release-card zz-release-summary">
            <div>
                <span class="zz-release-kicker">{{ $release['type_label'] }}</span>
                <h2 class="zz-release-version">v{{ $release['version'] }}</h2>
                <p class="zz-release-meta">
                    Released {{ $release['date'] ?? 'date not set' }}
                    @if ($release['short_commit'])
                        - Commit {{ $release['short_commit'] }}
                    @endif
                </p>
            </div>

            <a href="{{ route('health.version') }}" class="zz-release-link" target="_blank" rel="noreferrer">
                Version endpoint
            </a>
        </section>

        <section class="zz-release-card">
            <div class="zz-release-section-head">
                <h2>Release History</h2>
            </div>

            @forelse ($this->changelogEntries() as $entry)
                <article class="zz-release-entry">
                    <div class="zz-release-entry-head">
                        <h3>v{{ $entry['version'] }}</h3>
                        <span class="zz-release-kicker">{{ $entry['release_type'] }}</span>
                        <span class="zz-release-date">{{ $entry['date'] }}</span>
                    </div>

                    <div class="zz-release-body">
                        @foreach ($entry['sections'] as $section)
                            <div>
                                <h4>{{ $section['title'] }}</h4>
                                <ul>
                                    @foreach ($section['items'] as $item)
                                        <li>{{ $item }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </article>
            @empty
                <div class="zz-release-empty">No release notes have been published yet.</div>
            @endforelse
        </section>

        @if (auth()->user()?->isSuperAdmin())
            <section class="zz-release-card zz-release-rule">
                <h2>Super Admin Database & Deployment Notes</h2>

                @foreach ($this->technicalChangelogEntries() as $entry)
                    <div style="margin-bottom: 14px;">
                        <h3 style="margin: 0 0 7px; color: var(--zz-title); font-size: 15px; font-weight: 900;">
                            v{{ $entry['version'] }} — {{ $entry['date'] }}
                        </h3>

                        @foreach ($entry['sections'] as $section)
                            <div style="margin-bottom: 9px;">
                                <h4 style="margin: 0 0 6px; color: var(--zz-title); font-size: 12px; font-weight: 900; text-transform: uppercase;">
                                    {{ $section['title'] }}
                                </h4>
                                <ul>
                                    @foreach ($section['items'] as $item)
                                        <li>{{ $item }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                @endforeach

                <h2>Production Update Rules</h2>
                <ul>
                    <li>Create a database backup before every live update.</li>
                    <li>Use <code>php artisan migrate --force</code> for production migrations.</li>
                    <li>Never run <code>migrate:fresh</code>, <code>migrate:refresh</code>, <code>migrate:reset</code>, or <code>db:wipe</code> against the live database.</li>
                    <li>Update <code>CHANGELOG.md</code>, the GitHub release notes, and the app version for every release.</li>
                </ul>
            </section>
        @endif
    </div>
</x-filament-panels::page>
