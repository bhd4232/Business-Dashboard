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
use App\Services\CompanyContext;
use App\Services\CompanySettingsService;
use App\Services\DynamicColorService;
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
                // Base/fallback palette — used for "All Companies" (no single
                // company selected) and overridden per request below via CSS
                // custom properties for whichever company is active.
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
                PanelsRenderHook::HEAD_END,
                function (): HtmlString {
                    $context = app(CompanyContext::class);

                    // "All Companies" (or no company resolved) keeps the
                    // static Amber fallback set via ->colors() above —
                    // consistent with the existing All-Companies safeguard
                    // that disables company-scoped write actions.
                    $company = $context->hasCompany() && ! $context->isAllCompanies()
                        ? $context->company()
                        : null;

                    $hex = $company?->dashboard_color ?: DynamicColorService::DEFAULT_COLOR;

                    $declarations = implode('; ', app(DynamicColorService::class)->cssVariables($hex));

                    return new HtmlString(
                        '<style>:root { '.e($declarations).'; }</style>'
                    );
                },
            )
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

                        /* Dropdown panels (sub-navigation tabs collapsed to a
                           select on mobile, column manager, etc.) must float
                           above the sticky page header above, not behind it. */
                        .fi-dropdown-panel {
                            z-index: 30;
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

                        .zz-mobile-notifications-item {
                            display: none;
                        }

                        @media (max-width: 640px) {
                            .zz-company-switcher {
                                width: min(12rem, 46vw);
                            }

                            /* On mobile the header has no room for a separate
                               bell icon — hide it and expose notifications
                               from inside the profile/avatar dropdown menu
                               instead (see USER_MENU_PROFILE_AFTER hook). */
                            .fi-topbar-database-notifications-btn {
                                display: none;
                            }

                            .fi-topbar-end {
                                column-gap: 0.375rem;
                                padding-inline-end: 10px;
                            }

                            .zz-mobile-notifications-item {
                                display: flex;
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
                PanelsRenderHook::SCRIPTS_AFTER,
                fn (): HtmlString => new HtmlString(<<<'HTML'
                    <script>
                        // After any successful save/create/delete that doesn't already
                        // navigate away (e.g. an Edit form staying on the same page, a
                        // table row delete, a Settings page save), Filament flashes a
                        // notification and dispatches this browser event. Reload so the
                        // page always reflects the freshly persisted state.
                        window.addEventListener('notificationsSent', () => {
                            // Pages that manage their own live state (e.g. the
                            // Inbox chat) opt out — a full reload would wipe the
                            // open conversation mid-chat.
                            if (document.querySelector('[data-zz-no-reload]')) {
                                return;
                            }

                            window.location.reload();
                        });

                        // Chrome-style pull-to-refresh for the mobile app.
                        // The Capacitor webview has no reload UI, so dragging
                        // down from the very top of the page reloads it. Real
                        // mobile browsers already ship native pull-to-refresh,
                        // so this only activates inside the app's webview.
                        (() => {
                            const inAppWebView = !! window.Capacitor
                                || /; wv\)/.test(navigator.userAgent);

                            if (! inAppWebView || window.__zzPullToRefresh) {
                                return;
                            }
                            window.__zzPullToRefresh = true;

                            const THRESHOLD = 80;
                            const indicator = document.createElement('div');
                            indicator.style.cssText = 'position:fixed;top:-3.5rem;left:50%;z-index:9999;width:2.5rem;height:2.5rem;margin-left:-1.25rem;border-radius:50%;background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.25);display:flex;align-items:center;justify-content:center;transition:none;pointer-events:none;';
                            indicator.innerHTML = '<svg viewBox="0 0 24 24" style="width:1.35rem;height:1.35rem;fill:none;stroke:rgb(217 119 6);stroke-width:2.5;stroke-linecap:round;"><path d="M20 11A8 8 0 1 0 18.7 16"/><path d="M20 5v6h-6"/></svg>';
                            document.body.appendChild(indicator);

                            let startY = null;
                            let pulling = false;
                            let distance = 0;

                            const atTop = () => (document.scrollingElement?.scrollTop ?? 0) <= 0;

                            const innerScrolled = (el) => {
                                for (; el && el !== document.body; el = el.parentElement) {
                                    if (el.scrollTop > 0) return true;
                                }
                                return false;
                            };

                            document.addEventListener('touchstart', (e) => {
                                startY = (atTop() && ! innerScrolled(e.target)) ? e.touches[0].clientY : null;
                                pulling = false;
                                distance = 0;
                            }, { passive: true });

                            document.addEventListener('touchmove', (e) => {
                                if (startY === null || ! atTop()) return;
                                distance = (e.touches[0].clientY - startY) * 0.45;
                                pulling = distance > 10;
                                if (! pulling) return;
                                const shift = Math.min(distance, THRESHOLD * 1.4);
                                indicator.style.transition = 'none';
                                indicator.style.top = (shift - 56) + 'px';
                                indicator.style.transform = 'rotate(' + shift * 3 + 'deg)';
                            }, { passive: true });

                            document.addEventListener('touchend', () => {
                                if (pulling && distance >= THRESHOLD) {
                                    indicator.style.transition = 'top .15s';
                                    indicator.style.top = '14px';
                                    indicator.firstElementChild.style.animation = 'zz-ptr-spin .7s linear infinite';
                                    window.location.reload();
                                } else {
                                    indicator.style.transition = 'top .2s';
                                    indicator.style.top = '-3.5rem';
                                }
                                startY = null;
                                pulling = false;
                            }, { passive: true });

                            const style = document.createElement('style');
                            style.textContent = '@keyframes zz-ptr-spin { to { transform: rotate(360deg); } }';
                            document.head.appendChild(style);
                        })();
                    </script>
                HTML),
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn (): HtmlString => new HtmlString(view('filament.partials.company-switcher')->render()),
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_PROFILE_AFTER,
                fn (): HtmlString => new HtmlString(view('filament.partials.mobile-notifications-menu-item')->render()),
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
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
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
