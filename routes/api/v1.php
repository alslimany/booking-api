<?php

use App\Core\Videcom;
use App\Http\Controllers\Api\FlightSearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

Route::get('tst', function () {


});

Route::group([
    'prefix' => 'v1',
    'middleware' => ['auth:sanctum', 'log-api'],
], function () {

    Route::get('shortest-way', function () {
        return trip_direction(request()->get('origin'), request()->get('destination'));
    });

    Route::get('tst', function () {


        // foreach (\App\Models\FlightSchedule::where('id', 17506)->get() as $flight) {
        //     foreach ($flight->availablities as $availability) {
        //         $command = "I^-4Pax/A#/B#.CH10/C#.IN06/D#.IS06/^0" . $flight->flight_number . $availability->class .
        //             date('dM', strtotime($flight->departure)) . $flight->origin . $flight->destination . "NN3^FG^FS1^*r~x";

        //         $cache_key = $flight->aero_token->getQueueId() . "I^-4Pax/A#/B#.CH10/C#.IN06/D#.IS06/^0" . $availability->class . $flight->origin . $flight->destination . "NN3^FG^FS1^*r~x";

        //         // $result = "";
        //         $offers = [];

        //         if (cache()->has($cache_key)) {
        //             $offers = cache()->get($cache_key);
        //         } else {
        //             $response = $flight->aero_token->build()->runCommand($command);
        //             $result = $response->response;

        //             $offers = p_r($result);
        //             if ($offers['status'] == "OK") {
        //                 cache()->put($cache_key, $offers, now()->addHours(6));
        //             }

        //         }

        //         // $offers = $this->parse_result($result);

        //         if ($offers['status'] == "OK") {
        //             \App\Jobs\UpdateOneWayOfferJob::dispatch($offers['data'], $flight, $command, $availability)
        //                 // ->delay(now()->addSeconds(5))
        //                 ->onQueue('default');
        //         } else {
        //             $availability->seats = -1;
        //             $availability->save();
        //         }
        //     }
        // }
    });

    // function p_r($result)
    // {
    //     $errors = [
    //         'ERROR' => 'ERROR',
    //         'SOLDOUT' => 'CLASS SOLD OUT ON THIS SERVICE',
    //         'NOTAVAILABLE' => 'CLASS NOT AVAILABLE ON THIS SERVICE',
    //         // 'NO AV' => 'NO AV',
    //     ];
    //     $status = "OK";

    //     $offers = [];

    //     foreach ($errors as $key => $val) {
    //         if (str_contains(strtolower($result), strtolower($val))) {
    //             $status = $key;
    //         }
    //     }

    //     // if ($result != "ERROR - no fare available" || $result != "ERROR - no fare available") {
    //     if ($status == "OK") {
    //         $xml = "<xml>" . $result . "</xml>";

    //         $xmlObject = simplexml_load_string($xml);

    //         // try {
    //         if ($xmlObject) {
    //             if ($xmlObject->PNR->Names != null) {
    //                 // Reserve Paxes
    //                 if (is_iterable($xmlObject->PNR->Names->PAX)) {
    //                     foreach ($xmlObject->PNR->Names->PAX as $pax) {
    //                         $offers[$pax->attributes('', true)->PaxNo . ''] = [
    //                             'passenger_type' => $pax->attributes('', true)->PaxType . '',
    //                             'from' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Depart . '',
    //                             'to' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Arrive . '',
    //                             'fare_basis' => $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->FQI . '',
    //                         ];
    //                     }
    //                 }

    //                 // Fare Price 
    //                 if (is_iterable($xmlObject->PNR->FareQuote->FareStore)) {
    //                     foreach ($xmlObject->PNR->FareQuote->FareStore as $fare_store) {
    //                         if ($fare_store->attributes('', true)->Pax != '') {
    //                             $offer = $offers[$fare_store->attributes('', true)->Pax . ''];

    //                             $offer['currency'] = $fare_store->attributes('', true)->Cur . '';
    //                             $offer['price'] = $fare_store->attributes('', true)->Total . '';
    //                             $offer['fare_price'] = $fare_store->SegmentFS->attributes('', true)->Fare . '';
    //                             $offer['tax'] = (
    //                                 (double) $fare_store->SegmentFS->attributes('', true)->Tax1 +
    //                                 (double) $fare_store->SegmentFS->attributes('', true)->Tax2 +
    //                                 (double) $fare_store->SegmentFS->attributes('', true)->Tax3
    //                             );

    //                             $offer['hold_pices'] = $fare_store->SegmentFS->attributes('', true)->HoldPcs . '';
    //                             $offer['hold_weight'] = $fare_store->SegmentFS->attributes('', true)->HoldWt . '';
    //                             $offer['hand_weight'] = $fare_store->SegmentFS->attributes('', true)->HandWt . '';

    //                             $offer['display_name'] = $xmlObject->PNR->Itinerary->Itin->attributes('', true)->ClassBandDisplayName . '';
    //                             $offer['name'] = $xmlObject->PNR->Itinerary->Itin->attributes('', true)->ClassBand . '';

    //                             $offers[$fare_store->attributes('', true)->Pax . ''] = $offer;
    //                         }
    //                     }
    //                 }
    //             }
    //         }
    //     }
    //     //  else {
    //     //     $status = "ERROR";
    //     // }

    //     return [
    //         'status' => $status,
    //         'data' => $offers
    //     ];
    // }

    Route::get('/aero-tokens', function () {
        return \App\Models\AeroToken::all();
        // $result = [];
        // foreach (\App\Models\AeroToken::all() as $aero_token) {
        //     $result[] = [
        //         'id' => $aero_token->id,
        //         'iata' => $aero_token->iata,
        //         'name' => $aero_token->name,
        //         'data' => [
        //             'added_tax' => $aero_token->data['added_tax'],
        //             'profit_from' => $aero_token->data['profit_from'],
        //             'currency_code' => $aero_token->data['currency_code'],
        //             'profit_percentage_domestic' => $aero_token->data['profit_percentage_domestic'],
        //             'profit_percentage_international' => $aero_token->data['profit_percentage_international'],
        //             // 'added_tax' => $aero_token->data['added_tax'],
        //         ],
        //     ];
        // }

        // return response()->json($result, 200);
    });

    Route::get('/{airport}/schedule', function ($airport) {
        return [
            'arrivals' => \App\Models\AirportSchedule::whereBetween('scheduled_arrival_at', [[date('Y-m-d H:i:s', strtotime(now() . ' - 30 minutes')), date('Y-m-d H:i:s', strtotime(now() . " + 16 hours"))]])
                ->where('destination', $airport)
                ->where('airline_iata', '!=', '')
                ->where('type', 'arrival')
                ->orderBy('scheduled_arrival_at')
                ->get(),
            'departures' => \App\Models\AirportSchedule::whereBetween('scheduled_departure_at', [date('Y-m-d H:i:s', strtotime(now() . ' - 30 minutes')), date('Y-m-d H:i:s', strtotime(now() . " + 16 hours"))])
                ->where('origin', $airport)
                ->where('airline_iata', '!=', '')
                ->where('type', 'departure')
                ->orderBy('scheduled_departure_at')
                ->get(),
        ];

        // if ($type == 'arrivals') {
        //     return $query->where('destination', $airport)
        //         ->get();
        // }

        // if ($type == 'departures') {
        //     return $query->where('origin', $airport)->get();
        // }
    });

    Route::get('/info', [FlightSearchController::class, 'info']);

    Route::get('/flight-schedule', [FlightSearchController::class, 'flight_schedule']);

    Route::get('/flight-dates-availability', [FlightSearchController::class, 'flight_dates_availability']);

    Route::get('/flight-availability', [FlightSearchController::class, 'flight_availability']);

    Route::get('/round-flight-search', [FlightSearchController::class, 'round_way_search']);

    Route::get('/multicity-flight-search', [FlightSearchController::class, 'multicity_flight_search']);

    Route::group([
        'prefix' => 'fare-notes',
    ], function () {
        Route::get('{iata}/{id}', function ($iata, $id) {
            $note = \App\Models\FareRule::where('carrier', $iata)->where('fare_id', $id)->first();
            return response()->json([
                'data' => $note->note ?? '',
            ]);
        });
    });

    Route::middleware('high-priority')->group(function () {

        Route::get('/flight-search', [FlightSearchController::class, 'one_way_search']);
        Route::get('/flight-searchv2', [FlightSearchController::class, 'one_way_searchv2']);
        Route::get('/flight-searchv3', [FlightSearchController::class, 'one_way_searchv3']);
        Route::get('/flight-searchv5', [FlightSearchController::class, 'one_way_searchv5']);


        Route::post('/hold-pnr', function (Request $request) {
            // foreach (\App\Models\FlightAvailablity::all() as $av) {
            //     $av->carrier = $av->flight_schedule->iata;
            //     $av->save();
            // }

            # artisan('horizon:pause');
            $result = null;
            try {
                $pnrs = [];
                $message = "pnr_holded";

                $adults = $request->passengers['adults'] ?? 0;
                $children = $request->passengers['children'] ?? 0;
                $infants = $request->passengers['infants'] ?? 0;
                $seated_infants = $request->passengers['seated_infants'] ?? 0;

                $passengers_command_segment = "";

                $letter = "A";

                for ($i = 0; $i < $adults; $i++) {
                    $passengers_command_segment .= $letter . '#/';
                    $letter++;
                }
                for ($i = 0; $i < $children; $i++) {
                    $passengers_command_segment .= $letter . '#.CH10/';
                    $letter++;
                }
                for ($i = 0; $i < $infants; $i++) {
                    $passengers_command_segment .= $letter . '#.IN06/';
                    $letter++;
                }


                $hold_pnr_command = "";

                foreach ($request->passengers as $passenger) {
                    //    switch ($passenger["type"]) {
                    //        case "adult":
                    //            $hold_pnr_command .= "-" . $passenger['id'] . $passenger['last_name'] . "/" . $passenger['first_name'] . '#/';
                    //            break;
                    //        case "child":
                    //            $hold_pnr_command .= "-" . $passenger['id'] . $passenger['last_name'] . "/" . $passenger['first_name'] . '#.CH10/';
                    //            break;
                    //        case "infant":
                    //            $hold_pnr_command .= "-" . $passenger['id'] . $passenger['last_name'] . "/" . $passenger['first_name'] . '#.IN06/';
                    //            break;
                    //    }

                    switch ($passenger["type"]) {
                        case "adult":
                            $hold_pnr_command .= "-1" . $passenger['last_name'] . "/" . $passenger['first_name'] . '#/';
                            break;
                        case "child":
                            $hold_pnr_command .= "-1" . $passenger['last_name'] . "/" . $passenger['first_name'] . '#.CH10/';
                            break;
                        case "infant":
                            $hold_pnr_command .= "-1" . $passenger['last_name'] . "/" . $passenger['first_name'] . '#.IN06/';
                            break;
                    }
                }

                // foreach ($request->passengers as $passenger) {
                //     if ($passenger['is_primary_contact']) {
                //         $hold_pnr_command .= "^9-" . $passenger['id'] . "M*" . $passenger['phone'];
                //     }
                // }

                $hold_pnr_command .= "^9-1" . "M*" . $request->contact['phone'];

                // $hold_pnr_command = substr($hold_pnr_command, 0, -1);

                $count_passengers = count($request->passengers);

                $offer_ids = collect($request->offers)->pluck('id');
                $availabilities = \App\Models\FlightAvailablity::whereIn('id', $offer_ids)->get();
                foreach ($availabilities->groupBy('carrier') as $carrier => $avs) {
                    $aero_token = \App\Models\AeroToken::where('iata', $carrier)->first();

                    $command = $hold_pnr_command;

                    foreach ($avs as $availability) {
                        $command .= "^0" . $availability->flight_schedule->flight_number . $availability->class . date('dM', strtotime($availability->flight_schedule->departure)) . $availability->flight_schedule->origin . $availability->flight_schedule->destination . "NN" . $count_passengers;
                    }

                    $command = "I^" . $command . "^FG^FS1^E*R~X";

                    $response = $aero_token->build()->runCommand($command);

                    $result = $response?->response;

                    if ($result != null) {
                        if ($result == 'ERROR - no fare available') {
                            $message = \App\Enum\VidecomError::NO_FARE_AVAILABLE;
                        } else {

                            $xml = "";

                            $xml = "<xml>" . $result . "</xml>";

                            $xmlObject = simplexml_load_string($xml);

                            $pax_taxes = [];

                            $pnr = [
                                'iata' => $aero_token->iata,
                                'type' => 'hold',
                                'rloc' => $xmlObject->PNR->attributes('', true)->RLOC . "",
                                'is_pnr_locked' => $xmlObject->PNR->attributes('', true)->PNRLocked . "",
                                'is_pnr_edittable' => $xmlObject->PNR->attributes('', true)->editPNR . "",
                                'is_voidable' => $xmlObject->PNR->attributes('', true)->CanVoid . "",

                                'total_price' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Total,
                                'currency' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Cur,
                                'fare_qoute' => [
                                    'currency' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Cur,
                                    'fqi' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->FQI,
                                    'price' => [
                                        'total' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Total,
                                        'fare' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Fare,
                                        'tax1' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Tax1,
                                        'tax2' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Tax2,
                                        'tax3' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Tax3,
                                    ],
                                    'taxes' => $pax_taxes,
                                ],
                                'fare_store' => [
                                    'miles' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->miles,
                                    'hold_pices' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->HoldPcs,
                                    'hold_wieght' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->HoldWt,
                                    'hand_wieght' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->HandWt,
                                ],
                                'itinerary' => [
                                    'line' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Line . "",
                                    'airline_id' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->AirID . "",
                                    'flight_number' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->FltNo . "",
                                    'class' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Class . "",
                                    'departure' => [
                                        'iataCode' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Depart . "",
                                        'terminal' => '',
                                        'at' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->DepDate . " " . $xmlObject->PNR->Itinerary->Itin->attributes('', true)->DepTime,
                                    ],
                                    'arrival' => [
                                        'iataCode' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Arrive . "",
                                        'terminal' => '',
                                        'at' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->DepDate . " " . $xmlObject->PNR->Itinerary->Itin->attributes('', true)->ArrTime,
                                    ],
                                    'status' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Status . "",
                                    'number_of_passengers' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->PaxQty . "",
                                    // 'ArrOfst' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->ArrOfst,
                                    'number_of_stops' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Stops . "",
                                    'cabine' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Cabin . "",
                                    'class_band' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->ClassBand . "",
                                    'class_band_display_name' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->ClassBandDisplayName . "",
                                    'online_checkin' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->onlineCheckin . "",
                                    'select_seat' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->SelectSeat . "",
                                    'mmb_select_seat' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->MMBSelectSeat . "",
                                    'is_online_checkin_allowed' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->MMBCheckinAllowed . "",
                                ],

                                'time_limits' => [

                                ],
                            ];


                            if (isset($xmlObject->PNR->TimeLimits->TTL)) {
                                $pnr['time_limits'] = [
                                    'ttl_id' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->TTLID . "",
                                    'ttl_city' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->TTLCity . "",
                                    'ttl_queue_number' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->TTLQNo . "",
                                    'ttl_time' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->TTLTime . "",
                                    'ttl_date' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->TTLDate . "",
                                    'age_city' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->AgCity . "",
                                    'sine_code' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->SineCode . "",
                                    'sine_type' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->SineType . "",
                                    'reservation_at' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->ResDate . "",
                                ];
                            }

                            $pnrs[] = $pnr;

                        }
                    }
                }


                return response()->json([
                    'message' => $message,
                    'pnrs' => $pnrs,
                ]);
            } catch (Exception $ex) {

            } finally {
                # artisan('horizon:continue');
            }
        });

        Route::post('/issue-pnr', function (Request $request) {
            // foreach (\App\Models\FlightAvailablity::all() as $av) {
            //     $av->carrier = $av->flight_schedule->iata;
            //     $av->save();
            // }

            # artisan('horizon:pause');
            $result = null;
            try {


                $adults = $request->passengers['adults'] ?? 0;
                $children = $request->passengers['children'] ?? 0;
                $infants = $request->passengers['infants'] ?? 0;
                $seated_infants = $request->passengers['seated_infants'] ?? 0;

                $passengers_command_segment = "";

                $letter = "A";

                for ($i = 0; $i < $adults; $i++) {
                    $passengers_command_segment .= $letter . '#/';
                    $letter++;
                }
                for ($i = 0; $i < $children; $i++) {
                    $passengers_command_segment .= $letter . '#.CH10/';
                    $letter++;
                }
                for ($i = 0; $i < $infants; $i++) {
                    $passengers_command_segment .= $letter . '#.IN06/';
                    $letter++;
                }


                $hold_pnr_command = "";
                $count_passengers = 0;

                foreach ($request->passengers as $passenger) {
                    switch ($passenger["type"]) {
                        case "adult":
                            $count_passengers++;
                            $hold_pnr_command .= "-1" . $passenger['last_name'] . "/" . $passenger['first_name'] . 'MR^';
                            break;
                        case "child":
                            $count_passengers++;
                            $hold_pnr_command .= "-1" . $passenger['last_name'] . "/" . $passenger['first_name'] . 'MISS.CH10^';
                            break;
                        case "infant":
                            $hold_pnr_command .= "-1" . $passenger['last_name'] . "/" . $passenger['first_name'] . 'MSTR.IN06^';
                            break;
                    }
                }

                // foreach ($request->passengers as $passenger) {
                //     if ($passenger['is_primary_contact']) {
                //         $hold_pnr_command .= "^9-" . $passenger['id'] . "M*" . $passenger['phone'];
                //     }
                // }

                $hold_pnr_command = substr($hold_pnr_command, 0, -1);

                $hold_pnr_command .= "^9-1" . "M*" . $request->contact['phone'];
                $hold_pnr_command .= "^9-1" . "E*" . $request->contact['email'];

                // $count_passengers = count($request->passengers);

                $offer_ids = collect($request->offers)->pluck('id');
                $availabilities = \App\Models\FlightAvailablity::whereIn('id', $offer_ids)->get();

                $pnrs = [];

                foreach ($availabilities->groupBy('carrier') as $carrier => $avs) {
                    $aero_token = \App\Models\AeroToken::where('iata', $carrier)->first();

                    $command = $hold_pnr_command;

                    $avs_type = [];

                    foreach ($avs as $availability) {
                        $avs_type[$availability->class . date('dM', strtotime($availability->flight_schedule->departure)) . $availability->flight_schedule->origin . $availability->flight_schedule->destination] = $availability->is_international;

                        $command .= "^0" . $availability->flight_schedule->flight_number . $availability->class . date('dM', strtotime($availability->flight_schedule->departure)) . $availability->flight_schedule->origin . $availability->flight_schedule->destination . "NN" . $count_passengers;
                    }

                    // $command = "I^" . $command;
                    $command = "I^" . $command;

                    $command .= "^FG";
                    $command .= "^FS1";
                    $command .= "^*R";
                    $command .= "^MI-ABC TOURS01012";
                    $command .= "^EZT*R";
                    $command .= "^EZRE";
                    $command .= "^*R~x";

                    // return $command;
                    $response = $aero_token->build()->runCommand($command);

                    $result = $response?->response;

                    // return [
                    //     'res' => $response,
                    //     'command' => $command,
                    // ];


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

                return response()->json([
                    'pnrs' => $pnrs,
                ]);
            } catch (Exception $ex) {

            } finally {
                # artisan('horizon:continue');
            }
        });

        Route::post('/flight-order', [FlightSearchController::class, 'flight_order']);

        Route::post('/query-pnr', [FlightSearchController::class, 'query_pnr']);

        Route::post('/confirm-order', [FlightSearchController::class, 'confirm_order']);
        Route::post('/void-pnr', [FlightSearchController::class, 'void_pnr']);
        Route::post('/refund-pnr', [FlightSearchController::class, 'refund_pnr']);
        Route::post('/calculate-refund-segment', [FlightSearchController::class, 'calculate_refund_segment']);
        Route::post('/refund-segment', [FlightSearchController::class, 'refund_segment']);
        Route::post('/divide-pnr', [FlightSearchController::class, 'divide_pnr']);
        Route::get('/print-pnr', [FlightSearchController::class, 'print_pnr']);

        Route::post('/price-offer', function (Request $request) {
            // foreach (\App\Models\FlightAvailablity::all() as $av) {
            //     $av->carrier = $av->flight_schedule->iata;
            //     $av->save();
            // }

            # artisan('horizon:pause');
            $status = false;
            $message = "AAA";

            $adults = $request->passengers['adults'] ?? 0;
            $children = $request->passengers['children'] ?? 0;
            $infants = $request->passengers['infants'] ?? 0;
            $seated_infants = $request->passengers['seated_infants'] ?? 0;

            $passengers_command_segment = "";

            $letter = "A";

            for ($i = 0; $i < $adults; $i++) {
                $passengers_command_segment .= $letter . '#/';
                $letter++;
            }
            for ($i = 0; $i < $children; $i++) {
                $passengers_command_segment .= $letter . '#.CH10/';
                $letter++;
            }
            for ($i = 0; $i < $infants; $i++) {
                $passengers_command_segment .= $letter . '#.IN06/';
                $letter++;
            }
            for ($i = 0; $i < $seated_infants; $i++) {
                $passengers_command_segment .= $letter . '#.IS06/';
                $letter++;
            }

            $offers = [];

            $availabilities = \App\Models\FlightAvailablity::whereIn('id', $request->offers)->get();
            foreach ($availabilities->groupBy('carrier') as $carrier => $avs) {
                // foreach ($avs as $availability) {
                $aero_token = \App\Models\AeroToken::where('iata', $carrier)->first();

                $command = "I^-" . ($adults + $children + $infants + $seated_infants) . "Pax/" . $passengers_command_segment;
                foreach ($avs as $availability) {
                    // return $segment->flight_schedule;
                    $command .= "^0" . $availability->flight_schedule->flight_number . $availability->class . date('dM', strtotime($availability->flight_schedule->departure)) . $availability->flight_schedule->origin . $availability->flight_schedule->destination . "QQ" . ($adults + $children + $seated_infants);
                }
                $command .= "^FG^FS1^*r~x";

                // return $command;
                $result = $aero_token->build()->runCommand($command);

                $response = parse_result($result->response);

                if ($response['status'] == 'OK') {
                    $status = true;

                    foreach ($avs as $av) {
                        $from = $av->flight_schedule->origin;
                        $to = $av->flight_schedule->destination;
                        $flight_number = $av->flight_schedule->flight_number;
                        $class = $av->class;

                        $fare = 0;
                        $tax = 0;
                        $price = 0;

                        $currency = "";
                        $hold_pices = "";
                        $hold_weight = "";
                        $hand_weight = "";

                        foreach ($response['segments'] as $index => $segment) {
                            foreach ($segment as $segment_price) {
                                if (
                                    $segment_price['from'] == $from
                                    && $segment_price['to'] == $to

                                    && $segment_price['class'] == $class
                                    && ($segment_price['iata'] . $segment_price['flight_number']) == $flight_number
                                ) {

                                    $fare += $segment_price['fare'];
                                    $tax += $segment_price['tax'];
                                    $price += $segment_price['price'];

                                    $currency = $segment_price['currency'];
                                    $hold_pices = $segment_price['hold_pices'];
                                    $hold_weight = $segment_price['hold_weight'];
                                    $hand_weight = $segment_price['hand_weight'];

                                }
                            }
                            // return $segment;

                        }


                        $offers[] = [
                            'command' => $command,
                            'carrier' => $carrier,
                            'fare_id' => $av->fare_id,
                            'cabin' => $av->cabin,
                            'class' => $av->class,
                            'display_name' => $av->display_name,
                            'flight_schedule_id' => $av->flight_schedule_id,
                            'flight_schedule' => $av->flight_schedule,
                            'id' => $av->id,
                            'name' => $av->name,
                            'seats' => $av->seats,
                            'fare' => $fare,
                            'tax' => $tax,
                            'price' => $price,
                            'currency' => $currency,
                            'hold_pices' => $hold_pices,
                            'hold_weight' => $hold_weight,
                            'hand_weight' => $hand_weight,
                        ];
                    }

                    # Start => Sorting offers as requested
                    $__offers = [];
                    foreach ($request->offers as $requested_offer) {
                        foreach ($offers as $offer) {
                            if ($offer['id'] == $requested_offer) {
                                $__offers[] = $offer;
                            }
                        }
                    }
                    $offers = $__offers;
                    # End => Sorting offers as requested

                } else {
                    $status = false;
                    $message = $response['status'];

                    if ($message == \App\Enum\VidecomError::NO_FLIGHT_AVAILABLE) {
                        // disable flight offer class
                    }
                }
            }

            $result = [
                'status' => $status,
                'message' => $message,
                'data' => $offers
            ];

            return $result;

        });
        // ==>



        Route::get('/destinations', [FlightSearchController::class, 'find_destinations']);
        Route::get('/seat-map', [FlightSearchController::class, 'fetch_seat_map']);

        Route::get('/flight-contacts', [FlightSearchController::class, 'get_flight_contacts']);

        Route::get('/sales-report', [FlightSearchController::class, 'get_sales_report']);

        Route::get('/run-command', [FlightSearchController::class, 'run_command']);

        Route::get('/change-fee-calculation', function (Request $request) {

            // $allowed_to_changed = ['YI', 'UZ', 'FQ', '5S', 'BM', 'YL'];
            
            // if (!in_array(strtoupper($request->iata), $allowed_to_changed)) {
            //     return response()->json([
            //         'status' => false,
            //         'message' => 'CHANGE_DISABLED',
            //     ], 500);
            // }

            $availability = \App\Models\FlightAvailablity::find($request->flight_availability_id);
            $number_of_passengers = $request->passengers;
            // return $availability->flight_schedule;

            // $command = "*" . $request->pnr['rloc'];
            $command = "*" . $request->pnr;
            $command .= "^X" . $request->segment;
            // $command .= "^0" . $availability->flight_schedule->flight_number . $availability->class . date('dM', strtotime($availability->flight_schedule->departure)) . $availability->flight_schedule->origin . $availability->flight_schedule->destination . "NN" . $number_of_passengers;
            if ($request->booking_type == 'open') {
                $command .= "^0" . $availability->flight_schedule->iata . "0000" . $availability->class . date('dM', strtotime($availability->flight_schedule->departure)) . $availability->flight_schedule->origin . $availability->flight_schedule->destination . "QQ" . $number_of_passengers;
            }

            if ($request->booking_type == 'confirm') {
                $command .= "^0" . $availability->flight_schedule->flight_number . $availability->class . date('dM', strtotime($availability->flight_schedule->departure)) . $availability->flight_schedule->origin . $availability->flight_schedule->destination . "NN" . $number_of_passengers;
            }
            $command .= "^FG";
            $command .= "^FS1";
            $command .= "^MB";
            $command .= "^*R~X";

            $result = $availability->flight_schedule->aero_token->build()->runCommand($command);
            // return $result->response;
            $pnr = [
                'cmd' => $command,
                'status' => true,
                'message' => "change_fee_calculated",
                'offers' => [],
                'mps' => [],
                'change_fee' => 0,
                'booking_type' => $request->booking_type,
            ];

            $offers = [];

            if ($result->response != null) {

                if ($result->response == "ERROR - no fare available") {
                    $pnr['status'] = false;
                    $pnr['message'] = \App\Enum\VidecomError::NO_FARE_AVAILABLE;

                    // \App\Jobs\CheckFlightSeatAvailabilityJob::dispatch($availability->flight_schedule)->onQueue('default');

                    return $pnr;
                }

                if (str_contains($result->response, 'SEGMENT ALREADY EXISTS IN THIS PNR')) {
                    $pnr['status'] = false;
                    $pnr['message'] = \App\Enum\VidecomError::SEGMENT_ALREADY_EXISTS;

                    return $pnr;
                }

                $xmlObject = simplexml_load_string("<xml>" . $result->response . "</xml>");
                $response = parse_result($result->response);

                if (str_contains($result->response, 'No Amount Outstanding')) {
                    $pnr['status'] = true;
                    $pnr['message'] = "no_amount";

                    $pnr['change_fee'] = 0;
                    $pnr['currency'] = 'LYD';

                    // $response = cache()->get('*' . $request->pnr . "^*R~x");
                    // $response['status'] = "OK";

                    $result = $availability->flight_schedule->aero_token->build()->runCommand('*' . $request->pnr . "^*R~x");
                    $response = parse_result($result->response);
                    // return $response;
                    // return $pnr;
                }

                if (str_contains( $result->response, 'Amount outstanding LYD')) {
                    $amount = str_replace('Amount outstanding ', '', $result->response);
                    // $amount = str_replace(' LYD', '', $amount);
                    // $amount = "LYD50";
                    $pnr['status'] = true;
                    $pnr['message'] = "change_fee_calculated";
                    $pnr['change_fee'] = (double) $amount;
                    $pnr['currency'] = 'LYD';
                    // $pnr['change_fee'] = str_replace('LYD ', '', $amount);

                    // $cached_pnr = cache()->get('*' . $request->pnr . "^*R~x");
                    // // $pnr['pnr'] =$cached_pnr;
                    // $response['status'] == 'OK'
                    // $re
                    $_cmd = str_replace('^MB', '', $command);
                    $result = $availability->flight_schedule->aero_token->build()->runCommand($_cmd);

                     $xmlObject = simplexml_load_string("<xml>" . $result->response . "</xml>");
                    $response = parse_result($result->response);
                }

                if ($response['status'] == 'OK') {


                    foreach ($response['segments'] as $index => $segment) {

                        $fare = 0;
                        $tax = 0;
                        $price = 0;

                        $currency = "";
                        $hold_pices = "";
                        $hold_weight = "";
                        $hand_weight = "";
                        foreach ($segment as $segment_price) {
                            $fare += $segment_price['fare'];
                            $tax += $segment_price['tax'];
                            $price += $segment_price['price'];

                            $currency = $segment_price['currency'];
                            $hold_pices = $segment_price['hold_pices'];
                            $hold_weight = $segment_price['hold_weight'];
                            $hand_weight = $segment_price['hand_weight'];
                        }
                        // return $segment;
                        $offers[] = [
                            'carrier' => $availability->flight_schedule->iata,
                            'departure' => $availability->flight_schedule->departure,
                            'arrival' => $availability->flight_schedule->arrival,
                            'fare_id' => $availability->fare_id,
                            'cabin' => $availability->cabin,
                            'class' => $availability->class,
                            'display_name' => $availability->display_name,
                            'flight_schedule_id' => $availability->flight_schedule_id,
                            'flight_schedule' => $availability->flight_schedule,
                            'id' => $availability->id,
                            'name' => $availability->name,
                            'seats' => $availability->seats,
                            'fare' => $fare,
                            'tax' => $tax,
                            'price' => $price,
                            'currency' => $currency,
                            'hold_pices' => $hold_pices,
                            'hold_weight' => $hold_weight,
                            'hand_weight' => $hand_weight,
                        ];
                    }

                    if (count($response['segments']) > 0) {
                        $offers = $response['segments'][$request->segment - 1][0];
                    } else {
                        $pnr['status'] = false;
                        $pnr['message'] = "uknown_error";
                        \App\Jobs\CheckFlightSeatAvailabilityJob::dispatch($availability->flight_schedule)->onQueue('default');
                    }
                }



                // Extraxt MPS
                if (is_iterable($xmlObject->PNR->MPS)) {
                    foreach ($xmlObject->PNR->MPS->MP as $mp) {
                        $pnr['mps'][] = [
                            'id' => (string) $mp->attributes()->{'MPID'},
                            'currency' => (string) $mp->attributes()->{'MPSCur'},
                            'amount' => (string) $mp->attributes()->{'MPSAmt'},
                        ];
                    }
                }

                // Extract Basket
                if (isset($xmlObject->PNR?->Basket?->Outstanding)) {
                    $pnr['change_fee'] = (double) $xmlObject->PNR->Basket->Outstanding->attributes()->{'amount'};
                    $pnr['currency'] = (string) $xmlObject->PNR->Basket->Outstanding->attributes()->{'cur'};
                }

                $pnr['offers'] = $offers;

                return $pnr;
            }

        });

        Route::put('/change-pnr', function (Request $request) {

            $availability = \App\Models\FlightAvailablity::find($request->flight_availability_id);

            $number_of_passengers = $request->passengers;

            $command = "*" . $request->pnr;
            $command .= "^X" . $request->segment;
            if ($request->booking_type == 'open') {
                $command .= "^0" . $availability->flight_schedule->iata . "0000" . $availability->class . date('dM', strtotime($availability->flight_schedule->departure)) . $availability->flight_schedule->origin . $availability->flight_schedule->destination . "QQ" . $number_of_passengers;
            }

            if ($request->booking_type == 'confirm') {
                $command .= "^0" . $availability->flight_schedule->flight_number . $availability->class . date('dM', strtotime($availability->flight_schedule->departure)) . $availability->flight_schedule->origin . $availability->flight_schedule->destination . "NN" . $number_of_passengers;
            }

            $command .= "^FG";
            $command .= "^FS1";
            $command .= "^MB";
            if ($request->get('option') == 'reissue') {
                // $command .= "^EZV*R";
                $command .= "^EZV*[E]";
            }
            if ($request->get('with_payment', false)) {
                $command .= "^MI";
            }
            if ($request->get('option') == 'revalidation') {
                $command .= "^REZT*R";
            } else if ($request->get('option') == 'reissue') {
                $command .= "^EZT*R";
            }
            $command .= "^*R~X";

            // return $command;
            $result = $availability->flight_schedule->aero_token->build()->runCommand($command);
            // return $result->response;

            // cache()->forget('*' . $request->pnr['rloc'] . "^*R~x");
            cache()->forget('*' . $request->pnr . "^*R~x");

            $pnr = [
                'cmd' => $command,
                'status' => true,
                'message' => "change_fee_calculated",
                'offers' => [],
                'mps' => [],
                'change_fee' => 0,
            ];

            $offers = [];

            if ($result->response != null) {

                if ($result->response == "ERROR - no fare available") {
                    $pnr['status'] = false;
                    $pnr['message'] = \App\Enum\VidecomError::NO_FARE_AVAILABLE;

                    // \App\Jobs\CheckFlightSeatAvailabilityJob::dispatch($availability->flight_schedule)->onQueue('default');

                    return $pnr;
                }

                if (str_contains($result->response, 'SEGMENT ALREADY EXISTS IN THIS PNR')) {
                    $pnr['status'] = false;
                    $pnr['message'] = "segment_already_exists";

                    return $pnr;
                }

                $xmlObject = simplexml_load_string("<xml>" . $result->response . "</xml>");
                $response = parse_result($result->response);
                
                if (str_contains($result->response, 'No Amount Outstanding')) {
                    $pnr['status'] = true;
                    $pnr['message'] = "no_amount";

                    $pnr['change_fee'] = 0;
                    $pnr['currency'] = 'LYD';

                    // $response = cache()->get('*' . $request->pnr . "^*R~x");
                    // $response['status'] = "OK";

                    $command_without_payment = str_replace('^MB', '', $command);
                    $result = $availability->flight_schedule->aero_token->build()->runCommand($command_without_payment);
                    $response = parse_result($result->response);
                    // return $response;
                    // return $pnr;
                }

              

                if ($response['status'] == 'OK') {


                    foreach ($response['segments'] as $index => $segment) {

                        $fare = 0;
                        $tax = 0;
                        $price = 0;

                        $currency = "";
                        $hold_pices = "";
                        $hold_weight = "";
                        $hand_weight = "";
                        foreach ($segment as $segment_price) {
                            $fare += $segment_price['fare'];
                            $tax += $segment_price['tax'];
                            $price += $segment_price['price'];

                            $currency = $segment_price['currency'];
                            $hold_pices = $segment_price['hold_pices'];
                            $hold_weight = $segment_price['hold_weight'];
                            $hand_weight = $segment_price['hand_weight'];
                        }
                        // return $segment;
                        $offers[] = [
                            'carrier' => $availability->flight_schedule->iata,
                            'departure' => $availability->flight_schedule->departure,
                            'arrival' => $availability->flight_schedule->arrival,
                            'fare_id' => $availability->fare_id,
                            'cabin' => $availability->cabin,
                            'class' => $availability->class,
                            'display_name' => $availability->display_name,
                            'flight_schedule_id' => $availability->flight_schedule_id,
                            'id' => $availability->id,
                            'name' => $availability->name,
                            'seats' => $availability->seats,
                            'fare' => $fare,
                            'tax' => $tax,
                            'price' => $price,
                            'currency' => $currency,
                            'hold_pices' => $hold_pices,
                            'hold_weight' => $hold_weight,
                            'hand_weight' => $hand_weight,
                        ];
                    }

                    if (count($response['segments']) > 0) {
                        $offers = $response['segments'][$request->segment - 1][0];
                    } else {
                        $pnr['status'] = false;
                        $pnr['message'] = "uknown_error";
                        \App\Jobs\CheckFlightSeatAvailabilityJob::dispatch($availability->flight_schedule)->onQueue('default');
                    }
                }



                // Extraxt MPS
                if (is_iterable($xmlObject->PNR->MPS)) {
                    foreach ($xmlObject->PNR->MPS->MP as $mp) {
                        $pnr['mps'][] = [
                            'id' => (string) $mp->attributes()->{'MPID'},
                            'currency' => (string) $mp->attributes()->{'MPSCur'},
                            'amount' => (string) $mp->attributes()->{'MPSAmt'},
                        ];
                    }
                }

                // Extract Basket
                if (isset($xmlObject->PNR?->Basket?->Outstanding)) {
                    $pnr['change_fee'] = (double) $xmlObject->PNR->Basket->Outstanding->attributes()->{'amount'};
                }

                $pnr['offers'] = $offers;

                \App\Jobs\CheckFlightSeatAvailabilityJob::dispatch($availability->flight_schedule)->onQueue('default');
                dispatch(new \App\Jobs\UpdateAeroTokenInformationJob($availability->flight_schedule->aero_token))->delay(now()->addMinutes(15));

                return $pnr;


                // $refund_result = $aero_token->build()->runCommand($refund_segment_command . "^REF*^RI" . $refund_amount . "*R~X");

                // $refundXmlObject = simplexml_load_string("<xml>" . $refund_result->response . "</xml>");

                // $pnr = $this->parse_pnr($refundXmlObject);

                // return response()->json($pnr, 202);

            }

        });


        # V2 UPDATED

        Route::post('/price-offerv2', function (Request $request) {
            $status = false;
            $message = "AAA";

            $adults = $request->passengers['adults'] ?? 0;
            $children = $request->passengers['children'] ?? 0;
            $infants = $request->passengers['infants'] ?? 0;
            $seated_infants = $request->passengers['seated_infants'] ?? 0;

            $passengers_command_segment = "";

            $letter = "A";

            for ($i = 0; $i < $adults; $i++) {
                $passengers_command_segment .= $letter . '#/';
                $letter++;
            }
            for ($i = 0; $i < $children; $i++) {
                $passengers_command_segment .= $letter . '#.CH10/';
                $letter++;
            }
            for ($i = 0; $i < $infants; $i++) {
                $passengers_command_segment .= $letter . '#.IN06/';
                $letter++;
            }
            for ($i = 0; $i < $seated_infants; $i++) {
                $passengers_command_segment .= $letter . '#.IS06/';
                $letter++;
            }

            $offers = [];

            // $availabilities = \App\Models\FlightAvailablity::whereIn('id', $request->offers)->get();
            // $availabilities = collect();
            $offer_ids = collect($request->offers)->pluck('id');

            $availabilities = \App\Models\FlightAvailablity::with('flight_schedule')->whereIn('id', $offer_ids)->get();
            foreach ($availabilities->groupBy('aero_token_id') as $aero_token_id => $avs) {
                // foreach ($avs as $availability) {
                $aero_token = \App\Models\AeroToken::where('id', $aero_token_id)->first();

                $command = "I^-" . ($adults + $children + $infants + $seated_infants) . "Pax/" . $passengers_command_segment;
                foreach ($avs as $availability) {

                    $booking_type = "NN";
                    foreach ($request->offers as $requested_offer) {
                        if ($requested_offer['id'] == $availability->id) {
                            if ($requested_offer['booking_type'] == 'confirm') {
                                $booking_type = "NN";
                            }
                            if ($requested_offer['booking_type'] == 'open') {
                                $booking_type = "QQ";
                            }
                            if ($requested_offer['booking_type'] == 'waitlist') {
                                $booking_type = "LL";
                            }
                        }
                    }

                    if ($booking_type == 'QQ') {
                        // $flight_number = preg_replace('/[0-9]+/', '', $availability->flight_schedule->flight_number);
                        $command .= "^0" . $availability->flight_schedule->iata . '000' . $availability->class . date('dM', strtotime($availability->flight_schedule->departure)) . $availability->flight_schedule->origin . $availability->flight_schedule->destination . $booking_type . ($adults + $children + $seated_infants);
                    } else {
                        $command .= "^0" . $availability->flight_schedule->flight_number . $availability->class . date('dM', strtotime($availability->flight_schedule->departure)) . $availability->flight_schedule->origin . $availability->flight_schedule->destination . $booking_type . ($adults + $children + $seated_infants);
                    }

                    // $command .= "^0" . $availability->flight_schedule->flight_number . $availability->class . date('dM', strtotime($availability->flight_schedule->departure)) . $availability->flight_schedule->origin . $availability->flight_schedule->destination . $booking_type . ($adults + $children + $seated_infants);
                }
                $command .= "^FG^FS1^*r~x";

                // return $command;
                $result = $aero_token->build()->runCommand($command);

                $response = parse_result($result->response);

                if ($response['status'] == 'OK') {
                    $status = true;

                    foreach ($avs as $av) {
                        $from = $av->flight_schedule->origin;
                        $to = $av->flight_schedule->destination;
                        $flight_number = $av->flight_schedule->flight_number;
                        $class = $av->class;

                        $fare = 0;
                        $tax = 0;
                        $price = 0;

                        $currency = "";
                        $hold_pices = "";
                        $hold_weight = "";
                        $hand_weight = "";
                        $booking_type = "";

                        foreach ($response['segments'] as $segment) {
                            foreach ($segment as $segment_price) {
                                if (
                                    $segment_price['from'] == $from
                                    && $segment_price['to'] == $to

                                    && $segment_price['class'] == $class
                                    // && ($segment_price['iata'] . $segment_price['flight_number']) == $flight_number
                                ) {

                                    $fare += $segment_price['fare'];
                                    $tax += $segment_price['tax'];
                                    $price += $segment_price['price'];

                                    $currency = $segment_price['currency'];
                                    $hold_pices = $segment_price['hold_pices'];
                                    $hold_weight = $segment_price['hold_weight'];
                                    $hand_weight = $segment_price['hand_weight'];

                                    $booking_type = $segment_price['booking_type'];

                                }
                            }
                            // return $segment;

                        }


                        $offers[] = [
                            'command' => $command,
                            'carrier' => $av->flight_schedule->iata,
                            'fare_id' => $av->fare_id,
                            'cabin' => $av->cabin,
                            'class' => $av->class,
                            'display_name' => $av->display_name,
                            'flight_schedule_id' => $av->flight_schedule_id,
                            'flight_schedule' => $av->flight_schedule,
                            'id' => $av->id,
                            'name' => $av->name,
                            'seats' => $av->seats,
                            'fare' => $fare,
                            'tax' => $tax,
                            'price' => $price,
                            'currency' => $currency,
                            'hold_pices' => $hold_pices,
                            'hold_weight' => $hold_weight,
                            'hand_weight' => $hand_weight,
                            'booking_type' => $booking_type,
                        ];
                    }

                    # Start => Sorting offers as requested
                    $__offers = [];
                    foreach ($request->offers as $requested_offer) {
                        foreach ($offers as $offer) {
                            if ($offer['id'] == $requested_offer['id']) {
                                $__offers[] = $offer;
                            }
                        }
                    }
                    $offers = $__offers;
                    # End => Sorting offers as requested

                } else {
                    $status = false;
                    $message = $response['status'];

                    if ($message == \App\Enum\VidecomError::NO_FLIGHT_AVAILABLE) {
                        // disable flight offer class
                    }
                }
            }

            $result = [
                'status' => $status,
                'message' => $message,
                'data' => $offers,
            ];

            return $result;

        });

        Route::post('/hold-pnrv2', function (Request $request) {
            // foreach (\App\Models\FlightAvailablity::all() as $av) {
            //     $av->carrier = $av->flight_schedule->iata;
            //     $av->save();
            // }

            # artisan('horizon:pause');
            $pnrs = [];
            $message = "pnr_holded";

            $adults = $request->passengers['adults'] ?? 0;
            $children = $request->passengers['children'] ?? 0;
            $infants = $request->passengers['infants'] ?? 0;
            $seated_infants = $request->passengers['seated_infants'] ?? 0;

            $passengers_command_segment = "";

            $letter = "A";

            for ($i = 0; $i < $adults; $i++) {
                $passengers_command_segment .= $letter . '#/';
                $letter++;
            }
            for ($i = 0; $i < $children; $i++) {
                $passengers_command_segment .= $letter . '#.CH10/';
                $letter++;
            }
            for ($i = 0; $i < $infants; $i++) {
                $passengers_command_segment .= $letter . '#.IN06/';
                $letter++;
            }


            $hold_pnr_command = "";
            $count_passengers = 0;
            foreach ($request->passengers as $passenger) {
                //    switch ($passenger["type"]) {
                //        case "adult":
                //            $hold_pnr_command .= "-" . $passenger['id'] . $passenger['last_name'] . "/" . $passenger['first_name'] . '#/';
                //            break;
                //        case "child":
                //            $hold_pnr_command .= "-" . $passenger['id'] . $passenger['last_name'] . "/" . $passenger['first_name'] . '#.CH10/';
                //            break;
                //        case "infant":
                //            $hold_pnr_command .= "-" . $passenger['id'] . $passenger['last_name'] . "/" . $passenger['first_name'] . '#.IN06/';
                //            break;
                //    }


                switch ($passenger["type"]) {
                    case "adult":
                        $count_passengers++;
                        $hold_pnr_command .= "-1" . $passenger['last_name'] . "/" . $passenger['first_name'] . 'MR^';
                        break;
                    case "child":
                        $count_passengers++;
                        $hold_pnr_command .= "-1" . $passenger['last_name'] . "/" . $passenger['first_name'] . 'MISS.CH10^';
                        break;
                    case "infant":
                        $hold_pnr_command .= "-1" . $passenger['last_name'] . "/" . $passenger['first_name'] . 'MSTR.IN06^';
                        break;
                }
            }

            $passport_command = "";
            foreach ($request->passengers as $passenger) {
                if (isset($passenger['passport_number'])) {
                    $passport_expire = $passenger['passport_expire']['year'] . '-' . $passenger['passport_expire']['month'] . '-' . $passenger['passport_expire']['day'];
                    $date_of_birth = $passenger['date_of_birth']['year'] . '-' . $passenger['date_of_birth']['month'] . '-' . $passenger['date_of_birth']['day'];

                    $gender = titleToGender($passenger['title']);

                    $passport_command .= "4-" . $passenger['index'] . "FDOCS/P/" . $passenger['passport_issue'] .
                        "/" . $passenger['passport_number'] . "/" . $passenger['nationality'] .
                        "/" . date('dMy', strtotime($date_of_birth)) . "/" . $gender[0] .
                        "/" . date('dMy', strtotime($passport_expire)) .
                        "/" . $passenger['last_name'] . "/" . $passenger['first_name'] .
                        "/" . $passenger['middle_name'] . "^";
                }
            }

            $visa_command = "";
            foreach ($request->passengers as $passenger) {
                if (isset($passenger['visa_number'])) {
                    $visa_expire_date = $passenger['visa_expire']['year'] . '-' . $passenger['visa_expire']['month'] . '-' . $passenger['visa_expire']['day'];
                    $visa_issue_date = $passenger['visa_issue_date']['year'] . '-' . $passenger['visa_issue_date']['month'] . '-' . $passenger['visa_issue_date']['day'];

                    $gender = titleToGender($passenger['title']);

                    $visa_command .= "4-" . $passenger['index'] . "FDOCO//V/" . $passenger['visa_number'] .
                        "/" . $passenger['nationality'] . "/" . date('dMy', strtotime($visa_issue_date)) .
                        "/" . $passenger['visa_issuer'] . "//" . date('dMy', strtotime($visa_expire_date)) .
                        "^";
                }
            }

            $hold_pnr_command = substr($hold_pnr_command, 0, -1);
            // foreach ($request->passengers as $passenger) {
            //     if ($passenger['is_primary_contact']) {
            //         $hold_pnr_command .= "^9-" . $passenger['id'] . "M*" . $passenger['phone'];
            //     }
            // }

            $hold_pnr_command .= "^9-1" . "M*" . $request->contact['phone'];

            // $hold_pnr_command = substr($hold_pnr_command, 0, -1);

            // $count_passengers = count($request->passengers);

            $offer_ids = collect($request->offers)->pluck('id');
            $availabilities = \App\Models\FlightAvailablity::whereIn('id', $offer_ids)->get();
            // foreach ($availabilities->groupBy('carrier') as $carrier => $avs) {
            foreach ($availabilities->groupBy('aero_token_id') as $aero_token_id => $avs) {
                // $aero_token = \App\Models\AeroToken::where('iata', $carrier)->first();
                $aero_token = \App\Models\AeroToken::where('id', $aero_token_id)->first();

                $command = $hold_pnr_command;

                foreach ($avs as $availability) {

                    $booking_type = "NN";
                    foreach ($request->offers as $requested_offer) {
                        if ($requested_offer['id'] == $availability->id) {
                            if ($requested_offer['booking_type'] == 'confirm') {
                                $booking_type = "NN";
                            }
                            if ($requested_offer['booking_type'] == 'open') {
                                $booking_type = "QQ";
                            }
                            if ($requested_offer['booking_type'] == 'waitlist') {
                                $booking_type = "LL";
                            }
                        }
                    }

                    // $command .= "^0" . $availability->flight_schedule->flight_number . $availability->class . date('dM', strtotime($availability->flight_schedule->departure)) . $availability->flight_schedule->origin . $availability->flight_schedule->destination . $booking_type . $count_passengers;
                    if ($booking_type == 'QQ') {
                        // $flight_number = preg_replace('/[0-9]+/', '', $availability->flight_schedule->flight_number);
                        $command .= "^0" . $availability->flight_schedule->iata . '0' . $availability->class . date('dM', strtotime($availability->flight_schedule->departure)) . $availability->flight_schedule->origin . $availability->flight_schedule->destination . $booking_type . $count_passengers;
                    } else {
                        $command .= "^0" . $availability->flight_schedule->flight_number . $availability->class . date('dM', strtotime($availability->flight_schedule->departure)) . $availability->flight_schedule->origin . $availability->flight_schedule->destination . $booking_type . $count_passengers;
                    }
                }


                $command = "I^" . $command . '^';

                $command .= $passport_command;
                $command .= $visa_command;

                $command .= "FG^FS1^E*R~X";

                // return $command;

                $response = $aero_token->build()->runCommand($command);

                $result = $response?->response;


                if ($result != null) {
                    if ($result == 'ERROR - no fare available') {
                        $message = \App\Enum\VidecomError::NO_FARE_AVAILABLE;
                    } else {

                        $xml = "";

                        $xml = "<xml>" . $result . "</xml>";

                        $xmlObject = simplexml_load_string($xml);

                        $pax_taxes = [];
                        // foreach ($xmlObject->PNR->FareQuote->FareTax->PaxTax as $tax) {
                        //     $pax_taxes[] = [
                        //         'segment_id' => $tax->attributes('', true)->Seg . '',
                        //         'pax_id' => $tax->attributes('', true)->Pax . '',
                        //         'code' => $tax->attributes('', true)->Code . '',
                        //         'currency' => $tax->attributes('', true)->Cur . '',
                        //         'amount' => $tax->attributes('', true)->Amnt . '',
                        //         'description' => $tax->attributes('', true)->desc . '',
                        //         'separate' => $tax->attributes('', true)->separate . '',
                        //     ];
                        // }

                        $pnr_total_fare = 0;
                        $pnr_total_tax = 0;
                        $pnr_total_price = 0;
                        if (is_iterable($xmlObject->PNR->FareQuote)) {
                            foreach ($xmlObject->PNR->FareQuote as $fare_qoute) {
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

                                                $pnr_total_fare += $row['fare'];
                                                $pnr_total_tax += ($row['tax1'] + $row['tax2'] + $row['tax3']);
                                                $pnr_total_price += ($row['fare'] + ($row['tax1'] + $row['tax2'] + $row['tax3']));
                                            }

                                            // $pnr['fare_store'][] = [
                                            //     'pax_id' => $fare_store->attributes('', true)->Pax . '',
                                            //     // 'segment_id' => $fq_itin->attributes('', true)->Seg . '',
                                            //     'currency' => $fq_itin->attributes('', true)->Cur . '',
                                            //     'fare' => $total_fare,
                                            //     'tax' => $total_tax,
                                            //     'total' => (double) $fare_store->attributes('', true)->Total,
                                            //     'segments' => $fare_store_segments,
                                            // ];
                                        }
                                    }
                                }
                            }
                        }

                        $holded = cache()->get('holded_pnrs', []);
                        $holded_pnr = [
                            'iata' => $aero_token->iata,
                            'rloc' => $xmlObject->PNR->attributes('', true)->RLOC . "",
                            'created_at' => now(),
                        ];

                        $holded[] = $holded_pnr;

                        cache()->put('holded_pnrs', $holded);

                        // $pnr = [
                        //     'iata' => $aero_token->iata,
                        //     'type' => 'hold',
                        //     'rloc' => $xmlObject->PNR->attributes('', true)->RLOC . "",
                        //     'is_pnr_locked' => $xmlObject->PNR->attributes('', true)->PNRLocked . "",
                        //     'is_pnr_edittable' => $xmlObject->PNR->attributes('', true)->editPNR . "",
                        //     'is_voidable' => $xmlObject->PNR->attributes('', true)->CanVoid . "",

                        //     // 'total_price' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Total,
                        //     'total_price' => $pnr_total_price,
                        //     'total_tax' => $pnr_total_tax,
                        //     'total_fare' => $pnr_total_fare,
                        //     'currency' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Cur,
                        //     'fare_qoute' => [
                        //         'currency' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Cur,
                        //         'fqi' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->FQI,
                        //         'price' => [
                        //             'total' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Total,
                        //             'fare' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Fare,
                        //             'tax1' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Tax1,
                        //             'tax2' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Tax2,
                        //             'tax3' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Tax3,
                        //         ],
                        //         'taxes' => $pax_taxes,
                        //     ],
                        //     'fare_store' => [
                        //         'miles' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->miles,
                        //         'hold_pices' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->HoldPcs,
                        //         'hold_wieght' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->HoldWt,
                        //         'hand_wieght' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->HandWt,
                        //     ],
                        //     'itinerary' => [
                        //         'line' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Line . "",
                        //         'airline_id' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->AirID . "",
                        //         'flight_number' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->FltNo . "",
                        //         'class' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Class . "",
                        //         'departure' => [
                        //             'iataCode' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Depart . "",
                        //             'terminal' => '',
                        //             'at' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->DepDate . " " . $xmlObject->PNR->Itinerary->Itin->attributes('', true)->DepTime,
                        //         ],
                        //         'arrival' => [
                        //             'iataCode' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Arrive . "",
                        //             'terminal' => '',
                        //             'at' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->DepDate . " " . $xmlObject->PNR->Itinerary->Itin->attributes('', true)->ArrTime,
                        //         ],
                        //         'status' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Status . "",
                        //         'number_of_passengers' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->PaxQty . "",
                        //         // 'ArrOfst' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->ArrOfst,
                        //         'number_of_stops' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Stops . "",
                        //         'cabine' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Cabin . "",
                        //         'class_band' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->ClassBand . "",
                        //         'class_band_display_name' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->ClassBandDisplayName . "",
                        //         'online_checkin' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->onlineCheckin . "",
                        //         'select_seat' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->SelectSeat . "",
                        //         'mmb_select_seat' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->MMBSelectSeat . "",
                        //         'is_online_checkin_allowed' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->MMBCheckinAllowed . "",
                        //     ],

                        //     'time_limits' => [

                        //     ],
                        // ];

                        $pnrs[] = parse_pnr($xmlObject);

                        // $pnr = parse_pnr($xmlObject);

                        // if (isset($xmlObject->PNR->TimeLimits->TTL)) {
                        //     $pnr['time_limits'] = [
                        //         'ttl_id' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->TTLID . "",
                        //         'ttl_city' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->TTLCity . "",
                        //         'ttl_queue_number' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->TTLQNo . "",
                        //         'ttl_time' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->TTLTime . "",
                        //         'ttl_date' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->TTLDate . "",
                        //         'age_city' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->AgCity . "",
                        //         'sine_code' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->SineCode . "",
                        //         'sine_type' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->SineType . "",
                        //         'reservation_at' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->ResDate . "",
                        //     ];
                        // }

                        // $pnrs[] = $pnr;

                    }
                }
            }


            return response()->json([
                'message' => $message,
                'pnrs' => $pnrs,
            ]);
        });

        Route::post('/issue-pnrv2', function (Request $request) {
            // foreach (\App\Models\FlightAvailablity::all() as $av) {
            //     $av->carrier = $av->flight_schedule->iata;
            //     $av->save();
            // }

            # artisan('horizon:pause');
            $result = null;
            $pnrs = [];
            try {


                $adults = $request->passengers['adults'] ?? 0;
                $children = $request->passengers['children'] ?? 0;
                $infants = $request->passengers['infants'] ?? 0;
                $seated_infants = $request->passengers['seated_infants'] ?? 0;

                $passengers_command_segment = "";

                $letter = "A";

                for ($i = 0; $i < $adults; $i++) {
                    $passengers_command_segment .= $letter . '#/';
                    $letter++;
                }
                for ($i = 0; $i < $children; $i++) {
                    $passengers_command_segment .= $letter . '#.CH10/';
                    $letter++;
                }
                for ($i = 0; $i < $infants; $i++) {
                    $passengers_command_segment .= $letter . '#.IN06/';
                    $letter++;
                }


                $hold_pnr_command = "";
                $count_passengers = 0;

                # Old one, updated fixing the passenger title and age

                // foreach ($request->passengers as $passenger) {
                //     switch ($passenger["type"]) {
                //         case "adult":
                //             $count_passengers++;
                //             $hold_pnr_command .= "-1" . $passenger['last_name'] . "/" . $passenger['first_name'] . 'MR^';
                //             break;
                //         case "child":
                //             $count_passengers++;
                //             $hold_pnr_command .= "-1" . $passenger['last_name'] . "/" . $passenger['first_name'] . 'MISS.CH10^';
                //             break;
                //         case "infant":
                //             $hold_pnr_command .= "-1" . $passenger['last_name'] . "/" . $passenger['first_name'] . 'MSTR.IN06^';
                //             break;
                //     }
                // }

                 foreach ($request->passengers as $passenger) {
                    switch ($passenger["type"]) {
                        case "adult":
                            $count_passengers++;
                            $hold_pnr_command .= "-1" . $passenger['last_name'] . "/" . $passenger['first_name'] . $passenger['title'] . '^';
                            break;
                        case "child":
                            $count_passengers++;

                            $date_of_birth = date('Y-m-d', strtotime($passenger['date_of_birth']['year'] . '-' . $passenger['date_of_birth']['month'] . '-' . $passenger['date_of_birth']['day']));
                            // get child age depends on the date birth and today date
                            $age = \Carbon\Carbon::parse($date_of_birth)->diffInYears(\Carbon\Carbon::now());

                            $hold_pnr_command .= "-1" . $passenger['last_name'] . "/" . $passenger['first_name'] . $passenger['title'] . '.CH' . $age . '^';
                            break;
                        case "infant":

                             $date_of_birth = date('Y-m-d', strtotime($passenger['date_of_birth']['year'] . '-' . $passenger['date_of_birth']['month'] . '-' . $passenger['date_of_birth']['day']));
                            // get child age of months depends on the date birth and today date
                            $age = \Carbon\Carbon::parse($date_of_birth)->diffInMonths(\Carbon\Carbon::now());
                            // Make sure that the age is two digits, ex if the age is 3 months then age = 03

                            if ($age < 10) {
                                $age = '0' . $age;
                            }
                            
                            $hold_pnr_command .= "-1" . $passenger['last_name'] . "/" . $passenger['first_name'] . $passenger['title']  . '.IN' . $age . '^';
                            break;
                    }
                }

                $passport_command = "";
                foreach ($request->passengers as $passenger) {
                    if (isset($passenger['passport_number'])) {
                        $passport_expire = $passenger['passport_expire']['year'] . '-' . $passenger['passport_expire']['month'] . '-' . $passenger['passport_expire']['day'];
                        $date_of_birth = $passenger['date_of_birth']['year'] . '-' . $passenger['date_of_birth']['month'] . '-' . $passenger['date_of_birth']['day'];

                        $gender = titleToGender($passenger['title']);

                        $passport_command .= "4-" . $passenger['index'] . "FDOCS/P/" . $passenger['passport_issue'] .
                            "/" . $passenger['passport_number'] . "/" . $passenger['nationality'] .
                            "/" . date('dMy', strtotime($date_of_birth)) . "/" . $gender[0] .
                            "/" . date('dMy', strtotime($passport_expire)) .
                            "/" . $passenger['last_name'] . "/" . $passenger['first_name'] .
                            "/" . $passenger['middle_name'] . "^";
                    }
                }

                $visa_command = "";
                foreach ($request->passengers as $passenger) {
                    if (isset($passenger['visa_number'])) {
                        $visa_expire_date = $passenger['visa_expire']['year'] . '-' . $passenger['visa_expire']['month'] . '-' . $passenger['visa_expire']['day'];
                        $visa_issue_date = $passenger['visa_issue_date']['year'] . '-' . $passenger['visa_issue_date']['month'] . '-' . $passenger['visa_issue_date']['day'];

                        $gender = titleToGender($passenger['title']);

                        $visa_command .= "4-" . $passenger['index'] . "FDOCO//V/" . $passenger['visa_number'] .
                            "/" . $passenger['nationality'] . "/" . date('dMy', strtotime($visa_issue_date)) .
                            "/" . $passenger['visa_issuer'] . "//" . date('dMy', strtotime($visa_expire_date)) .
                            "^";
                    }
                }

                // foreach ($request->passengers as $passenger) {
                //     if ($passenger['is_primary_contact']) {
                //         $hold_pnr_command .= "^9-" . $passenger['id'] . "M*" . $passenger['phone'];
                //     }
                // }

                $hold_pnr_command = substr($hold_pnr_command, 0, -1);

                $hold_pnr_command .= "^9-1" . "M*" . $request->contact['phone'];
                $hold_pnr_command .= "^9-1" . "E*" . $request->contact['email'];

                // $count_passengers = count($request->passengers);

                $offer_ids = collect($request->offers)->pluck('id');
                // $availabilities = \App\Models\FlightAvailablity::whereIn('id', $offer_ids)->get();
                $availabilities = \App\Models\FlightAvailablity::with('flight_schedule')->whereIn('id', $offer_ids)->get();
                // foreach ($availabilities->groupBy('carrier') as $carrier => $avs) {
                foreach ($availabilities->groupBy('aero_token_id') as $aero_token_id => $avs) {
                    // $aero_token = \App\Models\AeroToken::where('iata', $carrier)->first();
                    $aero_token = \App\Models\AeroToken::where('id', $aero_token_id)->first();

                    $command = $hold_pnr_command;

                    $avs_type = [];

                    foreach ($avs as $availability) {
                        $avs_type[$availability->class . date('dM', strtotime($availability->flight_schedule->departure)) . $availability->flight_schedule->origin . $availability->flight_schedule->destination] = $availability->is_international;

                        $booking_type = "NN";
                        foreach ($request->offers as $requested_offer) {
                            if ($requested_offer['id'] == $availability->id) {
                                if ($requested_offer['booking_type'] == 'confirm') {
                                    $booking_type = "NN";
                                }
                                if ($requested_offer['booking_type'] == 'open') {
                                    $booking_type = "QQ";
                                }
                                if ($requested_offer['booking_type'] == 'waitlist') {
                                    $booking_type = "LL";
                                }
                            }
                        }

                        if ($booking_type == 'QQ') {
                            // $flight_number = preg_replace('/[0-9]+/', '', $availability->flight_schedule->flight_number);
                            $command .= "^0" . $availability->flight_schedule->iata . '0' . $availability->class . date('dM', strtotime($availability->flight_schedule->departure)) . $availability->flight_schedule->origin . $availability->flight_schedule->destination . $booking_type . $count_passengers;
                        } else {
                            $command .= "^0" . $availability->flight_schedule->flight_number . $availability->class . date('dM', strtotime($availability->flight_schedule->departure)) . $availability->flight_schedule->origin . $availability->flight_schedule->destination . $booking_type . $count_passengers;
                        }
                    }

                    // $command = "I^" . $command;
                    $command = "I^" . $command;

                    $command .= '^' . $passport_command;
                    $command .= $visa_command;

                    // $command .= "^FG";
                    $command .= "FG";
                    $command .= "^FS1";
                    // $command .= "^*R";
                    $command .= "^MI-ABC TOURS01012";
                    $command .= "^EZT*R";
                    $command .= "^EZRE";
                    $command .= "^*R~x";

                    // return $command;
                    $response = $aero_token->build()->runCommand($command);

                    dispatch(new \App\Jobs\UpdateAeroTokenInformationJob($aero_token))->delay(now()->addMinutes(15));

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

                        // $pax_taxes = [];
                        // if (is_array($xmlObject->PNR->FareQuote?->FareTax?->PaxTax) || is_object($xmlObject->PNR->FareQuote?->FareTax?->PaxTax)) {
                        //     foreach ($xmlObject->PNR->FareQuote?->FareTax?->PaxTax as $tax) {
                        //         $pax_taxes[] = [
                        //             'segment_id' => $tax->attributes('', true)->Seg . '',
                        //             'pax_id' => $tax->attributes('', true)->Pax . '',
                        //             'code' => $tax->attributes('', true)->Code . '',
                        //             'currency' => $tax->attributes('', true)->Cur . '',
                        //             'amount' => $tax->attributes('', true)->Amnt . '',
                        //             'description' => $tax->attributes('', true)->desc . '',
                        //             'separate' => $tax->attributes('', true)->separate . '',
                        //         ];

                        //         $tax += (double) $tax->attributes('', true)->Amnt;
                        //     }
                        // }

                        // $itineraries = [];
                        // if (is_iterable($xmlObject->PNR->Itinerary->Itin)) {
                        //     foreach ($xmlObject->PNR->Itinerary->Itin as $itin) {
                        //         // $av_key = $itin->attributes('', true)->Class . "" . date('dM', strtotime($itin->attributes('', true)->DepDate));

                        //         // $is_international = false;
                        //         // if (array_key_exists($av_key, $avs_type)) {
                        //         //     $is_international = $avs_type[$av_key];
                        //         // }

                        //         $itinerary_index = $itin->attributes('', true)->Line - 1;

                        //         $fare_qoute = [
                        //             'segment_id' => $xmlObject->PNR->FareQuote->FQItin[$itinerary_index]->attributes('', true)->Seg . "",
                        //             'total' => $xmlObject->PNR->FareQuote->FQItin[$itinerary_index]->attributes('', true)->Total . "",
                        //             'fare' => $xmlObject->PNR->FareQuote->FQItin[$itinerary_index]->attributes('', true)->Fare . "",
                        //             'tax1' => $xmlObject->PNR->FareQuote->FQItin[$itinerary_index]->attributes('', true)->Tax1 . "",
                        //             'tax2' => $xmlObject->PNR->FareQuote->FQItin[$itinerary_index]->attributes('', true)->Tax2 . "",
                        //             'tax3' => $xmlObject->PNR->FareQuote->FQItin[$itinerary_index]->attributes('', true)->Tax3 . "",
                        //             'currency' => $xmlObject->PNR->FareQuote->FQItin[$itinerary_index]->attributes('', true)->Cur . "",
                        //         ];

                        //         $airport_from = getAirport($itin->attributes('', true)->Depart);
                        //         $airport_to = getAirport($itin->attributes('', true)->Arrive);
                        //         $itineraries[] = [
                        //             'line' => $itin->attributes('', true)->Line . "",
                        //             'airline_id' => $itin->attributes('', true)->AirID . "",
                        //             'is_international' => ($airport_from->country != $airport_to->country),
                        //             'flight_number' => $itin->attributes('', true)->FltNo . "",
                        //             'class' => $itin->attributes('', true)->Class . "",
                        //             'departure' => [
                        //                 'iataCode' => $itin->attributes('', true)->Depart . "",
                        //                 'terminal' => '',
                        //                 'at' => $itin->attributes('', true)->DepDate . " " . $itin->attributes('', true)->DepTime,
                        //             ],
                        //             'arrival' => [
                        //                 'iataCode' => $itin->attributes('', true)->Arrive . "",
                        //                 'terminal' => '',
                        //                 'at' => $itin->attributes('', true)->ArrDate . " " . $itin->attributes('', true)->ArrTime,
                        //             ],
                        //             'fare_qoute' => $fare_qoute,
                        //             'status' => $itin->attributes('', true)->Status . "",
                        //             'number_of_passengers' => $itin->attributes('', true)->PaxQty . "",
                        //             // 'ArrOfst' => $itin->attributes('', true)->ArrOfst,
                        //             'number_of_stops' => $itin->attributes('', true)->Stops . "",
                        //             'cabine' => $itin->attributes('', true)->Cabin . "",
                        //             'class_band' => $itin->attributes('', true)->ClassBand . "",
                        //             'class_band_display_name' => $itin->attributes('', true)->ClassBandDisplayName . "",
                        //             'online_checkin' => $itin->attributes('', true)->onlineCheckin . "",
                        //             'select_seat' => $itin->attributes('', true)->SelectSeat . "",
                        //             'mmb_select_seat' => $itin->attributes('', true)->MMBSelectSeat . "",
                        //             'is_online_checkin_allowed' => $itin->attributes('', true)->MMBCheckinAllowed . "",
                        //         ];
                        //     }
                        // }

                        // $pnr = [
                        //     'iata' => $aero_token->iata,
                        //     'type' => 'pnr',
                        //     'rloc' => $xmlObject->PNR->attributes('', true)->RLOC . "",
                        //     'is_pnr_locked' => $xmlObject->PNR->attributes('', true)->PNRLocked . "",
                        //     'is_pnr_edittable' => $xmlObject->PNR->attributes('', true)->editPNR . "",
                        //     'is_voidable' => $xmlObject->PNR->attributes('', true)->CanVoid . "",

                        //     'total_fare' => $fare,
                        //     'total_tax' => $tax,
                        //     'total_price' => $price,
                        //     // 'total_price' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Total,
                        //     'currency' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Cur,
                        //     'fare_qoute' => [
                        //         'currency' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Cur,
                        //         'fqi' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->FQI,
                        //         'price' => [
                        //             'total' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Total,
                        //             'fare' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Fare,
                        //             'tax1' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Tax1,
                        //             'tax2' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Tax2,
                        //             'tax3' => "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Tax3,
                        //         ],
                        //         'taxes' => $pax_taxes,
                        //     ],
                        //     'fare_store' => [
                        //         'miles' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->miles,
                        //         'hold_pices' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->HoldPcs,
                        //         'hold_wieght' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->HoldWt,
                        //         'hand_wieght' => '' . $xmlObject->PNR->FareQuote->FareStore[0]->SegmentFS->attributes()->HandWt,
                        //     ],
                        //     'itinerary' => $itineraries,

                        //     // 'time_limits' => [
                        //     //     'ttl_id' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->TTLID . "",
                        //     //     'ttl_city' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->TTLCity . "",
                        //     //     'ttl_queue_number' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->TTLQNo . "",
                        //     //     'ttl_time' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->TTLTime . "",
                        //     //     'ttl_date' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->TTLDate . "",
                        //     //     'age_city' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->AgCity . "",
                        //     //     'sine_code' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->SineCode . "",
                        //     //     'sine_type' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->SineType . "",
                        //     //     'reservation_at' => $xmlObject->PNR->TimeLimits->TTL->attributes('', true)->ResDate . "",
                        //     // ],
                        // ];

                        $pnrs[] = parse_pnr($xmlObject);

                        // Subtract seats
                        try {
                            foreach ($avs as $availability) {
                                $availability->seats = $availability->seats - $count_passengers;
                                $availability->save();
                            }
                        } catch (Exception $ex) {

                        }
                    }
                }

                return response()->json([
                    'pnrs' => $pnrs,
                ]);
            } catch (Exception $ex) {

            } finally {
                # artisan('horizon:continue');
            }


            // dispatch(new \App\Jobs\UpdateAeroTokenInformationJob($aero_token));
        });
    });
});