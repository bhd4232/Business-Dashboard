@php
    $permissionLabels = \App\Models\User::CUSTOM_PERMISSION_OPTIONS;
    $permissions = collect($record->permissions ?? [])
        ->filter()
        ->map(fn (string $permission): string => $permissionLabels[$permission] ?? str($permission)->replace('.', ': ')->headline()->toString())
        ->values();

    $visiblePermissions = $permissions->take(2);
    $hiddenPermissions = $permissions->slice(2)->values();
@endphp

@once
    <style>
        .zz-permission-preview {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 0.375rem;
            max-width: 32rem;
            overflow: visible;
        }

        .zz-permission-badge {
            align-items: center;
            background: rgb(245 158 11 / 0.12);
            border: 1px solid rgb(245 158 11 / 0.42);
            border-radius: 0.375rem;
            color: rgb(217 119 6);
            display: inline-flex;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1rem;
            padding: 0.125rem 0.5rem;
            white-space: nowrap;
        }

        .dark .zz-permission-badge {
            color: rgb(252 211 77);
        }

        .zz-permission-empty {
            color: rgb(107 114 128);
            font-size: 0.75rem;
            line-height: 1rem;
        }

        .dark .zz-permission-empty {
            color: rgb(156 163 175);
        }

        .zz-permission-more {
            display: inline-flex;
        }

        .zz-permission-popover {
            background: white;
            border: 1px solid rgb(229 231 235);
            border-radius: 0.5rem;
            box-shadow: 0 10px 25px rgb(0 0 0 / 0.16);
            color: rgb(55 65 81);
            font-size: 0.75rem;
            isolation: isolate;
            line-height: 1rem;
            padding: 0.75rem;
            position: fixed;
            width: 18rem;
            z-index: 9999;
        }

        .zz-permission-more-trigger {
            align-items: center;
            background: rgb(245 158 11 / 0.12);
            border: 1px solid rgb(245 158 11 / 0.5);
            border-radius: 9999px;
            color: rgb(217 119 6);
            cursor: default;
            display: inline-flex;
            font-size: 0.75rem;
            font-weight: 700;
            height: 1.5rem;
            line-height: 1rem;
            outline: none;
            padding: 0 0.5rem;
            white-space: nowrap;
        }

        .dark .zz-permission-more-trigger {
            color: rgb(252 211 77);
        }

        .dark .zz-permission-popover {
            background: rgb(17 24 39);
            border-color: rgb(55 65 81);
            color: rgb(229 231 235);
        }

        .zz-permission-popover-title {
            color: rgb(17 24 39);
            display: block;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .dark .zz-permission-popover-title {
            color: rgb(243 244 246);
        }

        .zz-permission-popover-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.375rem;
        }

        .zz-permission-popover-badge {
            background: rgb(249 250 251);
            border: 1px solid rgb(229 231 235);
            border-radius: 0.375rem;
            display: inline-flex;
            font-weight: 600;
            padding: 0.125rem 0.5rem;
        }

        .dark .zz-permission-popover-badge {
            background: rgb(31 41 55);
            border-color: rgb(55 65 81);
        }
    </style>
@endonce

<div class="zz-permission-preview">
    @forelse ($visiblePermissions as $permission)
        <span class="zz-permission-badge">
            {{ $permission }}
        </span>
    @empty
        <span class="zz-permission-empty">No permissions</span>
    @endforelse

    @if ($hiddenPermissions->isNotEmpty())
        <span
            class="zz-permission-more"
            x-data="{
                open: false,
                x: 0,
                y: 0,
                place() {
                    const rect = this.$refs.trigger.getBoundingClientRect()
                    this.x = Math.max(8, Math.min(rect.left, window.innerWidth - 296))
                    this.y = Math.min(rect.bottom + 8, window.innerHeight - 180)
                },
                show() {
                    this.place()
                    this.open = true
                },
                hide() {
                    this.open = false
                },
            }"
            x-on:keydown.escape.window="hide()"
            x-on:resize.window="open && place()"
            x-on:scroll.window="open && place()"
        >
            <span
                x-ref="trigger"
                role="button"
                tabindex="0"
                class="zz-permission-more-trigger"
                aria-label="Show remaining permissions"
                x-on:mouseenter="show()"
                x-on:mouseleave="hide()"
                x-on:focus="show()"
                x-on:blur="hide()"
                x-on:click.stop.prevent="show()"
            >
                <span aria-hidden="true">+</span>{{ $hiddenPermissions->count() }}
            </span>

            <template x-teleport="body">
                <span
                    x-cloak
                    x-show="open"
                    x-transition.opacity.duration.100ms
                    class="zz-permission-popover"
                    x-bind:style="`left: ${x}px; top: ${y}px;`"
                    x-on:mouseenter="open = true"
                    x-on:mouseleave="hide()"
                >
                    <span class="zz-permission-popover-title">More permissions</span>
                    <span class="zz-permission-popover-list">
                        @foreach ($hiddenPermissions as $permission)
                            <span class="zz-permission-popover-badge">
                                {{ $permission }}
                            </span>
                        @endforeach
                    </span>
                </span>
            </template>
        </span>
    @endif
</div>
