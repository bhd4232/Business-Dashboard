@php
    use App\Services\AppUpdateService;
    use App\Support\AppDeployment;
    use App\Support\AppRelease;
    use App\Support\FirebaseSettings;

    $deployment = AppDeployment::current();
    $release = AppRelease::latestPublished();
    $upgradeAvailable = app(AppUpdateService::class)->isAvailable(auth()->user());
    $firebaseWebConfigured = FirebaseSettings::isWebConfigured();
@endphp

<div
    id="zz-app-updater-config"
    data-loaded-deployment="{{ $deployment['deployment_id'] }}"
    data-loaded-built-at="{{ $deployment['built_at'] }}"
    data-initial-upgrade-available="{{ $upgradeAvailable ? 'true' : 'false' }}"
    data-version-url="{{ route('health.version') }}"
    data-sync-url="{{ route('admin.app-updates.sync') }}"
    data-push-register-url="{{ route('admin.push-devices.store') }}"
    data-poll-interval="15000"
    data-firebase-web-configured="{{ $firebaseWebConfigured ? 'true' : 'false' }}"
    data-firebase-vapid-key="{{ $firebaseWebConfigured ? FirebaseSettings::vapidKey() : '' }}"
    data-firebase-api-key="{{ $firebaseWebConfigured ? FirebaseSettings::webConfig()['apiKey'] : '' }}"
    data-firebase-auth-domain="{{ $firebaseWebConfigured ? FirebaseSettings::webConfig()['authDomain'] : '' }}"
    data-firebase-project-id="{{ $firebaseWebConfigured ? FirebaseSettings::webConfig()['projectId'] : '' }}"
    data-firebase-storage-bucket="{{ $firebaseWebConfigured ? FirebaseSettings::webConfig()['storageBucket'] : '' }}"
    data-firebase-messaging-sender-id="{{ $firebaseWebConfigured ? FirebaseSettings::webConfig()['messagingSenderId'] : '' }}"
    data-firebase-app-id="{{ $firebaseWebConfigured ? FirebaseSettings::webConfig()['appId'] : '' }}"
    data-service-worker-url="{{ route('firebase-messaging-sw') }}"
    hidden
    aria-hidden="true"
></div>

<p
    data-zz-app-update-status
    class="sr-only"
    role="status"
    aria-live="polite"
></p>

<x-filament::modal
    id="app-upgrade-confirmation"
    width="md"
    icon="heroicon-o-arrow-up-circle"
    icon-color="warning"
    heading="Upgrade App"
    description="A newer deployment is ready. You decide when this open app reloads."
>
    <div class="space-y-4">
        <x-filament::section compact>
            <div class="flex items-center justify-between gap-3">
                <span>Available version</span>
                <x-filament::badge color="warning">
                    <span data-zz-update-version>v{{ $release['version'] }}</span>
                </x-filament::badge>
            </div>
        </x-filament::section>

        <p class="text-sm text-gray-600 dark:text-gray-400">
            Upgrading performs one full reload and clears cached app files.
            Save any unfinished form, note, or draft before continuing.
        </p>

        <form
            method="POST"
            action="{{ route('admin.app-upgrade') }}"
            data-zz-app-upgrade-form
            x-data="{ submitting: false }"
            x-on:submit="submitting = true"
        >
            @csrf
            <input
                type="hidden"
                name="return_to"
                value="{{ request()->fullUrl() }}"
                data-zz-app-upgrade-return
            >
            <input
                type="hidden"
                name="deployment_id"
                value="{{ $deployment['deployment_id'] }}"
                data-zz-app-upgrade-deployment
            >

            <div class="flex flex-wrap justify-end gap-3">
                <x-filament::button
                    type="button"
                    color="gray"
                    x-bind:disabled="submitting"
                    x-on:click="$dispatch('close-modal', { id: 'app-upgrade-confirmation' })"
                >
                    Not now
                </x-filament::button>

                <x-filament::button
                    type="submit"
                    color="warning"
                    icon="heroicon-o-arrow-up-circle"
                    x-bind:disabled="submitting"
                    x-bind:aria-busy="submitting"
                >
                    <span x-show="! submitting">Upgrade App</span>
                    <span x-cloak x-show="submitting">Upgrading…</span>
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament::modal>

@vite(['resources/js/app-updater.js', 'resources/js/push-notifications.js', 'resources/js/web-push.js'])
