<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncCanceledFlights extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-canceled-flights';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        foreach (\App\Models\FlightSchedule::where('departure', '>=', now())->get() as $flight_schedule) {
            if ($flight_schedule->aero_token != null) {
                $this->info("" . $flight_schedule->id);
                dispatch(new \App\Jobs\CheckFlightCanellationJob($flight_schedule))->onQueue($flight_schedule->aero_token->getQueueId())->delay(now()->addSeconds(5));
            }
        }
    }
}
