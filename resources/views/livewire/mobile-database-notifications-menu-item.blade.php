<x-filament::dropdown.list.item
    class="zz-mobile-notifications-item"
    wire:poll.15s="$refresh"
    icon="heroicon-o-bell"
    :badge="$unreadCount ?: null"
    :aria-label="$unreadCount
        ? 'Open notifications ('.$unreadCount.' unread)'
        : 'Open notifications'"
    x-on:click="$dispatch('open-modal', { id: 'database-notifications' })"
>
    Notifications
</x-filament::dropdown.list.item>
