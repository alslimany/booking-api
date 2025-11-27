<?php

namespace App\Http\Controllers\Api\V3\Air;

use App\Http\Controllers\Controller;
use App\Models\FlightSchedule;
use App\Models\OneWayOffer;
use App\Models\RoundWayOffer;
use Illuminate\Http\Request;

class OmraOfferSearchController extends Controller
{
    public function flight_offers(Request $request)
    {
        $request->validate([
            // 'origin_location_code' => 'required',
            // 'destination_location_code' => 'required',
            // 'departure_date' => 'required|after_or_equal:' . date('Y-m-d'),
            // 'return_date' => 'sometimes|after_or_equal:departure_date',
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

       
        $date_range = [];
        $result = [];
        for ($i = 0; $i < 10; $i++) {
            $departure_date = date('Y-m-d', strtotime(now() . ' + ' . $i . ' days'));
            $return_date = date('Y-m-d', strtotime(now() . ' + ' . ($i + 14) . ' days'));
            
            $request->merge([
                'origin_location_code' => 'MJI',
                'destination_location_code' => 'JED',
                'departure_date' => $departure_date,
                'return_date' => $return_date,
            ]);
            // $offers = $this->round_flight_offers([
            //     'origin_location_code' => 'MJI',
            //     'destination_location_code' => 'JED',
            //     'departure_date' => $departure_date,
            //     'return_date' => $return_date,
            //     'adults' => $request->get('adults',1),
            //     'children' => $request->get('children', 0),
            //     'infants' => $request->get('infants', 0),
            //     'seated_infants' => $request->get('seated_infants', 0),
            // ]);

            $offers = $this->round_flight_offers($request);

            if (count($offers['data']) > 0) {
                array_push($result, ...$offers['data']);
            }
            
        }

        return [
            'meta' => [
                'count' => count($result)
            ],
            'data' => $result,
        ];
       
    }

    private function round_flight_offers($request)
    {
        $total_passengers = ($request->get('adults', 0) + $request->get('children', 0) + $request->get('seated_infants', 0));

        $result = [];
        $data = [];

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

        $only = $request->get('only', []);
        $origin_airport = getAirport($request->origin_location_code);
        $destination_airport = getAirport(code: $request->destination_location_code);

        $p_type = [
            'AD' => 'adults',
            'CH' => 'children',
            'IN' => 'infants',
            'IS' => 'seated_infants',
        ];

        $travel_class = $request->travel_class;

        $itineraries = [
            [
                'origin' => $request->origin_location_code,
                'destination' => $request->destination_location_code,
                'departure_date' => $request->departure_date
            ],
            [
                'destination' => $request->origin_location_code,
                'origin' => $request->destination_location_code,
                'departure_date' => $request->return_date
            ],
        ];

        $round_way_offers = RoundWayOffer::whereDate('departure', '=', date('Y-m-d', strtotime($request->departure_date)))
            ->whereDate('return', '=', date('Y-m-d', strtotime($request->return_date)))
            ->where('from', '=', $request->origin_location_code)
            ->where('to', '=', $request->destination_location_code)
            ->orderBy('departure')
            ->with('segments')
            ->get();

        // return $round_way_offers;
        $offers = [];


        foreach ($round_way_offers as $round_way_offer) {
            $offer_found = true;
            foreach ($round_way_offer->segments as $round_way_offer_segment) {
                if ($round_way_offer_segment->flight_schedule == null) {
                    $offer_found = false;
                }
            }

            if ($offer_found) {
                // $offer = [
                //     'id' => $round_way_offer->id,
                //     'oneWay' => false,
                //     'itineraries' => [],
                //     'numberOfBookableSeats' => 0,
                // ];

                // // $itineraries = [];
                // // $number_of_bookable_seats = 0;
                // // $price = [
                // //     "currency" => 'LYD',
                // //     "total" => 0,
                // //     "base" => 0,
                // //     "grandTotal" => 0,

                // //     "fees" => [

                // //     ],
                // // ];


                foreach ($round_way_offer->segments()->where('type', 'outbound')->get() as $out_round_way_offer_segment) {


                    foreach ($round_way_offer->segments()->where('type', 'inbound')->get() as $in_round_way_offer_segment) {
                        foreach ($out_round_way_offer_segment->round_way_pricings->whereIn('passenger_type', $passenger_types)->groupBy('class') as $o_class => $out_round_way_segment_pricings) {
                            foreach ($in_round_way_offer_segment->round_way_pricings->whereIn('passenger_type', $passenger_types)->where('class', $o_class)->groupBy('class') as $i_class => $in_round_way_segment_pricings) {
                                // Set Itineraries with segments
                                $itineraries = [];

                                // $itineraries[] = [
                                //     'segments' => [
                                //         [
                                //             'departure' => [
                                //                 'iataCode' => $out_round_way_offer_segment->from,
                                //                 'at' => $out_round_way_offer_segment->departure,
                                //             ],
                                //             'arrival' => [
                                //                 'iataCode' => $out_round_way_offer_segment->to,
                                //                 'at' => $out_round_way_offer_segment->arrival,
                                //             ],
                                //             'carrierCode' => $out_round_way_offer_segment->carrier,
                                //             'carrier' => $out_round_way_offer_segment->flight_schedule->aero_token->only('iata'),
                                //             "number" => $out_round_way_offer_segment->flight_number,
                                //             'aircraft' => findAircraft($out_round_way_offer_segment->flight_schedule->aircraft_code > 0 ? $round_way_offer_segment->flight_schedule->aircraft_code : 320),
                                //             'id' => $out_round_way_offer_segment->flight_schedule_id,
                                //         ]
                                //     ]
                                // ];

                                // $itineraries[] = [
                                //     'segments' => [
                                //         [
                                //             'departure' => [
                                //                 'iataCode' => $in_round_way_offer_segment->from,
                                //                 'at' => $in_round_way_offer_segment->departure,
                                //             ],
                                //             'arrival' => [
                                //                 'iataCode' => $in_round_way_offer_segment->to,
                                //                 'at' => $in_round_way_offer_segment->arrival,
                                //             ],
                                //             'carrierCode' => $in_round_way_offer_segment->carrier,
                                //             'carrier' => $in_round_way_offer_segment->flight_schedule->aero_token->only('iata'),
                                //             "number" => $in_round_way_offer_segment->flight_number,
                                //             'aircraft' => findAircraft($in_round_way_offer_segment->flight_schedule->aircraft_code > 0 ? $round_way_offer_segment->flight_schedule->aircraft_code : 320),
                                //             'id' => $in_round_way_offer_segment->flight_schedule_id,
                                //         ]
                                //     ]
                                // ];

                                $in_segment = [
                                    'seats' => 0,
                                    'departure' => [
                                        'iataCode' => $in_round_way_offer_segment->from,
                                        'at' => $in_round_way_offer_segment->departure,
                                    ],
                                    'arrival' => [
                                        'iataCode' => $in_round_way_offer_segment->to,
                                        'at' => $in_round_way_offer_segment->arrival,
                                    ],
                                    'carrierCode' => $in_round_way_offer_segment->carrier,
                                    'carrier' => $in_round_way_offer_segment->flight_schedule->aero_token->only('iata'),
                                    "number" => $in_round_way_offer_segment->flight_number,
                                    'aircraft' => findAircraft($in_round_way_offer_segment->flight_schedule->aircraft_code > 0 ? $round_way_offer_segment->flight_schedule->aircraft_code : 320),
                                    'id' => $in_round_way_offer_segment->flight_schedule_id,
                                ];

                                $out_segment = [
                                    'seats' => 0,
                                    'departure' => [
                                        'iataCode' => $out_round_way_offer_segment->from,
                                        'at' => $out_round_way_offer_segment->departure,
                                    ],
                                    'arrival' => [
                                        'iataCode' => $out_round_way_offer_segment->to,
                                        'at' => $out_round_way_offer_segment->arrival,
                                    ],
                                    'carrierCode' => $out_round_way_offer_segment->carrier,
                                    'carrier' => $out_round_way_offer_segment->flight_schedule->aero_token->only('iata'),
                                    "number" => $out_round_way_offer_segment->flight_number,
                                    'aircraft' => findAircraft($out_round_way_offer_segment->flight_schedule->aircraft_code > 0 ? $round_way_offer_segment->flight_schedule->aircraft_code : 320),
                                    'id' => $out_round_way_offer_segment->flight_schedule_id,
                                ];

                                $passenger_index = 1;
                                $traveler_pricings = [];

                                foreach ($p_type as $k => $v) {
                                    for ($pid = 0; $pid < $request->get($p_type[$k]); $pid++) {
                                        $traveler_pricings[] = [
                                            "travelerId" => $passenger_index,
                                            "fareOption" => "standard",
                                            // 'travelerType' => getPassengerTypeByCode($k),
                                            'travelerType' => $k,
                                            "price" => [
                                                "currency" => '',
                                                "total" => 0.0,
                                                "base" => 0.0
                                            ],
                                            'fareDetailsBySegment' => []
                                        ];

                                        $passenger_index++;
                                    }
                                }

                                $number_of_bookable_seats = 0;

                                foreach ($out_round_way_segment_pricings as $out_round_way_segment_pricing) {
                                    foreach ($traveler_pricings as &$price) {
                                        if ($price['travelerType'] == $out_round_way_segment_pricing->passenger_type) {

                                            $price['fareDetailsBySegment'][] = [
                                                'segmentId' => $out_round_way_segment_pricing->flight_schedule_id,
                                                'cabin' => getClassNameByCabin($out_round_way_segment_pricing->cabin),
                                                'fareBasis' => $out_round_way_segment_pricing->fare_basis,
                                                'class' => $out_round_way_segment_pricing->class,
                                                'includedCheckedBags' => [
                                                    'weight' => (int) str_replace("K", "", $out_round_way_segment_pricing->hold_weight),
                                                    'weightUnit' => 'KG',
                                                ],
                                            ];

                                            $price['price']['currency'] = $out_round_way_segment_pricing->currency;
                                            $price['price']['total'] += ($out_round_way_segment_pricing->fare_price + $out_round_way_segment_pricing->tax);
                                            $price['price']['base'] += $out_round_way_segment_pricing->fare_price;

                                            $out_segment['seats'] = $out_round_way_segment_pricing->flight_availablity->seats;

                                            if ($number_of_bookable_seats < $out_round_way_segment_pricing->flight_availablity->seats) {
                                                $number_of_bookable_seats = $out_round_way_segment_pricing->flight_availablity->seats;
                                            }
                                        }
                                    }
                                }

                                foreach ($in_round_way_segment_pricings as $in_round_way_segment_pricing) {
                                    foreach ($traveler_pricings as &$price) {
                                        if ($price['travelerType'] == $in_round_way_segment_pricing->passenger_type) {

                                            $price['fareDetailsBySegment'][] = [
                                                'segmentId' => $in_round_way_segment_pricing->flight_schedule_id,
                                                'cabin' => getClassNameByCabin($in_round_way_segment_pricing->cabin),
                                                'fareBasis' => $in_round_way_segment_pricing->fare_basis,
                                                'class' => $in_round_way_segment_pricing->class,
                                                'includedCheckedBags' => [
                                                    'weight' => (int) str_replace("K", "", $in_round_way_segment_pricing->hold_weight),
                                                    'weightUnit' => 'KG',
                                                ],
                                            ];

                                            $in_segment['seats'] = $in_round_way_segment_pricing->flight_availablity->seats;

                                            $price['price']['currency'] = $in_round_way_segment_pricing->currency;
                                            $price['price']['total'] += ($in_round_way_segment_pricing->fare_price + $in_round_way_segment_pricing->tax);
                                            $price['price']['base'] += $in_round_way_segment_pricing->fare_price;

                                            if ($number_of_bookable_seats < $out_round_way_segment_pricing->flight_availablity->seats) {
                                                $number_of_bookable_seats = $out_round_way_segment_pricing->flight_availablity->seats;
                                            }
                                        }
                                    }
                                }

                                $itineraries[] = [
                                    'segments' => [$out_segment]
                                ];
                                $itineraries[] = [
                                    'segments' => [$in_segment]
                                ];

                                // Set Offer
                                $offer_price = [
                                    'currency' => '',
                                    'total' => 0.0,
                                    'base' => 0.0,
                                    'grandTotal' => 0.0,
                                    'fees' => [],
                                ];

                                // return [
                                //     'itineraries' => $itineraries,
                                //     'price' => $offer_price,
                                //     'traveler_pricing' => $traveler_pricing,
                                // ];

                                foreach ($traveler_pricings as $tp) {
                                    $offer_price['currency'] = $tp['price']['currency'];
                                    $offer_price['total'] += $tp['price']['total'];
                                    $offer_price['base'] += $tp['price']['base'];
                                    $offer_price['grandTotal'] += $tp['price']['total'];
                                }

                                foreach ($itineraries as $itin) {
                                    foreach ($itin['segments'] as $segment) {
                                        if ($segment['seats'] < $number_of_bookable_seats) {
                                            $number_of_bookable_seats = $segment['seats'];
                                        }
                                    }
                                }

                                if ($number_of_bookable_seats > 0) {
                                    $offers[] = [
                                        'itineraries' => $itineraries,
                                        'price' => $offer_price,
                                        'travelerPricings' => $traveler_pricings,
                                        'numberOfBookableSeats' => $number_of_bookable_seats,
                                    ];
                                }



                            }

                        }
                    }

                }

               
            }
        }

        return [
            'meta' => [
                'count' => count($offers),
            ],
            'data' => $offers,
        ];

    }

    # Region Helpers
    private function h_convertToHoursMins($time, $format = '%02d:%02d')
    {
        if ($time < 1) {
            return;
        }

        $hours = floor($time / 60);
        $minutes = $time % 60;

        return sprintf($format, $hours, $minutes);
    }

    private function h_getClassCodes($class)
    {

        if ($class == 'economy') {
            return ['K', 'L', 'Q', 'V', 'W', 'U', 'T', 'X', 'N', 'O', 'S', 'R'];
        }

        if ($class == 'premium_economy') {
            return ['Y', 'B', 'M', 'H'];
        }

        if ($class == 'business') {
            return ['J', 'C', 'G', 'P'];
        }

        if ($class == 'first') {
            return ['A', 'F'];
        }


        // if (in_array($class, ['K', 'L', 'Q', 'V', 'W', 'U', 'T', 'X', 'N', 'O', 'S', 'R'])) {
        //    return 'discounted_economy';
        // }
        // if (in_array($class, ['Y', 'B', 'M', 'H'])) {
        //    return 'economy';
        // }
        // if (in_array($class, ['W', 'E'])) {
        //    return 'premium_economy';
        // }
        // if (in_array($class, ['D', 'I', 'Z'])) {
        //    return 'discounted_business';
        // }
        // if (in_array($class, ['J', 'C'])) {
        //    return 'business';
        // }
        // if (in_array($class, ['A', 'F'])) {
        //    return 'first';
        // }

        // return 'none';

    }

    private function h_combinations($arrays, $i = 0)
    {
        if (!isset($arrays[$i])) {
            return array();
        }
        if ($i == count($arrays) - 1) {
            return $arrays[$i];
        }

        // get combinations from subsequent arrays
        $tmp = $this->h_combinations($arrays, $i + 1);

        $result = array();

        // concat each array from tmp with each element from $arrays[$i]
        foreach ($arrays[$i] as $v) {
            foreach ($tmp as $t) {
                $result[] = is_array($t) ?
                    array_merge(array($v), $t) :
                    array($v, $t);
            }
        }

        return $result;
    }

    private function getColorForPriceRange($priceRange)
    {
        $colorScale = [
            0 => 'green', // Green
            25 => 'blue', // Blue
            50 => 'yellow', // Yellow
            75 => 'orange', // Orange
            100 => 'red', // Red 
        ];

        foreach ($colorScale as $range => $color) {
            if (floatval($priceRange) <= $range) {
                return $color;
            }
        }
        return 'red'; // Default to Red if no match
    }
}
