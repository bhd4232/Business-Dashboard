<?php

namespace App\Services;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;

class CourierAlertService
{
    /**
     * Send a persistent danger alert to the company's courier-responsible
     * admins. Deduplicated: at most one alert per subject per day so a
     * repeatedly failing provider does not flood the notification bell.
     */
    public function alert(int $companyId, string $type, string $subject, string $title, string $body): bool
    {
        $key = "courier-alert:{$companyId}:{$type}:".sha1($subject);

        if (! Cache::add($key, true, now()->addDay())) {
            return false;
        }

        $recipients = $this->recipients($companyId);

        if ($recipients->isEmpty()) {
            return false;
        }

        Notification::make()
            ->danger()
            ->title($title)
            ->body($body)
            ->sendToDatabase($recipients);

        return true;
    }

    protected function recipients(int $companyId)
    {
        return User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($companyId): void {
                $query->where('role', 'super_admin')
                    ->orWhere(function ($inner) use ($companyId): void {
                        $inner->where('role', 'manager')
                            ->whereHas('companies', fn ($companies) => $companies->whereKey($companyId));
                    });
            })
            ->get();
    }
}
