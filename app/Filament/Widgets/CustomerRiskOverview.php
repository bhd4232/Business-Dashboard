<?php

namespace App\Filament\Widgets;

use App\Models\CustomerRiskProfile;
use App\Models\CustomerRiskReview;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class CustomerRiskOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Customer Success & Risk';

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return Schema::hasTable('customer_risk_profiles') && (Auth::user()?->hasPermission('sales.view') ?? false);
    }

    protected function getStats(): array
    {
        $highRisk = CustomerRiskProfile::query()->where('risk_level', CustomerRiskProfile::LEVEL_HIGH)->count();
        $blacklisted = CustomerRiskProfile::query()->where('risk_level', CustomerRiskProfile::LEVEL_BLACKLISTED)->count();
        $pendingReviews = Schema::hasTable('customer_risk_reviews')
            ? CustomerRiskReview::query()->where('status', CustomerRiskReview::STATUS_PENDING)->count()
            : 0;

        return [
            Stat::make('High Risk Customers', $highRisk)
                ->description($highRisk > 0 ? 'Call confirm or manager review' : 'No high-risk customers')
                ->descriptionIcon($highRisk > 0 ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle)
                ->icon(Heroicon::OutlinedShieldExclamation)
                ->color($highRisk > 0 ? 'danger' : 'success'),
            Stat::make('Blacklisted Matches', $blacklisted)
                ->description($blacklisted > 0 ? 'Owner review required' : 'No blacklist matches')
                ->descriptionIcon($blacklisted > 0 ? Heroicon::OutlinedNoSymbol : Heroicon::OutlinedCheckCircle)
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color($blacklisted > 0 ? 'danger' : 'success'),
            Stat::make('Pending Risk Reviews', $pendingReviews)
                ->description($pendingReviews > 0 ? 'Approval queue needs attention' : 'No pending approvals')
                ->descriptionIcon($pendingReviews > 0 ? Heroicon::OutlinedClipboardDocumentCheck : Heroicon::OutlinedCheckCircle)
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color($pendingReviews > 0 ? 'warning' : 'success'),
        ];
    }
}
