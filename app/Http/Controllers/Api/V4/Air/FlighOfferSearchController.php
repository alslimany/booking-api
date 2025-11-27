<?php

namespace App\Http\Controllers\Api\V4\Air;

use App\Http\Controllers\Controller;
use App\Models\FlightSchedule;
use App\Models\OneWayOffer;
use App\Models\RoundWayOffer;
use Illuminate\Http\Request;

class FlighOfferSearchController extends Controller
{

    public function flight_offers(Request $request)
    {
        $request->validate([
            'origin_location_code' => 'required',
            'destination_location_code' => 'required',
            'departure_date' => 'required|after_or_equal:' . date('Y-m-d'),
            'return_date' => 'sometimes|after_or_equal:departure_date',
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

        if ($request->filled('return_date')) {
            return $this->round_flight_offers($request);
        } else {
            return $this->oneway_flight_offers($request);
        }
    }

    public function flight_availabilities(Request $request)
    {
        $request->validate([
            'origin_location_code' => 'required',
            'destination_location_code' => 'required',
            'departure_date' => 'required|after_or_equal:' . date('Y-m-d'),
            // 'return_date' => 'sometimes|after_or_equal:departure_date',
            // 'adults' => 'required',
            // 'children' => 'sometimes',
            // 'infants' => 'sometimes',
            // 'seated_infants' => 'sometimes',
            // 'travel_class' => 'sometimes',
            // 'airline_codes' => 'sometimes',
            // 'none_stop' => 'sometimes',
            // 'max_price' => 'sometimes',
            // 'max' => 'sometimes',
        ]);

        $from_date = date('Y-m-d');

        $dates = [];
        for ($day_index = 1; $day_index <= 40; $day_index++) {
            $dates[] = [
                'date' => date('Y-m-d', strtotime($from_date . " + $day_index days")),
                'number_of_flights' => 0,
                'price' => 0,
                'price_range' => 0,
            ];
        }

        // return $dates;

        if ($request->filled('return_date')) {
            # Cheapest Round Trip
            foreach ($dates as &$date) {
                // $round_way_offers
            }
        } else {
            # Cheapest Oneway Trip
            $all_prices = [];
            foreach ($dates as &$date) {
                $one_way_offers = OneWayOffer::whereDate('departure', $date['date'])
                    ->where('from', $request->origin_location_code)
                    ->where('to', $request->destination_location_code)
                    ->where('passenger_type', 'AD')
                    ->whereHas('flight_availablity', function ($q) {
                        $q->where('seats', '>', 0);
                    })
                    ->orderBy('price')
                    ->get();
                if (count($one_way_offers) > 0) {
                    foreach ($one_way_offers as $lpo) {
                        $lpo_price = $lpo->fare_price + $lpo->tax;

                        if (!in_array($lpo_price, $all_prices)) {
                            $all_prices[] = $lpo_price;
                        }
                    }

                    $number_of_flights = OneWayOffer::whereDate('departure', $date['date'])
                        ->where('from', $request->origin_location_code)
                        ->where('to', $request->destination_location_code)
                        ->where('passenger_type', 'AD')
                        ->whereHas('flight_availablity', function ($q) {
                            $q->where('seats', '>', 0);
                        })
                        ->groupBy('flight_schedule_id')
                        ->count();

                    $lowest_one_way_offer = $one_way_offers[0];

                    $date['price'] = $lowest_one_way_offer->fare_price + $lowest_one_way_offer->tax;
                    $date['number_of_flights'] = $number_of_flights;
                }

            }

            $min_price = min($all_prices);
            $max_price = max($all_prices);

            foreach ($dates as &$date) {
                $difference = $max_price - $min_price; // 200
                $difference_from_start = $max_price - $date['price']; // 100
                $percentage = (($difference / $difference_from_start) - 1) * 100; // 0.5 (50%) 

                $date['price_range'] = round($percentage, 2);

                $date['price_range_color'] = $this->getColorForPriceRange($percentage);
            }
        }

        return $dates;
    }

    public function flight_offers_pricing(Request $request)
    {
        $request->validate([
            // 'id' => 'required',
            'data' => 'required',
            'data.flightOffers' => 'required',
        ]);

        $itineraries = $request->data['flightOffers']['itineraries'];
        $price = $request->data['flightOffers']['price'];
        $traveler_pricings = $request->data['flightOffers']['travelerPricings'];



        $segments = [];
        $travelers = [
            'adult' => 0,
            'child' => 0,
            'infant' => 0,
            'infant_seated' => 0,
        ];
        // return $traveler_pricings;

        foreach ($traveler_pricings as $traveler_pricing) {
            $travelers[$traveler_pricing['travelerType']]++;

            foreach ($traveler_pricing['fareDetailsBySegment'] as $segment) {
                if (!array_key_exists($segment['segmentId'], $segments)) {
                    $segments[$segment['segmentId']] = [
                        'flight_id' => $segment['segmentId'],
                        'class' => $segment['class'],
                    ];
                }
            }
        }

        $result = [
            'data' => [
                'flightOffers' => [
                    $request->data['flightOffers']
                ],
                'bookingRequirements' => [],
                'dictionaries' => [],
            ],
        ];

        return response()->json($result, 200);
        return [
            'segments' => $segments,
            'travelers' => $travelers,
        ];
    }

    public function create_order(Request $request)
    {
        $request->validate([
            'data' => 'required',
            'data.flightOffers' => 'required',
            'data.travelers' => 'required',
            'data.contacts' => 'required',
        ]);

        $pnr_number = get_next_order_number();

        $data = $request->data;

        $flight_schedules = collect([]);

        foreach ($data['flightOffers'] as $flightOffer) {
            foreach ($flightOffer['itineraries'] as $itinerary) {
                foreach ($itinerary['segments'] as $segment) {
                    $flight_schedule = \App\Models\FlightSchedule::find($segment['id']);

                    foreach ($flightOffer['travelerPricings'] as $travelerPricing) {
                        foreach ($travelerPricing['fareDetailsBySegment'] as $fareDetailsBySegment) {
                            if ($flight_schedule->id == $fareDetailsBySegment['segmentId']) {
                                $flight_schedule->class = $fareDetailsBySegment['class'];
                            }
                        }
                    }

                    $flight_schedules->push($flight_schedule);
                }
            }
        }

        $count_passengers = 0;
        $issue_pnr_command = "";

        $ticket_passengers = [];
        foreach ($data['travelers'] as $traveler) {
            $count_passengers++;

            if ($traveler['type'] == "adult") {
                $issue_pnr_command .= "-1" . $traveler['name']['lastName'] . "/" . $traveler['name']['firstName'] . 'MR^';

                $ticket_passengers[] = [
                    "id" => $count_passengers,
                    "age" => "",
                    "type" => "AD",
                    "title" => "MR",
                    "last_name" => $traveler['name']['lastName'],
                    "first_name" => $traveler['name']['firstName'],
                    "group_number" => "1",
                    "passenger_group_number" => "1"
                ];
            }
            if ($traveler['type'] == "child") {
                $issue_pnr_command .= "-1" . $traveler['name']['lastName'] . "/" . $traveler['name']['firstName'] . 'MISS.CH10^';
                $ticket_passengers[] = [
                    "id" => $count_passengers,
                    "age" => "",
                    "type" => "CH",
                    "title" => "MISS",
                    "last_name" => $traveler['name']['lastName'],
                    "first_name" => $traveler['name']['firstName'],
                    "group_number" => "1",
                    "passenger_group_number" => "1"
                ];
            }
            if ($traveler['type'] == "infant") {
                $issue_pnr_command .= "-1" . $traveler['name']['lastName'] . "/" . $traveler['name']['firstName'] . 'MSTR.IN06^';
                $ticket_passengers[] = [
                    "id" => $count_passengers,
                    "age" => "",
                    "type" => "IN",
                    "title" => "MSTR",
                    "last_name" => $traveler['name']['lastName'],
                    "first_name" => $traveler['name']['firstName'],
                    "group_number" => "1",
                    "passenger_group_number" => "1"
                ];
            }
            if ($traveler['type'] == "seated_infant") {
                $issue_pnr_command .= "-1" . $traveler['name']['lastName'] . "/" . $traveler['name']['firstName'] . 'MSTR.IS06^';
                $ticket_passengers[] = [
                    "id" => $count_passengers,
                    "age" => "",
                    "type" => "IS",
                    "title" => "MSTR",
                    "last_name" => $traveler['name']['lastName'],
                    "first_name" => $traveler['name']['firstName'],
                    "group_number" => "1",
                    "passenger_group_number" => "1"
                ];
            }

            foreach ($traveler['documents'] as $document) {
                switch ($document['documentType']) {
                    case 'passport':
                        $issue_pnr_command .= "4-" . $traveler['id'] . "FDOCS/P/" . $document['issuanceCountry'] .
                            "/" . $document['number'] . "/" . $document['nationality'] .
                            "/" . date('dMy', strtotime($traveler['dateOfBirth'])) . "/" . $traveler['gender'][0] .
                            "/" . date('dMy', strtotime($document['expiryDate'])) .
                            "/" . $traveler['name']['lastName'] . "/" . $traveler['name']['firstName'] .
                            "/" . $traveler['name']['middleName'] . "^";
                        break;
                    case 'visa':
                        $issue_pnr_command .= "4-" . $traveler['id'] . "FDOCO//V/" . $document['number'] .
                            "/" . $document['nationality'] . "/" . date('dMy', strtotime($document['issuanceDate'])) .
                            "/" . $document['issuanceCountry'] . "//" . date('dMy', strtotime($document['expiryDate'])) .
                            "^";
                        break;
                }
            }

            $issue_pnr_command = substr($issue_pnr_command, 0, -1);

            $ticket_contacts = [];
            $ticket_contact_line_id = 1;
            foreach ($data['contacts'] as $type => $_contact) {
                switch ($type) {
                    case 'phones':
                        foreach ($_contact as $phone) {
                            // return $phone;
                            $issue_pnr_command .= "^9-1" . "M*" . $phone['countryCallingCode'] . $phone['number'];
                            $ticket_contacts[] = [
                                "line" => $ticket_contact_line_id,
                                "type" => "M",
                                "value" => $phone['countryCallingCode'] . $phone['number'],
                                "pax_id" => 0
                            ];
                        }
                        break;
                    case 'emailAddress':
                        $issue_pnr_command .= "^9-1" . "E*" . $_contact;
                        $ticket_contacts[] = [
                            "line" => $ticket_contact_line_id,
                            "type" => "M",
                            "value" => $_contact,
                            "pax_id" => 0
                        ];
                        break;
                }

                $ticket_contact_line_id++;
            }

            $issue_pnr_command .= '^';
        }

        $issue_pnr_command = substr($issue_pnr_command, 0, -1);

        $booking_type = "NN";
        $command = "";

        $pnrs = [];

        // return $flight_schedules;
        foreach ($flight_schedules->groupBy('aero_token_id') as $aero_token_id => $flights) {
            $aero_token = \App\Models\AeroToken::where('id', $aero_token_id)->first();

            $command = $issue_pnr_command;

            foreach ($flights as $flight) {
                $command .= "^0" . $flight->flight_number . $flight->class . date('dM', strtotime($flight->departure)) . $flight->origin . $flight->destination . $booking_type . $count_passengers;
            }

            $command = "I^" . $command;
            // $command .= "^FG";
            $command .= "^FG";
            $command .= "^FS1";
            // $command .= "^*R";
            if ($data['bookingType'] == "issue") {
                $command .= "^MI-ABC TOURS01012";
                $command .= "^EZT*R";
                $command .= "^EZRE";
                $command .= "^*R~x";
            }
            if ($data['bookingType'] == "hold") {
                $command .= "^E*R~x";
            }

            $order = [];

            # Create Order
            $order['id'] = 1;
            $order['number'] = $pnr_number;
            $order['owner_id'] = auth()->user()->id;
            $order['owner_type'] = "App\\Models\\User";
            $order['user_id'] = auth()->user()->id;
            $order['status'] = "completed";
            if ($data['bookingType'] == "issue") {
                $order['issued_at'] = date('Y-m-d H:i:s');
                $order['due_at'] = null;
            } else {
                $order['issued_at'] = date('Y-m-d H:i:s');
                $order['due_at'] = now()->addHours(3);
            }
            $order['contact_id'] = null;

            $order['items'] = [];

            foreach ($data['flightOffers'] as $flightOffer) {
                $order_item = [
                    "itineraries" => [],
                    "tickets" => [],
                    "passengers" => $ticket_passengers,
                    "fare_qoute" => [],
                    "fare_store" => [],
                    "contacts" => $ticket_contacts,
                    "currency" => "LYD",
                    "taxes" => [],
                    "total_price" => 0,
                    "total_fare" => 0,
                ];

                $itinerary_id = 1;

                foreach ($flightOffer['itineraries'] as $itinerary) {


                    foreach ($itinerary['segments'] as $segment) {
                        $flight_schedule = \App\Models\FlightSchedule::find($segment['id']);

                        $tickets = [];
                        foreach ($flightOffer['travelerPricings'] as $travelerPricing) {
                            // return $travelerPricing;
                            foreach ($travelerPricing['fareDetailsBySegment'] as $fareDetailsBySegment) {
                                if ($flight_schedule->id == $fareDetailsBySegment['segmentId']) {
                                    $flight_schedule->class = $fareDetailsBySegment['class'];
                                    // foreach ($travelerPricing as $_pricing) {
                                    //     return $_pricing;
                                    $order_item['total_price'] += $travelerPricing['price']['total'];
                                    $order_item['total_fare'] += $travelerPricing['price']['base'];
                                    // }
                                    // $items[]

                                    $order_item['fare_qoute'][] = [
                                        "tax" => $travelerPricing['price']['total'] - $travelerPricing['price']['base'],
                                        "fare" => $travelerPricing['price']['base'],
                                        "total" => $travelerPricing['price']['total'],
                                        "currency" => "LYD",
                                        "basic_fare" => "SITI 1596",
                                        "segment_id" => $itinerary_id
                                    ];

                                    $passenger_index = 0;
                                    foreach ($data['travelers'] as $traveler) {
                                        $passenger_index++;
                                        $title = "MSTR";

                                        if ($traveler['type'] == "adult") {
                                            // $issue_pnr_command .= "-1" . $traveler['name']['lastName'] . "/" . $traveler['name']['firstName'] . 'MR^';
                                            $title = "MR";
                                        }
                                        if ($traveler['type'] == "CHILD") {
                                            // $issue_pnr_command .= "-1" . $traveler['name']['lastName'] . "/" . $traveler['name']['firstName'] . 'MISS^';
                                            $title = "MISS";
                                        }

                                        $tickets[] = [
                                            "to" => $flight_schedule->origin,
                                            "from" => $flight_schedule->destination,
                                            "class" => $flight->class,
                                            "title" => $title,
                                            "coupon" => "01",
                                            "status" => "F",
                                            "last_name" => $traveler['name']['lastName'],
                                            "ticket_id" => "ETKT",
                                            "first_name" => $traveler['name']['firstName'],
                                            "hold_pices" => "1",
                                            "issue_date" => date('Y-m-d'),
                                            "flight_date" => $flight_schedule->departure,
                                            "hand_weight" => "0K",
                                            "hold_weight" => $fareDetailsBySegment['includedCheckedBags']['weight'] . "K",
                                            "passenger_id" => $passenger_index,
                                            "web_checkout" => false,
                                            "flight_number" => substr($flight_schedule->flight_number, 2),
                                            "ticket_number" => config('airline.airline_id.' . $flight_schedule->iata) . " " . get_next_ticket_number(),
                                            "segment_number" => $itinerary_id,
                                        ];

                                        $order_item['fare_store'][] = [
                                            "tax" => $travelerPricing['price']['total'] - $travelerPricing['price']['base'],
                                            "fare" => $travelerPricing['price']['base'],
                                            "total" => $travelerPricing['price']['total'],
                                            "pax_id" => $passenger_index,
                                            "currency" => "LYD",
                                            "segments" => [
                                                [
                                                    "fare" => $travelerPricing['price']['base'],
                                                    "tax1" => $travelerPricing['price']['total'] - $travelerPricing['price']['base'],
                                                    "tax2" => 0,
                                                    "tax3" => 0,
                                                    "segment_id" => $itinerary_id
                                                ]
                                            ],
                                        ];
                                    }
                                }
                            }
                        }

                        $airport_from = getAirport($flight_schedule->origin);
                        $airport_to = getAirport($flight_schedule->destination);

                        array_push($order_item['itineraries'], [
                            "to" => $flight_schedule->origin,
                            "date" => $flight_schedule->date,
                            "from" => $flight_schedule->destination,
                            "flight_number" => substr($flight_schedule->flight_number, 2),
                            "cabin" => $flight->cabin,
                            "class" => $flight->class,
                            "status" => ($data['bookingType'] == "issue") ? 'HK' : "QQ",
                            "arrival" => date('H:i:s', strtotime($flight_schedule->arrival)),
                            "departure" => date('H:i:s', strtotime($flight_schedule->departure)),
                            "origin" => getDbAirport($flight_schedule->origin),
                            "destination" => getDbAirport($flight_schedule->destination),
                            "airline_id" => $flight_schedule->iata,
                            "itinerary_id" => $itinerary_id,
                            "tickets" => $tickets,
                            'is_international' => ($airport_from->country != $airport_to->country),
                        ], );

                        // 
                        array_push($order_item['tickets'], ...$tickets);

                        $flight_schedules->push($flight_schedule);

                        $itinerary_id++;


                    }
                }

                // Calculate Teaxes
                $order_item['total_tax'] = $order_item['total_price'] - $order_item['total_fare'];

                $order_item['rloc'] = $pnr_number;
                $order_item['iata'] = $aero_token->iata;
                $order['items'][] = $order_item;
            }

            $_order = \App\Models\Order::create([
                'number' => $pnr_number,
                'owner_type' => 'App\\Models\\User',
                'owner_id' => auth()->user()->id,
                'status' => 'confirmed',
                'issued_at' => now(),
            ]);

            foreach ($order['items'] as $item) {
                $order_item_data = [
                    'order_id' => $_order->id,
                    'type' => 'ticket',
                    'reference' => $item['rloc'],
                    'price' => $item['total_fare'],
                    'taxes' => $item['total_tax'],
                    'total' => $item['total_price'],
                    'currency_code' => $item['currency'],
                    'exchange_rate' => 1,
                    'item' => $item,
                ];


                $total_fare = 0;
                $profit = 0;
                $agent_commission = 0;

                foreach ($item['itineraries'] as $itin) {
                    $fare = 0;
                    foreach ($item['fare_qoute'] as $fq) {
                        if ($fq['segment_id'] == $itin['itinerary_id']) {
                            $fare = $fq['fare'];
                        }
                    }

                    $total_fare += $fare;
                }

                $order_item_data['price'] = $total_fare;
                $order_item_data['net_commission'] = $profit;
                $order_item_data['agent_commission'] = $agent_commission;
                $order_item_data['remaning'] = 0;
                $order_item_data['paid'] = 0;
                $order_item_data['status'] = 'issue';

                // }

                $order_item = \App\Models\OrderItem::create($order_item_data);
            }

            return $order;
            # Run command
            $response = $aero_token->build()->runCommand($command);

            $result = $response?->response;

            if ($result != null) {
                $fare = 0;
                $tax = 0;
                $price = 0;
                $parsed_result = parse_result($result);
                if ($parsed_result['status'] == 'OK') {
                    foreach ($parsed_result['segments'] as $index => $segment) {
                        foreach ($segment as $segment_price) {
                            $fare += $segment_price['fare'];
                            // $tax += $segment_price['tax'];
                            $price += $segment_price['price'];
                        }

                    }
                }


                $xml = "";

                $xml = "<xml>" . $result . "</xml>";

                $xmlObject = simplexml_load_string($xml);

                $pnrs[] = parse_pnr($xmlObject);


            }
        }

        foreach ($flight_schedules as $flight_schedule) {
            $flight = \App\Models\FlightSchedule::find($flight_schedule->id);

            \App\Jobs\CheckFlightSeatAvailabilityJob::dispatch($flight)->onQueue($flight->aero_token?->getQueueId());
        }


        return response()->json([
            'pnrs' => $pnrs,
        ]);
    }

    #region Flight Offers Methods
    private function oneway_flight_offers(Request $request)
    {

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
        $destination_airport = getAirport($request->destination_location_code);
        $travel_class = $request->travel_class;

        $p_type = [
            'AD' => 'adults',
            'CH' => 'children',
            'IN' => 'infants',
            'IS' => 'seated_infants',
        ];

        $direct_flight_schedules = FlightSchedule::when($only, function ($q, $v) {
            $q->whereIn('iata', $v);
        })
            ->whereDate('departure', '=', date('Y-m-d', strtotime($request->departure_date)))
            ->where('origin', '=', $request->origin_location_code)
            ->where('destination', '=', $request->destination_location_code)
            ->whereHas('availablities', $availablities = function ($q) use ($total_passengers) {
                $q->where('seats', '>', $total_passengers);
            })
            // ->whereHas('one_way_offers', $one_way_offers = function ($q) use ($passenger_types, $travel_class) {
            //     $q->whereIn('passenger_type', $passenger_types)
            //         ->whereIn('class', $this->h_getClassCodes($travel_class));
            // })
            // ->with([
            //     'one_way_offers' => $one_way_offers
            // ])
            ->get();

        $data = [];

        foreach ($direct_flight_schedules as $direct_flight_schedule) {
            $dep = \Carbon\Carbon::parse($direct_flight_schedule->departure);
            $arr = \Carbon\Carbon::parse($direct_flight_schedule->arrival);

            $timezone_diff = ($origin_airport->timezone - $destination_airport->timezone) * 60;

            $flight_duration = $dep->diffInMinutes($arr) + $timezone_diff;

            foreach ($direct_flight_schedule->one_way_offers->groupBy('class') as $class => $one_way_offers) {
                $number_of_bookable_seats = 0;
                $passenger_index = 1;
                $offer = [
                    'itineraries' => [],
                ];
                $traveler_pricings = [];
                $segments = [];

                $currency = "";
                $total = 0;
                $base = 0;

                $passenger_found = true;

                if ($number_of_bookable_seats < $one_way_offers[0]->flight_availablity->seats) {
                    $number_of_bookable_seats = $one_way_offers[0]->flight_availablity->seats;
                }

                $segments[] = [
                    'departure' => [
                        'iataCode' => $direct_flight_schedule->origin,
                        'at' => $direct_flight_schedule->departure,
                    ],
                    'arrival' => [
                        'iataCode' => $direct_flight_schedule->destination,
                        'at' => $direct_flight_schedule->arrival,
                    ],
                    'carrierCode' => $direct_flight_schedule->iata,
                    'carrier' => $direct_flight_schedule->aero_token->only('iata'),
                    "number" => $direct_flight_schedule->flight_number,
                    'aircraft' => findAircraft($direct_flight_schedule->aircraft_code > 0 ? $direct_flight_schedule->aircraft_code : 320),
                    'id' => $direct_flight_schedule->id,
                ];

                foreach ($passenger_types as $passenger_type) {
                    $one_way_offer = $one_way_offers->firstWhere('passenger_type', $passenger_type);

                    // return $one_way_offer;
                    if ($one_way_offer?->passenger_type != null) {
                        for ($pid = 0; $pid < $request->get($p_type[$one_way_offer->passenger_type]); $pid++) {
                            $pricing = [
                                "travelerId" => $passenger_index,
                                "fareOption" => "standard",
                                'id' => $one_way_offer->flight_availablity_id,
                                // "traveler_type" => $passenger_pricing->passenger_type,
                                "travelerType" => getPassengerTypeByCode($one_way_offer->passenger_type),
                                "price" => [
                                    "currency" => $one_way_offer->currency,
                                    "total" => (double) $one_way_offer->price,
                                    "base" => (double) $one_way_offer->fare_price
                                ],
                                'fareDetailsBySegment' => []
                            ];

                            $pricing['fareDetailsBySegment'][] = [
                                'segmentId' => $one_way_offer->flight_schedule_id,
                                'cabin' => getClassNameByCabin($one_way_offer->cabin),
                                'fareBasis' => $one_way_offer->fare_basis,
                                'class' => $one_way_offer->class,
                                'includedCheckedBags' => [
                                    'weight' => (int) str_replace("K", "", $one_way_offer->hold_weight),
                                    'weightUnit' => 'KG',
                                ],
                            ];

                            $traveler_pricings[] = $pricing;

                            $currency = $one_way_offer->currency;
                            $base += $one_way_offer->fare_price;
                            $total += $one_way_offer->price;

                            $passenger_index++;
                        }
                    } else {
                        $passenger_found = false;
                    }
                }

                $offer['itineraries'][] = [
                    'duration' => $this->h_convertToHoursMins($flight_duration, 'PT%02dH%02dM'),
                    'segments' => $segments,
                ];
                $offer['travelerPricings'] = $traveler_pricings;
                $offer['price'] = [
                    "currency" => $currency,
                    "total" => $total,
                    "base" => $base,
                    "grandTotal" => $total,

                    "fees" => [
                        // [
                        //    "amount" => 0.00,
                        //    "type" => "SUPPLIER"
                        // ],
                        // [
                        //    "amount" => 0.00,
                        //    "type" => "TICKETING"
                        // ],
                    ],
                ];

                $offer['id'] = $direct_flight_schedule->id;
                $offer['numberOfBookableSeats'] = $number_of_bookable_seats;
                $offer['oneWay'] = true;

                // $offer['ref_uri'] = 'https://flights.booknow.ly/ref/' . sha1(now());
                if ($passenger_found) {
                    ksort($offer, SORT_STRING);
                    $data[] = $offer;
                }
            }
        }

        $result = [
            'meta' => [
                'count' => count(value: $data),
            ],
            'data' => $data,
        ];

        return $result;

    }

    private function round_flight_offers(Request $request)
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
                        foreach ($out_round_way_offer_segment->round_way_pricings->whereIn('passenger_type', $passenger_types)->whereIn('class', $this->h_getClassCodes($travel_class))->groupBy('class') as $o_class => $out_round_way_segment_pricings) {
                            foreach ($in_round_way_offer_segment->round_way_pricings->whereIn('passenger_type', $passenger_types)->whereIn('class', $this->h_getClassCodes($travel_class))->groupBy('class') as $i_class => $in_round_way_segment_pricings) {
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

                // $p_found = true;
                // foreach ($itineraries as $itinerary) {
                //     if (!$itinerary['passenger_found']) {
                //         $p_found = false;
                //     }
                // }

                // if ($p_found && $number_of_bookable_seats > 0) {
                //     $offer['itineraries'] = $itineraries;
                //     $offer['numberOfBookableSeats'] = $number_of_bookable_seats;
                //     $offer['price'] = $price;
                //     $offer['travelerPricings'] = $traveler_pricings;

                //     $offers[] = $offer;

                //     ksort($offers, SORT_STRING);

                // }
            }
        }

        return [
            'meta' => [
                'count' => count($offers),
            ],
            'data' => $offers,
        ];

    }
    #endregion

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

    public function flight_search(Request $request)
    {
        // 1. Enhanced validation with explicit types and optional parameters
        $validated = $request->validate([
            'origin_location_code' => 'required|string|size:3',
            'destination_location_code' => 'required|string|size:3',
            'departure_date' => 'required|date_format:Y-m-d',
            'adults' => 'required|integer|min:1',
            'children' => 'nullable|integer|min:0',
            'infants' => 'nullable|integer|min:0',
            'seated_infants' => 'nullable|integer|min:0',
            'travel_class' => 'nullable|string',
            'airline_codes' => 'nullable|string',
            'none_stop' => 'nullable|boolean',
            'max_price' => 'nullable|numeric',
            'max' => 'nullable|integer',
            'only' => 'nullable|string',
        ]);

        // 2. Simplified token query using scopes
        $aeroTokens = \App\Models\AeroToken::whereIn('iata', ['YI', 'UZ', '5S'])->get();

        // 3. Improved async request handling
        $client = new \GuzzleHttp\Client();
        $promises = $aeroTokens->mapWithKeys(function ($token) use ($validated, $client) {
            $command = $this->generateCommand($validated);
            $request = $token->build()->getAsyncCommandRunner($command);

            return $request ? [$token->id => $client->sendAsync($request)] : [];
        });

        // 4. Fixed XML parsing with proper SOAP handling
        $client = new \GuzzleHttp\Client();
        $promises = [];

        foreach ($aeroTokens as $token) {
            $command = $this->generateCommand($validated);
            $request = $token->build()->getAsyncCommandRunner($command);

            if ($request) {
                // Use the actual token ID as the promise key
                $promises[$token->id] = $client->sendAsync($request);
                $promises[$token->id] = $client->sendAsync($request, [
                    'timeout' => config('services.aero.timeout', 15)
                ]);
            }
        }

        // Process results with proper token association
        $results = \GuzzleHttp\Promise\Utils::settle($promises)->wait();

        // Preload all tokens in one query
        $tokenIds = array_keys($results);
        $tokens = \App\Models\AeroToken::findMany($tokenIds)->keyBy('id');

        $availabilities = collect($results)->flatMap(function ($result, $tokenId) use ($tokens) {
            // Get the corresponding token
            $token = $tokens->get($tokenId);

            if (!$token) {
                \Log::error('Token not found', ['token_id' => $tokenId]);
                return [];
            }

            if ($result['state'] !== 'fulfilled') {
                \Log::error('API request failed', [
                    'token_id' => $tokenId,
                    'error' => $result['reason']->getMessage()
                ]);
                // $this->markTokenAsFailed($tokenId);
                return [];
            }

            // Process response with the correct token
            $response = $result['value']->getBody()->getContents();

            \Log::info('API request succeeded', [
                'token_id' => $tokenId,
                'response' => $response // Consider logging a truncated version
            ]);

            return $this->processResponse($response, $token);
        })->filter()->values();

        return response()->json($availabilities);
    }
    // Helper methods
    protected function generateCommand(array $data): string
    {
        return sprintf(
            'A%s%s%s~x',
            date("dM", strtotime($data['departure_date'])),
            $data['origin_location_code'],
            $data['destination_location_code']
        );
    }

    protected function processResponse($responseData, \App\Models\AeroToken $token): array
    {
        try {
            if ($token->data['mode'] === 'user_auth') {
                return \App\Parsers\VidecomParser::parseAvailabilityJson($responseData);
            }
            return \App\Parsers\VidecomParser::parseAvailabilityXml($responseData);
        } catch (\Exception $e) {
            \Log::error('Response processing failed', [
                'token_id' => $token->id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}
