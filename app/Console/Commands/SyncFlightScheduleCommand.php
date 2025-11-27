<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

class SyncFlightScheduleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-flight-schedule-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch flight scheules of airlines';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $start_date = date('Y-m-d');
        $to = date('Y-m-d', strtotime(date('Y-m-d') . ' + 30 days'));
        $times = 0;

        for ($i = 0; $i <= 30; $i++) {
            $date = date('Y-m-d', strtotime(date('Y-m-d') . ' + ' . $i . ' days'));
            foreach (\App\Models\AeroToken::where('data->mode', '=', 'user_auth')->get() as $aero) {
                // foreach (['MJI', 'TUN', 'IST', 'HBE', 'BEN', 'CAI', 'LAQ', 'MRA'] as $airport) {
                foreach ($this->get_airports($aero) as $airport) {
                    // if ($aero->isAirportNotExecluded($airport)) {
                    $this->info("Syncing $aero->name {$airport} - [$date | $to] : total flights");
                    dispatch(new \App\Jobs\FetchWebFlightSchedule($aero, start_date: $date, airport: $airport, to: $to))->onQueue($aero->getQueueId())->delay(now()->addSeconds(5));
                    // }

                }
            }
        }
        
        // Artisan::call('horizon:pause');
        foreach (\App\Models\AeroToken::where('data->mode', operator: '=', value: 'api')->get() as $aero) {
            $this->info("Syncing {$aero->name} flight schedule");
            $days = 0;
            for ($days = 0; $days <= 60; $days += 30) {
                $from = date('Y-m-d', strtotime(date('Y-m-d') . ' + ' . (int) $days . ' days'));
                $to = date('Y-m-d', strtotime(date('Y-m-d') . ' + ' . (int) $days + 30 . ' days'));

                $this->info("Syncing {$aero->name} flight schedule from $from to $to");
                $schedule = $aero->build()->schedule([
                    'from' => $from,
                    'to' => $to,
                ]);
                

                $mode = "";
                $execluded_airports = [];
                $included_airports = [];

                if (array_key_exists('airport_management_type', $aero?->data)) {

                    if ($aero->data['airport_management_type'] == 'execulde') {
                        $mode = "execlude";
                        if (isset($aero->data['execluded_airports'])) {
                            $execluded_airports = $aero->data['execluded_airports'];
                        }
                    }
                    if ($aero->data['airport_management_type'] == 'include') {
                        $mode = "include";
                        if (isset($aero->data['included_airports'])) {
                            $included_airports = $aero->data['included_airports'];
                        }
                    }

                }

                if ($mode == "execlude") {
                    $execluded_airports = $aero->getMeta('execluded_airports', [])->toArray();
                }
                if ($mode == "include") {
                    $included_airports = (array) $aero->getMeta('included_airports', [])->toArray();
                }

                foreach ($schedule as $flight) {
                    $origin = $flight['departure']['airport'];
                    $destination = $flight['arrival']['airport'];

                    // Mode is not set
                    if ($mode == "") {
                        $this->getFlightSchedule($aero, $flight);
                    } else {
                        // Check if flight is execluded
                        // EX: MLA - FCO
                        if ($mode == "execlude") {
                            if (in_array($origin, $execluded_airports) || in_array($destination, $execluded_airports)) {
                                continue;
                            } else {
                                $this->getFlightSchedule($aero, $flight);
                            }
                        }

                        // EX: MLA - FCO
                        if ($mode == "include") {
                            if (in_array($origin, $included_airports) || in_array($destination, $included_airports)) {
                                $this->getFlightSchedule($aero, $flight);
                            } else {
                                continue;
                            }
                        }

                    }
                }


            }

        }
    }

    private function get_airports($aeroToken)
    {
        $airports = [];
        if (cache()->has($aeroToken->iata . "_airports")) {
            $airports = cache()->get($aeroToken->iata . "_airports");
        } else {
            $session = cache()->get($aeroToken->iata);

            $url = $aeroToken->data['url'] . "/VARS/Agent/Components/PNR/AvailabilitySearchWS.asmx/GetJSONOriginList?" . $session;

            try {
                $airports = Http::withHeaders(['content-type' => 'application/json, text/javascript,; q=0.01'])
                ->post(url: $url);
                $text = string_between_two_string($airports->body(), '[', ']');

                $data = json_decode("[" . $text . "]");

                $ava_airports = [];
                foreach ($data as $row) {
                    $ava_airports[] = string_between_two_string($row->label, '(', ')');
                }

                cache()->put($aeroToken->iata . "_airports", $ava_airports, now()->addHours(5));

                $airports = $ava_airports;
            } catch (\Throwable $th) {
                $airports = [];
            }

            
        }


        return $airports;
    }

    private function getFlightSchedule($aero, $row)
    {
        $uuid = md5(
            $aero->iata . "_" . $row['departure']['airport'] . "_" .
            $row['arrival']['airport'] . "_" . $row['flight_number'] . "_" .
            $row['departure']['datetime'] . "_" . $row['arrival']['datetime']
        );

        $duration = 0;
        $origin_airport = getAirport($row['departure']['airport']);
        $destination_irport = getAirport($row['arrival']['airport']);

        // if (!in_array($row['departure']['airport'], $execluded_airports) && !in_array($row['arrival']['airport'], $execluded_airports)) {
        $this->info("Syncing $aero->name {$row['departure']['airport']} - {$row['arrival']['airport']} : " . $row['departure']['datetime']);
        $timezone_diff = ((int) $origin_airport->timezone - (int)$destination_irport->timezone) * 60;

        $depart = \Carbon\Carbon::parse($row['departure']['datetime']);
        $arrive = \Carbon\Carbon::parse($row['arrival']['datetime']);

        // show difference in days between now and end dates
        $duration = $depart->diffInMinutes($arrive) + $timezone_diff;

        $flight = \App\Models\FlightSchedule::where('uuid', $uuid)->where('aero_token_id', $aero->id)->first();
        if ($flight!= null ) {
            // $flight->
            $flight->iata = $aero->iata;
            $flight->origin = $row['departure']['airport'];
            $flight->destination = $row['arrival']['airport'];
            $flight->flight_number = $row['flight_number'];
            $flight->duration = $duration;
            $flight->departure = date('Y-m-d H:i:s', strtotime($row['departure']['datetime']));
            $flight->arrival = date('Y-m-d H:i:s', strtotime($row['arrival']['datetime']));
            $flight->aircraft_code = $row['carrier']['number'];

            $flight->save();
        } else {
            $flight = \App\Models\FlightSchedule::create(
                [
                    'uuid' => $uuid,
                    'aero_token_id' => $aero->id,
                    'iata' => $aero->iata,
                    'origin' => $row['departure']['airport'],
                    'destination' => $row['arrival']['airport'],
                    'flight_number' => $row['flight_number'],
                    'duration' => $duration,
                    'departure' => date('Y-m-d H:i:s', strtotime($row['departure']['datetime'])),
                    'arrival' => date('Y-m-d H:i:s', strtotime($row['arrival']['datetime'])),
                    'aircraft_code' => $row['carrier']['number'],
                ]
            );   

            \App\Jobs\GetFlightClassbandInformation::dispatch($flight)->onQueue( $aero->getQueueId())->delay(now()->addMinute());
        }

        // if ($sh->wasRecentlyCreated || $sh->availablities()->count() == 0) {
            
        // }
    }
}
