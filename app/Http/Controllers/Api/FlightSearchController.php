<?php

namespace App\Http\Controllers\Api;

use App\Models\FlightAvailablity;
use App\Http\Controllers\Controller;
use App\Models\AeroToken;
use App\Models\FlightSchedule;
use App\Models\OneWayOffer;
use App\Models\RoundWayOffer;
use App\Models\RoundWayPricing;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Promise\Utils;

class FlightSearchController extends Controller
{

    public function run_command(Request $request)
    {

        $request->validate([
            'command' => 'required|string',
        ]);

        $result = [];

        $user = $request->user();

        $aeros = AeroToken::where('user_id', $user->id)
            ->when($request->get('only', null), function ($query, $only) {
                $query->whereIn('iata', $only);
            })->get();

        foreach ($aeros as $aero) {
            $data = [
                'command' => $request->command,
            ];

            $response = $aero->build()->runCommand($data['command'], false);
            return $response->response;
        }

        return $result;
    }

    public function info(Request $request)
    {
        // return $request->all();
        $request->validate([
            'aero_token_id' => 'required',
        ]);

        $result = [];

        $user = $request->user();

        $aero_token = AeroToken::findOrFail($request->aero_token_id);

        return $aero_token->build()->info();
    }
    public function flight_schedule(Request $request)
    {

        $request->validate([
            'from' => 'required|date',
            'to' => 'sometimes|date'
        ]);

        $result = [];

        $user = $request->user();

        $aeros = AeroToken::where('user_id', $user->id)
            ->when($request->get('only', null), function ($query, $only) {
                $query->whereIn('iata', $only);
            })->get();

        foreach ($aeros as $aero) {
            $data = [
                'from' => $request->from,
                'to' => $request->get('to', date('Y-m-d', strtotime($request->from . ' + 7 days'))),
            ];

            $response = $aero->build()->schedule($data);
            return $response;
            array_push($result, ...$response);
        }

        return $result;
    }

    public function flight_dates_availability(Request $request)
    {
        $request->validate([
            'origin_location_code' => 'required',
            'destination_location_code' => 'required',
            'departure_date' => 'required',
        ]);

        $result = [];

        $user = $request->user();

        $aeros = AeroToken::where('user_id', $user->id)
            ->when($request->get('only', null), function ($query, $only) {
                $query->whereIn('iata', $only);
            })->get();

        $dates = [];

        foreach ($aeros as $aero) {
            $data = [
                'from' => $request->origin_location_code,
                'to' => $request->destination_location_code,
                'date' => $request->departure_date,
            ];

            $response = $aero->build()->flightDatesAvialability($data);

            foreach ($response as $key => $val) {
                if (array_key_exists($key, $dates)) {
                    if ($val['is_avialable']) {
                        $dates[$key]['is_avialable'] = true;
                        // $dates[$key]['carrierCode'][] = $aero->iata;
                    }
                } else {
                    $dates[$key] = $val;
                    if ($val['is_avialable']) {
                        // $dates[$key]['carrierCode'][] = $aero->iata;
                    }
                }
            }
        }

        return $dates;

    }

    public function flight_availability(Request $request)
    {
        $request->validate([
            'origin_location_code' => 'required',
            'destination_location_code' => 'required',
            'departure_date' => 'required',
        ]);

        $result = [];

        $user = $request->user();
        $only = $request->get('only', []);

        $dates = [];
        for ($i = -5; $i <= 5; $i++) {
            $dates[date('Y-m-d', strtotime($request->departure_date . $i . ' days'))] = [
                'departure' => date('Y-m-d', strtotime($request->departure_date . $i . ' days')),
                'carrier' => [],
                'has_flight' => false,
            ];
        }

        foreach ($dates as $date) {
            $available_flights = FlightSchedule::where('origin', strtoupper($request->origin_location_code))
                ->where('destination', strtoupper($request->destination_location_code))
                ->whereDate('departure', date('Y-m-d', strtotime($date['departure'])))
                // ->where('has_offers', true)
                ->whereHas('one_way_offers')
                // ->whereIn('iata', $only)
                ->get();

            if (count($available_flights) > 0) {
                $dates[$date['departure']]['has_flight'] = true;

                foreach ($available_flights as $avf) {
                    $dates[$date['departure']]['carrier'][] = 1;
                }
            }

        }


        return $dates;

    }

    public function get_flight_contacts(Request $request)
    {

        $request->validate([
            // 'flight_number' => 'required|string',
            // 'flight_date' => 'required'
        ]);

        $result = [];

        $user = $request->user();

        $aeros = AeroToken::where('user_id', $user->id)
            ->when($request->get('only', null), function ($query, $only) {
                $query->whereIn('iata', $only);
            })->get();

        foreach ($aeros as $aero) {
            $data = [
                'flight_number' => $request->flight_number,
                'flight_date' => $request->flight_date,
                'airport' => $request->airport,
            ];

            $response = $aero->build()->flightContacts($data);

            return $response;
            array_push($result, ...$response);
        }

        return $result;
    }
    public function get_sales_report(Request $request)
    {

        $request->validate([
            // 'flight_number' => 'required|string',
            // 'flight_date' => 'required'
        ]);

        $result = [];

        $user = $request->user();

        $aeros = AeroToken::where('user_id', $user->id)
            ->when($request->get('only', null), function ($query, $only) {
                $query->whereIn('iata', $only);
            })->get();

        foreach ($aeros as $aero) {
            $data = [
                'from' => $request->from,
                'to' => $request->to,
            ];

            $response = $aero->build()->salesReport($data);

            return $response;
            array_push($result, ...$response);
        }

        return $result;
    }
    public function one_way_search(Request $request)
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
            'only' => 'sometimes',
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

        $only = $request->get('only', []);
        $origin_airport = getAirport($request->origin_location_code);
        $destination_irport = getAirport($request->destination_location_code);

        $one_way_offers = OneWayOffer::where([
            ['from', '=', $request->origin_location_code],
            ['to', '=', $request->destination_location_code],
            ['price', '>', 0]
        ])
            ->whereHas('flight_schedule', function ($q) use ($only) {
                $q->whereIn('iata', $only);
            })
            // ->when($request->get('iata', null), function ($query, $iata) {
            //     $query->where('iata', $iata);
            // })
            ->whereIn('passenger_type', $passenger_types)
            ->whereDate('departure', date('Y-m-d', strtotime($request->departure_date)))
            ->get();



        $schedule_ids = $one_way_offers->pluck('flight_schedule_id')->toArray();

        // $schedules = FlightSchedule::where('has_offers', true)
        //     ->whereIn('id', $schedule_ids)
        //     ->whereIn('iata', $only)
        //     ->orderBy('departure')
        //     ->get();

        $schedules = FlightSchedule::whereIn('id', $schedule_ids)
            ->whereIn('iata', $only)
            ->orderBy('departure')
            ->get();

        foreach ($schedules as $schedule) {

            $dep = \Carbon\Carbon::parse($schedule->departure);
            $arr = \Carbon\Carbon::parse($schedule->arrival);

            $timezone_diff = ($origin_airport->timezone - $destination_irport->timezone) * 60;

            $flight_duration = $dep->diffInMinutes($arr) + $timezone_diff;

            $itinerary = [
                'duration' => 0,
                'segments' => [],
            ];

            $segment = [
                'departure' => [
                    'iataCode' => $schedule->origin,
                    'at' => $schedule->departure,
                    'airport' => getAirport($schedule->origin),
                    'weather' => fetch_current_weather($schedule->destination),
                ],
                'arrival' => [
                    'iataCode' => $schedule->destination,
                    'at' => $schedule->arrival,
                    'airport' => getAirport($schedule->destination),
                    'weather' => fetch_current_weather($schedule->destination),
                ],
                'carrierCode' => $schedule->iata,
                'carrier' => $schedule->aero_token->only('name', 'iata'),
                "number" => $schedule->flight_number,
                'aircraft' => findAircraft($schedule->aircraft_code > 0 ? $schedule->aircraft_code : 320),
                // 'aircraft' => [
                //     'code' => $schedule->aircraft_code ?? 'na',
                // ],
                // 'duration' => $schedule->duration,
                'duration' => $flight_duration,
                'id' => $schedule->id,
            ];

            $itinerary['duration'] += $schedule->duration;
            $itinerary['segments'][] = $segment;

            // return $itinerary;

            $pricing = [
                'none' => [
                    'lowest_price' => 100000,
                    'currency' => '',
                    'offers' => [],
                ],
                'discounted_economy' => [
                    'lowest_price' => 100000,
                    'currency' => '',
                    'offers' => [],
                ],
                'economy' => [
                    'lowest_price' => 100000,
                    'currency' => '',
                    'offers' => [],
                ],
                'premium_economy' => [
                    'lowest_price' => 100000,
                    'currency' => '',
                    'offers' => [],
                ],
                'discounted_business' => [
                    'lowest_price' => 100000,
                    'currency' => '',
                    'offers' => [],
                ],
                'business' => [
                    'lowest_price' => 100000,
                    'currency' => '',
                    'offers' => [],
                ],
                'first' => [
                    'lowest_price' => 100000,
                    'currency' => '',
                    'offers' => [],
                ],
            ];

            $class_avilability = [];

            $seats_available = 0;

            foreach ($itinerary['segments'] as $segment) {

                foreach (FlightAvailablity::where('flight_schedule_id', $segment['id'])->where('seats', '>=', $total_passengers)->get() as $class) {
                    $class_avilability[] = $class;
                }
            }

            foreach ($class_avilability as $class_avilable) {
                // $class_name = getClassName($class_avilable['class']);
                $class_name = getClassNameByCabin($class_avilable['cabin']);

                $total_price = 0;
                $total_fare = 0;
                $total_tax = 0;

                $offer = $class_avilable;
                $prices = OneWayOffer::where([
                    ['flight_availablity_id', '=', $offer->id],
                    ['flight_schedule_id', '=', $offer->flight_schedule_id],
                    ['cabin', '=', $offer->cabin],
                    ['class', '=', $offer->class],
                    ['price', '>', 0],
                ])->get();

                // return $prices;

                $hold_weight = 0;
                $hand_weight = 0;
                $hold_pices = 0;
                $currency = 0;
                foreach ($prices as $price) {

                    if ($price->flight_availablity->seats > $seats_available) {
                        $seats_available = $price->flight_availablity->seats;
                    }

                    switch ($price->passenger_type) {
                        case 'AD':
                            $hold_weight = $price->hold_weight;
                            $hand_weight = $price->hand_weight;
                            $hold_pices = $price->hold_pices;
                            $currency = $price->currency;

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
                        case 'IS':
                            $total_price += $price->price * ($request->get('seated_infants', 0));
                            $total_fare += $price->fare_price * ($request->get('seated_infants', 0));
                            $total_tax += $price->tax * ($request->get('seated_infants', 0));
                            break;
                    }
                }

                if ($pricing[$class_name]['lowest_price'] > $total_price && $total_price > 0) {
                    $pricing[$class_name]['lowest_price'] = $total_price;
                    $pricing[$class_name]['currency'] = $currency;
                }

                $offer['price'] = $total_price;
                $offer['tax'] = $total_tax;
                $offer['fare'] = $total_fare;

                $offer['hold_weight'] = $hold_weight;
                $offer['hand_weight'] = $hand_weight;
                $offer['hold_pices'] = $hold_pices;
                $offer['currency'] = $currency;

                // $offer['passenger_pricing'] = $prices->whereIn('passenger_type', $passenger_types)->only([
                //     'passenger_type',
                //     'cabin',
                //     'class',
                //     'fare_basis',
                //     'fare_price',
                //     'tax',
                //     'price',
                //     'currency',
                //     'hold_pices',
                //     'hold_weight',
                //     'hand_weight'
                // ]);

                // $offer['fare_rules'] = getFareRuleItems($offer['carrier'], $offer['fare_id']);
                $offer['rules'] = $class_avilable['rules'];
                $offer['fare_note'] = getFareNote($offer['carrier'], $offer['fare_id']);
                if ($offer['price'] > 0 && $offer['seats'] > 0) {
                    $pricing[$class_name]['offers'][] = $offer;
                }

            }

            // New Added
            $prices = [];
            foreach ($pricing as $cabin => $p) {
                $sorted_offers = [...collect($p['offers'])->sortBy('price')->toArray()];
                $prices[$cabin] = [
                    'lowest_price' => $p['lowest_price'],
                    'offers' => $sorted_offers,
                ];
                // return $p;
            }

            $pricing = $prices;



            $itinerary['pricing'] = $pricing;
            // $itinerary['pricing'] = $prices;
            $itinerary['seats_available'] = $seats_available;

            // if ($seats_available) {
            $result[] = [
                'type' => 'offer',
                'itineraries' => [$itinerary],
                // 'pricing' => $pricing,
                // 'seats_available' => $seats_available,
            ];
            // }
        }

        $dates = [];
        for ($i = -10; $i <= 10; $i++) {
            $dates[date('Y-m-d', strtotime($request->departure_date . $i . ' days'))] = [
                'departure' => date('Y-m-d', strtotime($request->departure_date . $i . ' days')),
                'carrier' => [],
                'has_flight' => false,
            ];
        }

        foreach ($dates as $date) {
            $available_flights = FlightSchedule::where('origin', strtoupper($request->origin_location_code))
                ->where('destination', strtoupper($request->destination_location_code))
                ->whereDate('departure', date('Y-m-d', strtotime($date['departure'])))
                ->where('has_offers', true)
                ->whereHas('one_way_offers')
                ->whereIn('iata', $only)
                ->get();

            if (count($available_flights) > 0) {
                $dates[$date['departure']]['has_flight'] = true;

                foreach ($available_flights as $avf) {
                    $dates[$date['departure']]['carrier'][] = 1;
                }
            }



            // foreach ($available_flights as $flight) {
            //     if (!in_array($flight->iata, $dates[$date['departure']]['carrier'])) {
            //         $dates[$date['departure']]['carrier'][] = $flight->iata;
            //     }
            // }

        }
        return [
            'meta' => [
                'count' => count($result),
                'available_dates' => $dates,
                'from' => getAirport($request->origin_location_code),
                'to' => getAirport($request->destination_location_code),
                'date' => $request->departure_date,
            ],
            'data' => $result,
        ];

    }

    public function one_way_searchv2(Request $request)
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
            'only' => 'sometimes',
        ]);

        # Sync Flight Schedules
        if (!env("SYNC_QUEUE")) {
            $client = new \GuzzleHttp\Client();

            // $aeroTokens1 = AeroToken::whereHas('flight_schedules', function ($query) use ($request) {
            //     // $query->where('origin', $request->origin_location_code)
            //     //     ->where('destination', $request->destination_location_code);

            //     $query->where([
            //         'origin' => $request->origin_location_code,
            //         'destination' => $request->destination_location_code,
            //     ]);
            // })->distinct()
            //     ->get();
            // $aeroTokens2 = AeroToken::whereHas('flight_schedules', function ($query) use ($request) {
            //     // $query->where('origin', $request->origin_location_code)
            //     //     ->where('destination', $request->destination_location_code);

            //     $query->where([
            //         'origin' => $request->destination_location_code,
            //         'destination' => $request->origin_location_code,
            //     ]);
            // })->distinct()
            //     ->get();

            $aeroTokens = AeroToken::when($request->get('only', null), function ($query, $only) {

                $query->whereIn('iata', values: ['BM', 'UZ', 'YI']);
                // $query->whereIn('iata', $only);

            })

                ->distinct()

                ->get();

            // $tks = [];

            // foreach ($aeroTokens1 as $tk) {

            // if (!in_array($tk->id, $tks)) {

            // $tks[] = $tk->id;

            // }

            // }

            // foreach ($aeroTokens2 as $tk) {

            // if (!in_array($tk->id, $tks)) {

            // $tks[] = $tk->id;

            // }

            // }



            $promises = [];

            foreach ($aeroTokens as $aero_token) {

                $command = "A" . date("dM", strtotime($request->departure_date)) . $request->origin_location_code . $request->destination_location_code . "~x";



                // if ($aero_token->iata != 'YL') {

                // Build the request

                $_request = $aero_token->build()->getAsyncCommandRunner($command);



                // Store the promise in the $promises array, keyed by flight ID

                if ($_request != null) {

                    $promises[$aero_token?->id] = $client->sendAsync($_request);

                }

                // }

            }



            // Wait for all the promises to complete

            // This will wait for all the requests to finish, regardless of success or failure

            $_results = Utils::settle($promises)->wait();



            // Process the results

            foreach ($_results as $aero_token_id => $_result) {

                if ($_result['state'] === 'fulfilled') {

                    $response = $_result['value'];

                    $aero_token = AeroToken::find($aero_token_id);

                    $command_result = null;



                    $command = "A" . date("dM", strtotime($request->departure_date)) . $request->origin_location_code . $request->destination_location_code . "~x";



                    if ($aero_token?->data['mode'] == 'user_auth') {

                        // $result = $response->json('d')['Data'];

                        $content = $response->getBody()->getContents();

                        $command_result = (object) [

                            'response' => json_decode($content, true)['d']['Data']

                        ];





                    } else {



                        // $xml = $response->getBody()->getContents();

                        $x = $response->getBody()->getContents();

                        $x = str_replace('<?xml version="1.0" encoding="utf-8"?>', '', $x);

                        // $x = str_replace('<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">', '', $x);

                        $x = str_replace('<soap:Body>', '', $x);

                        $x = str_replace('<RunVRSCommandResult xmlns="http://videcom.com/">', '', $x);

                        $x = str_replace('</RunVRSCommandResult>', '', $x);

                        $x = str_replace('</soap:Body>', '', $x);



                        $xml = simplexml_load_string($x);





                        $body = $xml->xpath('/soap:Envelope')[0];





                        $command_result = (object) [

                            'response' => $body

                        ];

                    }



                    $this->update_flight_seats($aero_token, $command_result, $command);



                } else {

                    // Handle the rejected promise

                    $reason = $_result['reason'];

                    Log::error($reason);



                }

            }



        }
        # End Sync Flight Schedules

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

        $only = $request->get('only', []);
        // $origin_airport = getAirport($request->origin_location_code);
        // $destination_irport = getAirport($request->destination_location_code);

        $schedule_ids = OneWayOffer::where([
            ['from', '=', $request->origin_location_code],
            ['to', '=', $request->destination_location_code],
            ['price', '>', 0]
        ])
            ->whereHas('flight_schedule', function ($q) use ($only) {
                $q->whereIn('iata', $only);
            })
            ->whereIn('passenger_type', $passenger_types)
            ->whereDate('departure', date('Y-m-d', strtotime($request->departure_date)))
            ->get('flight_schedule_id')
            ->pluck('flight_schedule_id')
            ->toArray();

        $schedules = FlightSchedule::whereIn('id', $schedule_ids)
            ->whereIn('iata', $only)
            ->orderBy('departure')
            ->get();

        if (env('SYNC_QUEUE')) {
            foreach ($schedules->groupBy('iata')->toArray() as $iata => $schedule) {
                $flight = FlightSchedule::find(id: $schedule[0]['id']);
                \App\Jobs\CheckFlightSeatAvailabilityJob::dispatch($flight)->onQueue($flight->aero_token?->getQueueId());
            }
        }

        $allow_open_reservation = config('airline.allow_open_reservation');

        foreach ($schedules as $schedule) {

            $itinerary = [
                'duration' => 0,
                'segments' => [],
            ];

            $segment = [
                'departure' => [
                    'iataCode' => $schedule->origin,
                    'at' => $schedule->departure,
                    'airport' => getAirport($schedule->origin),
                    'weather' => [],//fetch_current_weather($schedule->destination),
                ],
                'arrival' => [
                    'iataCode' => $schedule->destination,
                    'at' => $schedule->arrival,
                    'airport' => getAirport($schedule->destination),
                    'weather' => [],//fetch_current_weather($schedule->destination),
                ],
                'carrierCode' => $schedule->iata,
                'carrier' => $schedule->aero_token->only('name', 'iata'),
                "number" => $schedule->flight_number,
                'aircraft' => findAircraft($schedule->aircraft_code > 0 ? $schedule->aircraft_code : 320),
                'duration' => $schedule->duration,
                'id' => $schedule->id,
            ];

            $itinerary['duration'] += $schedule->duration;
            $itinerary['segments'][] = $segment;

            // return $itinerary;

            $pricing = [
                'none' => [
                    'lowest_price' => 100000,
                    'currency' => '',
                    'offers' => [],
                ],
                'discounted_economy' => [
                    'lowest_price' => 100000,
                    'currency' => '',
                    'offers' => [],
                ],
                'economy' => [
                    'lowest_price' => 100000,
                    'currency' => '',
                    'offers' => [],
                ],
                'premium_economy' => [
                    'lowest_price' => 100000,
                    'currency' => '',
                    'offers' => [],
                ],
                'discounted_business' => [
                    'lowest_price' => 100000,
                    'currency' => '',
                    'offers' => [],
                ],
                'business' => [
                    'lowest_price' => 100000,
                    'currency' => '',
                    'offers' => [],
                ],
                'first' => [
                    'lowest_price' => 100000,
                    'currency' => '',
                    'offers' => [],
                ],
            ];

            $class_avilability = [];

            $seats_available = 0;

            foreach ($itinerary['segments'] as $segment) {
                foreach (FlightAvailablity::where('flight_schedule_id', $segment['id'])->get() as $class) {
                    // dispatch(new \App\Jobs\SyncOneWayOfferJob($class));
                    $class_avilability[] = $class;
                }
            }

            foreach ($class_avilability as $class_avilable) {
                // $class_name = getClassName($class_avilable['class']);
                $class_name = getClassNameByCabin($class_avilable['cabin']);

                $total_price = 0;
                $total_fare = 0;
                $total_tax = 0;

                $offer = $class_avilable;
                $prices = OneWayOffer::where([
                    ['flight_availablity_id', '=', $offer->id],
                    ['flight_schedule_id', '=', $offer->flight_schedule_id],
                    ['cabin', '=', $offer->cabin],
                    ['class', '=', $offer->class],
                    ['price', '>', 0],
                ])->get();

                $hold_weight = 0;
                $hand_weight = 0;
                $hold_pices = 0;
                $currency = 0;
                foreach ($prices as $price) {

                    if ($price->flight_availablity->seats > $seats_available) {
                        $seats_available = $price->flight_availablity->seats;
                    }

                    switch ($price->passenger_type) {
                        case 'AD':
                            $hold_weight = $price->hold_weight;
                            $hand_weight = $price->hand_weight;
                            $hold_pices = $price->hold_pices;
                            $currency = $price->currency;

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
                        case 'IS':
                            $total_price += $price->price * ($request->get('seated_infants', 0));
                            $total_fare += $price->fare_price * ($request->get('seated_infants', 0));
                            $total_tax += $price->tax * ($request->get('seated_infants', 0));
                            break;
                    }
                }

                if ($pricing[$class_name]['lowest_price'] > $total_price && $total_price > 0) {
                    $pricing[$class_name]['lowest_price'] = $total_price;
                    $pricing[$class_name]['currency'] = $currency;
                }

                $offer['price'] = $total_price;
                $offer['tax'] = $total_tax;
                $offer['fare'] = $total_fare;

                $offer['hold_weight'] = (int) str_replace("K", "", $hold_weight);
                $offer['hand_weight'] = (int) str_replace("K", "", $hand_weight);

                $offer['hold_pices'] = $hold_pices;
                $offer['currency'] = $currency;

                // $offer['fare_rules'] = getFareRuleItems($offer['carrier'], $offer['fare_id']);
                $offer['rules'] = $class_avilable['rules'];
                $offer['fare_note'] = getFareNote($offer['carrier'], $offer['fare_id']);

                if ($offer['price'] > 0) {
                    $offer['allow_open_booking'] = in_array($offer['class'], $allow_open_reservation);

                    $pricing[$class_name]['offers'][] = $offer;
                }

            }

            $prices = [];
            foreach ($pricing as $cabin => $p) {
                $sorted_offers = [...collect($p['offers'])->sortBy('price')->toArray()];
                # Fix seats lower than 0
                foreach ($sorted_offers as &$o) {
                    if ($o['seats'] < 0) {
                        $o['seats'] = 0;
                    }
                }

                $prices[$cabin] = [
                    'lowest_price' => $p['lowest_price'],
                    'offers' => $sorted_offers,
                ];
            }

            $pricing = $prices;



            $itinerary['pricing'] = $pricing;
            $itinerary['seats_available'] = $seats_available;

            // if ($seats_available) {
            $result[] = [
                'type' => 'offer',
                'itineraries' => [$itinerary],
            ];
        }

        $dates = [];
        for ($i = -10; $i <= 10; $i++) {
            $dates[date('Y-m-d', strtotime($request->departure_date . $i . ' days'))] = [
                'departure' => date('Y-m-d', strtotime($request->departure_date . $i . ' days')),
                'carrier' => [],
                'has_flight' => false,
            ];
        }

        foreach ($dates as $date) {
            $available_flights = FlightSchedule::where('origin', strtoupper($request->origin_location_code))
                ->where('destination', strtoupper($request->destination_location_code))
                ->whereDate('departure', date('Y-m-d', strtotime($date['departure'])))
                // ->where('has_offers', true)
                ->whereHas('one_way_offers')
                ->whereIn('iata', $only)
                ->get();

            if (count($available_flights) > 0) {
                $dates[$date['departure']]['has_flight'] = true;

                foreach ($available_flights as $avf) {
                    $dates[$date['departure']]['carrier'][] = 1;
                }
            }

        }
        return [
            'meta' => [
                'count' => count($result),
                'available_dates' => $dates,
                'from' => getAirport($request->origin_location_code),
                'to' => getAirport($request->destination_location_code),
                'date' => $request->departure_date,
            ],
            'data' => $result,
        ];

    }

    public function one_way_searchv3(Request $request)
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
            'only' => 'sometimes',
        ]);

        # Sync Flight Schedules
        if (!env("SYNC_QUEUE")) {
            $client = new \GuzzleHttp\Client();
            $aeroTokens = [];

            if ($request->has('only')) {
                $aeroTokens = AeroToken::whereIn('iata', $request->only)
                    ->whereHas('flight_schedules', function ($query) use ($request) {
                        $query->where('origin', $request->origin_location_code)
                            ->where('destination', $request->destination_location_code);
                    })
                    ->get();
            } else {
                $aeroTokens = AeroToken::whereHas('flight_schedules', function ($query) use ($request) {
                    $query->where('origin', $request->origin_location_code)
                        ->where('destination', $request->destination_location_code);
                })->distinct()
                    ->get();
            }

            // return $aeroTokens;

            $promises = [];
            foreach ($aeroTokens as $aero_token) {
                $command = "A" . date("dM", strtotime($request->departure_date)) . $request->origin_location_code . $request->destination_location_code . "~x";

                // Build the request
                $_request = $aero_token->build()->getAsyncCommandRunner($command);

                // Store the promise in the $promises array, keyed by flight ID
                if ($_request != null) {
                    $promises[$aero_token->id] = $client->sendAsync($_request);
                }
            }

            // Wait for all the promises to complete
            // This will wait for all the requests to finish, regardless of success or failure
            $_results = Utils::settle($promises)->wait();

            // Process the results
            foreach ($_results as $aero_token_id => $_result) {
                if ($_result['state'] === 'fulfilled') {
                    $response = $_result['value'];
                    $aero_token = AeroToken::find($aero_token_id);
                    $command_result = null;

                    $command = "A" . date("dM", strtotime($request->departure_date)) . $request->origin_location_code . $request->destination_location_code . "~x";

                    if ($aero_token?->data['mode'] == 'user_auth') {
                        // $result = $response->json('d')['Data'];
                        $content = $response->getBody()->getContents();
                        $command_result = (object) [
                            'response' => json_decode($content, true)['d']['Data']
                        ];


                    } else {

                        // $xml = $response->getBody()->getContents();
                        $x = $response->getBody()->getContents();
                        $x = str_replace('<?xml version="1.0" encoding="utf-8"?>', '', $x);
                        // $x = str_replace('<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">', '', $x);
                        $x = str_replace('<soap:Body>', '', $x);
                        $x = str_replace('<RunVRSCommandResult xmlns="http://videcom.com/">', '', $x);
                        $x = str_replace('</RunVRSCommandResult>', '', $x);
                        $x = str_replace('</soap:Body>', '', $x);

                        $xml = simplexml_load_string($x);


                        $body = $xml->xpath('/soap:Envelope')[0];


                        $command_result = (object) [
                            'response' => $body
                        ];
                    }

                    $this->update_flight_seats($aero_token, $command_result, $command);

                } else {
                    // Handle the rejected promise
                    $reason = $_result['reason'];
                    Log::error($reason);

                }
            }
        }
        # End Sync Flight Schedules

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

        $only = $request->get('only', []);
        // $origin_airport = getAirport($request->origin_location_code);
        // $destination_irport = getAirport($request->destination_location_code);

        $schedule_ids = OneWayOffer::where([
            ['from', '=', $request->origin_location_code],
            ['to', '=', $request->destination_location_code],
            ['price', '>', 0]
        ])
            ->whereHas('flight_schedule', function ($q) use ($only) {
                $q->whereIn('iata', $only);
            })
            ->whereIn('passenger_type', $passenger_types)
            ->whereDate('departure', date('Y-m-d', strtotime($request->departure_date)))
            ->get('flight_schedule_id')
            ->pluck('flight_schedule_id')
            ->toArray();

        $schedules = FlightSchedule::whereIn('id', $schedule_ids)
            ->whereIn('iata', $only)
            ->orderBy('departure')
            ->get();

        if (env('SYNC_QUEUE')) {
            foreach ($schedules->groupBy('iata')->toArray() as $iata => $schedule) {
                $flight = FlightSchedule::find(id: $schedule[0]['id']);
                \App\Jobs\CheckFlightSeatAvailabilityJob::dispatch($flight)->onQueue($flight->aero_token?->getQueueId());
            }
        }

        $allow_open_reservation = config('airline.allow_open_reservation');

        foreach ($schedules as $schedule) {

            $itinerary = [
                'duration' => 0,
                'segments' => [],
            ];

            $segment = [
                'departure' => [
                    'iataCode' => $schedule->origin,
                    'at' => $schedule->departure,
                    'airport' => getAirport($schedule->origin),
                    'weather' => [],//fetch_current_weather($schedule->destination),
                ],
                'arrival' => [
                    'iataCode' => $schedule->destination,
                    'at' => $schedule->arrival,
                    'airport' => getAirport($schedule->destination),
                    'weather' => [],//fetch_current_weather($schedule->destination),
                ],
                'carrierCode' => $schedule->iata,
                'carrier' => $schedule->aero_token->only('name', 'iata'),
                "number" => $schedule->flight_number,
                'aircraft' => findAircraft($schedule->aircraft_code > 0 ? $schedule->aircraft_code : 320),
                'duration' => $schedule->duration,
                'id' => $schedule->id,
            ];

            $itinerary['duration'] += $schedule->duration;
            $itinerary['segments'][] = $segment;

            // return $itinerary;

            $pricing = [
                'none' => [
                    'lowest_price' => 100000,
                    'currency' => '',
                    'offers' => [],
                ],
                'discounted_economy' => [
                    'lowest_price' => 100000,
                    'currency' => '',
                    'offers' => [],
                ],
                'economy' => [
                    'lowest_price' => 100000,
                    'currency' => '',
                    'offers' => [],
                ],
                'premium_economy' => [
                    'lowest_price' => 100000,
                    'currency' => '',
                    'offers' => [],
                ],
                'discounted_business' => [
                    'lowest_price' => 100000,
                    'currency' => '',
                    'offers' => [],
                ],
                'business' => [
                    'lowest_price' => 100000,
                    'currency' => '',
                    'offers' => [],
                ],
                'first' => [
                    'lowest_price' => 100000,
                    'currency' => '',
                    'offers' => [],
                ],
            ];

            $class_avilability = [];

            $seats_available = 0;

            foreach ($itinerary['segments'] as $segment) {
                foreach (FlightAvailablity::where('flight_schedule_id', $segment['id'])->get() as $class) {
                    // dispatch(new \App\Jobs\SyncOneWayOfferJob($class));
                    $class_avilability[] = $class;
                }
            }

            foreach ($class_avilability as $class_avilable) {
                // $class_name = getClassName($class_avilable['class']);
                $class_name = getClassNameByCabin($class_avilable['cabin']);

                $total_price = 0;
                $total_fare = 0;
                $total_tax = 0;

                $offer = $class_avilable;
                $prices = OneWayOffer::where([
                    ['flight_availablity_id', '=', $offer->id],
                    ['flight_schedule_id', '=', $offer->flight_schedule_id],
                    ['cabin', '=', $offer->cabin],
                    ['class', '=', $offer->class],
                    ['price', '>', 0],
                ])->get();

                $hold_weight = 0;
                $hand_weight = 0;
                $hold_pices = 0;
                $currency = 0;
                foreach ($prices as $price) {

                    if ($price->flight_availablity->seats > $seats_available) {
                        $seats_available = $price->flight_availablity->seats;
                    }

                    switch ($price->passenger_type) {
                        case 'AD':
                            $hold_weight = $price->hold_weight;
                            $hand_weight = $price->hand_weight;
                            $hold_pices = $price->hold_pices;
                            $currency = $price->currency;

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
                        case 'IS':
                            $total_price += $price->price * ($request->get('seated_infants', 0));
                            $total_fare += $price->fare_price * ($request->get('seated_infants', 0));
                            $total_tax += $price->tax * ($request->get('seated_infants', 0));
                            break;
                    }
                }

                if ($pricing[$class_name]['lowest_price'] > $total_price && $total_price > 0) {
                    $pricing[$class_name]['lowest_price'] = $total_price;
                    $pricing[$class_name]['currency'] = $currency;
                }

                $offer['price'] = $total_price;
                $offer['tax'] = $total_tax;
                $offer['fare'] = $total_fare;

                $offer['hold_weight'] = (int) str_replace("K", "", $hold_weight);
                $offer['hand_weight'] = (int) str_replace("K", "", $hand_weight);

                $offer['hold_pices'] = $hold_pices;
                $offer['currency'] = $currency;

                // $offer['fare_rules'] = getFareRuleItems($offer['carrier'], $offer['fare_id']);
                $offer['rules'] = $class_avilable['rules'];
                $offer['fare_note'] = getFareNote($offer['carrier'], $offer['fare_id']);

                if ($offer['price'] > 0) {
                    $offer['allow_open_booking'] = in_array($offer['class'], $allow_open_reservation);

                    $pricing[$class_name]['offers'][] = $offer;
                }

            }

            $prices = [];
            foreach ($pricing as $cabin => $p) {
                $sorted_offers = [...collect($p['offers'])->sortBy('price')->toArray()];
                # Fix seats lower than 0
                foreach ($sorted_offers as &$o) {
                    if ($o['seats'] < 0) {
                        $o['seats'] = 0;
                    }
                }

                $prices[$cabin] = [
                    'lowest_price' => $p['lowest_price'],
                    'offers' => $sorted_offers,
                ];
            }

            $pricing = $prices;



            $itinerary['pricing'] = $pricing;
            $itinerary['seats_available'] = $seats_available;

            // if ($seats_available) {
            $result[] = [
                'type' => 'offer',
                'itineraries' => [$itinerary],
            ];
        }

        $dates = [];
        for ($i = -10; $i <= 10; $i++) {
            $dates[date('Y-m-d', strtotime($request->departure_date . $i . ' days'))] = [
                'departure' => date('Y-m-d', strtotime($request->departure_date . $i . ' days')),
                'carrier' => [],
                'has_flight' => false,
            ];
        }

        foreach ($dates as $date) {
            $available_flights = FlightSchedule::where('origin', strtoupper($request->origin_location_code))
                ->where('destination', strtoupper($request->destination_location_code))
                ->whereDate('departure', date('Y-m-d', strtotime($date['departure'])))
                // ->where('has_offers', true)
                ->whereHas('one_way_offers')
                ->whereIn('iata', $only)
                ->get();

            if (count($available_flights) > 0) {
                $dates[$date['departure']]['has_flight'] = true;

                foreach ($available_flights as $avf) {
                    $dates[$date['departure']]['carrier'][] = 1;
                }
            }

        }
        return [
            'meta' => [
                'count' => count($result),
                'available_dates' => $dates,
                'from' => getAirport($request->origin_location_code),
                'to' => getAirport($request->destination_location_code),
                'date' => $request->departure_date,
            ],
            'data' => $result,
        ];

    }

    public function one_way_searchv5(Request $request)
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
            'only' => 'sometimes',
        ]);

        # Sync Flight Schedules
        if (!env("SYNC_QUEUE")) {
            $client = new \GuzzleHttp\Client();

            $aeroTokens = AeroToken::when($request->get('only', null), function ($query, $only) {
                $query->whereIn('iata', $only);
            })
            ->distinct()
            ->get();

            $promises = [];

            foreach ($aeroTokens as $aero_token) {

                $command = "A" . date("dM", strtotime($request->departure_date)) . $request->origin_location_code . $request->destination_location_code . "~x";

                $_request = $aero_token->build()->getAsyncCommandRunner($command);
                if ($_request != null) {
                    $promises[$aero_token?->id] = $client->sendAsync($_request);
                }
            }

            $_results = Utils::settle($promises)->wait();

            // Process the results

            foreach ($_results as $aero_token_id => $_result) {

                if ($_result['state'] === 'fulfilled') {

                    $response = $_result['value'];

                    $aero_token = AeroToken::find($aero_token_id);

                    $command_result = null;

                    $command = "A" . date("dM", strtotime($request->departure_date)) . $request->origin_location_code . $request->destination_location_code . "~x";

                    if ($aero_token?->data['mode'] == 'user_auth') {
                        $content = $response->getBody()->getContents();
                        $command_result = (object) [
                            'response' => json_decode($content, true)['d']['Data']
                        ];
                    } else {

                        $x = $response->getBody()->getContents();

                        $x = str_replace('<?xml version="1.0" encoding="utf-8"?>', '', $x);

                        $x = str_replace('<soap:Body>', '', $x);

                        $x = str_replace('<RunVRSCommandResult xmlns="http://videcom.com/">', '', $x);

                        $x = str_replace('</RunVRSCommandResult>', '', $x);

                        $x = str_replace('</soap:Body>', '', $x);

                        $xml = simplexml_load_string($x);

                        $body = $xml->xpath('/soap:Envelope')[0];

                        $command_result = (object) [
                            'response' => $body
                        ];

                    }
                    $this->update_flight_seats($aero_token, $command_result, $command);
                } else {
                    // Handle the rejected promise
                    $reason = $_result['reason'];
                    Log::error($reason);
                }

            }
        }
        # End Sync Flight Schedules

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

        $only = $request->get('only', []);
        // $origin_airport = getAirport($request->origin_location_code);
        // $destination_irport = getAirport($request->destination_location_code);

        $schedule_ids = OneWayOffer::where([
            ['from', '=', $request->origin_location_code],
            ['to', '=', $request->destination_location_code],
            ['price', '>', 0]
        ])
            ->whereHas('flight_schedule', function ($q) use ($only) {
                $q->whereIn('iata', $only);
            })
            ->whereIn('passenger_type', $passenger_types)
            ->whereDate('departure', date('Y-m-d', strtotime($request->departure_date)))
            ->get('flight_schedule_id')
            ->pluck('flight_schedule_id')
            ->toArray();

        $schedules = FlightSchedule::whereIn('id', $schedule_ids)
            ->whereIn('iata', $only)
            ->orderBy('departure')
            ->get();

        foreach ($schedules as $schedule) {
            foreach ($schedule->availablities()->get() as $availability) {
                \App\Jobs\UpdateOneWayOfferPrices::dispatch(($availability))->onQueue($schedule->aero_token->getQueueId())->delay(now()->addSeconds(5));
            }
        }

        if (env('SYNC_QUEUE')) {
            foreach ($schedules->groupBy('iata')->toArray() as $iata => $schedule) {
                $flight = FlightSchedule::find(id: $schedule[0]['id']);
                \App\Jobs\CheckFlightSeatAvailabilityJob::dispatch($flight)->onQueue($flight->aero_token?->getQueueId());
            }
        }

        $allow_open_reservation = config('airline.allow_open_reservation');

        foreach ($schedules as $schedule) {

            $itinerary = [
                'duration' => 0,
                'segments' => [],
            ];

            $segment = [
                'departure' => [
                    'iataCode' => $schedule->origin,
                    'at' => $schedule->departure,
                    'airport' => getAirport($schedule->origin),
                    'weather' => [],//fetch_current_weather($schedule->destination),
                ],
                'arrival' => [
                    'iataCode' => $schedule->destination,
                    'at' => $schedule->arrival,
                    'airport' => getAirport($schedule->destination),
                    'weather' => [],//fetch_current_weather($schedule->destination),
                ],
                'carrierCode' => $schedule->iata,
                'carrier' => $schedule->aero_token->only('name', 'iata'),
                "number" => $schedule->flight_number,
                'aircraft' => findAircraft($schedule->aircraft_code > 0 ? $schedule->aircraft_code : 320),
                'duration' => $schedule->duration,
                'id' => $schedule->id,
            ];

            $itinerary['duration'] += $schedule->duration;
            $itinerary['segments'][] = $segment;

            // return $itinerary;

            $pricing = [
                'none' => [
                    'lowest_price' => 100000,
                    'currency' => '',
                    'offers' => [],
                ],
                'discounted_economy' => [
                    'lowest_price' => 100000,
                    'currency' => '',
                    'offers' => [],
                ],
                'economy' => [
                    'lowest_price' => 100000,
                    'currency' => '',
                    'offers' => [],
                ],
                'premium_economy' => [
                    'lowest_price' => 100000,
                    'currency' => '',
                    'offers' => [],
                ],
                'discounted_business' => [
                    'lowest_price' => 100000,
                    'currency' => '',
                    'offers' => [],
                ],
                'business' => [
                    'lowest_price' => 100000,
                    'currency' => '',
                    'offers' => [],
                ],
                'first' => [
                    'lowest_price' => 100000,
                    'currency' => '',
                    'offers' => [],
                ],
            ];

            $class_avilability = [];

            $seats_available = 0;

            foreach ($itinerary['segments'] as $segment) {
                foreach (FlightAvailablity::where('flight_schedule_id', $segment['id'])->get() as $class) {
                    // dispatch(new \App\Jobs\SyncOneWayOfferJob($class));
                    $class_avilability[] = $class;
                }
            }

            foreach ($class_avilability as $class_avilable) {
                // $class_name = getClassName($class_avilable['class']);
                $class_name = getClassNameByCabin($class_avilable['cabin']);

                $total_price = 0;
                $total_fare = 0;
                $total_tax = 0;

                $offer = $class_avilable;
                $prices = OneWayOffer::where([
                    ['flight_availablity_id', '=', $offer->id],
                    ['flight_schedule_id', '=', $offer->flight_schedule_id],
                    ['cabin', '=', $offer->cabin],
                    ['class', '=', $offer->class],
                    ['price', '>', 0],
                ])->get();

                $hold_weight = 0;
                $hand_weight = 0;
                $hold_pices = 0;
                $currency = 0;
                foreach ($prices as $price) {

                    if ($price->flight_availablity->seats > $seats_available) {
                        $seats_available = $price->flight_availablity->seats;
                    }

                    switch ($price->passenger_type) {
                        case 'AD':
                            $hold_weight = $price->hold_weight;
                            $hand_weight = $price->hand_weight;
                            $hold_pices = $price->hold_pices;
                            $currency = $price->currency;

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
                        case 'IS':
                            $total_price += $price->price * ($request->get('seated_infants', 0));
                            $total_fare += $price->fare_price * ($request->get('seated_infants', 0));
                            $total_tax += $price->tax * ($request->get('seated_infants', 0));
                            break;
                    }
                }

                if ($pricing[$class_name]['lowest_price'] > $total_price && $total_price > 0) {
                    $pricing[$class_name]['lowest_price'] = $total_price;
                    $pricing[$class_name]['currency'] = $currency;
                }

                $offer['price'] = $total_price;
                $offer['tax'] = $total_tax;
                $offer['fare'] = $total_fare;

                $offer['hold_weight'] = (int) str_replace("K", "", $hold_weight);
                $offer['hand_weight'] = (int) str_replace("K", "", $hand_weight);

                $offer['hold_pices'] = $hold_pices;
                $offer['currency'] = $currency;

                // $offer['fare_rules'] = getFareRuleItems($offer['carrier'], $offer['fare_id']);
                $offer['rules'] = $class_avilable['rules'];
                $offer['fare_note'] = getFareNote($offer['carrier'], $offer['fare_id']);

                if ($offer['price'] > 0) {
                    $offer['allow_open_booking'] = in_array($offer['class'], $allow_open_reservation);

                    $pricing[$class_name]['offers'][] = $offer;
                }

            }

            $prices = [];
            foreach ($pricing as $cabin => $p) {
                $sorted_offers = [...collect($p['offers'])->sortBy('price')->toArray()];
                # Fix seats lower than 0
                foreach ($sorted_offers as &$o) {
                    if ($o['seats'] < 0) {
                        $o['seats'] = 0;
                    }
                }

                $prices[$cabin] = [
                    'lowest_price' => $p['lowest_price'],
                    'offers' => $sorted_offers,
                ];
            }

            $pricing = $prices;



            $itinerary['pricing'] = $pricing;
            $itinerary['seats_available'] = $seats_available;

            // if ($seats_available) {
            $result[] = [
                'type' => 'offer',
                'itineraries' => [$itinerary],
            ];
        }

        $dates = [];
        for ($i = -5; $i <= 5; $i++) {
            $dates[date('Y-m-d', strtotime($request->departure_date . $i . ' days'))] = [
                'departure' => date('Y-m-d', strtotime($request->departure_date . $i . ' days')),
                'carrier' => [],
                'has_flight' => false,
            ];
        }

        foreach ($dates as $date) {
            $available_flights = FlightSchedule::where('origin', strtoupper($request->origin_location_code))
                ->where('destination', strtoupper($request->destination_location_code))
                ->whereDate('departure', date('Y-m-d', strtotime($date['departure'])))
                // ->where('has_offers', true)
                ->whereHas('one_way_offers')
                ->whereIn('iata', $only)
                ->get();

            if (count($available_flights) > 0) {
                $dates[$date['departure']]['has_flight'] = true;

                foreach ($available_flights as $avf) {
                    $dates[$date['departure']]['carrier'][] = 1;
                }
            }

        }
        return [
            'meta' => [
                'count' => count($result),
                'available_dates' => $dates,
                'from' => getAirport($request->origin_location_code),
                'to' => getAirport($request->destination_location_code),
                'date' => $request->departure_date,
            ],
            'data' => $result,
        ];

    }

    public function round_way_search(Request $request)
    {
        $request->validate([
            'origin_location_code' => 'required',
            'destination_location_code' => 'required',
            'departure_date' => 'required',
            'return_date' => 'required',
            'adults' => 'required',
            'children' => 'sometimes',
            'infants' => 'sometimes',
            'travel_class' => 'sometimes',
            'airline_codes' => 'sometimes',
            'none_stop' => 'sometimes',
            'max_price' => 'sometimes',
            'max' => 'sometimes',
            'merged' => 'sometimes',
        ]);

        if (!$request->merged) {
            $origin_destinations = [
                [
                    'from' => $request->origin_location_code,
                    'to' => $request->destination_location_code,
                    'departure' => $request->departure_date,
                ],
                [
                    'from' => $request->destination_location_code,
                    'to' => $request->origin_location_code,
                    'departure' => $request->return_date,
                ]
            ];

            return $this->multicity_flight_search($request->merge(['origin_destinations' => $origin_destinations]));
        }

        $total_passengers = ($request->get('adults', 0) + $request->get('children', 0) + $request->get('seated_infants', 0));

        $result = [
            'offers' => [],
        ];

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

        $round_offers = RoundWayOffer::with('segments')
            ->where('from', $request->origin_location_code)
            ->where('to', $request->destination_location_code)
            ->whereDate('departure', date('Y-m-d', strtotime($request->departure_date)))
            ->whereDate('return', date('Y-m-d', strtotime($request->return_date)))
            ->get();


        // return $round_offers;

        foreach ($round_offers as $round_way_offer) {
            $round_offer = [
                'itineraries' => [],
            ];
            foreach ($round_way_offer->segments as $offer_segment) {
                $itinerary = [
                    'duration' => 0,
                    'segments' => [],
                ];

                $segment = [
                    'departure' => [
                        'iataCode' => $offer_segment->flight_schedule->origin,
                        'at' => $offer_segment->flight_schedule->departure,
                        'airport' => getAirport($offer_segment->flight_schedule->origin)
                    ],
                    'arrival' => [
                        'iataCode' => $offer_segment->flight_schedule->destination,
                        'at' => $offer_segment->flight_schedule->arrival,
                        'airport' => getAirport($offer_segment->flight_schedule->destination)
                    ],
                    'carrierCode' => $offer_segment->flight_schedule->iata,
                    "number" => $offer_segment->flight_schedule->flight_number,
                    'aircraft' => [
                        'code' => '320',
                    ],
                    'duration' => $offer_segment->flight_schedule->duration,
                    'flight_schedule_id' => $offer_segment->flight_schedule_id,
                    'round_way_offer_id' => $offer_segment->round_way_offer_id,
                    'round_way_segment_id' => $offer_segment->id,
                ];

                $itinerary['duration'] += $offer_segment->flight_schedule->duration;
                $itinerary['segments'][] = $segment;

                $pricing = [
                    'economy' => [
                        'lowest_price' => 100000,
                        'offers' => [],
                    ],
                    'business' => [
                        'lowest_price' => 100000,
                        'offers' => [],
                    ],
                    'first' => [
                        'lowest_price' => 100000,
                        'offers' => [],
                    ],
                ];

                foreach ($itinerary['segments'] as $segment) {

                    $pricings = RoundWayPricing::where('round_way_segment_id', $segment['round_way_segment_id'])
                        ->whereIn('passenger_type', $passenger_types)
                        ->get();

                    foreach ($pricings->groupBy('cabin') as $cabin => $prices) {

                        switch ($cabin) {
                            case 'Y':


                                foreach ($prices as $price) {
                                    $total_price = 0;
                                    $total_fare = 0;
                                    $total_tax = 0;

                                    $offer = [];

                                    $offer['name'] = $price->flight_availablity->name;
                                    $offer['display_name'] = $price->flight_availablity->display_name;
                                    $offer['seats'] = $price->flight_availablity->seats;
                                    $offer['cabin'] = $price->cabin;
                                    $offer['class'] = $price->class;

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
                                        case 'IS':
                                            $total_price += $price->price * ($request->get('seated_infants', 0));
                                            $total_fare += $price->fare_price * ($request->get('seated_infants', 0));
                                            $total_tax += $price->tax * ($request->get('seated_infants', 0));
                                            break;

                                    }

                                    if ($pricing['economy']['lowest_price'] > $total_price) {
                                        $pricing['economy']['lowest_price'] = $total_price;
                                    }

                                    $offer['price'] = $total_price;
                                    $offer['tax'] = $total_tax;
                                    $offer['fare'] = $total_fare;
                                    $pricing['economy']['offers'][] = $offer;
                                }
                                break;
                            case 'C':

                                foreach ($prices as $price) {
                                    $total_price = 0;
                                    $total_fare = 0;
                                    $total_tax = 0;

                                    $offer = [];

                                    $offer['name'] = $price->flight_availablity->name;
                                    $offer['display_name'] = $price->flight_availablity->display_name;
                                    $offer['seats'] = $price->flight_availablity->seats;
                                    $offer['cabin'] = $price->cabin;
                                    $offer['class'] = $price->class;

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
                                        case 'IS':
                                            $total_price += $price->price * ($request->get('seated_infants', 0));
                                            $total_fare += $price->fare_price * ($request->get('seated_infants', 0));
                                            $total_tax += $price->tax * ($request->get('seated_infants', 0));
                                            break;
                                    }

                                    if ($pricing['business']['lowest_price'] > $total_price) {
                                        $pricing['business']['lowest_price'] = $total_price;
                                    }

                                    $offer['price'] = $total_price;
                                    $offer['tax'] = $total_tax;
                                    $offer['fare'] = $total_fare;
                                    $pricing['business']['offers'][] = $offer;
                                }

                                break;
                            default:

                                foreach ($prices as $price) {

                                    $total_price = 0;
                                    $total_fare = 0;
                                    $total_tax = 0;

                                    $offer = [];


                                    $offer['name'] = $price->flight_availablity->name;
                                    $offer['display_name'] = $price->flight_availablity->display_name;
                                    $offer['seats'] = $price->flight_availablity->seats;
                                    $offer['cabin'] = $price->cabin;
                                    $offer['class'] = $price->class;

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
                                        case 'IS':
                                            $total_price += $price->price * ($request->get('seated_infants', 0));
                                            $total_fare += $price->fare_price * ($request->get('seated_infants', 0));
                                            $total_tax += $price->tax * ($request->get('seated_infants', 0));
                                            break;
                                    }

                                    if ($pricing['economy']['lowest_price'] > $total_price) {
                                        $pricing['economy']['lowest_price'] = $total_price;
                                    }

                                    $offer['name'] = $total_price;
                                    $offer['price'] = $total_price;
                                    $offer['tax'] = $total_tax;
                                    $offer['fare'] = $total_fare;
                                    $pricing['economy']['offers'][] = $offer;
                                }

                                //     "name": "Economy B Class",
                                // "display_name": "Fly Flex Plus",
                                // "cabin": "Y",
                                // "class": "B",
                                // "is_international": 0,

                                break;
                        }
                    }
                }

                $itinerary['pricing'] = $pricing;

                $round_offer['itineraries'][] = $itinerary;


            }

            $result['offers'][] = $round_offer;
        }

        return [
            'meta' => [
                'count' => count($result['offers'])
            ],
            'data' => $result['offers'],
        ];
    }

    public function multicity_flight_search(Request $request)
    {
        $request->validate([
            'origin_destinations' => 'required',
            'adults' => 'required',
            'children' => 'sometimes',
            'infants' => 'sometimes',
            'travel_class' => 'sometimes',
            'airline_codes' => 'sometimes',
            'none_stop' => 'sometimes',
            'max_price' => 'sometimes',
            'max' => 'sometimes',
        ]);

        $offer = [];

        $data = [
            'origin_destinations' => $request->get('origin_destinations'),
            'adults' => $request->get('adults', 0),
            'children' => $request->get('children', 0),
            'infants' => $request->get('infants', 0),
            'seated_infants' => $request->get('seated_infants', 0),
        ];

        $total_passengers = ($data['adults'] + $data['children'] + $data['seated_infants']);

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

        foreach ($data['origin_destinations'] as $origin_destination) {
            $one_way_offers = OneWayOffer::where([
                'from' => $origin_destination['from'],
                'to' => $origin_destination['to'],
            ])
                ->whereIn('passenger_type', $passenger_types)
                ->whereDate('departure', date('Y-m-d', strtotime($origin_destination['departure'])))
                ->get();

            $schedule_ids = $one_way_offers->pluck('flight_schedule_id')->toArray();

            $schedules = FlightSchedule::whereIn('id', $schedule_ids)->where('has_offers', true)->get();

            $line_offers = [];

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

                $pricing = [
                    'economy' => [
                        'lowest_price' => 100000,
                        'offers' => [],
                    ],
                    'business' => [
                        'lowest_price' => 100000,
                        'offers' => [],
                    ],
                    'first' => [
                        'lowest_price' => 100000,
                        'offers' => [],
                    ],
                ];

                $class_avilability = [];

                $seats_available = 0;

                foreach ($itinerary['segments'] as $segment) {

                    foreach (FlightAvailablity::where('flight_schedule_id', $segment['id'])->where('seats', '>=', $total_passengers)->get() as $class) {
                        $class_avilability[] = $class;
                    }
                }

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

                                if ($price->flight_availablity->seats > $seats_available) {
                                    $seats_available = $price->flight_availablity->seats;
                                }

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
                                    case 'IS':
                                        $total_price += $price->price * ($request->get('seated_infants', 0));
                                        $total_fare += $price->fare_price * ($request->get('seated_infants', 0));
                                        $total_tax += $price->tax * ($request->get('seated_infants', 0));
                                        break;
                                }
                            }

                            if ($pricing['economy']['lowest_price'] > $total_price) {
                                $pricing['economy']['lowest_price'] = $total_price;
                            }

                            $offer['price'] = $total_price;
                            $offer['tax'] = $total_tax;
                            $offer['fare'] = $total_fare;
                            $offer['rules'] = $class_avilable['rules'];
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
                                if ($price->flight_availablity->seats > $seats_available) {
                                    $seats_available = $price->flight_availablity->seats;
                                }
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
                                    case 'IS':
                                        $total_price += $price->price * ($request->get('seated_infants', 0));
                                        $total_fare += $price->fare_price * ($request->get('seated_infants', 0));
                                        $total_tax += $price->tax * ($request->get('seated_infants', 0));
                                        break;
                                }
                            }

                            if ($pricing['business']['lowest_price'] > $total_price) {
                                $pricing['business']['lowest_price'] = $total_price;
                            }

                            $offer['price'] = $total_price;
                            $offer['tax'] = $total_tax;
                            $offer['fare'] = $total_fare;
                            $offer['rules'] = $class_avilable['rules'];
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
                                if ($price->flight_availablity->seats > $seats_available) {
                                    $seats_available = $price->flight_availablity->seats;
                                }

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
                                    case 'IS':
                                        $total_price += $price->price * ($request->get('seated_infants', 0));
                                        $total_fare += $price->fare_price * ($request->get('seated_infants', 0));
                                        $total_tax += $price->tax * ($request->get('seated_infants', 0));
                                        break;
                                }
                            }

                            if ($pricing['economy']['lowest_price'] > $total_price) {
                                $pricing['economy']['lowest_price'] = $total_price;
                            }

                            $offer['price'] = $total_price;
                            $offer['tax'] = $total_tax;
                            $offer['fare'] = $total_fare;
                            $offer['rules'] = $class_avilable['rules'];
                            $pricing['economy']['offers'][] = $offer;
                            break;
                    }
                }

                $itinerary['pricing'] = $pricing;
                $itinerary['seats_available'] = $seats_available;

                $line_offers[] = $itinerary;
            }

            $offers[] = [
                'from' => $origin_destination['from'],
                'to' => $origin_destination['to'],
                'date' => $origin_destination['departure'],
                'type' => 'offer',
                'itineraries' => $line_offers,
                // 'pricing' => $pricing,
            ];
        }

        // return $data;

        return [
            'meta' => [
                'count' => count($offers),
            ],
            'data' => $offers,
        ];
    }

    public function find_destinations(Request $request)
    {
        $request->validate([
            'origin_location_code' => 'required',
            'destination_location_code' => 'required',
        ]);

        $dates = [
            date("Y-m-d H:i:s"),
            date("Y-m-d H:i:s", strtotime(date('Y-m-d') . '+ 30 days')),
        ];

        // return $dates;
        $offers = \App\Models\FlightSchedule::where('has_offers', true)
            ->when($request->get('origin_location_code'), function ($q, $v) {
                $q->where('origin', $v);
            })
            ->when($request->get('destination_location_code'), function ($q, $v) {
                $q->where('destination', $v);
            })
            ->whereBetween('departure', $dates)
            ->get();

        return $offers;
    }

    public function fetch_seat_map(Request $request)
    {
        $flight_schedule = \App\Models\FlightSchedule::find($request->flight_schedule_id);

        $seat_map = $flight_schedule->aero_token->build()->seatMap($flight_schedule->flight_number, $flight_schedule->departure, $flight_schedule->origin, $flight_schedule->destination);

        $number_of_rows = 0;
        $number_of_columns = 0;

        foreach ($seat_map['seats'] as $seats) {
            foreach ($seats as $seat) {
                if ($seat['row'] > $number_of_rows) {
                    $number_of_rows = $seat['row'];
                }

                if ($seat['col'] > $number_of_columns) {
                    $number_of_columns = $seat['col'];
                }
            }
        }

        $sorted_seat_map = [];
        for ($i = 1; $i <= $number_of_rows; $i++) {
            foreach ($seat_map['seats'] as $_seats) {
                $firstKey = array_key_first($_seats);
                if ($_seats[$firstKey]['row'] == $i) {
                    $sorted_seat_map[] = $_seats;
                }
            }
        }

        return [
            'meta' => [
                'rows' => $number_of_rows,
                'columns' => $number_of_columns,
                'info' => $seat_map['info'],
            ],
            'data' => $sorted_seat_map,
        ];
    }

    public function issue_pnr(Request $request)
    {
        $request->validate([
            'offers' => 'required',
            'class_offers' => 'required',
            'contact' => 'required',
        ]);

        $result = [];

        $user = $request->user();

        $aero = AeroToken::where('user_id', $user->id)
            ->where('iata', $request->only)
            ->firstOrFail();

        // $offer_ids = collect($request->offers)->pluck('id');
        // $availabilities = \App\Models\FlightAvailablity::whereIn('id', $offer_ids)->get();
        // foreach ($availabilities->groupBy('carrier') as $carrier => $avs) {
        //     $aero_token = \App\Models\AeroToken::where('iata', $carrier)->first();


        // }

        # artisan('horizon:pause');
        $pnr = [];
        try {
            $pnr = $aero->build()->createPnr([
                'offers' => $request->offers,
                'passengers' => $request->passengers,
                'contact' => $request->contact,
            ]);
        } catch (Exception $ex) {

        } finally {
            # artisan('horizon:continue');
        }


        return [
            'pnrs' => $pnr
        ];
    }

    public function hold_pnr(Request $request)
    {
        $request->validate([
            'offers' => 'required',
            'class_offers' => 'required',
            'contact' => 'required',
        ]);

        $result = [];

        $user = $request->user();

        $aero = AeroToken::where('user_id', $user->id)
            ->where('iata', $request->only)
            ->firstOrFail();


        # artisan('horizon:pause');
        $pnr = [];
        try {
            $pnr = $aero->build()->holdPnr([
                'offers' => $request->offers,
                'passengers' => $request->passengers,
                'contact' => $request->contact,
            ]);
        } catch (Exception $ex) {

        } finally {
            # artisan('horizon:continue');
        }


        return $pnr;
    }

    public function flight_order(Request $request)
    {
        $request->validate([
            'holded_pnr' => 'required',
            'passengers' => 'required',
            'reference' => 'required',
        ]);

        $result = [];

        $user = $request->user();

        $aero = AeroToken::where('user_id', $user->id)
            ->where('iata', $request->holded_pnr['iata'])
            ->firstOrFail();

        # artisan('horizon:pause');
        $pnr = [];
        try {
            $pnr = $aero->build()->createHoldedPnr([
                'holded_pnr' => $request->holded_pnr,
                'passengers' => $request->passengers,
                'payment_references' => $request->reference,
                'with_email' => false,
            ]);
        } catch (Exception $ex) {

        } finally {
            # artisan('horizon:continue');
        }


        return response()->json($pnr, 201);
    }

    public function query_pnr(Request $request)
    {
        $request->validate([
            'pnr' => 'required',
            'iata' => 'required',
            'provider_id' => 'sometimes'
        ]);

        if (strtolower($request->iata) == "yi") {
            // return $this->query_pnr_YI($request);
        }

        $aero_token = null;

        if ($request->has('provider_id')) {
            $aero_token = \App\Models\AeroToken::where('id', $request->provider_id)->first();
        } else {
            $aero_token = \App\Models\AeroToken::where('iata', $request->iata)->first();
        }

        if (strtoupper($request->pnr) == 'AAQSQ9') {
            $aero_token = \App\Models\AeroToken::find(7);
        }


        $query_pnr_command = "*" . $request->pnr;
        $query_pnr_command .= "^*R~x";


        # artisan('horizon:pause');
        $pnr = [];
        try {
            $pnr = cache()->remember($query_pnr_command, now()->addMinutes(value: 60), function () use ($query_pnr_command, $aero_token, $request) {
                $result = $aero_token->build()->runCommand($query_pnr_command);

                $xmlObject = simplexml_load_string("<xml>" . $result->response . "</xml>");

                $pnr = parse_pnr($xmlObject);

                // return $command;

                if ($pnr['is_issued'] && !$pnr['is_voidable']) {
                    $command = "*" . $request->pnr;
                    $index = 1;
                    $segment_cancellation = "X";

                    // Check used itinenraries
                    $itinerary_status = [];
                    $number_of_used_itins = 0;

                    // Append flight schedule for each itinerary
                    $pnr['flight_schedules'] = [];

                    foreach ($pnr['itineraries'] as $itinerary) {
                        $flight_schedule = \App\Models\FlightSchedule::withTrashed()
                        ->where([
                            'iata' => $itinerary['airline_id'],
                            'origin' => $itinerary['from'],
                            'destination' => $itinerary['to'],
                            'flight_number' =>  $itinerary['airline_id'] . $itinerary['flight_number']
                        ])->whereDate('departure', $itinerary['date'])
                        ->first();

                        if ($flight_schedule != null) {
                            $aircraft = \App\Models\Aircraft::where('code', $flight_schedule['aircraft_code'])->first();

                            if ($aircraft != null) {
                                $flight_schedule['aircraft'] = getAircraft($aircraft->iata);
                            } else {
                                $flight_schedule['aircraft'] = null;
                            }

                            $pnr['flight_schedules'][] = $flight_schedule;
                        }
                    }

                    foreach ($pnr['itineraries'] as $it) {
                        $all_tickets_used = true;
                        foreach ($pnr['tickets'] as $ticket) {
                            if ($ticket['segment_number'] == $it['itinerary_id']) {
                                if ($ticket['ticket_id'] == 'ETKT') {
                                    $all_tickets_used = false;
                                } else {
                                    $number_of_used_itins++;
                                }
                            }
                        }

                        $itinerary_status[$it['itinerary_id']] = $all_tickets_used;
                    }

                    if ($aero_token->iata == 'YI') {

                    } else {
                        foreach ($pnr['itineraries'] as $it) {
                            $is_itinerary_used = $itinerary_status[$it['itinerary_id']];

                            if (!$is_itinerary_used) {
                                $command .= "^FCR" . $index . "^FCC" . $index;
                            }

                            $index++;
                        }
                    }

                    # [OLD]
                    // if (count($pnr['itineraries']) > 1) {
                    //     $segment_cancellation = "";
                    //     foreach ($itinerary_status as $id => $is_used) {
                    //         if (!$is_used) {
                    //             if (strlen($segment_cancellation) > 0) {
                    //                 $segment_cancellation .= "^X" . $id;
                    //             } else {
                    //                 $segment_cancellation .= "X" . $id;
                    //             }
                    //         }
                    //     }

                    // } else {
                    //     $segment_cancellation .= "1";
                    // }

                    // if ($number_of_used_itins == 0) {
                    //     $command .= "^" . $segment_cancellation . "^FSM";
                    // } else {
                    //     if ($number_of_used_itins != count($itinerary_status)) {
                    //         $command .= "^" . $segment_cancellation . "^FG";
                    //     } else {
                    //         $command .= "^" . $segment_cancellation . "^FSM";
                    //     }
                    // }

                    # [CHANGED]
                    if (count($pnr['itineraries']) > 1) {
                        $segment_cancellation = "";

                        krsort($itinerary_status);

                        foreach ($itinerary_status as $id => $is_used) {
                            if (!$is_used) {
                                if (strlen($segment_cancellation) > 0) {
                                    // $segment_cancellation .= "^X" . $id;
                                } else {
                                    // $segment_cancellation .= "X" . $id;
                                }
                            }
                        }

                    } else {
                        // $segment_cancellation .= "1";
                        $segment_cancellation = "";
                    }

                    if ($number_of_used_itins == 0) {
                        $command .= "" . $segment_cancellation . "^FSM";
                    } else {
                        if ($number_of_used_itins != count($itinerary_status)) {
                            $command .= "" . $segment_cancellation . "^FG";
                        } else {
                            $command .= "" . $segment_cancellation . "^FSM";
                        }
                    }
                    # [END]
                    // if ($aero_token->iata == 'YI') {
                    //     $command .= "^" . $segment_cancellation . "^FSM";
                    // } else {
                    //     $command .= "^" . $segment_cancellation . "^FSM";
                    // }



                    $command .= "^*R~X";
                    // return $command;
                    $pnr_fsm_result = $aero_token->build()->runCommand($command);

                    $mps_object = simplexml_load_string("<xml>" . $pnr_fsm_result->response . "</xml>");

                    $pnr_fsm = $this->parse_mps_pnr($mps_object);

                    $pnr['mps'] = $pnr_fsm['mps'];

                    if (isset($pnr_fsm['refund_amount'])) {
                        if ($pnr_fsm['refund_amount'] < 0) {
                            $pnr['refund_amount'] = $pnr_fsm['refund_amount'] * -1;
                        } else if ($pnr_fsm['refund_amount'] > 0) {
                            $pnr['refund_amount'] = $pnr_fsm['refund_amount'];
                        } else {
                            $pnr['refund_amount'] = 1;
                        }

                    }
                }

                return $pnr;
            });

        } catch (Exception $ex) {

        } finally {
            # artisan('horizon:continue');
        }




        return $pnr;

        // return [
        //     'pnrs' => $pnr,
        //     'xml' => $result->response,
        // ];
    }

    public function confirm_order(Request $request)
    {
        $request->validate([
            'holded_pnrs' => 'required|array',
            'payment_reference' => 'required',
        ]);

        $data = [];

        # artisan('horizon:pause');
        try {
            foreach ($request->holded_pnrs as $holded_pnr) {
                $issue_pnr_command = "*" . $holded_pnr['rloc'];

                $issue_pnr_command .= "^MI-ABC " . $request->payment_reference;
                $issue_pnr_command .= "^EZT*R";
                // $issue_pnr_command .= "^EZRE";
                $issue_pnr_command .= "^*R~x";

                $aero_token = \App\Models\AeroToken::where('iata', $holded_pnr['iata'])->first();

                $result = $aero_token->build()->runCommand($issue_pnr_command);

                $data[] = $result->response;
                // $data[] = $issue_pnr_command;
            }
        } catch (Exception $ex) {

        } finally {
            # artisan('horizon:continue');
        }


        return $data;
    }

    public function void_pnr(Request $request)
    {
        $allowed_to_void = ['YI', 'UZ', 'FQ', '5S', 'BM', 'YL'];

        $request->validate([
            'pnr' => 'required',
            'iata' => 'required',
        ]);

        // if (!in_array(strtoupper($request->iata), $allowed_to_void)) {
        //     return response()->json([
        //         'status' => false,

        //         'message' => 'VOID_DISABLED',
        //     ], 500);
        // }

        // if (strtoupper($request->iata) != "YI") {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'VOID_DISABLED',
        //     ], 500);
        // }

        $aero_token = \App\Models\AeroToken::where('iata', $request->iata)->first();


        // $i = 1;
        // $segment_canelation_command = "";
        // foreach ($request->pnr['itineraries'] as $iti) {
        //     $segment_canelation_command .= '^X' . $i;
        //     $i++;
        // }

        # artisan('horizon:pause');
        $pnr = [];
        try {
            $void_pnr_command = "*" . $request->pnr . "^X1^EZV*R^*R~x";

            $result = $aero_token->build()->runCommand($void_pnr_command);

            $xmlObject = simplexml_load_string("<xml>" . $result->response . "</xml>");

            // $pnr = $this->parse_pnr($xmlObject);

            cache()->forget('*' . $request->pnr . "^*R~x");

        } catch (Exception $ex) {

        } finally {
            # artisan('horizon:continue');
            dispatch(new \App\Jobs\UpdateAeroTokenInformationJob($aero_token))->delay(now()->addMinutes(15));
        }


        return [
            'status' => true,
            'mesage' => 'PNR_VOIDED',
        ];

        return $pnr;
    }

    public function refund_pnr(Request $request)
    {
        $allowed_to_refund = ['YI', 'UZ', 'FQ', '5S', 'BM', 'YL'];

        $request->validate([
            'pnr' => 'required',
            'iata' => 'required',
        ]);

        if (!in_array(strtoupper($request->iata), $allowed_to_refund)) {
            return response()->json([
                'status' => false,
                'message' => 'REFUND_DISABLED',
            ], 500);
        }

        $status = false;
        $message = "PNR_NOT_REFUNDED";

        if (strtolower($request->iata) == "yi") {
            return $this->refund_pnr_YI($request);
        }

        cache()->forget('*' . $request->pnr . "^*R~x");

        $aero_token = null;

        if (strtoupper($request->pnr) == 'AANY4I') {
            $aero_token = \App\Models\AeroToken::find(7);
        } else {
            $aero_token = \App\Models\AeroToken::where('iata', $request->iata)->first();
        }


        $pnr_query = "*" . $request->pnr . "^*R~x";

        $result = $aero_token->build()->runCommand($pnr_query);

        $xmlObject = simplexml_load_string("<xml>" . $result->response . "</xml>");

        $pnr = $this->parse_pnr($xmlObject);

        $refund_command = "*" . $request->pnr;
        $index = 1;
        $segment_cancellation = "X";

        // Check used itinenraries
        $itinerary_status = [];
        $number_of_used_itins = 0;

        foreach ($pnr['itineraries'] as $it) {
            $all_tickets_used = true;
            foreach ($pnr['tickets'] as $ticket) {
                if ($ticket['segment_number'] == $it['itinerary_id']) {
                    if ($ticket['ticket_id'] == 'ETKT') {
                        $all_tickets_used = false;
                    } else {
                        $number_of_used_itins++;
                    }
                }
            }

            $itinerary_status[$it['itinerary_id']] = $all_tickets_used;
        }

        if ($aero_token->iata == 'YI') {

        } else {
            foreach ($pnr['itineraries'] as $it) {
                $is_itinerary_used = $itinerary_status[$it['itinerary_id']];

                if (!$is_itinerary_used) {
                    $refund_command .= "^FCR" . $index . "^FCC" . $index;
                }

                $index++;
            }
        }

        if (count($pnr['itineraries']) > 1) {

            $segment_cancellation = "";

            krsort($itinerary_status);

            foreach ($itinerary_status as $id => $is_used) {
                if (!$is_used) {
                    if (strlen($segment_cancellation) > 0) {
                        $segment_cancellation .= "^X" . $id;
                    } else {
                        $segment_cancellation .= "X" . $id;
                    }
                }
            }
        } else {
            $segment_cancellation .= "1";
        }

        // if ($number_of_used_itins != count($itinerary_status)) {
        //     $refund_command .= "^" . $segment_cancellation . "^FG";
        // } else {
        //     $refund_command .= "^" . $segment_cancellation . "^FSM";
        // }

        if ($number_of_used_itins == 0) {
            $refund_command .= "^" . $segment_cancellation . "^FSM";
        } else {
            if ($number_of_used_itins != count($itinerary_status)) {
                $refund_command .= "^" . $segment_cancellation . "^FG^FS1";
            } else {
                // Check if itineraries are opened
                $open_flight = false;

                foreach ($itinerary_status as $id => $is_used) {
                    if (!$is_used) {
                        if ($pnr['itineraries'][$id - 1]['flight_number'] == '0000') {
                            $open_flight = true;
                        }
                    }
                }

                if ($open_flight) {
                    $refund_command .= "^" . $segment_cancellation . "^FG";
                } else {
                    $refund_command .= "^" . $segment_cancellation . "^FSM";
                }
            }
        }

        // if ($aero_token->iata == 'YI') {
        //     // $refund_command .= "^" . $segment_cancellation . "^FG^FSM";
        //     $refund_command .= "^" . $segment_cancellation . "^FSM";
        // } else {
        //     $refund_command .= "^" . $segment_cancellation . "^FSM";
        // }


        if ($pnr['is_issued'] && !$pnr['is_voidable']) {

            # artisan('horizon:pause');
            $pnr = [];
            try {
                $command = "";

                $pnr_fsm_result = $aero_token->build()->runCommand($refund_command . "^*R~X");
                $mps_object = simplexml_load_string("<xml>" . $pnr_fsm_result->response . "</xml>");

                $pnr_fsm = $this->parse_mps_pnr($mps_object);

                $pnr['mps'] = $pnr_fsm['mps'];

                $refund_amount = 0;
                if (isset($pnr_fsm['refund_amount'])) {
                    if ($pnr_fsm['refund_amount'] < 0) {
                        $refund_amount = $pnr_fsm['refund_amount'] * -1;
                    } else {
                        $refund_amount = $pnr_fsm['refund_amount'];
                    }
                }

                if ($refund_amount > 0) {
                    $command .= $refund_command . "^REF*^RI" . $refund_amount . "*R~X";
                } else {
                    $command .= $refund_command . "^REF*^*R~X";
                }



                $result = $aero_token->build()->runCommand($command);

                if ($result->response != 'AGENT NOT AUTHORISED TO REFUND TICKET' && !str_contains($result->response, 'ERROR')) {
                    $status = true;
                    $message = 'PNR_REFUNDED';
                }


            } catch (Exception $ex) {

            } finally {
                # artisan('horizon:continue');
            }
        }


        // return $command;



        // if ($pnr['is_issued'] && !$pnr['is_voidable']) {


        //     $mps_object = simplexml_load_string("<xml>" . $pnr_fsm_result->response . "</xml>");

        //     $pnr_fsm = $this->parse_mps_pnr($mps_object);

        //     $pnr['mps'] = $pnr_fsm['mps'];
        //     $pnr['refund_amount'] = $pnr_fsm['refund_amount'];
        // }

        dispatch(new \App\Jobs\UpdateAeroTokenInformationJob($aero_token))->delay(now()->addMinutes(15));

        return [
            'status' => $status,
            'message' => $message,
            'data' => $pnr,
        ];
        // return $pnr;
    }

    private function refund_pnr_YI(Request $request)
    {
        /**
         * Refund unused tickets
         * *[PNR]^REF*^X1^FSM^MB^[PRICE]RI*R
         * *[PNR]^REF*^X1-2^FSM^MB^[PRICE]RI*R
         * 
         * Refund used ticket
         * *[PNR]^X2^FG^FS1^FSM^MB^[PRICE]RI*R
         */
        $request->validate([
            'pnr' => 'required',
            'iata' => 'required',
        ]);

        $status = false;
        $message = "PNR_NOT_REFUNDED";

        cache()->forget('*' . $request->pnr . "^*R~x");

        $aero_token = \App\Models\AeroToken::where('iata', $request->iata)->first();

        $pnr_query = "*" . $request->pnr . "^*R~x";

        $result = $aero_token->build()->runCommand($pnr_query);

        $xmlObject = simplexml_load_string("<xml>" . $result->response . "</xml>");

        $pnr = $this->parse_pnr($xmlObject);
        // $pnr = $this->query_pnr_YI($request);

        $refund_command = "";

        $data = [
            'itineraries' => [],
        ];

        // Extract Itinenraries
        foreach ($pnr['itineraries'] as $itinerary) {
            $itinerary_id = (int) $itinerary['itinerary_id'];
            $data['itineraries'][$itinerary_id - 1] = [
                'index' => $itinerary_id,
                'is_used' => false,
                'tickets' => [],
            ];
        }

        // Extract Itinenary Tickets
        foreach ($pnr['tickets'] as $ticket) {
            $itinerary_id = (int) $ticket['segment_number'];

            $data['itineraries'][$itinerary_id - 1]['tickets'][] = $ticket;
        }

        // Detrmine if all tickets in itinerary are used
        foreach ($data['itineraries'] as &$itinerary) {
            $used_tickets = array_filter($itinerary['tickets'], fn($val, $key) => $val['ticket_id'] == "ELFT", ARRAY_FILTER_USE_BOTH);
            if (count($used_tickets) == count($itinerary['tickets'])) {
                $itinerary['is_used'] = true;
            }
        }

        # if all itineraries are unused
        $not_used_itineraries = $used_tickets = array_filter($data['itineraries'], fn($val, $key) => $val['is_used'] == false, ARRAY_FILTER_USE_BOTH);
        if (count($not_used_itineraries) == count($data['itineraries'])) {
            // define refund command
            $refund_command = "*[PNR]^REF*^[SEGMENTS]FSM^MB";

            $refunded_segments = "X1-" . count($data['itineraries']) - 1 . '^';
            $refund_command = str_replace('[SEGMENTS]', $refunded_segments, $refund_command);
        } else {
            // define refund command
            $refund_command = "*[PNR]^[SEGMENTS]FG^FS1^FSM^MB";

            $refunded_segments = "";
            foreach ($not_used_itineraries as $itinerary) {
                // if (!$itinerary['is_used']) {
                $refunded_segments .= "X" . $itinerary['index'] . '^';
                // }
            }

            $refund_command = str_replace('[SEGMENTS]', $refunded_segments, $refund_command);
        }

        # Set [PNR]
        $refund_command = str_replace('[PNR]', $request->pnr, $refund_command);

        // Check used itinenraries
        // $itinerary_status = [];
        // $number_of_used_itins = 0;

        // foreach ($pnr['itineraries'] as $it) {
        //     $all_tickets_used = true;
        //     foreach ($pnr['tickets'] as $ticket) {
        //         if ($ticket['segment_number'] == $it['itinerary_id']) {
        //             if ($ticket['ticket_id'] == 'ETKT') {
        //                 $all_tickets_used = false;
        //             } else {
        //                 $number_of_used_itins++;
        //             }
        //         }
        //     }

        //     $itinerary_status[$it['itinerary_id']] = $all_tickets_used;
        // }

        // if ($aero_token->iata == 'YI') {

        // } else {
        //     foreach ($pnr['itineraries'] as $it) {
        //         $is_itinerary_used = $itinerary_status[$it['itinerary_id']];

        //         if (!$is_itinerary_used) {
        //             $refund_command .= "^FCR" . $index . "^FCC" . $index;
        //         }

        //         $index++;
        //     }
        // }

        // if (count($pnr['itineraries']) > 1) {
        //     $segment_cancellation = "";
        //     foreach ($itinerary_status as $id => $is_used) {
        //         if (!$is_used) {
        //             if (strlen($segment_cancellation) > 0) {
        //                 $segment_cancellation .= "^X" . $id;
        //             } else {
        //                 $segment_cancellation .= "X" . $id;
        //             }
        //         }
        //     }
        // } else {
        //     $segment_cancellation .= "1";
        // }

        // if ($number_of_used_itins == 0) {
        //     $refund_command .= "^" . $segment_cancellation . "^FSM";
        // } else {
        //     if ($number_of_used_itins != count($itinerary_status)) {
        //         $refund_command .= "^" . $segment_cancellation . "^FG";
        //     } else {
        //         $refund_command .= "^" . $segment_cancellation . "^FSM";
        //     }
        // }

        if ($pnr['is_issued'] && !$pnr['is_voidable']) {

            # artisan('horizon:pause');
            $pnr = [];
            try {
                $command = "";

                $pnr_fsm_result = $aero_token->build()->runCommand($refund_command . "^*R~X");
                $mps_object = simplexml_load_string("<xml>" . $pnr_fsm_result->response . "</xml>");

                $pnr_fsm = $this->parse_mps_pnr($mps_object);

                $pnr['mps'] = $pnr_fsm['mps'];

                $refund_amount = 0;
                if (isset($pnr_fsm['refund_amount'])) {
                    if ($pnr_fsm['refund_amount'] < 0) {
                        $refund_amount = $pnr_fsm['refund_amount'] * -1;
                    } else {
                        $refund_amount = $pnr_fsm['refund_amount'];
                    }
                }

                if ($refund_amount > 0) {
                    $command .= $refund_command . "^REF*^RI" . $refund_amount . "*R~X";
                } else {
                    $command .= $refund_command . "^FG^REF*^*R~X";
                }

                // $command .= $refund_command . "^REF*^RI" . $refund_amount . "*R~X";


                $result = $aero_token->build()->runCommand($command);

                if ($result->response != 'AGENT NOT AUTHORISED TO REFUND TICKET') {
                    $status = true;
                    $message = 'PNR_REFUNDED';
                }


            } catch (Exception $ex) {

            } finally {
                # artisan('horizon:continue');
            }
        }

        return [
            'status' => $status,
            'message' => $message,
            'data' => $pnr,
        ];
    }

    private function query_pnr_YI(Request $request)
    {
        // $request->validate([
        //     'pnr' => 'required',
        //     'iata' => 'required',
        // ]);

        $aero_token = \App\Models\AeroToken::where('iata', $request->iata)->first();

        $query_pnr_command = "*" . $request->pnr;
        $query_pnr_command .= "^*R~x";


        # artisan('horizon:pause');
        $pnr = [];
        try {
            $pnr = cache()->remember($query_pnr_command, now()->addSeconds(60), function () use ($query_pnr_command, $aero_token, $request) {
                $result = $aero_token->build()->runCommand($query_pnr_command);

                $xmlObject = simplexml_load_string("<xml>" . $result->response . "</xml>");

                $pnr = parse_pnr($xmlObject);

                // return $command;

                if ($pnr['is_issued'] && !$pnr['is_voidable']) {
                    $refund_command = "";

                    $data = [
                        'itineraries' => [],
                    ];

                    // Extract Itinenraries
                    foreach ($pnr['itineraries'] as $itinerary) {
                        $itinerary_id = (int) $itinerary['itinerary_id'];
                        $data['itineraries'][$itinerary_id - 1] = [
                            'index' => $itinerary_id,
                            'is_used' => false,
                            'tickets' => [],
                        ];
                    }

                    // Extract Itinenary Tickets
                    foreach ($pnr['tickets'] as $ticket) {
                        $itinerary_id = (int) $ticket['segment_number'];

                        $data['itineraries'][$itinerary_id - 1]['tickets'][] = $ticket;
                    }

                    // Detrmine if all tickets in itinerary are used
                    foreach ($data['itineraries'] as &$itinerary) {
                        $used_tickets = array_filter($itinerary['tickets'], fn($val, $key) => $val['ticket_id'] == "ELFT", ARRAY_FILTER_USE_BOTH);
                        if (count($used_tickets) == count($itinerary['tickets'])) {
                            $itinerary['is_used'] = true;
                        }
                    }

                    // return $data;
                    # if all itineraries are unused
                    $not_used_itineraries = $used_tickets = array_filter($data['itineraries'], fn($val, $key) => $val['is_used'] == false, ARRAY_FILTER_USE_BOTH);

                    // return $used_itineraries;
                    if (count($not_used_itineraries) == count($data['itineraries'])) {
                        // define refund command
                        $refund_command = "*[PNR]^REF*^[SEGMENTS]FSM^MB";

                        $refunded_segments = "X1-" . count($data['itineraries']) - 1 . '^';
                        $refund_command = str_replace('[SEGMENTS]', $refunded_segments, $refund_command);
                    } else {
                        // define refund command
                        $refund_command = "*[PNR]^[SEGMENTS]FG^FS1^FSM^MB";

                        $refunded_segments = "";
                        foreach ($not_used_itineraries as $itinerary) {
                            // if (!$itinerary['is_used']) {
                            $refunded_segments .= "X" . $itinerary['index'] . '^';
                            // }
                        }

                        $refund_command = str_replace('[SEGMENTS]', $refunded_segments, $refund_command);
                    }

                    # Set [PNR]
                    $refund_command = str_replace('[PNR]', $request->pnr, $refund_command);

                    $refund_command .= "^*R~X";
                    // return $refund_command;
                    $pnr_fsm_result = $aero_token->build()->runCommand($refund_command);

                    $mps_object = simplexml_load_string("<xml>" . $pnr_fsm_result->response . "</xml>");

                    $pnr_fsm = $this->parse_mps_pnr($mps_object);

                    $pnr['mps'] = $pnr_fsm['mps'];
                    if (isset($pnr_fsm['refund_amount'])) {
                        if ($pnr_fsm['refund_amount'] < 0) {
                            $pnr['refund_amount'] = $pnr_fsm['refund_amount'] * -1;
                        } else {
                            $pnr['refund_amount'] = $pnr_fsm['refund_amount'];
                        }

                    }
                }

                return $pnr;
            });

        } catch (Exception $ex) {

        } finally {
            # artisan('horizon:continue');
        }




        return $pnr;

        // return [
        //     'pnrs' => $pnr,
        //     'xml' => $result->response,
        // ];
    }

    public function calculate_refund_segment(Request $request)
    {
        $request->validate([
            'pnr' => 'required',
            'iata' => 'required',
            'segment' => 'required',
        ]);

        // if (strtoupper($request->iata) != 'YI') {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'REFUND_DISABLED',
        //     ], 500);
        // }

        $status = false;
        $statusCode = 500;
        $message = "";
        $data = [];

        $query_pnr_command = "";
        $refund_segment_command = "";

        try {
            $aero_token = \App\Models\AeroToken::where('iata', $request->iata)->first();

            # 1 - Query PNR to get the segment pricing
            $segment_pricing = null;
            $pnr = [];

            $query_pnr_command = "*" . $request->pnr . "^*R~x";
            if (cache()->has($query_pnr_command)) {
                $pnr = parse_pnr(cache()->get($query_pnr_command));
            } else {
                $result = $aero_token->build()->runCommand($query_pnr_command);
                $xmlObject = simplexml_load_string("<xml>" . $result->response . "</xml>");
                $pnr = parse_pnr($xmlObject);
            }

            foreach ($pnr['fare_qoute'] as $fare_qoute) {
                if ($fare_qoute['segment_id'] == $request->segment) {
                    $segment_pricing = $fare_qoute;
                }
            }

            $refunded_tickets = [];
            foreach ($pnr['tickets'] as $ticket) {
                $segment_number = (int) $ticket['segment_number'];
                if ($segment_number == $request->segment) {
                    $refunded_tickets[] = $ticket;
                }
            }

            # 2 - Calculate refunded segment penalties

            $refund_segment_command = "*" . $request->pnr;

            $refund_segment_command .= "^FCR" . $request->segment;
            $refund_segment_command .= "^FCC" . $request->segment;
            $refund_segment_command .= "^X" . $request->segment;
            $refund_segment_command .= "^FG";
            $refund_segment_command .= "^FS1";
            $refund_segment_command .= "^FSM";
            $refund_segment_command .= "^*R~x";

            $refund_segment_result = cache()->remember($refund_segment_command, now()->addSeconds(60), function () use ($refund_segment_command, $aero_token) {
                try {
                    $result = $aero_token->build()->runCommand($refund_segment_command);
                    $xmlObject = simplexml_load_string("<xml>" . $result->response . "</xml>");
                    return parse_pnr($xmlObject);
                } catch (Exception $ex) {
                    cache()->forget($refund_segment_command);
                }
            });

            $mps = $refund_segment_result['mps'];
            $outstanding = [];
            foreach ($refund_segment_result['basket'] as $basket) {
                if ($basket['id'] == 'outstanding') {
                    $outstanding = $basket;
                }
            }

            $status = true;
            $message = 'SUCCESS';
            $statusCode = 200;
            $data = [
                'pnr' => $pnr,
                'removed_segment' => $segment_pricing,
                'removed_tickets' => $refunded_tickets,
                'mps' => $mps,
                'outstanding' => $outstanding,
            ];
        } catch (Exception $ex) {
            $status = false;
            $message = 'UNABLE_TO_FETCH_DATA' . $ex->getMessage() . '-' . $ex->getTraceAsString();
            $statusCode = 500;
            $data = [];

            cache()->forget($query_pnr_command);
            cache()->forget($refund_segment_command);
        }

        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    public function refund_segment(Request $request)
    {
        $request->validate([
            'pnr' => 'required',
            'iata' => 'required',
            'segment' => 'required',
        ]);

        $status = false;
        $statusCode = 500;
        $message = "";
        $data = [];

        try {
            $aero_token = \App\Models\AeroToken::where('iata', $request->iata)->first();

            # 1 - Query PNR to get the segment pricing
            $segment_pricing = null;
            $pnr = [];

            $query_pnr_command = "*" . $request->pnr . "^*R~x";
            if (cache()->has($query_pnr_command)) {
                $pnr = parse_pnr(cache()->get($query_pnr_command));
            } else {
                $result = $aero_token->build()->runCommand($query_pnr_command);
                $xmlObject = simplexml_load_string("<xml>" . $result->response . "</xml>");
                $pnr = parse_pnr($xmlObject);
            }

            foreach ($pnr['fare_qoute'] as $fare_qoute) {
                if ($fare_qoute['segment_id'] == $request->segment) {
                    $segment_pricing = $fare_qoute;
                }
            }

            $refunded_tickets = [];
            foreach ($pnr['tickets'] as $ticket) {
                $segment_number = (int) $ticket['segment_number'];
                if ($segment_number == $request->segment) {
                    $refunded_tickets[] = $ticket;
                }
            }

            # 2 - Calculate refunded segment penalties

            $refund_segment_command = "*" . $request->pnr;

            $refund_segment_command .= "^FCR" . $request->segment;
            $refund_segment_command .= "^FCC" . $request->segment;
            $refund_segment_command .= "^X" . $request->segment;
            $refund_segment_command .= "^FG";
            $refund_segment_command .= "^FS1";
            $refund_segment_command .= "^FSM";

            $refund_segment_result = cache()->remember($refund_segment_command . "^*R~x", now()->addSeconds(60), function () use ($refund_segment_command, $aero_token) {
                try {
                    $result = $aero_token->build()->runCommand($refund_segment_command . "^*R~x");
                    $xmlObject = simplexml_load_string("<xml>" . $result->response . "</xml>");
                    return parse_pnr($xmlObject);
                } catch (Exception $ex) {
                    cache()->forget($refund_segment_command);
                }
            });

            $mps = $refund_segment_result['mps'];
            $outstanding = [];
            foreach ($refund_segment_result['basket'] as $basket) {
                if ($basket['id'] == 'outstanding') {
                    $outstanding = $basket;
                }
            }

            # 3 - Refund segment

            if ($outstanding != null) {

                $refund_amount = -1 * (double) $outstanding['amount'];

                if ($refund_amount <= 0) {
                    $refund_result = $aero_token->build()->runCommand($refund_segment_command . "^FG^REF*^*R~X");
                } else {
                    $refund_result = $aero_token->build()->runCommand($refund_segment_command . "^REF*^RI" . $refund_amount . "*R~X");
                }
                // $refund_result = $aero_token->build()->runCommand($refund_segment_command . "^REF*^RI" . $refund_amount . "*R~X");

                // $refundXmlObject = simplexml_load_string("<xml>" . $refund_result->response . "</xml>");

                // $pnr = $this->parse_pnr($refundXmlObject);

                $status = true;
                $message = 'SEGMENT_REFUNDED';
                $statusCode = 200;
                $data = [
                    'pnr' => $pnr,
                    'removed_segment' => $segment_pricing,
                    'removed_tickets' => $refunded_tickets,
                    'mps' => $mps,
                    'outstanding' => $outstanding,
                ];

                foreach ($refunded_tickets as $rft) {
                    if (str_contains($refund_result->response, $rft['ticket_number'])) {
                        $status = false;
                        $message = 'UNABLE_TO_REFUND_SEGMENT';
                        $statusCode = 500;
                        $data = [];
                    }
                }

            }

        } catch (Exception $ex) {
            $status = false;
            $message = 'UNABLE_TO_REFUND_SEGMENT';
            $statusCode = 500;
            $data = [];
        }

        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    public function divide_pnr(Request $request)
    {
        $status = false;
        $message = "PNR_NOT_VOIDED";
        $response_data = [];

        $request->validate([
            'pnr' => 'required',
            'iata' => 'required',
            'passengers' => 'required',
        ]);

        $aero_token = \App\Models\AeroToken::where('iata', $request->iata)->first();

        $divide_pnr_command = "*" . $request->pnr . "^D";
        foreach ($request->passengers as $passenger) {
            $divide_pnr_command .= $passenger . "/";
        }

        $divide_pnr_command = substr($divide_pnr_command, 0, -1);

        $divide_pnr_command .= "^E^E*R~x";

        // return $divide_pnr_command;

        $result = $aero_token->build()->runCommand($divide_pnr_command);

        // $xmlObject = simplexml_load_string("<xml>" . $result->response . "</xml>");

        // $pnr = $this->parse_pnr($xmlObject);

        cache()->forget('*' . $request->pnr . "^*R~x");

        $response = $result->response;

        if (str_contains($response, "OK LOCATOR FOR NEW PNR")) {
            // OK LOCATOR FOR NEW PNR AAGJJG
            $data = explode(" ", $response);
            $pnr = $data[count($data) - 1];

            $status = true;
            $message = "PNR_VOIDED";
            $response_data['pnr'] = $pnr;

            dispatch(new \App\Jobs\CachePNRJob($request->pnr, $request->iata))->onQueue('default');
            dispatch(new \App\Jobs\CachePNRJob($pnr, $request->iata))->onQueue('default');
        }

        return [
            'status' => $status,
            'message' => $message,
            'data' => $response_data
        ];
    }

    public function print_pnr(Request $request)
    {
        $request->validate([
            'pnr' => 'required',
            'iata' => 'required',
        ]);

        $aero_token = \App\Models\AeroToken::where('iata', $request->iata)->first();

        $print_pnr_command = "*" . $request->pnr . "^EZRC";

        # artisan('horizon:pause');
        $result = null;
        try {
            $result = $aero_token->build()->runCommand($print_pnr_command);
        } catch (Exception $ex) {

        } finally {
            # artisan('horizon:continue');
        }


        return $result?->response;
    }

    public function pricingByAvilability($segments, $classes, $passengers)
    {
        $schedules_ids = collect();
        $pricing_command = "";

        $results = [];

        $passengers_command_segment = "";
        $letter = "A";

        for ($i = 0; $i < $passengers['adults']; $i++) {
            $passengers_command_segment .= $letter . '#/';
            $letter++;
        }
        for ($i = 0; $i < $passengers['children']; $i++) {
            $passengers_command_segment .= $letter . '#.CH10/';
            $letter++;
        }
        for ($i = 0; $i < $passengers['infants']; $i++) {
            $passengers_command_segment .= $letter . '#.IN06/';
            $letter++;
        }

        foreach ($segments as $segment) {
            $schedules_ids->push($segment->flight_schedule_id);
        }

        foreach (\App\Models\FlightSchedule::whereIn('id', $schedules_ids)->hasSeats()->get() as $schedule) {
            foreach ($classes as $class) {

                $pricing_command = "^0" . $schedule->flight_number . $class . date('dM', strtotime($schedule->departure)) . $schedule->origin . $schedule->destination . "QQ" . ($passengers['adults'] + $passengers['children']);

                $cmd = "I^-" . ($passengers['adults'] + $passengers['children'] + $passengers['infants']) . "Pax/" . $passengers_command_segment . $pricing_command . "^FG^FS1^*r~x";

                $offer = cache()->remember($cmd, 60 /**(10 * 60) **/ , function () use ($cmd, $schedule) {

                    // $results[] = $cmd;
                    $result = $schedule->aero_token->build()->runCommand($cmd);
                    $xml = "<xml>" . $result->response . "</xml>";

                    $xmlObject = simplexml_load_string($xml);

                    // $result = [];

                    foreach ($xmlObject->PNR->Itinerary->Itin as $itin) {

                        $availability = [
                            'flight_schedule_id' => $schedule->id,
                            'name' => $itin->attributes('', true)->ClassBand . '',
                            'display_name' => $itin->attributes('', true)->ClassBandDisplayName . '',
                            'cabin' => $itin->attributes('', true)->Cabin . '',
                            'class' => $itin->attributes('', true)->Class . '',
                            'rules' => $schedule->availablities()->where('class', $itin->attributes('', true)->Class . '')->first()?->rules,
                            'fare_qoute' => [],
                            'fare_store' => [],
                            'fare_tax' => [],
                        ];

                        foreach ($xmlObject->PNR->FareQuote as $fare_qoute) {
                            if ($fare_qoute->FQItin->attributes('', true)->Seg . '' == $itin->attributes('', true)->Line . '') {
                                $availability['fare_qoute'] = [
                                    'total' => $fare_qoute->FQItin->attributes('', true)->Total . '',
                                    'fare' => $fare_qoute->FQItin->attributes('', true)->Fare . '',
                                    'tax1' => $fare_qoute->FQItin->attributes('', true)->Tax1 . '',
                                    'tax2' => $fare_qoute->FQItin->attributes('', true)->Tax2 . '',
                                    'tax3' => $fare_qoute->FQItin->attributes('', true)->Tax3 . '',
                                ];

                                foreach ($fare_qoute->FareTax->PaxTax as $tax) {
                                    $availability['fare_tax'][] = [
                                        'code' => $tax->attributes('', true)->Code . '',
                                        'description' => $tax->attributes('', true)->desc . '',
                                        'amount' => $tax->attributes('', true)->Amnt . '',
                                        'currency' => $tax->attributes('', true)->Cur . '',
                                    ];
                                }
                            }
                        }

                        $results[] = $availability;
                    }

                    return $results;

                });

                array_push($results, ...$offer);

            }
        }

        return $results;
    }

    public function pricingBySchedule($schedules, $classes, $passengers, $merge = false)
    {
        $schedules_ids = collect();
        $pricing_command = "";

        $results = [];

        $passengers_command_segment = "";
        $letter = "A";

        for ($i = 0; $i < $passengers['adults']; $i++) {
            $passengers_command_segment .= $letter . '#/';
            $letter++;
        }
        for ($i = 0; $i < $passengers['children']; $i++) {
            $passengers_command_segment .= $letter . '#.CH10/';
            $letter++;
        }
        for ($i = 0; $i < $passengers['infants']; $i++) {
            $passengers_command_segment .= $letter . '#.IN06/';
            $letter++;
        }

        if ($merge) {
            $results = [];

            $air_classes = [];
            foreach ($classes as $class) {
                // $pricing_command = "";
                foreach ($schedules as $schedule) {
                    $air_classes[$schedule->iata][$class][] = "^0" . $schedule->flight_number . $class . date('dM', strtotime($schedule->departure)) . $schedule->origin . $schedule->destination . "QQ" . ($passengers['adults'] + $passengers['children']);
                    // $pricing_command .= "^0" . $schedule->flight_number . $class . date('dM', strtotime($schedule->departure)) . $schedule->origin . $schedule->destination . "QQ" . ($passengers['adults'] + $passengers['children']);
                }
            }

            // return $segments;

            foreach ($air_classes as $iata => $classes) {
                foreach ($classes as $class) {
                    $cmd = "I^-" . ($passengers['adults'] + $passengers['children'] + $passengers['infants']) . "Pax/" . $passengers_command_segment . implode("", $class) . "^FG^FS1^*r~x";
                    // array_push($results, $cmd);

                    $offer = cache()->remember($cmd, (10 * 60), function () use ($cmd, $iata) {

                        $_results = [];

                        // $results[] = $cmd;
                        $result = \App\Models\AeroToken::where('iata', $iata)->first()->build()->runCommand($cmd);
                        $xml = "<xml>" . $result->response . "</xml>";

                        $xmlObject = simplexml_load_string($xml);

                        // $result = [];

                        if (isset($xmlObject->PNR->Itinerary->Itin)) {
                            foreach ($xmlObject->PNR->Itinerary->Itin as $itin) {

                                $availability = [
                                    // 'flight_schedule_id' => $schedule->id,
                                    'name' => $itin->attributes('', true)->ClassBand . '',
                                    'display_name' => $itin->attributes('', true)->ClassBandDisplayName . '',
                                    'cabin' => $itin->attributes('', true)->Cabin . '',
                                    'class' => $itin->attributes('', true)->Class . '',
                                    // 'rules' => $schedule->availablities()->where('class', $itin->attributes('', true)->Class . '')->first()?->rules,
                                    'fare_qoute' => [],
                                    'fare_store' => [],
                                    'fare_tax' => [],
                                    "price" => [],
                                ];

                                foreach ($xmlObject->PNR->FareQuote as $fare_qoute) {
                                    if ($fare_qoute->FQItin->attributes('', true)->Seg . '' == $itin->attributes('', true)->Line . '') {
                                        $availability['fare_qoute'] = [
                                            'total' => $fare_qoute->FQItin->attributes('', true)->Total . '',
                                            'fare' => $fare_qoute->FQItin->attributes('', true)->Fare . '',
                                            'tax1' => $fare_qoute->FQItin->attributes('', true)->Tax1 . '',
                                            'tax2' => $fare_qoute->FQItin->attributes('', true)->Tax2 . '',
                                            'tax3' => $fare_qoute->FQItin->attributes('', true)->Tax3 . '',
                                        ];

                                        foreach ($fare_qoute->FareTax->PaxTax as $tax) {
                                            $availability['fare_tax'][] = [
                                                'code' => $tax->attributes('', true)->Code . '',
                                                'description' => $tax->attributes('', true)->desc . '',
                                                'amount' => $tax->attributes('', true)->Amnt . '',
                                                'currency' => $tax->attributes('', true)->Cur . '',
                                            ];
                                        }
                                        // $price = [
                                        //     "currency" => "EUR",
                                        //     "total" => "355.34",
                                        //     "base" => "255.00",
                                        //     "fees" => [
                                        //       [
                                        //         "amount" => "0.00",
                                        //         "type" => "SUPPLIER"
                                        //       ]
                                        //     ],
                                        //     "grandTotal" => "355.34"
                                        // ];

                                        foreach ($fare_qoute->FareStore as $fare_store) {
                                            $fare_store_segments = [];
                                            foreach ($fare_store->SegmentFS as $segment_fs) {
                                                $fare_store_segments[] = [
                                                    'segment' => $segment_fs->attributes('', true)->Seg . '',
                                                    'fare' => $segment_fs->attributes('', true)->Fare . '',
                                                    'tax1' => $segment_fs->attributes('', true)->Tax1 . '',
                                                    'tax2' => $segment_fs->attributes('', true)->Tax2 . '',
                                                    'tax3' => $segment_fs->attributes('', true)->Tax3 . '',
                                                    'hold_pcs' => $segment_fs->attributes('', true)->HoldPcs . '',
                                                    'hold_weight' => $segment_fs->attributes('', true)->HoldWt . '',
                                                    'hand_weight' => $segment_fs->attributes('', true)->HandWt . '',
                                                ];
                                            }

                                            $availability['fare_store'][] = [
                                                'id' => $fare_store->attributes('', true)->FSID . '',
                                                'pax' => $fare_store->attributes('', true)->Pax . '',
                                                'currency' => $fare_store->attributes('', true)->Cur . '',
                                                'total' => $fare_store->attributes('', true)->Total . '',
                                                'fares' => $fare_store_segments,
                                            ];
                                        }

                                        foreach ($fare_qoute->FareTax->PaxTax as $tax) {

                                            $availability['fare_tax'][] = [
                                                'code' => $tax->attributes('', true)->Code . '',
                                                'description' => $tax->attributes('', true)->desc . '',
                                                'amount' => $tax->attributes('', true)->Amnt . '',
                                                'currency' => $tax->attributes('', true)->Cur . '',
                                            ];
                                        }
                                    }
                                }

                                if (count($availability['fare_qoute']) > 0) {
                                    $_results[] = $availability;
                                }
                            }

                            return $_results;
                        }

                    });
                    if ($offer != null) {
                        array_push($results, ...$offer);
                    }
                }
            }

            return $results;
        } else {
            foreach ($schedules as $schedule) {
                foreach ($classes as $class) {

                    $pricing_command = "^0" . $schedule->flight_number . $class . date('dM', strtotime($schedule->departure)) . $schedule->origin . $schedule->destination . "QQ" . ($passengers['adults'] + $passengers['children']);

                    $cmd = "I^-" . ($passengers['adults'] + $passengers['children'] + $passengers['infants']) . "Pax/" . $passengers_command_segment . $pricing_command . "^FG^FS1^*r~x";

                    // $offer = cache()->remember($cmd, 30 /**(10 * 60) **/ , function () use ($cmd, $schedule) {

                    //     // $results[] = $cmd;
                    //     $result = $schedule->aero_token->build()->runCommand($cmd);
                    //     $xml = "<xml>" . $result->response . "</xml>";

                    //     $xmlObject = simplexml_load_string($xml);

                    //     // $result = [];

                    //     foreach ($xmlObject->PNR->Itinerary->Itin as $itin) {

                    //         $availability = [
                    //             'flight_schedule_id' => $schedule->id,
                    //             'name' => $itin->attributes('', true)->ClassBand . '',
                    //             'display_name' => $itin->attributes('', true)->ClassBandDisplayName . '',
                    //             'cabin' => $itin->attributes('', true)->Cabin . '',
                    //             'class' => $itin->attributes('', true)->Class . '',
                    //             'rules' => $schedule->availablities()->where('class', $itin->attributes('', true)->Class . '')->first()?->rules,
                    //             'fare_qoute' => [],
                    //             'fare_store' => [],
                    //             'fare_tax' => [],
                    //         ];

                    //         foreach ($xmlObject->PNR->FareQuote as $fare_qoute) {
                    //             if ($fare_qoute->FQItin->attributes('', true)->Seg . '' == $itin->attributes('', true)->Line . '') {
                    //                 $availability['fare_qoute'] = [
                    //                     'total' => $fare_qoute->FQItin->attributes('', true)->Total . '',
                    //                     'fare' => $fare_qoute->FQItin->attributes('', true)->Fare . '',
                    //                     'tax1' => $fare_qoute->FQItin->attributes('', true)->Tax1 . '',
                    //                     'tax2' => $fare_qoute->FQItin->attributes('', true)->Tax2 . '',
                    //                     'tax3' => $fare_qoute->FQItin->attributes('', true)->Tax3 . '',
                    //                 ];

                    //                 foreach ($fare_qoute->FareTax->PaxTax as $tax) {
                    //                     $availability['fare_tax'][] = [
                    //                         'code' => $tax->attributes('', true)->Code . '',
                    //                         'description' => $tax->attributes('', true)->desc . '',
                    //                         'amount' => $tax->attributes('', true)->Amnt . '',
                    //                         'currency' => $tax->attributes('', true)->Cur . '',
                    //                     ];
                    //                 }
                    //             }
                    //         }

                    //         $results[] = $availability;
                    //     }

                    //     return $results;

                    // });

                    // array_push($results, ...$offer);
                    array_push($results, $cmd);

                }
            }
        }

        return $results;
    }

    private function parse_pnr($xmlObject)
    {

        $pnr = [
            'itineraries' => [],
            'passengers' => [],
            'contacts' => [],
            'payments' => [],
            'timelimits' => [],
            'tickets' => [],
            'remarks' => [],
            'basket' => [],
            'mps' => [],
            'fare_qoute' => [],
            'fare_store' => [],
            'taxes' => [],

            'is_issued' => false,
            'is_locked' => false,
            'is_voidable' => false,
            'void_cutoff_time' => null,
            'rloc' => '',
            'iata' => '',
        ];


        if ($xmlObject) {
            $pnr['rloc'] = (string) $xmlObject->PNR->attributes()->{'RLOC'};
            $pnr['iata'] = (string) $xmlObject->PNR->RLE->attributes()->{'AirID'};
            $pnr['is_voidable'] = ($xmlObject->PNR->attributes()->{'CanVoid'} == "True");
            $pnr['is_locked'] = ($xmlObject->PNR->attributes()->{'PNRLocked'} == "True");
            $pnr['void_cutoff_time'] = date('Y-m-d H:i', strtotime($xmlObject->PNR->attributes()->{'VoidCutoffTime'}));

            // Extract passengers
            if (is_iterable($xmlObject->PNR->Names->PAX)) {
                foreach ($xmlObject->PNR->Names->PAX as $pax) {
                    $pnr['passengers'][] = [
                        'id' => $pax->attributes('', true)->PaxNo . '',
                        'group_number' => $pax->attributes('', true)->GrpNo . '',
                        'passenger_group_number' => $pax->attributes('', true)->GrpPaxNo . '',
                        'title' => $pax->attributes('', true)->Title . '',
                        'first_name' => $pax->attributes('', true)->FirstName . '',
                        'last_name' => $pax->attributes('', true)->Surname . '',
                        'type' => $pax->attributes('', true)->PaxType . '',
                        'age' => $pax->attributes('', true)->Age . '',
                    ];
                }
            }

            // Extract itineraries
            if (is_iterable($xmlObject->PNR->Itinerary->Itin)) {


                foreach ($xmlObject->PNR->Itinerary->Itin as $itinerary) {
                    $itinerary_index = $itinerary->attributes('', true)->Line - 1;

                    $airport_from = getAirport($itinerary->attributes('', true)->Depart);
                    $airport_to = getAirport($itinerary->attributes('', true)->Arrive);

                    $pnr['itineraries'][] = [
                        'itinerary_id' => $itinerary->attributes('', true)->Line . '',
                        // 'fare_qoute' => $fare_qoute,
                        'is_international' => ($airport_from->country != $airport_to->country),
                        'airline_id' => $itinerary->attributes('', true)->AirID . '',
                        'flight_number' => $itinerary->attributes('', true)->FltNo . '',
                        'class' => $itinerary->attributes('', true)->Class . '',
                        'cabin' => $itinerary->attributes('', true)->Cabin . '',
                        'class_band' => $itinerary->attributes('', true)->ClassBand . '',
                        'class_band_display_name' => $itinerary->attributes('', true)->ClassBandDisplayName . '',
                        'date' => $itinerary->attributes('', true)->DepDate . '',
                        'from' => $itinerary->attributes('', true)->Depart . '',
                        'to' => $itinerary->attributes('', true)->Arrive . '',
                        'departure' => $itinerary->attributes('', true)->DepTime . '',
                        'arrival' => $itinerary->attributes('', true)->ArrTime . '',
                        'status' => $itinerary->attributes('', true)->Status . '',
                        'number_of_passengers' => $itinerary->attributes('', true)->PaxQty . '',
                        'number_of_stops' => (integer) $itinerary->attributes()->{'Stops'},
                        'select_seat' => ($itinerary->attributes()->{'SelectSeat'} == "True"),
                        'mmb_select_seat' => ($itinerary->attributes()->{'MMBSelectSeat'} == "True"),
                        'open_seating' => ($itinerary->attributes()->{'OpenSeating'} == "True"),
                        'mmb_checkin_allow' => ($itinerary->attributes()->{'MMBCheckinAllowed'} == "True"),
                    ];
                }
            }

            // Extract fare qoute
            if (is_iterable($xmlObject->PNR->FareQuote)) {
                foreach ($xmlObject->PNR->FareQuote as $fare_qoute) {
                    // Fare Qoute
                    if (is_iterable($fare_qoute->FQItin)) {
                        foreach ($fare_qoute->FQItin as $fq_itin) {
                            $pnr['fare_qoute'][] = [
                                'segment_id' => $fq_itin->attributes('', true)->Seg . '',
                                'basic_fare' => $fq_itin->attributes('', true)->FQI . '',
                                'currency' => $fq_itin->attributes('', true)->Cur . '',
                                // 'price' => $xmlObject->PNR->FareQuote->FareStore[$pax_no]->SegmentFS[$segment_id]->attributes('', true)->Total . '',
                                'fare' => (double) $fq_itin->attributes('', true)->Fare . '',
                                'tax' => (
                                    (double) $fq_itin->attributes('', true)->Tax1 +
                                    (double) $fq_itin->attributes('', true)->Tax2 +
                                    (double) $fq_itin->attributes('', true)->Tax3
                                ),
                                'total' => (double) $fq_itin->attributes('', true)->Total
                            ];
                        }
                    }
                    // Fare Store
                    if (is_iterable($fare_qoute->FareStore)) {
                        foreach ($fare_qoute->FareStore as $fare_store) {
                            if ($fare_store->attributes('', true)->FSID == 'FQC') {
                                $fare_store_segments = [];
                                $total_fare = 0;
                                $total_tax = 0;

                                foreach ($fare_store->SegmentFS as $segment_fare_store) {
                                    $row = [
                                        'segment_id' => $segment_fare_store->attributes('', true)->Seg . '',
                                        'fare' => (double) $segment_fare_store->attributes('', true)->Fare,
                                        'tax1' => (double) $segment_fare_store->attributes('', true)->Tax1,
                                        'tax2' => (double) $segment_fare_store->attributes('', true)->Tax2,
                                        'tax3' => (double) $segment_fare_store->attributes('', true)->Tax3,
                                    ];

                                    $total_fare += $row['fare'];
                                    $total_tax += ($row['tax1'] + $row['tax2'] + $row['tax3']);

                                    $fare_store_segments[] = $row;
                                }

                                $pnr['fare_store'][] = [
                                    'pax_id' => $fare_store->attributes('', true)->Pax . '',
                                    // 'segment_id' => $fq_itin->attributes('', true)->Seg . '',
                                    'currency' => $fq_itin->attributes('', true)->Cur . '',
                                    'fare' => $total_fare,
                                    'tax' => $total_tax,
                                    'total' => (double) $fare_store->attributes('', true)->Total,
                                    'segments' => $fare_store_segments,
                                ];
                            }
                        }
                    }

                    // Fare Tax
                    if (is_iterable($fare_qoute->FareTax->PaxTax)) {
                        foreach ($fare_qoute->FareTax->PaxTax as $pax_tax) {
                            $pnr['taxes'][] = [
                                'segment_id' => $pax_tax->attributes('', true)->Seg . '',
                                'pax_id' => $pax_tax->attributes('', true)->Pax . '',
                                'code' => $pax_tax->attributes('', true)->Code . '',
                                'currency' => $pax_tax->attributes('', true)->Cur . '',
                                'amount' => $pax_tax->attributes('', true)->Amnt . '',
                                'description' => $pax_tax->attributes('', true)->desc . '',
                            ];
                        }
                    }
                }
            }

            // Extract payments
            if (is_iterable($xmlObject->PNR->Payments->FOP)) {
                foreach ($xmlObject->PNR->Payments->FOP as $payment) {
                    $pnr['payments'][] = [
                        'itinerary_id' => (string) $payment->attributes()->{'Line'},
                        'form_of_payment_id' => (string) $payment->attributes()->{'FOPID'},
                        'currency' => (string) $payment->attributes()->{'PayCur'},
                        'amount' => (double) $payment->attributes()->{'PayAmt'},
                        'reference' => (string) $payment->attributes()->{'PayRef'},
                        'pnr_currency' => (double) $payment->attributes()->{'PNRCur'},
                        'pnr_amount' => (double) $payment->attributes()->{'PNRAmt'},
                        'pnr_extchange_rate' => (double) $payment->attributes()->{'PNRExRate'},
                        'date' => date('Y-m-d', strtotime($payment->attributes()->{'PayDate'})),
                    ];
                }
            }

            // Extract tickets
            if (is_iterable($xmlObject->PNR->Tickets->TKT)) {
                foreach ($xmlObject->PNR->Tickets->TKT as $ticket) {
                    $pnr['tickets'][] = [
                        'passenger_id' => (string) $ticket->attributes()->{'Pax'},
                        'ticket_id' => (string) $ticket->attributes()->{'TKTID'},
                        'ticket_number' => (string) $ticket->attributes()->{'TktNo'},
                        'coupon' => (string) $ticket->attributes()->{'Coupon'},
                        'flight_date' => date("Y-m-d", strtotime($ticket->attributes()->{'TktFltDate'})),
                        'flight_number' => (string) $ticket->attributes()->{'TktFltNo'},
                        'from' => (string) $ticket->attributes()->{'TktDepart'},
                        'to' => (string) $ticket->attributes()->{'TktArrive'},
                        'class' => (string) $ticket->attributes()->{'TktBClass'},
                        'issue_date' => date('Y-m-d', strtotime($ticket->attributes()->{'IssueDate'})),
                        'status' => (string) $ticket->attributes()->{'Status'},
                        'segment_number' => (string) $ticket->attributes()->{'SegNo'},
                        'title' => (string) $ticket->attributes()->{'Title'},
                        'first_name' => (string) $ticket->attributes()->{'Firstname'},
                        'last_name' => (string) $ticket->attributes()->{'Surname'},
                        'hold_pices' => (string) $ticket->attributes()->{'HoldPcs'},
                        'hold_weight' => (string) $ticket->attributes()->{'HoldWt'},
                        'hand_weight' => (string) $ticket->attributes()->{'HandWt'},
                        'web_checkout' => ($ticket->attributes()->{'WebCheckOut'} == "True"),
                    ];
                }
            }

            // Extract contacts
            if (is_iterable($xmlObject->PNR->Contacts?->CTC)) {
                foreach ($xmlObject->PNR->Contacts?->CTC as $contact) {
                    $pnr['contacts'][] = [
                        'line' => (integer) $contact->attributes()->{'Line'},
                        'type' => (string) $contact->attributes()->{'CTCID'},
                        'pax_id' => (integer) $contact->attributes()->{'PAX'},
                        'value' => (string) $contact,
                    ];
                }
            }

            // Extract remarks
            if (is_iterable($xmlObject->PNR->Remarks?->RMK)) {
                foreach ($xmlObject->PNR->Remarks->RMK as $remark) {
                    $pnr['remarks'][] = [
                        'line' => (integer) $remark->attributes()->{'Line'},
                        'text' => (string) $remark,
                    ];
                }
            }

            // Extraxt MPS
            if (is_iterable($xmlObject->PNR->MPS->MP)) {
                foreach ($xmlObject->PNR->MPS->MP as $mp) {
                    $pnr['mps'][] = [
                        'id' => (string) $mp->attributes()->{'MPID'},
                        'pax_id' => (string) $mp->attributes()->{'Pax'},
                        'segment_id' => (string) $mp->attributes()->{'Seg'},
                        'currency' => (string) $mp->attributes()->{'MPSCur'},
                        'amount' => (string) $mp->attributes()->{'MPSAmt'},
                    ];
                }
            }


            if (count($pnr['tickets']) > 0) {
                if (count($pnr['payments']) > 0) {
                    $pnr['is_issued'] = true;
                }
            }

            return $pnr;

        }
    }

    private function parse_mps_pnr($xmlObject)
    {

        $pnr = [
            'mps' => [],
        ];


        if ($xmlObject) {

            // Extraxt MPS
            if (is_iterable($xmlObject->PNR->MPS)) {
                foreach ($xmlObject->PNR->MPS->MP as $mp) {
                    $pnr['mps'][] = [
                        'id' => (string) $mp->attributes()->{'MPID'},
                        'pax_id' => (string) $mp->attributes()->{'Pax'},
                        'segment_id' => (string) $mp->attributes()->{'Seg'},
                        'currency' => (string) $mp->attributes()->{'MPSCur'},
                        'amount' => (string) $mp->attributes()->{'MPSAmt'},
                    ];
                }
            }

            // Extract Basket
            if (isset($xmlObject->PNR?->Basket?->Outstanding)) {
                $pnr['refund_amount'] = (-1) * (double) $xmlObject->PNR->Basket->Outstanding->attributes()->{'amount'};
            }

            return $pnr;

        }
    }

    private function update_flight_seats($aero_token, $response, $cmd = "")
    {
        $result = $response;

        \App\Models\CommandRequest::create([
            'aero_token_id' => $aero_token->id,
            'user_id' => (request()?->user() != null) ? request()?->user()?->id : 1,
            'command' => $cmd,
            'result' => $result->response ?? '<EMPTY></EMPTY>',
        ]);

        if ($result->response != null) {
            $xml = $result->response;

            $xmlObject = null;

            if (isValidXml($xml)) {
                $xmlObject = simplexml_load_string($xml);
            } else {
                $startPos = strpos($xml, '<AvailabilityResponse>');
                $endPos = strpos($xml, '</AvailabilityResponse>') + strlen('</AvailabilityResponse>');
                $xml = substr($xml, $startPos, $endPos - $startPos);

                $xmlObject = null;
            }

            $flights_data = [];


            if ($xmlObject) {
                // Check if the flight is canceled
                foreach ($xmlObject->Journeys->Journey as $row) {

                    $origin = $row->DepartureAirportCityCode . '';
                    $destination = $row->ArrivalAirportCityCode . '';
                    $departure = date('Y-m-d H:i', strtotime($row->DepartureDate));
                    $arrival = date('Y-m-d H:i', strtotime($row->Legs->BookFlightSegmentType->ArrivalDateTime));
                    $duration = durationStringToMinutes($row->Legs->BookFlightSegmentType->FlightDuration . '');
                    $flight_number = $row->Legs->BookFlightSegmentType->FlightNumber . '';
                    $aircraft_code = $row->Legs->BookFlightSegmentType->Equipment->attributes('', true)->AirEquipType . '';

                    $airport_from = getAirport($origin);
                    $airport_to = getAirport($destination);

                    $uuid = md5(
                        $aero_token->iata . "_" . $origin . "_" .
                        $destination . "_" . $flight_number . "_" .
                        $departure . "_" . $arrival
                    );

                    $flights_data[] = [
                        'origin' => $origin,
                        'destination' => $destination,
                        'departure' => $departure,
                        'arrival' => $arrival,
                        'flight_number' => $flight_number,
                        'aircraft_code' => $aircraft_code,
                        'uuid' => $uuid,
                        'aero_token_id' => $aero_token->id,
                    ];


                    Log::info(json_encode([
                        'uuid' => $uuid,
                        'aero_token_id' => $aero_token->id,
                        'iata' => $flight_number[0] . $flight_number[1],
                        'origin' => $origin,
                        'destination' => $destination,
                        'flight_number' => $flight_number,
                        'aircraft_code' => $aircraft_code,
                        'departure' => $departure,
                        'arrival' => $arrival,
                        'duration' => $duration,
                        'has_offers' => true,
                        'date' => date('Y-m-d', strtotime($departure)),
                        'is_international' => ($airport_from->country != $airport_to->country),
                    ]));

                    $flight = \App\Models\FlightSchedule::where([
                        'origin' => $origin,
                        'destination' => $destination,
                        'departure' => $departure,
                        'arrival' => $arrival,
                        'flight_number' => $flight_number,
                        'canceled_at' => null,
                    ])->first();


                    if ($flight == null) {



                        $flight = \App\Models\FlightSchedule::create([
                            'uuid' => $uuid,
                            'aero_token_id' => $aero_token->id,
                            'iata' => $flight_number[0] . $flight_number[1],
                            'origin' => $origin,
                            'destination' => $destination,
                            'flight_number' => $flight_number,
                            'aircraft_code' => $aircraft_code,
                            'departure' => $departure,
                            'arrival' => $arrival,
                            'duration' => $duration,
                            'has_offers' => true,
                            'date' => date('Y-m-d', strtotime($departure)),
                            'is_international' => ($airport_from->country != $airport_to->country),
                        ]);

                        if ($flight->one_way_offers->count() < 2) {
                            dispatch(new \App\Jobs\GetFlightClassbandInformation($flight))->onQueue($aero_token->getQueueId());
                            // dispatch(new \App\Jobs\SyncOneWayOfferJob($av_model))->onQueue($aero_token->getQueueId());
                        }



                    }

                    // Flight Seats
                    foreach ($row->Legs->BookFlightSegmentType->Availability->Class as $availability_class) {
                        if (
                            $flight->availablities()->where([
                                'cabin' => $availability_class->attributes('', true)->cab . '',
                                'class' => $availability_class->attributes('', true)->id . ''
                            ])->count() > 0
                        ) {
                            $flight->availablities()->where([
                                'cabin' => $availability_class->attributes('', true)->cab . '',
                                'class' => $availability_class->attributes('', true)->id . ''
                            ])->update([
                                        'seats' => $availability_class->attributes('', true)->av . '',
                                    ]);
                        } else {
                            $new_availability = [
                                'flight_schedule_id' => $flight->id,
                                'name' => $availability_class->attributes('', true)->cab . '',
                                'display_name' => $availability_class->attributes('', true)->id . '',
                                'cabin' => $availability_class->attributes('', true)->cab . '',
                                'class' => $availability_class->attributes('', true)->id . '',
                                'is_international' => false,
                                'rules' => '[]',
                                'carrier' => $flight->iata,
                            ];

                            $new_availability['seats'] = $availability_class->attributes('', true)->av . '';
                            $new_availability['price'] = 0;
                            $new_availability['currency'] = '';
                            $new_availability['tax'] = 0;
                            $new_availability['miles'] = 0;
                            $new_availability['fare_available'] = false;
                            $new_availability['fare_id'] = 0;
                            $new_availability['aircraft_code'] = 0;

                            $uuid = $new_availability['flight_schedule_id'] . $new_availability['cabin'] . $new_availability['class'];

                            $av_model = \App\Models\FlightAvailablity::updateOrCreate([
                                'uuid' => md5($uuid),
                            ], $new_availability);

                            if ($av_model->wasRecentlyCreated) {
                                dispatch(new \App\Jobs\SyncOneWayOfferJob($av_model))->onQueue($aero_token->getQueueId());
                            }
                        }
                    }

                    if ($flight->one_way_offers->count() == 0) {
                        foreach ($flight->availablities as $availablity) {
                            dispatch(new \App\Jobs\SyncOneWayOfferJob($availablity))->onQueue($aero_token->getQueueId());
                        }
                    } else {
                        foreach ($flight->availablities as $availablity) {
                            if ($availablity->one_way_offers->count() == 0) {
                                dispatch(new \App\Jobs\SyncOneWayOfferJob($availablity))->onQueue($aero_token->getQueueId());
                            }
                        }   
                    }
                }

                $dates = [];
                foreach ($flights_data as $flight_data) {
                    $date = date('Y-m-d', strtotime($flight_data['departure']));
                    $dates[$date][] = $flight_data;
                }

                // foreach ($dates as $date => $flights) {
                //     $uuids = [];

                //     foreach ($flights as $flight_data) {
                //         $uuids[] = $flight_data['uuid'];
                //     }

                //     $canceled_flights = \App\Models\FlightSchedule::whereDate('departure', $date)
                //         ->where('aero_token_id', $flight_data['aero_token_id'])
                //         ->whereNotIn('uuid', $uuids)
                //         ->get();

                //     foreach ($canceled_flights as $canceled_flight) {
                //         $canceled_flight->update([
                //             'canceled_at' => now(),
                //         ]);
                //     }
                // }
            }

        }
    }

}
