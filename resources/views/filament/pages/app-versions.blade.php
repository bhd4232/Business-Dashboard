<x-filament-panels::page>
    @if (! $this->isConfigured())
        <x-filament::section heading="Not connected to Coolify yet">
            <p style="font-size: .875rem; color: rgb(113 113 122);">
                Fill in and save the Coolify instance URL, API token, and Application UUID on the
                <a href="{{ \App\Filament\Pages\CoolifyDeploymentSettings::getUrl() }}" style="text-decoration: underline; font-weight: 600;">Deployment Settings</a>
                page first, then use its "Test Connection" button to confirm it works. Recent deployments will then show up here.
            </p>
        </x-filament::section>
    @elseif ($loadError)
        <x-filament::section heading="Could not load recent deployments">
            <p style="font-size: .875rem; color: rgb(220 38 38);">{{ $loadError }}</p>
            <p style="font-size: .8rem; color: rgb(113 113 122); margin-top: .5rem;">
                Double-check the credentials on
                <a href="{{ \App\Filament\Pages\CoolifyDeploymentSettings::getUrl() }}" style="text-decoration: underline; font-weight: 600;">Deployment Settings</a>.
            </p>
        </x-filament::section>
    @else
        @php($candidates = $this->candidates())

        <x-filament::section
            heading="Roll back to a previous version"
            description="These are the last few deployments that finished successfully, newest first — the currently-live version is not shown, since rolling back to it would do nothing. Rolling back only changes the app's code; it never touches the database.">

            @if (empty($candidates))
                <p style="font-size: .875rem; color: rgb(113 113 122);">No earlier finished deployment was found to roll back to yet.</p>
            @else
                <div style="display: grid; gap: .75rem;">
                    @foreach ($candidates as $deployment)
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .85rem 1rem; border: 1px solid rgb(228 228 231 / .8); border-radius: .6rem;" class="dark:tw-border-white/10">
                            <div style="min-width: 0;">
                                <div style="display: flex; align-items: center; gap: .5rem; font-family: ui-monospace, monospace; font-size: .8rem; font-weight: 700;">
                                    {{ \Illuminate\Support\Str::substr($deployment['commit'], 0, 7) }}
                                </div>
                                <div style="font-size: .8rem; color: rgb(113 113 122); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 32rem;">
                                    {{ $deployment['commit_message'] ?: '—' }}
                                </div>
                                <div style="font-size: .72rem; color: rgb(161 161 170); margin-top: .15rem;">
                                    @if ($deployment['created_at'])
                                        Deployed {{ \Illuminate\Support\Carbon::parse($deployment['created_at'])->diffForHumans() }}
                                    @endif
                                </div>
                            </div>

                            <x-filament::button
                                color="danger"
                                size="sm"
                                icon="heroicon-m-arrow-uturn-left"
                                wire:click="mountAction('rollback', { commit: '{{ $deployment['commit'] }}' })"
                            >
                                Rollback to this version
                            </x-filament::button>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
