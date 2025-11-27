<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FlightPruneJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {

    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // [1] = Prune older schedules
        foreach (\App\Models\FlightSchedule::where('departure', '<', date("Y-m-d H:i:s"))->get() as $flight_schedule) {
            // $flight_schedule->has_offers = 0;
            // $flight_schedule->save();

            $flight_schedule->delete();
        }

        // [2] = Prune schedules where available seats = 0
        // foreach (\App\Models\FlightSchedule::where('has_offers', true)->get() as $flight_schedule) {
        //     if ($flight_schedule->availablities()->where('seats', '>', 0)->count() == 0) {
        //         $flight_schedule->has_offers = false;
        //         $flight_schedule->save();
        //     }
        // }
    }
}
