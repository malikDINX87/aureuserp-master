<?php

namespace Webkul\DinxCommerce;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Webkul\PluginManager\Package;

class DinxCommercePlugin implements Plugin
{
    public function getId(): string
    {
        return 'dinx-commerce';
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public function register(Panel $panel): void
    {
        if (! Package::isPluginInstalled($this->getId())) {
            return;
        }

        if ($panel->getId() !== 'admin') {
            return;
        }

        $panel
            ->discoverResources(
                in: __DIR__.'/Filament/Admin/Resources',
                for: 'Webkul\\DinxCommerce\\Filament\\Admin\\Resources'
            )
            ->discoverPages(
                in: __DIR__.'/Filament/Admin/Pages',
                for: 'Webkul\\DinxCommerce\\Filament\\Admin\\Pages'
            )
            ->discoverClusters(
                in: __DIR__.'/Filament/Admin/Clusters',
                for: 'Webkul\\DinxCommerce\\Filament\\Admin\\Clusters'
            );
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
