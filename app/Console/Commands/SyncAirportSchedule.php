<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SyncAirportSchedule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-airport-schedule';

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
        $origins = \App\Models\FlightSchedule::select('origin')->distinct()->get()->toArray();

        // DB::beginTransaction();

        try {
            \App\Models\AirportSchedule::truncate();

            foreach ($origins as $origin) {
                $airport = $origin['origin'];

                $this->info("Syncing Flights " . $airport);

                $data = [];
                $response = Http::get("https://api.flightradar24.com/common/v1/airport.json?code=" . strtolower($airport) . "&plugin[]=&plugin-setting[schedule][timestamp]=" . time() . "&page=1&limit=100&fleet=&token=");
                $result = $response->json('result');

                // \App\Models\AirportSchedule::where([
                //     'destination' => $airport,
                // ])->delete();
                // return $result['response']['airport']['pluginData']['schedule']['arrivals']['data'];
                foreach ($result['response']['airport']['pluginData']['schedule']['arrivals']['data'] as $arrival) {
                    // return $arrival;
                    $data = [
                        'ref_id' => $arrival['flight']['identification']['id'],
                        'type' => $arrival['flight']['status']['generic']['status']['type'],
                        'number' => $arrival['flight']['identification']['number']['default'],
                        'airline_iata' => $arrival['flight']['airline']['code']['iata'] ?? '',
                        'status' => $arrival['flight']['status']['generic']['status']['text'],
                        'status_at' => date('Y-m-d H:i:s', $arrival['flight']['status']['generic']['eventTime']['utc']),
                        'aircraft' => $arrival['flight']['aircraft']['model']['code'],
                        'origin' => $arrival['flight']['airport']['origin']['code']['iata'],
                        'destination' => $airport,
                        'scheduled_departure_at' => date('Y-m-d H:i:s', ($arrival['flight']['time']['scheduled']['departure'])),
                        'scheduled_arrival_at' => date('Y-m-d H:i:s', ($arrival['flight']['time']['scheduled']['arrival'])),
                    ];

                    // return $data;
                    \App\Models\AirportSchedule::updateOrCreate([
                        'number' => $data['number'],
                        'origin' => $data['origin'],
                        'destination' => $data['destination'],
                        'scheduled_arrival_at' => $data['scheduled_arrival_at']
                    ], $data);

                    if (isset($arrival['flight']['airline']['code']['iata'])) {
                        \App\Models\Airline::updateOrCreate([
                            'iata' => $arrival['flight']['airline']['code']['iata']
                        ], [
                            'name' => $arrival['flight']['airline']['name'],
                            'alias' => $arrival['flight']['airline']['short'],
                            'iata' => $arrival['flight']['airline']['code']['iata'],
                            'icao' => $arrival['flight']['airline']['code']['icao'],
                        ]);
                    }

                }

                // \App\Models\AirportSchedule::where([
                //     'origin' => $airport,
                // ])->delete();

                foreach ($result['response']['airport']['pluginData']['schedule']['departures']['data'] as $arrival) {
                    // return $arrival;
                    $data = [
                        'ref_id' => $arrival['flight']['identification']['id'],
                        'type' => $arrival['flight']['status']['generic']['status']['type'],
                        'number' => $arrival['flight']['identification']['number']['default'],
                        'airline_iata' => $arrival['flight']['airline']['code']['iata'] ?? '',
                        'status' => $arrival['flight']['status']['generic']['status']['text'],
                        'status_at' => date('Y-m-d H:i:s', $arrival['flight']['status']['generic']['eventTime']['utc']),
                        'aircraft' => $arrival['flight']['aircraft']['model']['code'],
                        'origin' => $airport,
                        'destination' => $arrival['flight']['airport']['destination']['code']['iata'],
                        'scheduled_departure_at' => date('Y-m-d H:i:s', ($arrival['flight']['time']['scheduled']['departure'])),
                        'scheduled_arrival_at' => date('Y-m-d H:i:s', ($arrival['flight']['time']['scheduled']['arrival'])),
                    ];

                    // return $data;
                    \App\Models\AirportSchedule::updateOrCreate([
                        'number' => $data['number'],
                        'origin' => $data['origin'],
                        'destination' => $data['destination'],
                        'scheduled_departure_at' => $data['scheduled_departure_at']
                    ], $data);

                }


                // sleep(5);
            }
        } catch (\Exception $ex) {
            // DB::rollback();
        }

        // DB::commit();

    }
}
