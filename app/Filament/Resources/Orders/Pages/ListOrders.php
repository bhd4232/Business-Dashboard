<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // The marker class keeps "New order" on the same row as the page
            // title on mobile (see .zz-orders-mobile-header-action in
            // resources/css/filament/admin/theme.css); Filament otherwise
            // stacks the header below its own sm breakpoint.
            CreateAction::make()
                ->extraAttributes(['class' => 'zz-orders-mobile-header-action']),
        ];
    }

    /**
     * Order-status quick-filter tabs above the table (Nuport-style order
     * dashboard): "All" plus one tab per Order::STATUSES value, each showing
     * a live count and scoping the list to that status when clicked. The
     * Status column filter in OrdersTable is kept so dashboard "see all"
     * deep links (?filters[status]=…) and filter-combining still work.
     */
    public function getTabs(): array
    {
        $counts = Order::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $tabs = [
            'all' => Tab::make('All')->badge($counts->sum()),
        ];

        foreach (Order::STATUSES as $value => $label) {
            $tabs[$value] = Tab::make($label)
                ->badge($counts->get($value, 0))
                ->badgeColor(self::statusTabColor($value))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $value));
        }

        return $tabs;
    }

    protected static function statusTabColor(string $status): string
    {
        return match ($status) {
            Order::STATUS_CONFIRMED, Order::STATUS_COMPLETED => 'success',
            Order::STATUS_PROCESSING => 'warning',
            Order::STATUS_CANCELLED, Order::STATUS_RETURNED => 'danger',
            default => 'gray',
        };
    }
}
