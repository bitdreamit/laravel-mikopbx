<?php

namespace BitDreamIT\MikoPBX;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use BitDreamIT\MikoPBX\Services\{AMIService,ARIService,RestApiService,CampaignService,AgentService,RecordingService,ConferenceService,BlacklistService,AnalyticsService,SmsNotificationService,CallbackService,HealthCheckService};
use BitDreamIT\MikoPBX\Commands\{AmiListenCommand,CampaignRunCommand,InstallCommand,SyncExtensionsCommand,HealthCheckCommand,CdrSyncCommand};

class MikoPBXServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/mikopbx.php', 'mikopbx');
        $this->app->singleton(RestApiService::class,      fn($a) => new RestApiService($a['config']['mikopbx']));
        $this->app->singleton(AMIService::class,          fn($a) => new AMIService($a['config']['mikopbx']));
        $this->app->singleton(ARIService::class,          fn($a) => new ARIService($a['config']['mikopbx']));
        $this->app->singleton(CampaignService::class,     fn($a) => new CampaignService($a->make(RestApiService::class)));
        $this->app->singleton(AgentService::class,        fn($a) => new AgentService($a->make(RestApiService::class), $a->make(AMIService::class)));
        $this->app->singleton(RecordingService::class,    fn($a) => new RecordingService($a->make(RestApiService::class), $a->make(ARIService::class), $a['config']['mikopbx']));
        $this->app->singleton(ConferenceService::class,   fn($a) => new ConferenceService($a->make(ARIService::class)));
        $this->app->singleton(BlacklistService::class,    fn($a) => new BlacklistService());
        $this->app->singleton(AnalyticsService::class,    fn($a) => new AnalyticsService($a->make(RestApiService::class)));
        $this->app->singleton(SmsNotificationService::class, fn($a) => new SmsNotificationService($a['config']['mikopbx']));
        $this->app->singleton(CallbackService::class,     fn($a) => new CallbackService($a->make(RestApiService::class), $a->make(AMIService::class)));
        $this->app->singleton(HealthCheckService::class,  fn($a) => new HealthCheckService($a->make(RestApiService::class), $a->make(AMIService::class)));
        $this->app->singleton('mikopbx', fn($a) => new MikoPBXManager(
            $a->make(RestApiService::class), $a->make(AMIService::class), $a->make(ARIService::class),
            $a->make(CampaignService::class), $a->make(AgentService::class), $a->make(RecordingService::class),
            $a->make(ConferenceService::class), $a->make(BlacklistService::class), $a->make(AnalyticsService::class),
            $a->make(CallbackService::class), $a->make(HealthCheckService::class),
        ));
    }

    public function boot(): void
    {
        $this->publishes([__DIR__ . '/../config/mikopbx.php'  => config_path('mikopbx.php')],   'mikopbx-config');
        $this->publishes([__DIR__ . '/../database/migrations' => database_path('migrations')],   'mikopbx-migrations');
        $this->publishes([__DIR__ . '/../resources/views'     => resource_path('views/vendor/mikopbx')], 'mikopbx-views');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'mikopbx');
        if (config('mikopbx.routes.enabled', true)) {
            Route::middleware(config('mikopbx.routes.middleware', ['api']))
                ->prefix(config('mikopbx.routes.prefix', 'mikopbx'))
                ->group(__DIR__ . '/../routes/api.php');
        }
        if ($this->app->runningInConsole()) {
            $this->commands([AmiListenCommand::class, CampaignRunCommand::class, InstallCommand::class, SyncExtensionsCommand::class, HealthCheckCommand::class, CdrSyncCommand::class]);
        }
    }
}
