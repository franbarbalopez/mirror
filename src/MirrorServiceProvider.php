<?php

namespace Mirror;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Application;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Mirror\Facades\Mirror;
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

        $this->app->singleton(ImpersonationSession::class, fn (Application $app): ImpersonationSession => new ImpersonationSession(
            $app->make(Store::class),
            $app->make(Repository::class)->get('app.key'),
        ));

        $this->app->singleton(Impersonator::class);

        $this->app->alias(Impersonator::class, 'mirror');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerMiddlewares();
        $this->registerBladeDirectives();

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

    /**
     * Register the Mirror Blade directives.
     */
    protected function registerBladeDirectives(): void
    {
        Blade::if('impersonating', fn (?string $guard = null): bool => $this->checkImpersonating($guard));
        Blade::if('canImpersonate', fn (?string $guard = null): bool => $this->checkCanImpersonate($guard));
        Blade::if('canBeImpersonated', fn (?Authenticatable $user = null, ?string $guard = null): bool => $this->checkCanBeImpersonated($user, $guard));
    }

    /**
     * Check if the authenticated model is impersonating.
     */
    protected function checkImpersonating(?string $guard): bool
    {
        if ($guard !== null) {
            return session()->has('mirror.impersonating') && session('mirror.guard_name') === $guard;
        }

        return Mirror::isImpersonating();
    }

    /**
     * Check if the authenticated model can impersonate.
     */
    protected function checkCanImpersonate(?string $guard): bool
    {
        $user = auth($guard)->user();

        if (! $user) {
            return false;
        }

        // @phpstan-ignore-next-line function.alreadyNarrowedType
        return method_exists($user, 'canImpersonate')
            ? $user->canImpersonate()
            : true;
    }

    /**
     * Check if the authenticated model can be impersonated.
     */
    protected function checkCanBeImpersonated(?Authenticatable $user, ?string $guard): bool
    {
        if (! $user instanceof Authenticatable) {
            $user = auth($guard)->user();
        }

        if (! $user) {
            return false;
        }

        // @phpstan-ignore-next-line function.alreadyNarrowedType
        return method_exists($user, 'canBeImpersonated')
            ? $user->canBeImpersonated()
            : true;
    }
}
