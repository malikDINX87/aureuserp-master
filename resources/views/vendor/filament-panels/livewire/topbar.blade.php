<div class="fi-topbar-ctn">
    @php
        $navigation = filament()->getNavigation();
        $isRtl = __('filament-panels::layout.direction') === 'rtl';
        $isSidebarCollapsibleOnDesktop = filament()->isSidebarCollapsibleOnDesktop();
        $isSidebarFullyCollapsibleOnDesktop = filament()->isSidebarFullyCollapsibleOnDesktop();
        $hasTopNavigation = filament()->hasTopNavigation();
        $hasNavigation = filament()->hasNavigation();
        $hasTenancy = filament()->hasTenancy();
        $isAdminPanel = filament()->getCurrentPanel()->getId() === 'admin';
    @endphp

    <nav class="fi-topbar dinx-topbar">
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::TOPBAR_START) }}

        @if ($hasNavigation)
            <x-filament::icon-button
                color="gray"
                :icon="\Filament\Support\Icons\Heroicon::OutlinedBars3"
                :icon-alias="\Filament\View\PanelsIconAlias::TOPBAR_OPEN_SIDEBAR_BUTTON"
                icon-size="lg"
                :label="__('filament-panels::layout.actions.sidebar.expand.label')"
                x-cloak
                x-data="{}"
                x-on:click="$store.sidebar.open()"
                x-show="! $store.sidebar.isOpen"
                class="fi-topbar-open-sidebar-btn"
            />

            <x-filament::icon-button
                color="gray"
                :icon="\Filament\Support\Icons\Heroicon::OutlinedXMark"
                :icon-alias="\Filament\View\PanelsIconAlias::TOPBAR_CLOSE_SIDEBAR_BUTTON"
                icon-size="lg"
                :label="__('filament-panels::layout.actions.sidebar.collapse.label')"
                x-cloak
                x-data="{}"
                x-on:click="$store.sidebar.close()"
                x-show="$store.sidebar.isOpen"
                class="fi-topbar-close-sidebar-btn"
            />
        @endif

        <div class="fi-topbar-start dinx-topbar-start">
            @if ($isSidebarCollapsibleOnDesktop)
                <x-filament::icon-button
                    color="gray"
                    :icon="$isRtl ? \Filament\Support\Icons\Heroicon::OutlinedChevronLeft : \Filament\Support\Icons\Heroicon::OutlinedChevronRight"
                    :icon-alias="
                        $isRtl
                        ? [
                            \Filament\View\PanelsIconAlias::SIDEBAR_EXPAND_BUTTON_RTL,
                            \Filament\View\PanelsIconAlias::SIDEBAR_EXPAND_BUTTON,
                        ]
                        : \Filament\View\PanelsIconAlias::SIDEBAR_EXPAND_BUTTON
                    "
                    icon-size="lg"
                    :label="__('filament-panels::layout.actions.sidebar.expand.label')"
                    x-cloak
                    x-data="{}"
                    x-on:click="$store.sidebar.open()"
                    x-show="! $store.sidebar.isOpen"
                    class="fi-topbar-open-collapse-sidebar-btn"
                />
            @endif

            @if ($isSidebarCollapsibleOnDesktop || $isSidebarFullyCollapsibleOnDesktop)
                <x-filament::icon-button
                    color="gray"
                    :icon="$isRtl ? \Filament\Support\Icons\Heroicon::OutlinedChevronRight : \Filament\Support\Icons\Heroicon::OutlinedChevronLeft"
                    :icon-alias="
                        $isRtl
                        ? [
                            \Filament\View\PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON_RTL,
                            \Filament\View\PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON,
                        ]
                        : \Filament\View\PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON
                    "
                    icon-size="lg"
                    :label="__('filament-panels::layout.actions.sidebar.collapse.label')"
                    x-cloak
                    x-data="{}"
                    x-on:click="$store.sidebar.close()"
                    x-show="$store.sidebar.isOpen"
                    class="fi-topbar-close-collapse-sidebar-btn"
                />
            @endif

            @if ($isAdminPanel)
                <div class="dinx-topbar-heading">
                    <p class="dinx-topbar-title">DINX ERP</p>
                    <p class="dinx-topbar-subtitle">Operations Workspace</p>
                </div>
            @else
                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::TOPBAR_LOGO_BEFORE) }}

                @if ($homeUrl = filament()->getHomeUrl())
                    <a {{ \Filament\Support\generate_href_html($homeUrl) }}>
                        <x-filament-panels::logo />
                    </a>
                @else
                    <x-filament-panels::logo />
                @endif

                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::TOPBAR_LOGO_AFTER) }}
            @endif
        </div>

        @if (! $isAdminPanel && ($hasTopNavigation || (! $hasNavigation)))
            @if ($hasTenancy && filament()->hasTenantMenu())
                <x-filament-panels::tenant-menu />
            @endif

            @if ($hasNavigation)
                <ul class="fi-topbar-nav-groups">
                    @foreach ($navigation as $group)
                        @php
                            $groupLabel = $group->getLabel();
                            $groupIcon = $group->getIcon();
                            $isGroupActive = $group->isActive();
                        @endphp

                        @if ($groupLabel)
                            <x-filament-panels::topbar.item
                                :active="$isGroupActive"
                                :icon="$groupIcon"
                            >
                                {{ $groupLabel }}
                            </x-filament-panels::topbar.item>
                        @endif

                        @foreach ($group->getItems() as $item)
                            @php
                                $isItemActive = $item->isActive();
                                $itemActiveIcon = $item->getActiveIcon();
                                $itemBadge = $item->getBadge();
                                $itemBadgeColor = $item->getBadgeColor();
                                $itemBadgeTooltip = $item->getBadgeTooltip();
                                $itemIcon = $item->getIcon();
                                $shouldItemOpenUrlInNewTab = $item->shouldOpenUrlInNewTab();
                                $itemUrl = $item->getUrl();
                            @endphp

                            <x-filament-panels::topbar.item
                                :active="$isItemActive"
                                :active-icon="$itemActiveIcon"
                                :badge="$itemBadge"
                                :badge-color="$itemBadgeColor"
                                :badge-tooltip="$itemBadgeTooltip"
                                :icon="$itemIcon"
                                :should-open-url-in-new-tab="$shouldItemOpenUrlInNewTab"
                                :url="$itemUrl"
                            >
                                {{ $item->getLabel() }}
                            </x-filament-panels::topbar.item>
                        @endforeach
                    @endforeach
                </ul>
            @endif
        @endif

        <div
            @if ($hasTenancy)
                x-persist="topbar.end.panel-{{ filament()->getId() }}.tenant-{{ filament()->getTenant()?->getKey() }}"
            @else
                x-persist="topbar.end.panel-{{ filament()->getId() }}"
            @endif
            class="fi-topbar-end dinx-topbar-end"
        >
            @if ($hasTenancy && filament()->hasTenantMenu())
                <x-filament-panels::tenant-menu />
            @endif

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::GLOBAL_SEARCH_BEFORE) }}

            @if (filament()->isGlobalSearchEnabled())
                @livewire(Filament\Livewire\GlobalSearch::class)
            @endif

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::GLOBAL_SEARCH_AFTER) }}

            @if (filament()->auth()->check())
                @if (filament()->hasDatabaseNotifications())
                    @livewire(Filament\Livewire\DatabaseNotifications::class, [
                        'lazy' => filament()->hasLazyLoadedDatabaseNotifications(),
                    ])
                @endif

                @if (filament()->hasUserMenu())
                    <x-filament-panels::user-menu />
                @endif
            @endif
        </div>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::TOPBAR_END) }}
    </nav>

    <x-filament-actions::modals />
</div>
