<?php

namespace ShakewellAgency\PassKitLaravel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Console\Scheduling\Schedule;
use ShakewellAgency\PassKitLaravel\Services\PassKitService;
use ShakewellAgency\PassKitLaravel\Services\PassKitCrudManager;
use ShakewellAgency\PassKitLaravel\Console\Commands\PassKitSetupCommand;
use ShakewellAgency\PassKitLaravel\Console\Commands\PassKitTestCommand;
use ShakewellAgency\PassKitLaravel\Console\Commands\PassKitSyncCommand;
use ShakewellAgency\PassKitLaravel\Services\PassKitSyncService;

class PassKitServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/passkit.php', 'passkit'
        );

        $this->app->singleton(PassKitService::class, function ($app) {
            return new PassKitService();
        });

        $this->app->singleton(PassKitCrudManager::class, function ($app) {
            return new PassKitCrudManager($app->make(PassKitService::class));
        });

        $this->app->singleton(PassKitSyncService::class, function ($app) {
            return new PassKitSyncService($app->make(PassKitService::class));
        });

        $this->app->alias(PassKitService::class, 'passkit');
        $this->app->alias(PassKitCrudManager::class, 'passkit.crud');
        $this->app->alias(PassKitSyncService::class, 'passkit.sync');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/passkit.php' => config_path('passkit.php'),
            ], 'passkit-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'passkit-migrations');

            $this->commands([
                PassKitSetupCommand::class,
                PassKitTestCommand::class,
                PassKitSyncCommand::class,
            ]);
        }

        // Register scheduled tasks
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $this->registerScheduledTasks($schedule);
        });
    }

    /**
     * Register PassKit sync scheduled tasks
     */
    protected function registerScheduledTasks(Schedule $schedule): void
    {
        // Only register if sync scheduling is enabled
        if (!config('passkit.sync.enabled', true)) {
            return;
        }

        // Incremental sync every 15 minutes (most frequent)
        $schedule->command('passkit:sync --type=incremental')
            ->everyFifteenMinutes()
            ->withoutOverlapping(10) // 10 minute timeout
            ->runInBackground()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/passkit-incremental-sync.log'));

        // Member sync every hour
        $schedule->command('passkit:sync --type=members')
            ->hourly()
            ->withoutOverlapping(30)
            ->runInBackground()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/passkit-member-sync.log'));

        // Transaction sync every 2 hours
        $schedule->command('passkit:sync --type=transactions')
            ->everyTwoHours()
            ->withoutOverlapping(45)
            ->runInBackground()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/passkit-transaction-sync.log'));

        // Program and template sync every 6 hours (less frequent)
        $schedule->command('passkit:sync --type=programs')
            ->everySixHours()
            ->withoutOverlapping(20)
            ->runInBackground()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/passkit-program-sync.log'));

        $schedule->command('passkit:sync --type=templates')
            ->everySixHours()
            ->at('30') // Offset by 30 minutes from programs
            ->withoutOverlapping(20)
            ->runInBackground()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/passkit-template-sync.log'));

        // Full sync once daily at 2 AM
        $schedule->command('passkit:sync --type=full --force')
            ->dailyAt('02:00')
            ->withoutOverlapping(120) // 2 hour timeout for full sync
            ->runInBackground()
            ->onOneServer()
            ->emailOutputOnFailure(config('passkit.sync.alert_email'))
            ->appendOutputTo(storage_path('logs/passkit-full-sync.log'));

        // Weekly deep sync with cleanup (Sundays at 3 AM)
        $schedule->command('passkit:sync --type=full --force')
            ->weeklyOn(0, '03:00') // Sunday at 3 AM
            ->withoutOverlapping(180) // 3 hour timeout
            ->runInBackground()
            ->onOneServer()
            ->emailOutputOnFailure(config('passkit.sync.alert_email'))
            ->appendOutputTo(storage_path('logs/passkit-weekly-sync.log'));
    }
}