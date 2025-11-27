<?php
require 'AircraftHelper.php';
require 'AirportHelper.php';
require 'CountryHelper.php';

use \Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

function artisan($command)
{
   // Artisan::call($command);
   // $result = Illuminate\Support\Facades\Process::run('cd ' . base_path() . ' && php artisan ' . $command);
   // Storage::disk('local')->append('cli-log', base_path(''));
   // Storage::disk('local')->append('cli-log', $result->command());
   // Storage::disk('local')->append('cli-log', $result->output());

   // return $result->output();

   $output = shell_exec('cd ' . base_path() . ' && php artisan ' . $command);
   Storage::disk('local')->append('cli-log', $output);
   return $output;

   // $output = null;
   // $retval = null;

   // $output = exec('cd ' . base_path() . ' && php artisan ' . $command, $output, $retval);
   // Storage::disk('local')->append('cli-log', $retval);
   // return $retval;

}

function getClassName($class)
{
   // G, P
   if (in_array($class, ['K', 'L', 'Q', 'V', 'W', 'U', 'T', 'X', 'N', 'O', 'S', 'R'])) {
      return 'discounted_economy';
   }
   if (in_array($class, ['Y', 'B', 'M', 'H'])) {
      return 'economy';
   }
   if (in_array($class, ['W', 'E'])) {
      return 'premium_economy';
   }
   if (in_array($class, ['D', 'I', 'Z'])) {
      return 'discounted_business';
   }
   if (in_array($class, ['J', 'C'])) {
      return 'business';
   }
   if (in_array($class, ['A', 'F'])) {
      return 'first';
   }

   return 'none';
}

function getClassNameByCabin($cabin)
{
   $cabin = trim($cabin);
   // $cabin = strtoupper($cabin);

   // if ($cabin == '') {
   //    return 'discounted_economy';
   // }
   if (strtolower($cabin) == 'y') {
      return 'economy';
   }
   if (strtolower($cabin) == 'w') {
      return 'premium_economy';
   }
   // if ($cabin == '') {
   //    return 'discounted_business';
   // }
   if (strtolower($cabin) == 'c') {
      return 'business';
   }
   if (strtolower($cabin) == 'f') {
      return 'first';
   }
}

function getPassengerTypeByCode($type)
{
   // if ($cabin == '') {
   //    return 'discounted_economy';
   // }
   if ($type == 'AD') {
      return 'adult';
   }
   if ($type == 'CH') {
      return 'child';
   }
   // if ($cabin == '') {
   //    return 'discounted_business';
   // }
   if ($type == 'IN') {
      return 'infant';
   }
   if ($type == 'IS') {
      return 'seated_infant';
   }
}

function multiKeyExists(array $arr, $key)
{

   // is in base array?
   if (array_key_exists($key, $arr)) {
      return true;
   }

   // check arrays contained in this array
   foreach ($arr as $element) {
      if (is_array($element)) {
         if (multiKeyExists($element, $key)) {
            return true;
         }
      }

   }

   return false;
}

function titleToGender($title)
{
   switch (strtolower($title)) {
      case "mr":
         return 'male';
      case "ms":
         return 'female';
      case "mrs":
         return 'female';
      case "miss":
         return 'female';
      case "mstr":
         return 'male';
      default:
         return 'male';
   }
}


function getAirline($airline)
{
   return $airline;
}


/**
 * Convert an array of SimpleXMLElement's into a
 * collection.
 *
 * @param array $records
 *
 * @return \Illuminate\Support\Collection
 * @access public
 */
function recordsToCollection($records): \Illuminate\Support\Collection
{
   $array = [];
   foreach ($records as $record) {
      $array[] = array_values((array) $record)[0];
   }

   return collect($array);
}

function getFareNote($carrier, $fare_id)
{
   $items = [];
   $fare_rule = \App\Models\FareRule::where([
      'carrier' => $carrier,
      'fare_id' => $fare_id,
   ])->first();

   if ($fare_rule != null) {
      // return $fare_rule->note;
      return fare_rules_to_array(string: $fare_rule->note[0]);
      return implode(fare_rules_to_array($fare_rule->note));
   }

   return $items;
}

function getFareRuleItems($carrier, $fare_id)
{
   $items = [];
   $fare_rule = \App\Models\FareRule::where([
      'carrier' => $carrier,
      'fare_id' => $fare_id,
   ])->first();

   if ($fare_rule != null) {
      foreach ($fare_rule->items as $item) {
         $items[$item->key] = [
            'status' => $item->status,
            'value' => $item->value,
            'note' => $item->note,
         ];
      }
   }

   return $items;
}

function multisort(&$array, $key)
{
   $valsort = array();
   $ret = array();
   reset($array);
   foreach ($array as $ii => $va) {
      $valsort[$ii] = $va[$key];
   }
   asort($valsort);
   foreach ($valsort as $ii => $va) {
      $ret[$ii] = $array[$ii];
   }
   $array = $ret;
}

function fetch_current_weather($airport)
{
   // http://api.weatherapi.com/v1/current.json
   // ae99212af0994430bdb164125243006

   $data = cache()->remember($airport . '-current-weather', now()->addHours(5), function () use ($airport) {
      return
         file_get_contents(
            "http://api.weatherapi.com/v1/current.json?key=ae99212af0994430bdb164125243006&q=iata:" . $airport
         );
   });


   $current_weather = @json_decode($data);

   return [
      'location' => [
         'name' => $current_weather->location->name,
      ],
      'current' => [
         'tempreture' => $current_weather->current->temp_c,
         'condition' => $current_weather->current->condition->text,
         'icon' => $current_weather->current->condition->icon,
      ],
   ];

}

function is_adult($date_of_birth)
{
   $now = \Carbon\Carbon::now();
   $dob = \Carbon\Carbon::parse($date_of_birth);

   $age = $now->diffInYears($dob);

   if ($age > 11) {
      return true;
   }

   return false;
}

function is_child($date_of_birth)
{
   $now = \Carbon\Carbon::now();
   $dob = \Carbon\Carbon::parse($date_of_birth);

   $age = $now->diffInYears($dob);

   if ($age < 12 and $age > 2) {
      return true;
   }

   return false;
}

function is_infant($date_of_birth)
{
   $now = \Carbon\Carbon::now();
   $dob = \Carbon\Carbon::parse($date_of_birth);

   $age = $now->diffInYears($dob);

   if ($age <= 2) {
      return true;
   }

   return false;
}

function age_in_years($date_of_birth)
{
   $now = \Carbon\Carbon::now();
   $dob = \Carbon\Carbon::parse($date_of_birth);

   $age = $now->diffInYears($dob);

   return $age;
}

function age_in_months($date_of_birth)
{
   $now = \Carbon\Carbon::now();
   $dob = \Carbon\Carbon::parse($date_of_birth);

   $age = $now->diffInMonths($dob);

   return $age;
}

function parse_pnr($xmlObject)
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

      'total_fare' => '',
      'total_tax' => '',
      'total_price' => '',
      'currency' => '',

      'is_issued' => false,
      'is_locked' => false,
      'is_voidable' => false,
      'void_cutoff_time' => null,
      'rloc' => '',
      'iata' => '',
   ];

   $pnr_total_fare = 0;
   $pnr_total_tax = 0;
   $pnr_total_price = 0;

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

                        $pnr_total_fare += $row['fare'];
                        $pnr_total_tax += ($row['tax1'] + $row['tax2'] + $row['tax3']);
                        $pnr_total_price += ($row['fare'] + ($row['tax1'] + $row['tax2'] + $row['tax3']));
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

                  $total_tax += (double) $pax_tax->attributes('', true)->Amnt;
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

      // Extraxt Basket
      $pnr['basket'][] = [
         'id' => 'outstanding',
         'currency' => (string) $xmlObject->PNR->Basket->Outstanding->attributes()->{'cur'},
         'info' => (string) $xmlObject->PNR->Basket->Outstanding->attributes()->{'info'},
         'amount' => (string) $xmlObject->PNR->Basket->Outstanding->attributes()->{'amount'},
      ];

      $pnr['basket'][] = [
         'id' => 'outstanding_airmiles',
         'currency' => (string) $xmlObject->PNR->Basket->Outstandingairmiles->attributes()->{'cur'},
         'info' => (string) $xmlObject->PNR->Basket->Outstandingairmiles->attributes()->{'info'},
         'amount' => (string) $xmlObject->PNR->Basket->Outstandingairmiles->attributes()->{'amount'},
      ];

      // if (is_iterable($xmlObject->PNR->Basket)) {
      //    foreach ($xmlObject->PNR->MPS->MP as $mp) {
      //       $pnr['mps'][] = [
      //          'id' => (string) $mp->attributes()->{'MPID'},
      //          'pax_id' => (string) $mp->attributes()->{'Pax'},
      //          'segment_id' => (string) $mp->attributes()->{'Seg'},
      //          'currency' => (string) $mp->attributes()->{'MPSCur'},
      //          'amount' => (string) $mp->attributes()->{'MPSAmt'},
      //       ];
      //    }
      // }

      if (count($pnr['tickets']) > 0) {
         if (count($pnr['payments']) > 0) {
            $pnr['is_issued'] = true;
         }
      }

      $pnr['total_price'] = "" . $pnr_total_price;
      $pnr['total_fare'] = "" . $pnr_total_fare;
      $pnr['total_tax'] = "" . $pnr_total_tax;
      $pnr['currency'] = "" . $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->Cur;

      return $pnr;
   }
}

function trip_direction($origin, $destination)
{
   $graph = \Taniko\Dijkstra\Graph::create();

   foreach (\App\Models\FlightSchedule::whereDate('departure', '>', date('Y-m-d'))->select('origin', 'destination', 'duration')->get() as $flight) {
      $graph->add($flight->origin, $flight->destination, $flight->duration);
   }

   // $graph
   //     ->add('s', 'a', 1)
   //     ->add('s', 'b', 2)
   //     ->add('a', 'b', 2)
   //     ->add('a', 'c', 4)
   //     ->add('b', 'c', 2)
   //     ->add('b', 'd', 5)
   //     ->add('c', 'd', 1)
   //     ->add('c', 't', 3)
   //     ->add('d', 't', 1);
   $route = $graph->search($origin, $destination); // ['s', 'b', 'c', 'd', 't']
   $cost = $graph->cost($route);     // 6.0

   return [
      'route' => $route,
      'cost' => $cost,
   ];
}

function isValidXml($content)
{
   $content = trim($content);
   if (empty($content)) {
      return false;
   }
   //html go to hell!
   //  if (stripos($content, '<!DOCTYPE html>') !== false) {
   //      return false;
   //  }

   libxml_use_internal_errors(true);
   simplexml_load_string($content);
   $errors = libxml_get_errors();
   libxml_clear_errors();

   return empty($errors);
}

# Store Sales Report
function store_order_sale($order)
{
   $agent = $order->owner;
   $lyd_wallet = $agent->getWallet('lyd-wallet');
   $eur_wallet = $agent->getWallet('eur-wallet');
   $user = $order->user;

   // app(\Bavix\Wallet\Services\AtomicServiceInterface::class)
   //     ->blocks([$lyd_wallet, $eur_wallet], function () use ($lyd_wallet, $eur_wallet, $order, $agent) {

   $order_item_sales = [];

   foreach ($order->order_items as $order_item) {

      $query_result = booking_api_request()
         ->post("query-pnr", [
            'pnr' => $order_item->item['rloc'],
            'iata' => $order_item->item['iata'],
         ]);
      // dd($query_result->json());
      // foreach ($order_item->item['tickets'] as $ticket) {
      foreach ($query_result->json('tickets') as $ticket) {
         $segment_id = (integer) $ticket['segment_number'];
         $pax_id = (integer) $ticket['passenger_id'];
         $itinerary = $query_result->json('itineraries')[$segment_id - 1];
         $fare_stores = $query_result->json('fare_store');

         // dd($fare_stores);
         $segment_fare = $fare_stores[$pax_id - 1]['segments'][$segment_id - 1];
         $currency_code = strtolower($fare_stores[$pax_id - 1]['currency']);

         $ticket_taxes = [];
         $total_taxes = 0;
         foreach ($query_result->json('taxes') as $tax) {
            if ($tax['segment_id'] == $segment_id && $tax['pax_id'] == $pax_id) {
               $total_taxes += $tax['amount'];
               $ticket_taxes[] = $tax;
            }
         }

         $ticket_commission = get_commession($agent->id, $itinerary['airline_id'], $segment_fare['fare'], $itinerary['is_international']);

         $wallet = $agent->getWallet($currency_code . '-wallet');

         $order_item_sale = [
            'order_item_id' => $order_item->id,
            'user_id' => $user->id,
            'route' => $ticket['from'] . '-' . $ticket['to'],
            'class' => $ticket['class'],
            'ticket_number' => $ticket['ticket_number'],
            'coupon' => $ticket['coupon'],
            'passenger_name' => $ticket['last_name'] . '/' . $ticket['first_name'] . '' . $ticket['title'],
            'flight_number' => $ticket['flight_number'],
            'flight_date' => $ticket['flight_date'],
            'status' => $ticket['ticket_id'],
            'fare_price' => $segment_fare['fare'],
            'taxes' => $ticket_taxes,
            // 'total_tax' => $segment_fare['tax1'] + $segment_fare['tax2'] + $segment_fare['tax3'],
            'percentage' => $ticket_commission['agent_percentage'],
            'total_tax' => $total_taxes,
            'commission_paid' => 0,
            'agent_commission' => $ticket_commission['agent_commission'],
            'total_commission' => $ticket_commission['net_commission'],
            'net_fare' => ($segment_fare['fare'] + $segment_fare['tax1'] + $segment_fare['tax2'] + $segment_fare['tax3']) - $ticket_commission['agent_commission'],
            'total' => ($segment_fare['fare'] + $segment_fare['tax1'] + $segment_fare['tax2'] + $segment_fare['tax3']),
            'w_tax' => 0,
            'balance' => $wallet->balanceFloat,
            'currency_code' => 'LYD',
         ];

         # 1 => Withdraw total price
         $wallet->withdrawFloat($order_item_sale['total'], [
            'currency' => strtoupper($order_item_sale['currency_code']),
            'description' => 'ticket-purchase',
            'message' => 'Withdraw ticket #' . $order_item->reference . ' price',
            'order_item_id' => $order_item->id,
            'reference' => $order_item->reference,
            'ticket_number' => $order_item_sale['ticket_number']
         ]);

         # 2 => Deposite percentage
         if ($order_item_sale['agent_commission'] > 0) {
            $wallet->depositFloat($order_item_sale['agent_commission'], [
               'currency' => strtoupper($order_item_sale['currency_code']),
               'description' => 'ticket-commission-deposit',
               'message' => 'Deposit ticket #' . $order_item->reference . ' commission',
               'order_item_id' => $order_item->id,
               'reference' => $order_item->reference,
               'ticket_number' => $order_item_sale['ticket_number']
            ]);
         }

         $order_item_sale['balance'] = $wallet->balanceFloat;

         \App\Models\OrderItemSale::create($order_item_sale);
         // $order_item_sales[] = $order_item_sale;
         // dd($ticket);
      }
   }

   // });
}

function fare_rules_to_array($string)
{
   $result = null;
   $data = explode("\n", $string);

   $escape = ['Rules', 'End of list', ''];

   foreach ($data as $row) {
      $row = trim($row);

      if ($row == 'Rules') {
         $result = [];
      }

      if (is_array($result)) {
         if (!in_array($row, $escape)) {
            if (is_numeric($row[0]))
            {
               $result[] = trim(substr($row, 1));
            }else {
               $result[] = trim(string: $row);
            }
         }
      }
   }

   return $result;
}

function durationStringToMinutes($timeString) {
   // Initialize hours and minutes
   $hours = 0;
   $minutes = 0;

   // Use regex to find hours
   if (preg_match('/(\d+)\s*h/', $timeString, $matches)) {
       $hours = (int)$matches[1];
   }

   // Use regex to find minutes
   if (preg_match('/(\d+)\s*(?:m|min|mins)/', $timeString, $matches)) {
       $minutes = (int)$matches[1];
   }

   // Calculate total minutes
   $totalMinutes = ($hours * 60) + $minutes;

   return $totalMinutes;
}

function get_next_order_number()
{
    $last_order = \App\Models\Order::orderBy('number', 'desc')->take(1)->first();

    if (isset($last_order?->number)) {
      $number = $last_order->number;

      if ($number == "") {
         $number = "AXQ003Z4";
       } else {
         $number = increment_number($number);
       }   
    } else {
      $number = "AXQ003Z4";
    }
    

    return $number;
}

function increment_number($current)
{
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $length = strlen($characters);

    // Split the current number into an array
    $arr = str_split($current);

    // Start incrementing from the last character
    for ($i = count($arr) - 1; $i >= 0; $i--) {
        $pos = strpos($characters, $arr[$i]);
        if ($pos === false)
            continue; // If the character isn't in our character set, skip it

        if ($pos == $length - 1) {
            // If the current character is the last in the set, reset it to the first character
            $arr[$i] = $characters[0];
        } else {
            // Increment the character to the next in the set and break
            $arr[$i] = $characters[$pos + 1];
            break;
        }
    }

    return implode('', $arr);
}

function get_next_ticket_number() {
   $last_order = \App\Models\Order::orderBy('number', 'desc')->take(1)->first();
   $number = "";
   if ($last_order == null) {
      $number = "2971813076";
   } else {
      $last_item = $last_order->order_items()->orderBy('created_at', 'desc')->take(1)->first();

   if ($last_item != null) {
      $last_ticket = $last_item->item['tickets'][ count($last_item->item['tickets']) - 1];

         if ($last_ticket == null) {
            $number = "2971813076";
         } else {
            $number = (explode(' ', $last_ticket['ticket_number'])[1]);
            $number++;
         }
      } else {
         $number = "2971813076";
      }
   }

   

   return $number;
}

// Moved from v1.php
function parse_result($command_result): array
{
    $status = "OK";

    $offers = [];

    if (str_contains($command_result, 'no fare available')) {
        $status = \App\Enum\VidecomError::NO_FARE_AVAILABLE;
    } elseif (str_contains($command_result, 'NO AVAILABILITY INFORMATION FOR THIS CITY PAIR/DATE')) {
        $status = \App\Enum\VidecomError::NO_FLIGHT_AVAILABLE;
    } else {
        $xml = "<xml>" . $command_result . "</xml>";

        $xmlObject = simplexml_load_string($xml);

        try {
            if ($xmlObject) {
                if (is_iterable($xmlObject->PNR->FareQuote)) {
                    foreach ($xmlObject->PNR->FareQuote as $fare_qoute) {
                        foreach ($fare_qoute->FQItin as $fare_qoute_segment) {
                            $segment_id = $fare_qoute_segment->attributes('', true)->Seg - 1;

                            $offers[$segment_id] = [];
                        }
                    }

                    foreach ($xmlObject->PNR->FareQuote as $fare_qoute) {
                        foreach ($fare_qoute->FQItin as $fare_qoute_segment) {
                            $segment_id = $fare_qoute_segment->attributes('', true)->Seg - 1;

                            // $offers[$segment_id] = [];
                            foreach ($xmlObject->PNR->Names->PAX as $pax) {
                                $pax_no = $pax->attributes('', true)->PaxNo - 1;

                                $booking_type = "";
                                if ($xmlObject->PNR->Itinerary->Itin[$segment_id]->attributes('', true)->Status == "HK") {
                                    $booking_type = 'confirm';
                                }
                                if ($xmlObject->PNR->Itinerary->Itin[$segment_id]->attributes('', true)->Status == "QQ") {
                                    $booking_type = 'open';
                                }
                                if ($xmlObject->PNR->Itinerary->Itin[$segment_id]->attributes('', true)->Status == "LL") {
                                    $booking_type = 'waitlist';
                                }

                                $fare_store_pax = [];
                                foreach ($xmlObject->PNR->FareQuote->FareStore as $pax_fs) {
                                    if ($pax_fs->attributes('', true)->FSID == 'FQC') {
                                        if ($pax_fs->attributes('', true)->Pax == ($pax_no + 1)) {
                                            $fare_store_pax = $pax_fs;
                                        }
                                    }
                                }

                                $row = [
                                    'iata' => $xmlObject->PNR->Itinerary->Itin[$segment_id]->attributes('', true)->AirID . '',
                                    'flight_number' => $xmlObject->PNR->Itinerary->Itin[$segment_id]->attributes('', true)->FltNo . '',
                                    'class' => $xmlObject->PNR->Itinerary->Itin[$segment_id]->attributes('', true)->Class . '',
                                    'passenger_type' => $pax->attributes('', true)->PaxType . '',
                                    'from' => $xmlObject->PNR->Itinerary->Itin[$segment_id]->attributes('', true)->Depart . '',
                                    'to' => $xmlObject->PNR->Itinerary->Itin[$segment_id]->attributes('', true)->Arrive . '',
                                    'fare_basis' => $xmlObject->PNR->FareQuote->FQItin[$segment_id]->attributes('', true)->FQI . '',
                                    'currency' => $fare_store_pax->attributes('', true)->Cur . '',
                                    // 'price' => $fare_store_pax->SegmentFS[$segment_id]->attributes('', true)->Total . '',
                                    'fare' => $fare_store_pax->SegmentFS[$segment_id]?->attributes('', true)->Fare . '',
                                    'tax' => (
                                        (double) $fare_store_pax->SegmentFS[$segment_id]?->attributes('', true)->Tax1 +
                                        (double) $fare_store_pax->SegmentFS[$segment_id]?->attributes('', true)->Tax2 +
                                        (double) $fare_store_pax->SegmentFS[$segment_id]?->attributes('', true)->Tax3
                                    ),
                                    'hold_pices' => $fare_store_pax->SegmentFS[$segment_id]?->attributes('', true)->HoldPcs . '',
                                    'hold_weight' => $fare_store_pax->SegmentFS[$segment_id]?->attributes('', true)->HoldWt . '',
                                    'hand_weight' => $fare_store_pax->SegmentFS[$segment_id]?->attributes('', true)->HandWt . '',
                                    'booking_type' => $booking_type,
                                ];

                                try {
                                    $row['price'] = $row['fare'] + $row['tax'];
                                } catch (Exception $ex) {

                                }

                                $offers[$segment_id][$pax_no] = $row;
                            }
                        }
                    }
                }
            }
        } catch (Exception $ex) {
            $status = "FAILED";
        }
        // $this->info($command_result);
    }

    return [
        'status' => $status,
        'segments' => $offers,
    ];
}

// Moved from web.php
function string_between_two_string($str, $starting_word, $ending_word)
{
    $subtring_start = strpos($str, $starting_word);
    //Adding the starting index of the starting word to 
    //its length would give its ending index
    $subtring_start += strlen($starting_word);
    //Length of our required sub string
    $size = strpos($str, $ending_word, $subtring_start) - $subtring_start;
    // Return the substring from the index substring_start of length size 
    return substr($str, $subtring_start, $size);
}