<script>
    (() => {
        const menuSelector = '.zz-sidebar-menu-parent';
        const triggerSelector = ':scope > .fi-sidebar-item-btn';
        const navigation = window.ZamZamSidebarNavigation ?? {};

        const setupHoverExpansion = () => {
            const sidebar = document.querySelector('.fi-main-sidebar');

            if (! sidebar || sidebar.dataset.zzHoverExpansionReady === 'true') {
                return;
            }

            sidebar.dataset.zzHoverExpansionReady = 'true';

            const desktopPointer = window.matchMedia('(min-width: 1025px) and (hover: hover) and (pointer: fine)');
            const sidebarStore = () => window.Alpine?.store('sidebar');
            const open = () => desktopPointer.matches && sidebarStore()?.open();
            const close = () => desktopPointer.matches && sidebarStore()?.close();

            sidebar.addEventListener('mouseenter', open);
            sidebar.addEventListener('mouseleave', close);
            sidebar.addEventListener('focusin', open);
            sidebar.addEventListener('focusout', (event) => {
                if (! sidebar.contains(event.relatedTarget)) {
                    close();
                }
            });

            if (! navigation.hoverExpansionInitialized) {
                navigation.hoverExpansionInitialized = true;

                const collapseWhenReady = () => window.requestAnimationFrame(close);

                if (sidebarStore()) {
                    collapseWhenReady();
                } else {
                    window.addEventListener('alpine:initialized', collapseWhenReady, { once: true });
                }
            }
        };

        const hydrateChildIcons = () => {
            const parser = new DOMParser();

            document.querySelectorAll('.zz-sidebar-child-item[data-zz-sidebar-icon]').forEach((item) => {
                const button = item.querySelector(':scope > .fi-sidebar-item-btn');

                if (! button || button.querySelector(':scope > .zz-sidebar-child-icon')) {
                    return;
                }

                try {
                    const iconDocument = parser.parseFromString(
                        window.atob(item.dataset.zzSidebarIcon),
                        'image/svg+xml',
                    );
                    const icon = iconDocument.documentElement;

                    if (icon.nodeName.toLowerCase() !== 'svg') {
                        return;
                    }

                    const renderedIcon = document.importNode(icon, true);
                    const groupedBorder = button.querySelector(':scope > .fi-sidebar-item-grouped-border');

                    if (groupedBorder) {
                        groupedBorder.replaceWith(renderedIcon);

                        return;
                    }

                    button.prepend(renderedIcon);
                } catch {
                    // Keep Filament's original submenu marker if SVG hydration fails.
                }
            });
        };

        const revealSidebar = () => {
            const sidebar = document.querySelector('.fi-main-sidebar');

            if (! sidebar) {
                return;
            }

            // Filament removes x-cloak when Alpine initializes. Keep the
            // navigation usable if another client-side error delays that
            // initialization and would otherwise leave the sidebar blank.
            sidebar.removeAttribute('x-cloak');

            if (window.getComputedStyle(sidebar).display === 'none') {
                sidebar.style.setProperty('display', 'flex', 'important');
            }
        };

        const setOpen = (menu, open) => {
            menu.classList.toggle('zz-sidebar-submenu-open', open);

            const trigger = menu.querySelector(triggerSelector);
            trigger?.setAttribute('aria-expanded', open ? 'true' : 'false');
        };

        const prepareMenus = () => {
            revealSidebar();
            hydrateChildIcons();
            setupHoverExpansion();
            document.documentElement.classList.add('zz-sidebar-navigation-ready');

            const menus = document.querySelectorAll(menuSelector);
            const activeMenu = document.querySelector(`${menuSelector}.zz-sidebar-route-active`);

            menus.forEach((menu) => {
                const trigger = menu.querySelector(triggerSelector);

                if (! trigger) {
                    return;
                }

                trigger.setAttribute('role', 'button');
                trigger.setAttribute('tabindex', '0');
                trigger.setAttribute('aria-label', `Toggle ${menu.dataset.zzSidebarMenu} submenu`);
                setOpen(menu, menu === activeMenu);
            });
        };

        const toggleMenu = (menu) => {
            const shouldOpen = ! menu.classList.contains('zz-sidebar-submenu-open');

            document.querySelectorAll(menuSelector).forEach((candidate) => {
                setOpen(candidate, shouldOpen && candidate === menu);
            });
        };

        navigation.prepare = prepareMenus;
        navigation.toggle = toggleMenu;
        window.ZamZamSidebarNavigation = navigation;

        if (! navigation.listenersAttached) {
            navigation.listenersAttached = true;

            document.addEventListener('click', (event) => {
                const trigger = event.target.closest('.fi-sidebar-item-btn');
                const menu = trigger?.parentElement;

                if (! menu?.matches(menuSelector)) {
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();
                window.ZamZamSidebarNavigation.toggle(menu);
            }, true);

            document.addEventListener('keydown', (event) => {
                if (! ['Enter', ' '].includes(event.key)) {
                    return;
                }

                const trigger = event.target.closest('.fi-sidebar-item-btn');
                const menu = trigger?.parentElement;

                if (! menu?.matches(menuSelector)) {
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();
                window.ZamZamSidebarNavigation.toggle(menu);
            }, true);

            document.addEventListener('livewire:navigated', () => {
                window.requestAnimationFrame(() => {
                    window.ZamZamSidebarNavigation.prepare();
                });
            });
        }

        const schedulePrepare = () => {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => {
                    window.requestAnimationFrame(() => {
                        window.ZamZamSidebarNavigation.prepare();
                    });
                }, { once: true });

                return;
            }

            window.requestAnimationFrame(() => {
                prepareMenus();
            });
        };

        schedulePrepare();
    })();
</script>
