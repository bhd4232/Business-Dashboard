@php
    $unreadCount = auth()->user()?->unreadNotifications()->count() ?? 0;
@endphp

@if (filament()->hasDatabaseNotifications())
    <x-filament::dropdown.list class="zz-mobile-notifications-item">
        <x-filament::dropdown.list.item
            icon="heroicon-o-bell"
            :badge="$unreadCount ?: null"
            x-on:click="$dispatch('open-modal', { id: 'database-notifications' })"
        >
            Notifications
        </x-filament::dropdown.list.item>
    </x-filament::dropdown.list>
@endif
