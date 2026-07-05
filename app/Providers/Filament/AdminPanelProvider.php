<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\BusinessOverview;
use App\Filament\Widgets\CustomerDueNotifications;
use App\Filament\Widgets\CustomerRiskAlerts;
use App\Filament\Widgets\CustomerRiskOverview;
use App\Filament\Widgets\LowStockProducts;
use App\Filament\Widgets\SalesPurchaseTrend;
use App\Filament\Widgets\TopBusinessPerformers;
use App\Http\Middleware\SetCurrentCompany;
use App\Services\CompanySettingsService;
use App\Services\ProductSetupService;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->spa()
            ->databaseNotifications()
            ->brandName(fn (): string => (string) app(CompanySettingsService::class)->profile()['name'])
            ->brandLogo(fn (): ?string => app(CompanySettingsService::class)->logoUrl())
            ->darkModeBrandLogo(fn (): ?string => app(CompanySettingsService::class)->darkLogoUrl())
            ->brandLogoHeight('2.25rem')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                NavigationGroup::make('Company Management'),
                NavigationGroup::make('Storefront'),
                NavigationGroup::make('Sales'),
                NavigationGroup::make('Purchasing'),
                NavigationGroup::make('Inventory'),
                NavigationGroup::make('Courier'),
                NavigationGroup::make('Customer Success'),
                NavigationGroup::make('Accounts'),
                NavigationGroup::make('Reports'),
                NavigationGroup::make('Settings'),
            ])
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): HtmlString => new HtmlString(<<<'HTML'
                    <style>
                        .fi-page-header-main-ctn > .fi-header {
                            position: sticky;
                            top: 4rem;
                            z-index: 20;
                            margin-inline: -1rem;
                            padding: 1rem;
                            background-color: rgb(249 250 251 / 0.94);
                            backdrop-filter: blur(10px);
                            border-bottom: 1px solid rgb(229 231 235 / 0.75);
                        }

                        .dark .fi-page-header-main-ctn > .fi-header {
                            background-color: rgb(17 24 39 / 0.94);
                            border-bottom-color: rgb(255 255 255 / 0.1);
                        }

                        .fi-header-actions-ctn {
                            justify-content: flex-end;
                        }

                        .fi-sidebar-item-icon {
                            transform-origin: center;
                            transition:
                                color 180ms ease,
                                opacity 180ms ease,
                                transform 220ms cubic-bezier(0.34, 1.56, 0.64, 1),
                                filter 220ms ease;
                        }

                        .fi-sidebar-item-btn:hover .fi-sidebar-item-icon,
                        .fi-sidebar-item-active .fi-sidebar-item-icon {
                            transform: translateY(-1px) scale(1.12) rotate(-4deg);
                            filter: drop-shadow(0 4px 7px rgb(245 158 11 / 0.28));
                        }

                        .fi-sidebar-item-btn:active .fi-sidebar-item-icon {
                            transform: scale(0.96);
                        }

                        .zz-company-switcher {
                            flex-shrink: 0;
                            width: min(14rem, 42vw);
                        }

                        .zz-company-switcher .fi-dropdown {
                            width: 100%;
                        }

                        .zz-company-switcher .fi-input-wrp {
                            width: 100%;
                        }

                        .zz-company-switcher-trigger {
                            cursor: pointer;
                            min-height: 2.25rem;
                            width: 100%;
                            padding-inline-start: .75rem;
                            padding-inline-end: .25rem;
                            font-size: .875rem;
                            font-weight: 400;
                            text-align: start;
                        }

                        .zz-company-switcher-trigger-label {
                            display: block;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            white-space: nowrap;
                        }

                        @media (max-width: 640px) {
                            .zz-company-switcher {
                                width: min(12rem, 46vw);
                            }
                        }

                        @media (prefers-reduced-motion: reduce) {
                            .fi-sidebar-item-icon {
                                transition: none;
                            }

                            .fi-sidebar-item-btn:hover .fi-sidebar-item-icon,
                            .fi-sidebar-item-active .fi-sidebar-item-icon,
                            .fi-sidebar-item-btn:active .fi-sidebar-item-icon {
                                transform: none;
                                filter: none;
                            }
                        }
                    </style>
                HTML),
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn (): HtmlString => new HtmlString(view('filament.partials.company-switcher')->render()),
            )
            ->renderHook(
                PanelsRenderHook::CONTENT_BEFORE,
                function (): HtmlString {
                    $setup = app(ProductSetupService::class);

                    if (! $setup->demoMode()) {
                        return new HtmlString('');
                    }

                    $notice = e($setup->demoNotice());

                    return new HtmlString(<<<HTML
                        <div style="margin: 0 1rem 1rem; border: 1px solid rgb(245 158 11 / .35); background: rgb(254 243 199 / .95); color: #92400e; border-radius: 8px; padding: .75rem 1rem; font-size: .875rem; font-weight: 800;">
                            {$notice}
                        </div>
                    HTML);
                },
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                BusinessOverview::class,
                CustomerRiskOverview::class,
                SalesPurchaseTrend::class,
                TopBusinessPerformers::class,
                LowStockProducts::class,
                CustomerDueNotifications::class,
                CustomerRiskAlerts::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                SetCurrentCompany::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
