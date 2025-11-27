<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountriesAndCitiesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // DB::table('cities')->truncate();
        // DB::table('countries')->truncate();

        $rows = collect(json_decode(file_get_contents(resource_path("js/Data/world-cities-codes.json"))));

        echo "Rows count: " . count($rows);

        foreach ($rows as $row) {
            try {
               
                $country = null;

                $is_country_existed = \App\Models\Country::where('code', $row['Country Code'])->count() > 0;

                if (!$is_country_existed) {
                    $country = new \App\Models\Country;
                    $country->name = $row['Country'];
                    $country->code = $row['Country Code'];
                    $country->save();
                } else {
                    $country = \App\Models\Country::where('code', $row['Country Code'])->first();
                }

                $city = null;

                $is_city_existed = \App\Models\City::where('code', $row['CityCode'])->count() > 0;

                if (!$is_city_existed) {
                    $city = new \App\Models\City;
                    $city->country_id = $country->id;
                    $city->country_code = $country->code;
                    $city->name = $row['City'];
                    $city->code = $row['CityCode'];
                    $city->save();
                }
            } catch (\Exception $e) {

            }
        }
    }
}
