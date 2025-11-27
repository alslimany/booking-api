<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class FlightsPruneCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:flights-prune-command';

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
        // \App\Jobs\FlightPruneJob::dispatch()->onQueue('default');

        foreach (\App\Models\FlightSchedule::where('departure', '<', date("Y-m-d H:i:s"))->get() as $flight_schedule) {
            // $flight_schedule->has_offers = 0;
            // $flight_schedule->save();

            $flight_schedule->delete();
        }
    }
}
