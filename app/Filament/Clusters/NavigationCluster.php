<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;
use Filament\Navigation\NavigationItem;
use Filament\Support\Enums\IconSize;
use Illuminate\View\ComponentAttributeBag;

use function Filament\Support\generate_icon_html;

abstract class NavigationCluster extends Cluster
{
    protected static bool $shouldRegisterSubNavigation = false;

    /**
     * Show clustered resources as icon-bearing links inside their module submenu.
     *
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        $parentItems = parent::getNavigationItems();
        $parentItem = $parentItems[0] ?? null;

        if (! $parentItem) {
            return [];
        }

        $childItems = [];

        foreach (static::getClusteredComponents() as $component) {
            if (! $component::shouldRegisterNavigation() || ! $component::canAccess()) {
                continue;
            }

            foreach ($component::getNavigationItems() as $childItem) {
                $iconHtml = generate_icon_html(
                    $childItem->getIcon(),
                    attributes: (new ComponentAttributeBag)->class([
                        'fi-sidebar-item-icon',
                        'zz-sidebar-child-icon',
                    ]),
                    size: IconSize::Large,
                )?->toHtml();

                if (filled($iconHtml)) {
                    $childItem->extraAttributes([
                        'class' => 'zz-sidebar-child-item',
                        'data-zz-sidebar-icon' => base64_encode($iconHtml),
                    ], merge: true);
                }

                $childItems[] = $childItem
                    ->group(null)
                    ->parentItem(null);
            }
        }

        $childItems = collect($childItems)
            ->sortBy(fn (NavigationItem $item): int => $item->getSort())
            ->values()
            ->all();

        $isActive = request()->routeIs(static::getNavigationItemActiveRoutePattern());

        return [
            $parentItem
                ->url(null)
                ->childItems($childItems)
                ->extraAttributes([
                    'class' => 'zz-sidebar-menu-parent'.($isActive ? ' zz-sidebar-route-active' : ''),
                    'data-zz-sidebar-menu' => static::getNavigationLabel(),
                ]),
        ];
    }
}
