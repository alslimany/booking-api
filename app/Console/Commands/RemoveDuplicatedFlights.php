<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RemoveDuplicatedFlights extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:remove-duplicated-flights';

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
        $flight_schedules = \App\Models\FlightSchedule::where('departure', '>=', now())->where('canceled_at', null)->get();
        foreach ($flight_schedules as $flight_schedule) {
            $matched_flights =  \App\Models\FlightSchedule::where([
                'origin' => $flight_schedule->origin,
                'destination' => $flight_schedule->destination,
                'departure' => $flight_schedule->departure,
                'arrival' => $flight_schedule->arrival,
                'flight_number' => $flight_schedule->flight_number,
            ])->get();

            if (count($matched_flights) > 1) {
                \App\Models\FlightSchedule::where([
                    'origin' => $flight_schedule->origin,
                    'destination' => $flight_schedule->destination,
                    'departure' => $flight_schedule->departure,
                    'arrival' => $flight_schedule->arrival,
                    'flight_number' => $flight_schedule->flight_number,
                ])->where('id', '!=', $flight_schedule->id)
                ->delete();   

                $flight_schedules = \App\Models\FlightSchedule::where('departure', '>=', now())->where('canceled_at', null)->get();
            }
        }
    }
}
