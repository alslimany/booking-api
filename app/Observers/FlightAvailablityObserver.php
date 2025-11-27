<?php

namespace App\Observers;

use App\Models\FlightAvailablity;

class FlightAvailablityObserver
{
    /**
     * Handle the FlightAvailablity "created" event.
     */
    public function created(FlightAvailablity $flightAvailablity): void
    {
        //
    }

    /**
     * Handle the FlightAvailablity "updated" event.
     */
    public function updated(FlightAvailablity $flightAvailablity): void
    {
        if ($flightAvailablity->flight_schedule != null) {
            if ($flightAvailablity->flight_schedule->aircraft_code == null || $flightAvailablity->flight_schedule->aircraft_code <= 0) {
                if ($flightAvailablity->aircraft_code != null && $flightAvailablity->aircraft_code > 0) {
                    $flightAvailablity->flight_schedule->aircraft_code = $flightAvailablity->aircraft_code;
                    $flightAvailablity->flight_schedule->save();
                }
            }
        }
    }

    /**
     * Handle the FlightAvailablity "deleted" event.
     */
    public function deleted(FlightAvailablity $flightAvailablity): void
    {
        //
    }

    /**
     * Handle the FlightAvailablity "restored" event.
     */
    public function restored(FlightAvailablity $flightAvailablity): void
    {
        //
    }

    /**
     * Handle the FlightAvailablity "force deleted" event.
     */
    public function forceDeleted(FlightAvailablity $flightAvailablity): void
    {
        //
    }
}
