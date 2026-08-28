<x-filament-panels::page>
    <x-filament::section heading="Firebase Project" description="One Firebase project powers both the Android app and desktop browser push notifications. Create it free at console.firebase.google.com, then paste its values below.">
        <div style="display: grid; gap: 1rem; max-width: 640px;">
            <label style="display: flex; align-items: center; gap: .6rem; font-size: .875rem; font-weight: 600;">
                <input type="checkbox" wire:model="settings.enabled" style="width: 1.1rem; height: 1.1rem;">
                Enable push notifications
            </label>
            <p style="font-size: .72rem; color: rgb(113 113 122); margin-top: -.6rem;">Turn this on once every field below is filled in and saved.</p>
        </div>
    </x-filament::section>

    @php
        // Each field's exact spot inside Firebase Console, shown as a hover
        // tooltip via an info icon next to its label -- the section-level
        // description above only gets the owner to the right page, this
        // gets them to the right field once they're there.
        $fieldPaths = [
            'api_key' => 'Firebase Console → ⚙ Project settings → General tab → Your apps → Web app (</>). If no Web app is listed yet, click "Add app" and choose Web first. In the "SDK setup and configuration" config snippet, copy the apiKey value.',
            'auth_domain' => 'Same Web app config snippet as API Key (Project settings → General → Your apps → Web app) → copy the authDomain value (looks like your-project.firebaseapp.com).',
            'project_id' => 'Same Web app config snippet (Project settings → General → Your apps → Web app) → copy the projectId value.',
            'storage_bucket' => 'Same Web app config snippet (Project settings → General → Your apps → Web app) → copy the storageBucket value.',
            'messaging_sender_id' => 'Same Web app config snippet (Project settings → General → Your apps → Web app) → copy the messagingSenderId value.',
            'app_id' => 'Same Web app config snippet (Project settings → General → Your apps → Web app) → copy the appId value.',
            'vapid_key' => 'Firebase Console → ⚙ Project settings → Cloud Messaging tab → scroll to "Web configuration" → "Web Push certificates" → click "Generate key pair" (or copy the existing one if already generated). Paste that key here.',
            'service_account_json' => 'Firebase Console → ⚙ Project settings → Service accounts tab → "Generate new private key" button → confirm → a .json file downloads to your computer. Open that file in any text editor, select all, copy, and paste the entire content here.',
        ];
    @endphp

    <x-filament::section heading="Web SDK Configuration" description="Firebase Console → Project settings → General → Your apps → Web app.">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; max-width: 900px;">
            <div>
                <label style="display:flex; align-items:center; gap:.35rem; font-size: .8rem; font-weight: 600; margin-bottom: .3rem;">
                    API Key
                    <x-filament::icon-button icon="heroicon-o-information-circle" label="Where to find this" :tooltip="$fieldPaths['api_key']" size="xs" color="gray" />
                </label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="settings.api_key" />
                </x-filament::input.wrapper>
            </div>
            <div>
                <label style="display:flex; align-items:center; gap:.35rem; font-size: .8rem; font-weight: 600; margin-bottom: .3rem;">
                    Auth Domain
                    <x-filament::icon-button icon="heroicon-o-information-circle" label="Where to find this" :tooltip="$fieldPaths['auth_domain']" size="xs" color="gray" />
                </label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="settings.auth_domain" placeholder="your-project.firebaseapp.com" />
                </x-filament::input.wrapper>
            </div>
            <div>
                <label style="display:flex; align-items:center; gap:.35rem; font-size: .8rem; font-weight: 600; margin-bottom: .3rem;">
                    Project ID
                    <x-filament::icon-button icon="heroicon-o-information-circle" label="Where to find this" :tooltip="$fieldPaths['project_id']" size="xs" color="gray" />
                </label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="settings.project_id" />
                </x-filament::input.wrapper>
            </div>
            <div>
                <label style="display:flex; align-items:center; gap:.35rem; font-size: .8rem; font-weight: 600; margin-bottom: .3rem;">
                    Storage Bucket
                    <x-filament::icon-button icon="heroicon-o-information-circle" label="Where to find this" :tooltip="$fieldPaths['storage_bucket']" size="xs" color="gray" />
                </label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="settings.storage_bucket" />
                </x-filament::input.wrapper>
            </div>
            <div>
                <label style="display:flex; align-items:center; gap:.35rem; font-size: .8rem; font-weight: 600; margin-bottom: .3rem;">
                    Messaging Sender ID
                    <x-filament::icon-button icon="heroicon-o-information-circle" label="Where to find this" :tooltip="$fieldPaths['messaging_sender_id']" size="xs" color="gray" />
                </label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="settings.messaging_sender_id" />
                </x-filament::input.wrapper>
            </div>
            <div>
                <label style="display:flex; align-items:center; gap:.35rem; font-size: .8rem; font-weight: 600; margin-bottom: .3rem;">
                    App ID
                    <x-filament::icon-button icon="heroicon-o-information-circle" label="Where to find this" :tooltip="$fieldPaths['app_id']" size="xs" color="gray" />
                </label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="settings.app_id" />
                </x-filament::input.wrapper>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section heading="Web Push Certificate" description="Firebase Console → Project settings → Cloud Messaging → Web configuration → Generate key pair.">
        <div style="max-width: 640px;">
            <label style="display:flex; align-items:center; gap:.35rem; font-size: .8rem; font-weight: 600; margin-bottom: .3rem;">
                VAPID Key {{ ($settings['has_vapid_key'] ?? false) ? '(already saved — leave blank to keep it)' : '' }}
                <x-filament::icon-button icon="heroicon-o-information-circle" label="Where to find this" :tooltip="$fieldPaths['vapid_key']" size="xs" color="gray" />
            </label>
            <x-filament::input.wrapper>
                <x-filament::input type="password" wire:model="settings.vapid_key" placeholder="{{ ($settings['has_vapid_key'] ?? false) ? '••••••••' : '' }}" />
            </x-filament::input.wrapper>
        </div>
    </x-filament::section>

    <x-filament::section heading="Server Credentials" description="Firebase Console → Project settings → Service accounts → Generate new private key. Paste the full downloaded JSON file content below.">
        <div style="max-width: 900px;">
            <label style="display:flex; align-items:center; gap:.35rem; font-size: .8rem; font-weight: 600; margin-bottom: .3rem;">
                Service Account JSON {{ ($settings['has_service_account'] ?? false) ? '(already saved — leave blank to keep it)' : '' }}
                <x-filament::icon-button icon="heroicon-o-information-circle" label="Where to find this" :tooltip="$fieldPaths['service_account_json']" size="xs" color="gray" />
            </label>
            <x-filament::input.wrapper>
                <textarea wire:model="settings.service_account_json" rows="8"
                    placeholder="{{ ($settings['has_service_account'] ?? false) ? '{ ... already saved, paste a new file only to replace it ... }' : '{ \"type\": \"service_account\", ... }' }}"
                    style="width: 100%; border: none; background: transparent; outline: none; padding: .5rem .75rem; font-family: ui-monospace, monospace; font-size: .78rem; color: inherit; resize: vertical;"></textarea>
            </x-filament::input.wrapper>
            <p style="font-size: .72rem; color: rgb(113 113 122); margin-top: .3rem;">Highly sensitive — stored encrypted and never shown again after saving.</p>
        </div>
    </x-filament::section>

    <div>
        <x-filament::button wire:click="save" icon="heroicon-m-check">Save settings</x-filament::button>
    </div>
</x-filament-panels::page>
