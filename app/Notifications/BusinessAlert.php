<?php

namespace App\Notifications;

use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/**
 * A single, generic in-app (bell) notification for every "business event"
 * push alert (new order, order status change, new staff, company events).
 * Modeled directly on App\Notifications\AppUpdateAvailable -- same via()/
 * toDatabase() shape -- parameterized by $alertKind instead of one class
 * per event, since the events only ever differ in title/body/data, never
 * in delivery mechanics. Recipients and permission gating are decided by
 * the caller (App\Services\BusinessNotificationService), not here.
 */
class BusinessAlert extends Notification
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        protected string $alertKind,
        protected string $title,
        protected string $body,
        protected array $data = [],
        protected ?string $actionUrl = null,
        protected ?string $actionLabel = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $notification = FilamentNotification::make()
            ->title($this->title)
            ->body($this->body)
            ->info();

        if (filled($this->actionUrl)) {
            $notification->actions([
                Action::make('view')
                    ->label($this->actionLabel ?? 'View')
                    ->url($this->actionUrl)
                    ->button()
                    ->markAsRead(),
            ]);
        }

        return [
            ...$notification->getDatabaseMessage(),
            'kind' => 'business-alert',
            'alert_kind' => $this->alertKind,
            'alert_data' => $this->data,
        ];
    }
}
