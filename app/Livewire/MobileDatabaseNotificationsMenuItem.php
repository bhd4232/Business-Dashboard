<?php

namespace App\Livewire;

use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class MobileDatabaseNotificationsMenuItem extends Component
{
    #[On('databaseNotificationsSent')]
    #[On('markedNotificationAsRead')]
    #[On('markedNotificationAsUnread')]
    #[On('notificationClosed')]
    public function refreshUnreadCount(): void {}

    public function getUnreadNotificationsCount(): int
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return 0;
        }

        return $user->unreadNotifications()
            ->where('data->format', 'filament')
            ->count();
    }

    public function render(): View
    {
        return view('livewire.mobile-database-notifications-menu-item', [
            'unreadCount' => $this->getUnreadNotificationsCount(),
        ]);
    }
}
