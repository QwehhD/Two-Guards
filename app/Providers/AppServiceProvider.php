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
     * Processes for `php artisan dev` (`composer dev`) on top of, or in place
     * of, the framework's serve/queue/logs/vite and Reverb's reverb:start.
     */
    protected function configureDevCommands(): void
    {
        // Replaces the framework's own `queue` process (same name, and app
        // registrations win), only adding --sleep=0.2: the broadcasts that put
        // a new pending scan on the Approvals page would otherwise wait up to
        // 3 s for the idle listener to poll again, out of a 30 s window.
        DevCommands::artisan('queue:listen --tries=1 --timeout=0 --sleep=0.2', 'queue');

        // The framework's `queue` process only works the default queue (the
        // broadcasts), so device commands need their own worker. queue:work
        // stays booted between jobs and polls every 0.2 s when idle, which
        // keeps approve-to-device latency low.
        //
        // It runs inside a shell loop because `php artisan dev` only restarts
        // a process that exits non-zero, and this worker never does: after a
        // job timeout Laravel ends the worker with SIGKILL, and
        // `php artisan queue:restart` makes it exit 0. Without the loop, one
        // hung attempt (or one queue:restart) would leave no worker for the
        // rest of the session; with it, queue:restart is simply how changed
        // job code gets loaded.
        DevCommands::register(
            'while true; do php artisan queue:work --queue='.PublishDeviceCommand::QUEUE.' --sleep=0.2; sleep 1; done',
            'device-commands',
        );

        DevCommands::artisan('mqtt:listen', 'mqtt');

        // Runs the scheduled tasks in routes/console.php (access-logs:expire-pending).
        DevCommands::artisan('schedule:work', 'schedule');
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
