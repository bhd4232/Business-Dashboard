<?php

namespace App\Filament\Pages;

use App\Services\CustomerRiskSettingsService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class CustomerRiskSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Customer Success';

    protected static ?int $navigationSort = 9;

    protected static ?string $title = 'Risk Rule Settings';

    protected string $view = 'filament.pages.customer-risk-settings';

    public array $settings = [];

    public function mount(CustomerRiskSettingsService $service): void
    {
        $this->settings = $service->all();
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->isSuperAdmin() ?? false;
    }

    public function save(CustomerRiskSettingsService $service): void
    {
        $this->validate([
            'settings.*' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);

        $service->save($this->settings);
        $this->settings = $service->all();

        Notification::make()
            ->title('Risk rule settings saved')
            ->success()
            ->send();
    }

    public function labels(): array
    {
        return [
            'high_cod_amount' => 'High COD first-order amount',
            'high_return_ratio_threshold' => 'High return ratio threshold (%)',
            'low_success_total_orders' => 'Low success minimum courier orders',
            'low_success_ratio_threshold' => 'Low success threshold (%)',
            'high_return_ratio_deduction' => 'High return ratio deduction',
            'low_success_ratio_deduction' => 'Low success ratio deduction',
            'phone_multiple_names_deduction' => 'Same phone / multiple names deduction',
            'high_cod_first_order_deduction' => 'High COD first-order deduction',
            'incomplete_address_deduction' => 'Incomplete address deduction',
            'recent_duplicate_order_deduction' => 'Recent duplicate order deduction',
            'repeated_cancellation_deduction' => 'Repeated cancellation deduction',
            'blacklist_match_deduction' => 'Blacklist match deduction',
            'external_fraud_low_ratio_threshold' => 'External courier success ratio review threshold (%)',
        ];
    }
}
