<?php

namespace App\Providers;

use App\Jobs\PublishDeviceCommand;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureDevCommands();
    }

    /**
     * Extra processes for `php artisan dev` (`composer dev`), next to the
     * framework's serve/queue/logs/vite and Reverb's own reverb:start.
     */
    protected function configureDevCommands(): void
    {
        // The framework's `queue` process only works the default queue (the
        // broadcasts), so device commands need their own worker. queue:work
        // stays booted between jobs and polls every 0.2 s when idle, which
        // keeps approve-to-device latency low — but it also keeps old code
        // in memory, so it must be restarted after changing job code.
        DevCommands::artisan('queue:work --queue='.PublishDeviceCommand::QUEUE.' --sleep=0.2', 'device-commands');

        DevCommands::artisan('mqtt:listen', 'mqtt');
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
