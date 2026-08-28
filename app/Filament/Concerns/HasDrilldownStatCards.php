<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

/**
 * Shared "preview popup" plumbing for a StatsOverviewWidget whose Stat cards
 * open a modal listing the records behind that number, instead of (or in
 * addition to) navigating away — the same drilldown UX
 * App\Filament\Widgets\BusinessOverview's cards already use on the main
 * Dashboard (owner request: give Vouchers/Accounts/Expenses the same UX).
 *
 * A widget using this trait must also:
 * - implement Filament\Actions\Contracts\HasActions and
 *   `use Filament\Actions\Concerns\InteractsWithActions;`
 * - set `protected string $view = 'filament.widgets.business-overview';`
 *   (byte-identical stats-overview-widget markup plus the
 *   <x-filament-actions::modals /> these mounted actions need to render)
 * - give each Stat a `wire:click="mountAction('someAction', {...})"` via
 *   `->extraAttributes([...], merge: true)` instead of `->url(...)`, and
 *   define a matching `someActionAction(): Action` method built with
 *   `drilldownModal()`/`drilldownView()` below. Since the record a card
 *   represents varies per click (which account, which voucher bucket...),
 *   the arguments passed to mountAction() are read back inside the
 *   Action's own closures via a parameter literally named `$arguments` —
 *   Filament injects the mounted action's arguments into any closure that
 *   asks for it by that name (see Action::resolveDefaultClosureDependencyForEvaluationByName()).
 */
trait HasDrilldownStatCards
{
    protected int $drilldownLimit = 50;

    /**
     * Wraps an Action with the modal chrome every drilldown popup shares
     * (BusinessOverview's drilldownAction() uses the same three calls).
     */
    protected function drilldownModal(Action $action): Action
    {
        return $action
            ->modalWidth('2xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    /**
     * @param  array<string, \Closure>  $columns
     */
    protected function drilldownView(
        Collection $rows,
        int $total,
        array $columns,
        string $emptyMessage,
        ?string $seeAllUrl = null,
        ?string $seeAllLabel = null,
    ): View {
        return view('filament.widgets.partials.dashboard-drilldown-list', [
            'rows' => $rows,
            'total' => $total,
            'columns' => $columns,
            'emptyMessage' => $emptyMessage,
            'seeAllUrl' => $seeAllUrl,
            'seeAllLabel' => $seeAllLabel,
        ]);
    }
}
