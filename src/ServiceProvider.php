<?php

namespace SocraNext\Statamic;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use SocraNext\Statamic\Support\StateStore;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    public const VERSION = '0.1.0';
    protected $viewNamespace = 'socranext';
    protected $config = false;
    protected $commands = [\SocraNext\Statamic\Console\InstallCommand::class, \SocraNext\Statamic\Console\DoctorCommand::class];

    public function register()
    {
        parent::register();
        $this->mergeConfigFrom(__DIR__.'/../config/socranext.php', 'socranext');
        $this->app->singleton(StateStore::class);
    }

    public function boot()
    {
        parent::boot();
        $this->publishes([__DIR__.'/../config/socranext.php' => config_path('socranext.php')], 'socranext-config');
        // HTTP API deliberately uses no browser session or CSRF exemption for other routes.
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->app['router']->pushMiddlewareToGroup('web', \SocraNext\Statamic\Http\Middleware\ManagedRedirect::class);
        RateLimiter::for('socranext-connect', fn ($request) => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('socranext-api', fn ($request) => Limit::perMinute(240)->by($request->ip()));
        Permission::register('configure socranext')->label('Configure SocraNext');
        Nav::extend(function ($nav) {
            $nav->tools('SocraNext')->route('socranext.index')->icon('link')->can('configure socranext');
        });
    }
}
