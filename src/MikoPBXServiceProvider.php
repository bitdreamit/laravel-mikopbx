<?php

namespace BitDreamIT\MikoPBX;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use BitDreamIT\MikoPBX\Services\AMIService;
use BitDreamIT\MikoPBX\Services\RestApiService;
use BitDreamIT\MikoPBX\Services\ARIService;
use BitDreamIT\MikoPBX\Services\CampaignService;
use BitDreamIT\MikoPBX\Services\AgentService;
use BitDreamIT\MikoPBX\Services\RecordingService;
use BitDreamIT\MikoPBX\Services\BlacklistService;
use BitDreamIT\MikoPBX\Services\CallbackService;
use BitDreamIT\MikoPBX\Services\ConferenceService;
use BitDreamIT\MikoPBX\Services\IVRService;
use BitDreamIT\MikoPBX\Services\AnalyticsService;
use BitDreamIT\MikoPBX\Services\HealthCheckService;
use BitDreamIT\MikoPBX\Services\SmsService;
use BitDreamIT\MikoPBX\Services\WebDialerService;

class MikoPBXServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mikopbx.php', 'mikopbx');

        // Register MikoPBXManager as singleton facade target
        $this->app->singleton('mikopbx', fn($app) => new MikoPBXManager($app));

        // Bind each service
        foreach ([
            AMIService::class, RestApiService::class, ARIService::class,
            CampaignService::class, AgentService::class, RecordingService::class,
            BlacklistService::class, CallbackService::class, ConferenceService::class,
            IVRService::class, AnalyticsService::class, HealthCheckService::class,
            SmsService::class, WebDialerService::class,
        ] as $service) {
            $this->app->singleton($service);
        }
    }

    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerRoutes();
        $this->registerViews();
        $this->registerCommands();
        $this->registerMigrations();

        // Register Livewire components if Livewire is installed
        if (class_exists(\Livewire\Livewire::class)) {
            $this->registerLivewireComponents();
        }
    }

    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) return;

        $this->publishes([
            __DIR__.'/../config/mikopbx.php' => config_path('mikopbx.php'),
        ], 'mikopbx-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'mikopbx-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/mikopbx'),
        ], 'mikopbx-views');

        $this->publishes([
            __DIR__.'/../resources/js'  => resource_path('js/mikopbx'),
            __DIR__.'/../resources/css' => resource_path('css/mikopbx'),
        ], 'mikopbx-assets');
    }

    protected function registerRoutes(): void
    {
        if (! $this->app['config']->get('mikopbx.route_prefix')) return;

        Route::group([
            'prefix'     => config('mikopbx.route_prefix', 'pbx'),
            'middleware' => config('mikopbx.route_middleware', ['web', 'auth']),
            'as'         => 'mikopbx.',
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });

        Route::group([
            'prefix'     => 'api/'.config('mikopbx.route_prefix', 'pbx'),
            'middleware' => config('mikopbx.route_middleware', ['web', 'auth']),
            'as'         => 'mikopbx.api.',
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        });

        // Webhook — no auth (secured by secret token)
        Route::group(['prefix' => 'mikopbx-webhook', 'middleware' => ['api']], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/webhook.php');
        });
    }

    protected function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'mikopbx');
    }

    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) return;

        $this->commands([
            \BitDreamIT\MikoPBX\Commands\InstallCommand::class,
            \BitDreamIT\MikoPBX\Commands\AmiListenCommand::class,
            \BitDreamIT\MikoPBX\Commands\CdrSyncCommand::class,
            \BitDreamIT\MikoPBX\Commands\SyncExtensionsCommand::class,
            \BitDreamIT\MikoPBX\Commands\CampaignRunCommand::class,
            \BitDreamIT\MikoPBX\Commands\HealthCheckCommand::class,
        ]);
    }

    protected function registerLivewireComponents(): void
    {
        $components = [
            'mikopbx-live-call-board'    => \BitDreamIT\MikoPBX\Livewire\LiveCallBoard::class,
            'mikopbx-agent-status-grid'  => \BitDreamIT\MikoPBX\Livewire\AgentStatusGrid::class,
            'mikopbx-campaign-manager'   => \BitDreamIT\MikoPBX\Livewire\CampaignManager::class,
            'mikopbx-call-log-table'     => \BitDreamIT\MikoPBX\Livewire\CallLogTable::class,
            'mikopbx-blacklist-manager'  => \BitDreamIT\MikoPBX\Livewire\BlacklistManager::class,
            'mikopbx-pending-callbacks'  => \BitDreamIT\MikoPBX\Livewire\PendingCallbacks::class,
            'mikopbx-incoming-popup'     => \BitDreamIT\MikoPBX\Livewire\IncomingCallPopup::class,
            'mikopbx-ivr-builder'        => \BitDreamIT\MikoPBX\Livewire\IVRBuilderComponent::class,
            'mikopbx-analytics-dash'     => \BitDreamIT\MikoPBX\Livewire\AnalyticsDashboard::class,
            'mikopbx-health-monitor'     => \BitDreamIT\MikoPBX\Livewire\HealthMonitor::class,
        ];

        foreach ($components as $alias => $class) {
            \Livewire\Livewire::component($alias, $class);
        }
    }
}
