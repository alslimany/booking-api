<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FlightSearchController extends Controller
{
    public function oneway_flight_search(Request $request)
    {
        $request->validate([
            'origin_location_code' => 'required',
            'destination_location_code' => 'required',
            'departure_date' => 'required',
            'adults' => 'required',
            'children' => 'sometimes',
            'infants' => 'sometimes',
            'seated_infants' => 'sometimes',
            'travel_class' => 'sometimes',
            'airline_codes' => 'sometimes',
            'none_stop' => 'sometimes',
            'max_price' => 'sometimes',
            'max' => 'sometimes',
        ]);

        $total_passengers = ($request->get('adults', 0) + $request->get('children', 0) + $request->get('seated_infants', 0));

        $result = [];

        $passenger_types = [];
        if ($request->get('adults') > 0) {
            $passenger_types[] = "AD";
        }
        if ($request->get('children') > 0) {
            $passenger_types[] = "CH";
        }
        if ($request->get('infants') > 0) {
            $passenger_types[] = "IN";
        }
        if ($request->get('seated_infants') > 0) {
            $passenger_types[] = "IS";
        }


        $one_way_offers = OneWayOffer::where([
            'from' => $request->origin_location_code,
            'to' => $request->destination_location_code,
        ])
            ->whereIn('passenger_type', $passenger_types)
            ->whereDate('departure', date('Y-m-d', strtotime($request->departure_date)))
            ->get();

        // return $one_way_offers;
        $schedule_ids = $one_way_offers->pluck('flight_schedule_id')->toArray();

        $schedules = FlightSchedule::whereIn('id', $schedule_ids)->get();

        foreach ($schedules as $schedule) {

            $itinerary = [
                'duration' => 0,
                'segments' => [],
            ];

            $segment = [
                'departure' => [
                    'iataCode' => $schedule->origin,
                    'at' => $schedule->departure,
                ],
                'arrival' => [
                    'iataCode' => $schedule->destination,
                    'at' => $schedule->arrival,
                ],
                'carrierCode' => $schedule->iata,
                "number" => $schedule->flight_number,
                'aircraft' => [
                    'code' => '320',
                ],
                'duration' => $schedule->duration,
                'id' => $schedule->id,
            ];

            $itinerary['duration'] += $schedule->duration;
            $itinerary['segments'][] = $segment;

            // return $itinerary;

            $pricing = [
                'economy' => [
                    'lowest_price' => 0,
                    'offers' => [],
                ],
                'business' => [
                    'lowest_price' => 0,
                    'offers' => [],
                ],
                'first' => [
                    'lowest_price' => 0,
                    'offers' => [],
                ],
            ];

            $class_avilability = [];

            foreach ($itinerary['segments'] as $segment) {

                foreach (FlightAvailablity::where('flight_schedule_id', $segment['id'])->where('seats', '>=', $total_passengers)->get() as $class) {
                    $class_avilability[] = $class;
                }
            }

            // return $class_avilability;



            foreach ($class_avilability as $class_avilable) {
                switch ($class_avilable['cabin']) {
                    case 'Y':
                        $total_price = 0;
                        $total_fare = 0;
                        $total_tax = 0;

                        $offer = $class_avilable;
                        $prices = OneWayOffer::where([
                            'flight_availablity_id' => $offer->id,
                            'cabin' => $offer->cabin,
                            'class' => $offer->class,
                        ])->get();

                        foreach ($prices as $price) {
                            switch ($price->passenger_type) {
                                case 'AD':
                                    $total_price += $price->price * ($request->get('adults', 0));
                                    $total_fare += $price->fare_price * ($request->get('adults', 0));
                                    $total_tax += $price->tax * ($request->get('adults', 0));
                                    break;
                                case 'CH':
                                    $total_price += $price->price * ($request->get('children', 0));
                                    $total_fare += $price->fare_price * ($request->get('children', 0));
                                    $total_tax += $price->tax * ($request->get('children', 0));
                                    break;
                                case 'IN':
                                    $total_price += $price->price * ($request->get('infants', 0));
                                    $total_fare += $price->fare_price * ($request->get('infants', 0));
                                    $total_tax += $price->tax * ($request->get('infants', 0));
                                    break;
                            }
                        }

                        $offer['price'] = $total_price;
                        $offer['tax'] = $total_tax;
                        $offer['fare'] = $total_fare;
                        $pricing['economy']['offers'][] = $offer;
                        break;
                    case 'C':
                        $total_price = 0;
                        $total_fare = 0;
                        $total_tax = 0;

                        $offer = $class_avilable;
                        $prices = OneWayOffer::where([
                            'flight_availablity_id' => $offer->id,
                            'cabin' => $offer->cabin,
                            'class' => $offer->class,
                        ])->get();

                        foreach ($prices as $price) {
                            switch ($price->passenger_type) {
                                case 'AD':
                                    $total_price += $price->price * ($request->get('adults', 0));
                                    $total_fare += $price->fare_price * ($request->get('adults', 0));
                                    $total_tax += $price->tax * ($request->get('adults', 0));
                                    break;
                                case 'CH':
                                    $total_price += $price->price * ($request->get('children', 0));
                                    $total_fare += $price->fare_price * ($request->get('children', 0));
                                    $total_tax += $price->tax * ($request->get('children', 0));
                                    break;
                                case 'IN':
                                    $total_price += $price->price * ($request->get('infants', 0));
                                    $total_fare += $price->fare_price * ($request->get('infants', 0));
                                    $total_tax += $price->tax * ($request->get('infants', 0));
                                    break;
                            }
                        }

                        $offer['price'] = $total_price;
                        $offer['tax'] = $total_tax;
                        $offer['fare'] = $total_fare;
                        $pricing['business']['offers'][] = $offer;
                        break;
                    default:
                        $total_price = 0;
                        $total_fare = 0;
                        $total_tax = 0;

                        $offer = $class_avilable;
                        $prices = OneWayOffer::where([
                            'flight_availablity_id' => $offer->id,
                            'cabin' => $offer->cabin,
                            'class' => $offer->class,
                        ])->get();

                        foreach ($prices as $price) {
                            switch ($price->passenger_type) {
                                case 'AD':
                                    $total_price += ($price->price * ($request->get('adults', 0)));
                                    $total_fare += ($price->fare_price * ($request->get('adults', 0)));
                                    $total_tax += ($price->tax * ($request->get('adults', 0)));
                                    break;
                                case 'CH':
                                    $total_price += $price->price * ($request->get('children', 0));
                                    $total_fare += $price->fare_price * ($request->get('children', 0));
                                    $total_tax += $price->tax * ($request->get('children', 0));
                                    break;
                                case 'IN':
                                    $total_price += $price->price * ($request->get('infants', 0));
                                    $total_fare += $price->fare_price * ($request->get('infants', 0));
                                    $total_tax += $price->tax * ($request->get('infants', 0));
                                    break;
                            }
                        }

                        $offer['price'] = $total_price;
                        $offer['tax'] = $total_tax;
                        $offer['fare'] = $total_fare;
                        $pricing['economy']['offers'][] = $offer;
                        break;
                }
            }

            // $itinerary['pricing'] = $pricing;

            $result[] = [
                'type' => 'offer',
                'itineraries' => [$itinerary],
                'pricing' => $pricing,
            ];
        }

        return [
            'meta' => [
                'count' => count($result),
            ],
            'data' => $result,
        ];
    }
}
