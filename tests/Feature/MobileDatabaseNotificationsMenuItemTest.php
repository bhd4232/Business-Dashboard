<?php

namespace Tests\Feature;

use App\Livewire\MobileDatabaseNotificationsMenuItem;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class MobileDatabaseNotificationsMenuItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_counts_only_unread_filament_database_notifications(): void
    {
        $user = User::factory()->create();

        Notification::make()
            ->title('First unread notification')
            ->sendToDatabase($user);

        Notification::make()
            ->title('Already read notification')
            ->sendToDatabase($user);

        $user->notifications()
            ->where('data->format', 'filament')
            ->latest('created_at')
            ->firstOrFail()
            ->markAsRead();

        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'application',
            'data' => [
                'format' => 'another-client',
                'title' => 'Not a Filament notification',
            ],
        ]);

        $component = Livewire::actingAs($user)
            ->test(MobileDatabaseNotificationsMenuItem::class)
            ->assertSee('Open notifications (1 unread)');

        $this->assertSame(1, $component->instance()->getUnreadNotificationsCount());
    }

    public function test_it_refreshes_the_badge_when_notifications_change(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(MobileDatabaseNotificationsMenuItem::class)
            ->assertSee('Open notifications')
            ->assertDontSee('unread)');

        Notification::make()
            ->title('A new notification')
            ->sendToDatabase($user);

        $component
            ->call('$refresh')
            ->assertSee('Open notifications (1 unread)');
    }

    public function test_it_uses_polling_and_opens_the_native_filament_notification_modal(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(MobileDatabaseNotificationsMenuItem::class)
            ->assertSeeHtml('wire:poll.15s="$refresh"')
            ->assertSeeHtml('x-on:click="$dispatch(\'open-modal\', { id: \'database-notifications\' })"')
            ->assertSee('Notifications');

        $this->assertStringNotContainsString(
            'class="fi-dropdown-list"',
            $component->html(),
            'The user-menu hook already renders inside Filament\'s dropdown list.',
        );
    }
}
