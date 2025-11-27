<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncFlightScheduleAvialability extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-flight-schedule-avialability';

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

        foreach (\App\Models\FlightSchedule::where('departure', '<', date('Y-m-d H:i:s'))->get() as $old_flight) {
            // $old_flight->availablities()->delete();
            $old_flight->availablities()->update(['seats' => 0]);
            $old_flight->delete();
        }

        foreach (\App\Models\FlightSchedule::where('departure', '>=', date('Y-m-d H:i:s'))->orderBy('departure')->get() as $flight) {

            // [Deprecated] => in flight schedule to created only
            // \App\Jobs\GetFlightClassbandInformation::dispatch($flight)->onQueue('videcom-low');
            \App\Jobs\GetFlightClassbandInformation::dispatch($flight)->onQueue($flight->aero_token->getQueueId())->delay(now()->addSeconds(5));
            // TODO: Change on production and chain seats availability

            // \App\Jobs\GetFlightClassbandInformation::dispatch($flight)->chain([
            //     new \App\Jobs\CheckFlightSeatAvailabilityJob($flight),
            // ]);

            $this->info("Syncing Flight " . date('dM', strtotime($flight->departure)) . " $flight->flight_number || $flight->origin -> $flight->destination");
        }
    }
}
