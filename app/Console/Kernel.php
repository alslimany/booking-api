<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        ## Management Commands ##
        // $schedule->command('horizon:snapshot')->everyFiveMinutes();
        // $schedule->command('app:sync-airport-schedule')->everyFiveMinutes();

        $schedule->command('queue:prune-failed --hours=1')->everyTenMinutes();
        // $schedule->command('queue:retry all')->everyTenMinutes();

        ## Flight Systme Commands ##
        $schedule->command('app:flights-prune-command')->everyMinute();

        // if (date('H') < 22 && date('H') > 5) {


        // if (date('H') > 4) {
        // $schedule->command('app:check-flight-seat-availability-command --days=10 --queue=none')->everyTenMinutes();
        // $schedule->command('app:check-flight-seat-availability-command --days=4 --queue=none')->everyFifteenMinutes();
        // }
        // }

        // if (date('H') > 8 && date('H') < 23) {
        $schedule->command(command: 'app:update-aero-token-information')->hourly();
        // }
        // $schedule->command('app:sync-flight-schedule-command')->everySixHours();

        // $schedule->command('app:check-flight-seat-availability-command')->everyTwoHours();

        $schedule->command('app:purge-api-logs --days=90')->hourly();
        $schedule->command('app:sync-one-way-offer-fares-command')->hourly();
        $schedule->command('app:purge-command-requests --days=30')->hourly();

        $schedule->command('telescope:prune --hours=12')->hourly();

        $schedule->command(command: 'app:clear-aero-token-session-command')->everyFifteenMinutes();

        # Daily
        $schedule->command('app:sync-canceled-flights')->dailyAt("01:00");
        $schedule->command('app:sync-flight-schedule-command')->dailyAt("01:20");
        $schedule->command('app:update-fare-note')->dailyAt("01:50");
        $schedule->command('app:sync-flight-schedule-avialability')->dailyAt('02:00');
        // $schedule->command('app:check-flight-seat-availability-command')->dailyAt("02:15");
        // $schedule->command('app:sync-one-way-offer-fares-command')->dailyAt('02:30');
        // $schedule->command('app:sync-round-offer-fare-command')->dailyAt("03:00");
        // $schedule->command('app:check-flight-seat-availability-command')->dailyAt("05:00");
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
