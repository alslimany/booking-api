<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FetchWebFlightSchedule implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $aero, $start_date, $airport, $to;
    /**
     * Create a new job instance.
     */
    public function __construct($aero, $start_date, $airport, $to)
    {
        $this->aero = $aero;
        $this->start_date = $start_date;
        $this->airport = $airport;
        $this->to = $to;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // sleep(1);

        $cache_key = $this->aero->iata . "_web_schedule_" . $this->start_date . '_' . $this->airport . '_' . '$$$';
        $schedule = [];

        if (cache()->has($cache_key)) {
            $schedule = cache()->get($cache_key);
        } else {
            $schedule = $this->aero->build()->web_schedule([
                'date' => $this->start_date,
                'from' => $this->airport,
                'to' => '$$$',
            ]);

            cache()->put($cache_key, $schedule, now()->addHours(6));
        }

        $mode = "";
        $execluded_airports = [];
        $included_airports = [];

        if (array_key_exists('airport_management_type', $this->aero->data)) {

            if ($this->aero->data['airport_management_type'] == 'execulde') {
                $mode = "execlude";
                if (isset($this->aero->data['execluded_airports'])) {
                    $execluded_airports = $this->aero->data['execluded_airports'];
                }
            }
            if ($this->aero->data['airport_management_type'] == 'include') {
                $mode = "include";
                if (isset($this->aero->data['included_airports'])) {
                    $included_airports = $this->aero->data['included_airports'];
                }
            }

        }

        if ($mode == "execlude") {
            $execluded_airports = $this->aero->getMeta('execluded_airports', [])->toArray();
        }
        if ($mode == "include") {
            $included_airports = $this->aero->getMeta('included_airports', [])->toArray();
        }

        foreach ($schedule as $flight) {
            $origin = $flight['departure']['airport'];
            $destination = $flight['arrival']['airport'];

            // Mode is not set
            if ($mode == "") {
                $this->getFlightSchedule($flight);
            } else {
                // Check if flight is execluded
                // EX: MLA - FCO
                if ($mode == "execlude") {
                    if (in_array($origin, $execluded_airports) || in_array($destination, $execluded_airports)) {
                        continue;
                    } else {
                        $this->getFlightSchedule($flight);
                    }
                }

                // EX: MLA - FCO
                if ($mode == "include") {
                    if (!in_array($origin, $included_airports) || !in_array($destination, $included_airports)) {
                        continue;
                    } else {
                        $this->getFlightSchedule($flight);
                    }
                }

            }
        }
    }

    private function getFlightSchedule($row)
    {
        $uuid = md5(
            $this->aero->iata . "_" . $row['departure']['airport'] . "_" .
            $row['arrival']['airport'] . "_" . $row['flight_number'] . "_" .
            $row['departure']['datetime'] . "_" . $row['arrival']['datetime']
        );

        $duration = 0;

        $origin_airport = getAirport($row['departure']['airport']);
        $destination_irport = getAirport($row['arrival']['airport']);


        $timezone_diff = ($origin_airport->timezone - $destination_irport->timezone) * 60;

        $depart = \Carbon\Carbon::parse($row['departure']['datetime']);
        $arrive = \Carbon\Carbon::parse($row['arrival']['datetime']);

        // show difference in days between now and end dates
        $duration = $depart->diffInMinutes($arrive) + $timezone_diff;

        $carrier_code = $row['carrier']['number'];
        if ($carrier_code == "GRR") {
            $carrier_code = "733";
        }
        if ($carrier_code == "INH") {
            $carrier_code = "320";
        }

        $sh = \App\Models\FlightSchedule::updateOrCreate(
            [
                'uuid' => $uuid,
                'aero_token_id' => $this->aero->id,
            ],
            [
                'iata' => $this->aero->iata,
                'origin' => $row['departure']['airport'],
                'destination' => $row['arrival']['airport'],
                'flight_number' => $row['flight_number'],
                'duration' => $duration,
                'departure' => date('Y-m-d H:i:s', strtotime($row['departure']['datetime'])),
                'arrival' => date('Y-m-d H:i:s', strtotime($row['arrival']['datetime'])),
                'aircraft_code' => $row['carrier']['number'],
            ]
        );

        if ($sh->wasRecentlyCreated || $sh->availablities()->count() == 0) {
            // \App\Jobs\GetFlightClassbandInformation::dispatch($sh)->onQueue('videcom-high');//->delay(now()->addMinute());
            \App\Jobs\GetFlightClassbandInformation::dispatch($sh)->onQueue($this->aero->getQueueId());//->delay(now()->addMinute());
        }

    }
}
