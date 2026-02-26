<?php

namespace Webkul\DinxErpSync;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Webkul\PluginManager\Package;

class DinxErpSyncPlugin implements Plugin
{
    public function getId(): string
    {
        return 'dinx-erp-sync';
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

        $panel
            ->when($panel->getId() == 'admin', function (Panel $panel) {
                $panel
                    ->discoverResources(
                        in: __DIR__.'/Filament/Admin/Resources',
                        for: 'Webkul\\DinxErpSync\\Filament\\Admin\\Resources'
                    )
                    ->discoverClusters(
                        in: __DIR__.'/Filament/Admin/Clusters',
                        for: 'Webkul\\DinxErpSync\\Filament\\Admin\\Clusters'
                    );
            });
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
