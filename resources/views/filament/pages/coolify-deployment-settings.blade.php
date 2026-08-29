<x-filament-panels::page>
    <x-filament::section heading="Coolify Instance" description="This app is self-hosted on Coolify. These credentials let the dashboard ask Coolify for recent deployments and queue a rollback — they never touch anything else on your VPS.">
        <div style="display: grid; gap: 1rem; max-width: 640px;">
            <label style="display: flex; align-items: center; gap: .6rem; font-size: .875rem; font-weight: 600;">
                <input type="checkbox" wire:model="settings.enabled" style="width: 1.1rem; height: 1.1rem;">
                Enable app-version rollback
            </label>
            <p style="font-size: .72rem; color: rgb(113 113 122); margin-top: -.6rem;">Turn this on once every field below is filled in, saved, and Test Connection succeeds.</p>
        </div>
    </x-filament::section>

    <x-filament::section heading="Credentials">
        <div style="display: grid; gap: 1rem; max-width: 640px;">
            <div>
                <label style="display:flex; align-items:center; gap:.35rem; font-size: .8rem; font-weight: 600; margin-bottom: .3rem;">
                    Coolify Instance URL
                    <x-filament::icon-button icon="heroicon-o-information-circle" label="Where to find this" tooltip="The URL you use to open the Coolify dashboard itself, e.g. https://coolify.yourdomain.com — no trailing slash or /api/v1 needed, that part is added automatically." size="xs" color="gray" />
                </label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="settings.base_url" placeholder="https://coolify.yourdomain.com" />
                </x-filament::input.wrapper>
            </div>
            <div>
                <label style="display:flex; align-items:center; gap:.35rem; font-size: .8rem; font-weight: 600; margin-bottom: .3rem;">
                    API Token {{ ($settings['has_api_token'] ?? false) ? '(already saved — leave blank to keep it)' : '' }}
                    <x-filament::icon-button icon="heroicon-o-information-circle" label="Where to find this" tooltip="Coolify dashboard → your profile (top right) → Keys & Tokens → API tokens → Create New Token. It only needs the 'deploy' permission." size="xs" color="gray" />
                </label>
                <x-filament::input.wrapper>
                    <x-filament::input type="password" wire:model="settings.api_token" placeholder="{{ ($settings['has_api_token'] ?? false) ? '••••••••' : '' }}" />
                </x-filament::input.wrapper>
            </div>
            <div>
                <label style="display:flex; align-items:center; gap:.35rem; font-size: .8rem; font-weight: 600; margin-bottom: .3rem;">
                    Application UUID
                    <x-filament::icon-button icon="heroicon-o-information-circle" label="Where to find this" tooltip="Coolify dashboard → open this app's resource → the UUID is in the page's URL (…/project/…/application/{this-part}) and also shown on its Configuration tab." size="xs" color="gray" />
                </label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="settings.application_uuid" placeholder="e.g. jc0k48s" />
                </x-filament::input.wrapper>
            </div>
        </div>
    </x-filament::section>

    <div style="display: flex; gap: .75rem;">
        <x-filament::button wire:click="save" icon="heroicon-m-check">Save settings</x-filament::button>
        <x-filament::button wire:click="testConnection" color="gray" icon="heroicon-m-signal">Test Connection</x-filament::button>
    </div>
</x-filament-panels::page>
