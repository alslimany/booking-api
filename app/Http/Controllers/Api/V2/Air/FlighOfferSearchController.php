<?php

namespace App\Http\Controllers\Api\V2\Air;

use App\Http\Controllers\Controller;
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
         'only' => 'sometimes',
      ]);

      if ($request->filled('return_date')) {
         return $this->round_flight_offers($request);
      } else {
         return $this->oneway_flight_offers($request);
      }
   }

   private function oneway_flight_offers(Request $request)
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
      $destination_irport = getAirport(code: $request->destination_location_code);

      $p_type = [
         'AD' => 'adults',
         'CH' => 'children',
         'IN' => 'infants',
         'IS' => 'seated_infants',
      ];

      $travel_class = $request->travel_class;

      if ($request->get('non_stop', false)) {

         $direct_flight_schedules = \App\Models\FlightSchedule::whereDate('departure', '=', date('Y-m-d', strtotime($request->departure_date)))
            ->where('canceled_at', '=', null)
            ->where('origin', '=', $request->origin_location_code)
            ->where('destination', '=', $request->destination_location_code)
            ->whereHas('availablities', $availablities = function ($q) use ($total_passengers) {
               $q->where('seats', '>', $total_passengers);
            })
            ->whereHas('one_way_offers', $one_way_offers = function ($q) use ($passenger_types, $travel_class) {
               $q->whereIn('passenger_type', $passenger_types)
                  ->whereIn('class', $this->h_getClassCodes($travel_class));
            })
            ->with([
               'one_way_offers' => $one_way_offers
            ])
            ->get();


         foreach ($direct_flight_schedules as $flight_schedule) {

            $dep = \Carbon\Carbon::parse($flight_schedule->departure);
            $arr = \Carbon\Carbon::parse($flight_schedule->arrival);

            $timezone_diff = ($origin_airport->timezone - $destination_irport->timezone) * 60;

            $flight_duration = $dep->diffInMinutes($arr) + $timezone_diff;

            $number_of_bookable_seats = 0;

            foreach ($flight_schedule->availablities()->whereIn('class', $this->h_getClassCodes($travel_class))->where('seats', '>=', $total_passengers)->get() as $availablity) {
               if ($number_of_bookable_seats < $availablity->seats) {
                  $number_of_bookable_seats = $availablity->seats;
               }

               $passenger_index = 1;

               $offer = [
                  'itineraries' => [],
               ];

               $traveler_pricings = [];

               $segments = [];

               $segments[] = [

                  'departure' => [
                     'iataCode' => $flight_schedule->origin,
                     'at' => $flight_schedule->departure,
                  ],
                  'arrival' => [
                     'iataCode' => $flight_schedule->destination,
                     'at' => $flight_schedule->arrival,
                  ],
                  'carrierCode' => $flight_schedule->iata,
                  'carrier' => $flight_schedule->aero_token->only('iata'),
                  "number" => $flight_schedule->flight_number,
                  'aircraft' => findAircraft($flight_schedule->aircraft_code > 0 ? $flight_schedule->aircraft_code : 320),
                  'id' => $flight_schedule->id,
               ];

               $currency = "";
               $total = 0;
               $base = 0;

               $passenger_found = true;
               foreach ($passenger_types as $passenger_type) {

                  // $one_way_offer = $one_way_offers->firstWhere('passenger_type', $passenger_type);
                  $one_way_offer = $flight_schedule->one_way_offers()->where('class', $availablity->class)
                     ->where('passenger_type', $passenger_type)
                     ->first();
                  if ($one_way_offer?->passenger_type != null) {

                     for ($pid = 0; $pid < $request->get($p_type[$one_way_offer->passenger_type]); $pid++) {

                        $pricing = [
                           "traveler_id" => $passenger_index,
                           "fare_option" => "standard",
                           'id' => $availablity->id,
                           // "traveler_type" => $passenger_pricing->passenger_type,
                           "traveler_type" => getPassengerTypeByCode($one_way_offer->passenger_type),
                           "price" => [
                              "currency" => $one_way_offer->currency,
                              "total" => (double) $one_way_offer->price,
                              "base" => (double) $one_way_offer->fare_price
                           ],
                           'fare_details_by_segment' => []
                        ];

                        $pricing['fare_details_by_segment'][] = [
                           'segment_id' => $one_way_offer->flight_schedule_id,
                           'cabin' => getClassNameByCabin($one_way_offer->cabin),
                           'fare_basis' => $one_way_offer->fare_basis,
                           'class' => $one_way_offer->class,
                           'included_checked_bags' => [
                              'weight' => (int) str_replace("K", "", $one_way_offer->hold_weight),
                              'weight_unit' => 'KG',
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
               $offer['traveler_pricings'] = $traveler_pricings;
               $offer['price'] = [
                  "currency" => $currency,
                  "total" => $total,
                  "base" => $base,
                  "grand_total" => $total,

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

               $offer['id'] = $flight_schedule->id;
               $offer['number_of_bookable_seats'] = $number_of_bookable_seats;

               // $offer['ref_uri'] = 'https://flights.booknow.ly/ref/' . sha1(now());
               if ($passenger_found) {
                  ksort($offer, SORT_STRING);
                  $data[] = $offer;
               }

            }

         }
      } else {
         $directions = trip_direction($request->origin_location_code, $request->destination_location_code);

         $route = [];

         $result = [];
         for ($direction_index = 1; $direction_index < count($directions['route']); $direction_index++) {
            $origin = $directions['route'][$direction_index - 1];
            $destination = $directions['route'][$direction_index];

            $result[] = [
               'origin' => $origin,
               'destination' => $destination,
            ];

            $route[$origin . '-' . $destination] = [
               'origin' => $origin,
               'destination' => $destination,
               'flights' => [],
            ];
         }

         $flight_schedule_ids = [];
         for ($i = 0; $i < count($result); $i++) {

            $flights = \App\Models\FlightSchedule::where('origin', $result[$i]['origin'])
               ->where('destination', $result[$i]['destination']);
            if ($i == 0) {
               $flights->whereDate('departure', '=', date('Y-m-d', strtotime($request->departure_date)));
            } else {
               $flights->whereDate('departure', '=', date('Y-m-d', strtotime($request->departure_date)));
            }
            $flights->get();

            $flight_schedule_ids[] = $flights->pluck('id')->toArray();
            foreach ($flights as $flight) {
               $route[$flight->origin . '-' . $flight->destination]['flights'][] = $flight;
            }
         }

         $combiniations = $this->h_combinations($flight_schedule_ids);

         return $route;
         for ($itinerary_index = 0; $itinerary_index < count($route); $itinerary_index++) {

            $offer = [
               'id' => 0,
               'itineraries' => [
                  // 'segments' => [],
               ],
            ];

            $traveler_pricings = [];

            $segments = [];

            $currency = "";
            $total = 0;
            $base = 0;

            // return $combiniations[$itinerary_index];
            for ($combination_id = 0; $combination_id < count($combiniations[$itinerary_index]); $combination_id++) {
               $schedule_id = $combiniations[$itinerary_index][$combination_id];

               $flight_schedule = \App\Models\FlightSchedule::find($schedule_id);

               $dep = \Carbon\Carbon::parse($flight_schedule->departure);
               $arr = \Carbon\Carbon::parse($flight_schedule->arrival);

               $timezone_diff = ($origin_airport->timezone - $destination_irport->timezone) * 60;

               // $flight_duration = $dep->diffInMinutes($arr) + $timezone_diff;
               $flight_duration = 0;

               $number_of_bookable_seats = 0;

               foreach ($flight_schedule->availablities()->whereIn('class', $this->h_getClassCodes($travel_class))->where('seats', '>=', $total_passengers)->get() as $availablity) {
                  if ($number_of_bookable_seats < $availablity->seats) {
                     $number_of_bookable_seats = $availablity->seats;
                  }

                  $passenger_index = 1;

                  foreach ($passenger_types as $passenger_type) {

                     // $one_way_offer = $one_way_offers->firstWhere('passenger_type', $passenger_type);
                     $one_way_offer = $flight_schedule->one_way_offers()->where('class', $availablity->class)
                        ->where('passenger_type', $passenger_type)
                        ->first();

                     for ($pid = 0; $pid < $request->get($p_type[$one_way_offer->passenger_type]); $pid++) {

                        $pricing = [
                           "traveler_id" => $passenger_index,
                           "fare_option" => "standard",
                           'id' => $availablity->id,
                           // "traveler_type" => $passenger_pricing->passenger_type,
                           "traveler_type" => getPassengerTypeByCode($one_way_offer->passenger_type),
                           "price" => [
                              "currency" => $one_way_offer->currency,
                              "total" => (double) $one_way_offer->price,
                              "base" => (double) $one_way_offer->fare_price
                           ],
                           'fare_details_by_segment' => []
                        ];

                        $pricing['fare_details_by_segment'][] = [
                           'segment_id' => $one_way_offer->flight_schedule_id,
                           'cabin' => getClassNameByCabin($one_way_offer->cabin),
                           'fare_basis' => $one_way_offer->fare_basis,
                           'class' => $one_way_offer->class,
                           'included_checked_bags' => [
                              'weight' => (int) str_replace("K", "", $one_way_offer->hold_weight),
                              'weight_unit' => 'KG',
                           ],
                        ];

                        $traveler_pricings[] = $pricing;

                        $currency = $one_way_offer->currency;
                        $base += $one_way_offer->fare_price;
                        $total += $one_way_offer->price;

                        $passenger_index++;

                     }
                  }
               }

               $segments[] = [
                  'departure' => [
                     'iataCode' => $flight_schedule->origin,
                     'at' => $flight_schedule->departure,
                  ],
                  'arrival' => [
                     'iataCode' => $flight_schedule->destination,
                     'at' => $flight_schedule->arrival,
                  ],
                  'carrierCode' => $flight_schedule->iata,
                  'carrier' => $flight_schedule->aero_token->only('iata'),
                  "number" => $flight_schedule->flight_number,
                  'aircraft' => findAircraft($flight_schedule->aircraft_code > 0 ? $flight_schedule->aircraft_code : 320),
                  'id' => $schedule_id,
               ];

               // $offer['itineraries']['segments'] = [
               //    // 'duration' => $this->h_convertToHoursMins($flight_duration, 'PT%02dH%02dM'),
               //    // 'segments' => [

               //    // ],
               // ];
            }

            $offer['itineraries'][] = [
               'duration' => $this->h_convertToHoursMins($flight_duration, 'PT%02dH%02dM'),
               'segments' => $segments,
            ];

            $offer['traveler_pricings'] = $traveler_pricings;
            $offer['price'] = [
               "currency" => $currency,
               "total" => $total,
               "base" => $base,
               "grand_total" => $total,

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

            $offer['id'] = $itinerary_index;
            $offer['number_of_bookable_seats'] = $number_of_bookable_seats;

            // $offer['ref_uri'] = 'https://flights.booknow.ly/ref/' . sha1(now());

            ksort($offer, SORT_STRING);
            $data[] = $offer;
         }
         // return $route;
      }

      $result = [
         'meta' => [
            'count' => count($data),
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
      $destination_irport = getAirport(code: $request->destination_location_code);

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

      if ($request->get('non_stop', false)) {
         $flight_schedules = [];

         foreach ($itineraries as $itinerary) {
            $direct_flight_schedules = \App\Models\FlightSchedule::whereDate('departure', '=', date('Y-m-d', strtotime($itinerary['departure_date'])))
               ->whereNot('aero_token_id', 7)
               ->where('origin', '=', $itinerary['origin'])
               ->where('destination', '=', $itinerary['destination'])
               ->whereHas('availablities', $availablities = function ($q) use ($total_passengers) {
                  $q->where('seats', '>', $total_passengers);
               })
               ->whereHas('one_way_offers', $one_way_offers = function ($q) use ($passenger_types, $travel_class) {
                  $q->whereIn('passenger_type', $passenger_types)
                     ->whereIn('class', $this->h_getClassCodes($travel_class));
               })
               ->with([
                  'one_way_offers' => $one_way_offers
               ])
               ->get();

            // $itinerary['flight_schedules'] = $direct_flight_schedules->pluck('id')->toArray();
            $flight_schedules[] = $direct_flight_schedules->pluck('id')->toArray();
         }

         $combiniations = $this->h_combinations($flight_schedules);
         // if (count($combiniations) > 0) {
         if (count($combiniations) == 2) {
            for ($itinerary_index = 0; $itinerary_index < count($itineraries); $itinerary_index++) {

               $offer = [
                  'itineraries' => [],
               ];

               $traveler_pricings = [];

               $segments = [];

               $currency = "";
               $total = 0;
               $base = 0;

               // return $combiniations[$itinerary_index];
               for ($combination_id = 0; $combination_id < count($combiniations[$itinerary_index]); $combination_id++) {
                  $schedule_id = $combiniations[$itinerary_index][$combination_id];

                  $flight_schedule = \App\Models\FlightSchedule::find($schedule_id);

                  $dep = \Carbon\Carbon::parse($flight_schedule->departure);
                  $arr = \Carbon\Carbon::parse($flight_schedule->arrival);

                  $timezone_diff = 0;
                  if ($combination_id == 0) {
                     $timezone_diff = ($origin_airport->timezone - $destination_irport->timezone) * 60;
                  } else {
                     $timezone_diff = ($destination_irport->timezone - $origin_airport->timezone) * 60;
                  }
                  $flight_duration = $arr->diffInMinutes($dep) + $timezone_diff;

                  $number_of_bookable_seats = 0;

                  foreach ($flight_schedule->availablities()->whereIn('class', $this->h_getClassCodes($travel_class))->where('seats', '>=', $total_passengers)->get() as $availablity) {
                     if ($number_of_bookable_seats < $availablity->seats) {
                        $number_of_bookable_seats = $availablity->seats;
                     }

                     $passenger_index = 1;

                     foreach ($passenger_types as $passenger_type) {

                        // $one_way_offer = $one_way_offers->firstWhere('passenger_type', $passenger_type);
                        $one_way_offer = $flight_schedule->one_way_offers()->where('class', $availablity->class)
                           ->where('passenger_type', $passenger_type)
                           ->first();

                        if ($one_way_offer != null) {
                           for ($pid = 0; $pid < $request->get($p_type[$one_way_offer->passenger_type]); $pid++) {

                              $pricing = [
                                 "traveler_id" => $passenger_index,
                                 "fare_option" => "standard",
                                 'id' => $availablity->id,
                                 // "traveler_type" => $passenger_pricing->passenger_type,
                                 "traveler_type" => getPassengerTypeByCode($one_way_offer->passenger_type),
                                 "price" => [
                                    "currency" => $one_way_offer->currency,
                                    "total" => (double) $one_way_offer->price,
                                    "base" => (double) $one_way_offer->fare_price
                                 ],
                                 'fare_details_by_segment' => []
                              ];

                              $pricing['fare_details_by_segment'][] = [
                                 'segment_id' => $one_way_offer->flight_schedule_id,
                                 'cabin' => getClassNameByCabin($one_way_offer->cabin),
                                 'fare_basis' => $one_way_offer->fare_basis,
                                 'class' => $one_way_offer->class,
                                 'included_checked_bags' => [
                                    'weight' => (int) str_replace("K", "", $one_way_offer->hold_weight),
                                    'weight_unit' => 'KG',
                                 ],
                              ];

                              $traveler_pricings[] = $pricing;

                              $currency = $one_way_offer->currency;
                              $base += $one_way_offer->fare_price;
                              $total += $one_way_offer->price;

                              $passenger_index++;

                           }
                        }
                     }
                  }

                  $offer['itineraries'][] = [
                     'duration' => $this->h_convertToHoursMins($flight_duration, 'PT%02dH%02dM'),
                     'segments' => [
                        'departure' => [
                           'iataCode' => $flight_schedule->origin,
                           'at' => $flight_schedule->departure,
                        ],
                        'arrival' => [
                           'iataCode' => $flight_schedule->destination,
                           'at' => $flight_schedule->arrival,
                        ],
                        'carrierCode' => $flight_schedule->iata,
                        'carrier' => $flight_schedule->aero_token->only('iata'),
                        "number" => $flight_schedule->flight_number,
                        'aircraft' => findAircraft($flight_schedule->aircraft_code > 0 ? $flight_schedule->aircraft_code : 320),
                        'id' => $schedule_id,
                     ],
                  ];
               }

               $offer['traveler_pricings'] = $traveler_pricings;
               $offer['price'] = [
                  "currency" => $currency,
                  "total" => $total,
                  "base" => $base,
                  "grand_total" => $total,

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

               $offer['id'] = $itinerary_index;
               $offer['number_of_bookable_seats'] = $number_of_bookable_seats;

               // $offer['ref_uri'] = 'https://flights.booknow.ly/ref/' . sha1(now());
               if ($number_of_bookable_seats > 0) {
                  ksort($offer, SORT_STRING);
                  $data[] = $offer;
               }
            }
         }

         // return
         //    [
         //       'flight_schedules' => $flight_schedules,
         //       'combinations' => $this->h_combinations($flight_schedules)
         //    ];

      } else {

      }

      $result = [
         'meta' => [
            'count' => count($data),
         ],
         'data' => $data,
      ];

      return $result;
   }

   public function flight_offers_pricing(Request $request)
   {
      $request->validate([
         'data' => 'array|required',
      ]);

      foreach ($request->data['flight_offers'] as $flight_offer) {
         foreach ($flight_offer['itineraries'] as $itinerary) {
            foreach ($itinerary['segments'] as $segment) {

            }
         }
      }
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
         return ['J', 'C'];
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

   public function round_way_flight_offers(Request $request)
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
         'only' => 'sometimes',
      ]);

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
      $destination_irport = getAirport(code: $request->destination_location_code);

      $p_type = [
         'AD' => 'adults',
         'CH' => 'children',
         'IN' => 'infants',
         'IS' => 'seated_infants',
      ];

      $itinerary_index = 0;

      $travel_class = $request->travel_class;
      $result = [];

      $round_way_offers = RoundWayOffer::where('from', $request->origin_location_code)
         ->where('to', $request->destination_location_code)
         ->whereDate('departure', operator: date('Y-m-d', strtotime($request->departure_date)))
         ->whereDate('return', date('Y-m-d', strtotime($request->return_date)))
         ->get();

      // return $round_way_offers;
      foreach ($round_way_offers as $round_way_offer) {


         foreach ($round_way_offer->segments()->orderBy('type', 'desc')->get() as $round_way_segment) {

            $offer = [
               'itineraries' => [],
            ];

            $traveler_pricings = [];

            $segments = [];

            $currency = "";
            $total = 0;
            $base = 0;

            $flight_schedule = \App\Models\FlightSchedule::find($round_way_segment->flight_schedule_id);

            $dep = \Carbon\Carbon::parse($flight_schedule->departure);
            $arr = \Carbon\Carbon::parse($flight_schedule->arrival);

            $timezone_diff = 0;
            if ($round_way_segment->type == 'outbound') {
               $timezone_diff = ($origin_airport->timezone - $destination_irport->timezone) * 60;
            } else {
               $timezone_diff = ($destination_irport->timezone - $origin_airport->timezone) * 60;
            }

            $flight_duration = $arr->diffInMinutes($dep) + $timezone_diff;

            $number_of_bookable_seats = 0;



            foreach ($round_way_segment->round_way_pricings()->whereIn('class', $this->h_getClassCodes($travel_class)) // ->where('seats', '>=', $total_passengers)
               ->get() as $availablity) {

               if ($number_of_bookable_seats < $availablity->seats) {
                  $number_of_bookable_seats = $availablity->seats;
               }

               $passenger_index = 1;

               foreach ($passenger_types as $passenger_type) {

                  // $one_way_offer = $one_way_offers->firstWhere('passenger_type', $passenger_type);
                  $segment_offer_pricing = $round_way_segment
                     ->round_way_pricings()
                     ->where('class', $availablity->class)
                     ->where('passenger_type', $passenger_type)
                     ->first();

                  // return $segment_offer_pricing;

                  for ($pid = 0; $pid < $request->get($p_type[$segment_offer_pricing->passenger_type]); $pid++) {

                     $pricing = [
                        "traveler_id" => $passenger_index,
                        "fare_option" => "standard",
                        'id' => $availablity->id,
                        // "traveler_type" => $passenger_pricing->passenger_type,
                        "traveler_type" => getPassengerTypeByCode($segment_offer_pricing->passenger_type),
                        "price" => [
                           "currency" => $segment_offer_pricing->currency,
                           "total" => (double) $segment_offer_pricing->price,
                           "base" => (double) $segment_offer_pricing->fare_price
                        ],
                        'fare_details_by_segment' => []
                     ];

                     $pricing['fare_details_by_segment'][] = [
                        'segment_id' => $round_way_segment->flight_schedule_id,
                        'cabin' => getClassNameByCabin($availablity->cabin),
                        'fare_basis' => $availablity->fare_basis,
                        'class' => $availablity->class,
                        'included_checked_bags' => [
                           'weight' => (int) str_replace("K", "", $availablity->hold_weight),
                           'weight_unit' => 'KG',
                        ],
                     ];

                     $traveler_pricings[] = $pricing;

                     $currency = $availablity->currency;
                     $base += $availablity->fare_price;
                     $total += $availablity->price;

                     $passenger_index++;

                  }
               }
            }

            $offer['itineraries'][] = [
               'duration' => $this->h_convertToHoursMins($flight_duration, 'PT%02dH%02dM'),
               'segments' => [
                  'departure' => [
                     'iataCode' => $flight_schedule->origin,
                     'at' => $flight_schedule->departure,
                  ],
                  'arrival' => [
                     'iataCode' => $flight_schedule->destination,
                     'at' => $flight_schedule->arrival,
                  ],
                  'carrierCode' => $flight_schedule->iata,
                  'carrier' => $flight_schedule->aero_token->only('iata'),
                  "number" => $flight_schedule->flight_number,
                  'aircraft' => findAircraft($flight_schedule->aircraft_code > 0 ? $flight_schedule->aircraft_code : 320),
                  'id' => $flight_schedule->id,
               ],
            ];
         }


         $offer['traveler_pricings'] = $traveler_pricings;
         $offer['price'] = [
            "currency" => $currency,
            "total" => $total,
            "base" => $base,
            "grand_total" => $total,

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

         $offer['id'] = $itinerary_index;
         $offer['number_of_bookable_seats'] = $number_of_bookable_seats;

         // $offer['ref_uri'] = 'https://flights.booknow.ly/ref/' . sha1(now());

         ksort($offer, SORT_STRING);
         $data[] = $offer;
      }


      $result = [
         'meta' => [
            'count' => count($data),
         ],
         'data' => $data,
      ];

      return $result;
   }
}
