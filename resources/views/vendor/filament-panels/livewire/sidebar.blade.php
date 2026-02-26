<div>
    @php
        $navigation = filament()->getNavigation();
        $isRtl = __('filament-panels::layout.direction') === 'rtl';
        $isSidebarCollapsibleOnDesktop = filament()->isSidebarCollapsibleOnDesktop();
        $isSidebarFullyCollapsibleOnDesktop = filament()->isSidebarFullyCollapsibleOnDesktop();
        $isAdminPanel = filament()->getCurrentPanel()->getId() === 'admin';

        $primaryMap = [
            'Projects' => '/admin/erp/projects',
            'Contracts' => '/admin/erp/contracts',
            'Invoices' => '/admin/erp/invoices',
            'Accounting' => '/admin/erp/accounting',
            'Reports' => '/admin/erp/reports',
            'Settings' => '/admin/erp/settings',
        ];

        $groupedItems = collect($navigation)
            ->flatMap(fn ($group) => collect($group->getItems())->map(fn ($item) => [
                'group' => $group,
                'item' => $item,
            ]))
            ->values();

        $usedItemIds = [];
        $primaryItems = collect();

        foreach ($primaryMap as $label => $path) {
            $entry = $groupedItems->first(function ($entry) use ($label, $path) {
                $item = $entry['item'];
                $url = (string) ($item->getUrl() ?? '');
                $urlPath = (string) (parse_url($url, PHP_URL_PATH) ?? $url);

                return rtrim($urlPath, '/') === rtrim($path, '/')
                    || \Illuminate\Support\Str::lower((string) $item->getLabel()) === \Illuminate\Support\Str::lower($label);
            });

            if (! $entry) {
                continue;
            }

            $primaryItems->push($entry['item']);
            $usedItemIds[] = spl_object_id($entry['item']);
        }

        $moreGroups = collect($navigation)
            ->map(function ($group) use ($usedItemIds) {
                $remaining = collect($group->getItems())
                    ->reject(fn ($item) => in_array(spl_object_id($item), $usedItemIds, true))
                    ->values();

                if ($remaining->isEmpty()) {
                    return null;
                }

                return [
                    'label' => $group->getLabel(),
                    'items' => $remaining,
                ];
            })
            ->filter()
            ->values();
    @endphp

    {{-- format-ignore-start --}}
    <aside
        x-data="{}"
        @if ($isSidebarCollapsibleOnDesktop || $isSidebarFullyCollapsibleOnDesktop)
            x-cloak
        @else
            x-cloak="-lg"
        @endif
        x-bind:class="{ 'fi-sidebar-open': $store.sidebar.isOpen }"
        class="fi-sidebar fi-main-sidebar"
    >
        <div class="fi-sidebar-header-ctn">
            <header class="fi-sidebar-header">
                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_LOGO_BEFORE) }}

                <div class="fi-dinx-sidebar-brand">
                    @if ($homeUrl = filament()->getHomeUrl())
                        <a {{ \Filament\Support\generate_href_html($homeUrl) }} class="fi-dinx-sidebar-logo">
                            <x-filament-panels::logo />
                        </a>
                    @else
                        <span class="fi-dinx-sidebar-logo">
                            <x-filament-panels::logo />
                        </span>
                    @endif

                    <div>
                        <p class="fi-dinx-sidebar-title">DINX ERP</p>
                        <p class="fi-dinx-sidebar-subtitle">Operations Dashboard</p>
                    </div>
                </div>

                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_LOGO_AFTER) }}
            </header>

            @if ($isAdminPanel)
                <div class="fi-dinx-workspace">
                    <p class="fi-dinx-workspace-title">DINX Workspace</p>
                    <p class="fi-dinx-workspace-copy">ERP Admin</p>
                    <a href="https://dinxsolutions.com/dashboard/apps" class="fi-dinx-workspace-link" target="_blank" rel="noreferrer">
                        Back to DINX Apps
                    </a>
                </div>
            @endif
        </div>

        <nav class="fi-sidebar-nav">
            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_NAV_START) }}

            @if (filament()->hasTenancy() && filament()->hasTenantMenu())
                <div class="fi-sidebar-nav-tenant-menu-ctn">
                    <x-filament-panels::tenant-menu />
                </div>
            @endif

            <ul class="fi-sidebar-nav-groups">
                @if ($primaryItems->isNotEmpty())
                    <li class="fi-dinx-sidebar-section">
                        <p class="fi-dinx-sidebar-section-label">DINX ERP</p>

                        <ul class="fi-sidebar-group-items fi-dinx-sidebar-group-items">
                            @foreach ($primaryItems as $item)
                                @php
                                    $isItemActive = $item->isActive();
                                    $isItemChildItemsActive = $item->isChildItemsActive();
                                    $itemActiveIcon = $item->getActiveIcon();
                                    $itemBadge = $item->getBadge();
                                    $itemBadgeColor = $item->getBadgeColor();
                                    $itemBadgeTooltip = $item->getBadgeTooltip();
                                    $itemChildItems = $item->getChildItems();
                                    $itemIcon = $item->getIcon();
                                    $shouldItemOpenUrlInNewTab = $item->shouldOpenUrlInNewTab();
                                    $itemUrl = $item->getUrl();
                                @endphp

                                <x-filament-panels::sidebar.item
                                    :active="$isItemActive"
                                    :active-child-items="$isItemChildItemsActive"
                                    :active-icon="$itemActiveIcon"
                                    :badge="$itemBadge"
                                    :badge-color="$itemBadgeColor"
                                    :badge-tooltip="$itemBadgeTooltip"
                                    :child-items="$itemChildItems"
                                    :first="$loop->first"
                                    :grouped="false"
                                    :icon="$itemIcon"
                                    :last="$loop->last"
                                    :should-open-url-in-new-tab="$shouldItemOpenUrlInNewTab"
                                    sidebar-collapsible="true"
                                    :url="$itemUrl"
                                >
                                    {{ $item->getLabel() }}

                                    @if ($itemIcon instanceof \Illuminate\Contracts\Support\Htmlable)
                                        <x-slot name="icon">
                                            {{ $itemIcon }}
                                        </x-slot>
                                    @endif

                                    @if ($itemActiveIcon instanceof \Illuminate\Contracts\Support\Htmlable)
                                        <x-slot name="activeIcon">
                                            {{ $itemActiveIcon }}
                                        </x-slot>
                                    @endif
                                </x-filament-panels::sidebar.item>
                            @endforeach
                        </ul>
                    </li>
                @endif

                @if ($moreGroups->isNotEmpty())
                    <li class="fi-dinx-sidebar-section" x-data="{ open: false }">
                        <button
                            type="button"
                            @click="open = ! open"
                            x-bind:aria-expanded="open ? 'true' : 'false'"
                            class="fi-dinx-more-modules-toggle"
                        >
                            <span>More Modules</span>
                            <span class="fi-dinx-more-modules-icon" x-bind:class="{ 'fi-rotate-180': open }">v</span>
                        </button>

                        <div x-show="open" x-cloak x-transition.opacity.duration.150ms class="fi-dinx-more-modules-body">
                            @foreach ($moreGroups as $group)
                                <div class="fi-dinx-more-group">
                                    @if ($group['label'])
                                        <p class="fi-dinx-sidebar-section-label">{{ $group['label'] }}</p>
                                    @endif

                                    <ul class="fi-sidebar-group-items fi-dinx-sidebar-group-items">
                                        @foreach ($group['items'] as $item)
                                            @php
                                                $isItemActive = $item->isActive();
                                                $isItemChildItemsActive = $item->isChildItemsActive();
                                                $itemActiveIcon = $item->getActiveIcon();
                                                $itemBadge = $item->getBadge();
                                                $itemBadgeColor = $item->getBadgeColor();
                                                $itemBadgeTooltip = $item->getBadgeTooltip();
                                                $itemChildItems = $item->getChildItems();
                                                $itemIcon = $item->getIcon();
                                                $shouldItemOpenUrlInNewTab = $item->shouldOpenUrlInNewTab();
                                                $itemUrl = $item->getUrl();
                                            @endphp

                                            <x-filament-panels::sidebar.item
                                                :active="$isItemActive"
                                                :active-child-items="$isItemChildItemsActive"
                                                :active-icon="$itemActiveIcon"
                                                :badge="$itemBadge"
                                                :badge-color="$itemBadgeColor"
                                                :badge-tooltip="$itemBadgeTooltip"
                                                :child-items="$itemChildItems"
                                                :first="$loop->first"
                                                :grouped="false"
                                                :icon="$itemIcon"
                                                :last="$loop->last"
                                                :should-open-url-in-new-tab="$shouldItemOpenUrlInNewTab"
                                                sidebar-collapsible="true"
                                                :url="$itemUrl"
                                            >
                                                {{ $item->getLabel() }}

                                                @if ($itemIcon instanceof \Illuminate\Contracts\Support\Htmlable)
                                                    <x-slot name="icon">
                                                        {{ $itemIcon }}
                                                    </x-slot>
                                                @endif

                                                @if ($itemActiveIcon instanceof \Illuminate\Contracts\Support\Htmlable)
                                                    <x-slot name="activeIcon">
                                                        {{ $itemActiveIcon }}
                                                    </x-slot>
                                                @endif
                                            </x-filament-panels::sidebar.item>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    </li>
                @endif
            </ul>

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_NAV_END) }}
        </nav>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_FOOTER) }}
    </aside>
    {{-- format-ignore-end --}}

    <x-filament-actions::modals />
</div>
