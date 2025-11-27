<?php

namespace Database\Seeders;

use App\Models\Airport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AirportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('airports')->truncate();
        $airports = collect(json_decode(file_get_contents(resource_path("js/Data/airports.json"))));

        foreach ($airports as $airport) {
            try {
                // if (Airport::where('iata', $airport->iata)->get()->count() > 0) {
                //     Airport::where('iata', $airport->iata)
                //         ->update([
                //             'name' => $airport->name,
                //             'city' => $airport->city,
                //             'state' => $airport->state,
                //             'country' => $airport->country,
                //             'iata' => $airport->iata,
                //             'icao' => $airport->icao,
                //             'elevation' => $airport->elevation,
                //             'lat' => $airport->lat,
                //             'lon' => $airport->lon,
                //             'timezone' => $airport->tz
                //         ]);
                // } else {
                    Airport::create([
                        'name' => $airport->name,
                        'city' => $airport->city,
                        'state' => $airport->state,
                        'country' => $airport->country,
                        'iata' => $airport->iata,
                        'icao' => $airport->icao,
                        'elevation' => $airport->elevation,
                        'lat' => $airport->lat,
                        'lon' => $airport->lon,
                        'timezone' => $airport->tz
                    ]);
                // }
            } catch (\Exception $e) {

            }
        }
    }
}
