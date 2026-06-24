<?php

declare(strict_types=1);

namespace Mirror;

use Illuminate\Support\ServiceProvider;
use Mirror\Contracts\Mirror;

class MirrorServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/mirror.php', 'mirror'
        );

        $this->app->scoped(SessionImpersonationStore::class);
        $this->app->scoped(Mirror::class, ImpersonationManager::class);
        $this->app->scoped(ImpersonationManager::class);

        $this->app->alias(Mirror::class, 'mirror');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->make(BladeDirectivesRegistrar::class)->register();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/mirror.php' => config_path('mirror.php'),
            ], 'mirror');
        }
    }
}
