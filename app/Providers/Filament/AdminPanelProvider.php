<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\BusinessOverview;
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
            ->colors([
                'primary' => Color::Amber,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                NavigationGroup::make('Sales'),
                NavigationGroup::make('Purchasing'),
                NavigationGroup::make('Inventory'),
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
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                BusinessOverview::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
