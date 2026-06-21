<?php

declare(strict_types=1);

namespace Mirror;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Mirror\Contracts\Mirror;
use Mirror\Http\Middleware\CheckImpersonationTtl;
use Mirror\Http\Middleware\PreventImpersonation;
use Mirror\Http\Middleware\RequireImpersonation;

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
        $this->registerMiddlewares();

        $this->app->make(BladeDirectivesRegistrar::class)->register();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/mirror.php' => config_path('mirror.php'),
            ], 'mirror');
        }
    }

    /**
     * Register the Mirror middlewares.
     */
    protected function registerMiddlewares(): void
    {
        Route::aliasMiddleware('mirror.prevent', PreventImpersonation::class)
            ->aliasMiddleware('mirror.require', RequireImpersonation::class)
            ->aliasMiddleware('mirror.ttl', CheckImpersonationTtl::class);
    }
}
