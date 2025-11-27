<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FlightScheduleController extends Controller
{
    public function index($iata)
    {
        $result = [
            'domestic' => [],
            'international' => [],
        ];

        $founds = [];

        foreach (\App\Models\FlightSchedule::where('iata', $iata)->get() as $flight_schedule) {
            if (!in_array($flight_schedule->origin . '-' . $flight_schedule->destination, $founds)) {
                $founds[] = $flight_schedule->origin . '-' . $flight_schedule->destination;

                $data = [
                    'schedule' => $flight_schedule,
                    // 'offers' => [],
                    'avg' => 0,
                ];

                $price = 0;
                foreach ($flight_schedule->one_way_offers as $oneway_offer) {
                    // $data['offers'][] = $oneway_offer;
                    $price += $oneway_offer->fare_price;
                }

                if ($price > 0) {
                    $data['avg'] = $price / count($flight_schedule->one_way_offers);
                }

                if ($flight_schedule->is_international) {
                    $result['international'][] = $data;
                } else {
                    $result['domestic'][] = $data;
                }
            }
        }

        $price = 0;
        foreach ($result['international'] as $offer) {
            $price += $offer['avg'];
        }

        $result['international_avg'] = $price / count($result['international']);

        $price = 0;
        foreach ($result['domestic'] as $offer) {
            $price += $offer['avg'];
        }

        if ($price > 0) {
            $result['domestic_avg'] = $price / count($result['domestic']);
        }

        return $result;
    }
}
