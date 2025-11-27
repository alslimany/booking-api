<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateOneWayOfferPrices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $availability;
    /**
     * Create a new job instance.
     */
    public function __construct($availability)
    {
        $this->availability = $availability;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $flight = $this->availability->flight_schedule;

      if ($flight != null && $flight->aero_token != null) {
            $command = "";
            # [NEW] #
            if ($this->availability->seats >= 3) {
                $command = "I^-4Pax/A#/B#.CH10/C#.IN06/D#.IS06/^0" . $flight->flight_number . $this->availability->class .
                    date('dM', strtotime($flight->departure)) . $flight->origin . $flight->destination . "NN3^FG^FS1^*r~x";
            } else {
                $command = "I^-4Pax/A#/B#.CH10/C#.IN06/D#.IS06/^0" . $flight->flight_number . $this->availability->class .
                    date('dM', strtotime($flight->departure)) . $flight->origin . $flight->destination . "QQ3^FG^FS1^*r~x";
            }

            $cache_key = $flight->aero_token->getQueueId() . "|pricing|I^-4Pax/A#/B#.CH10/C#.IN06/D#.IS06/^0" . $this->availability->class . $flight->origin . $flight->destination . "NN3^FG^FS1^*r~x";

            if (!cache()->has($cache_key)) {

                $response = $flight->aero_token->build()->runCommand($command);
                $result = $response->response;

                // $this->info($result);
                $offers = $this->parse_result($result);

                if ($offers['status'] == "OK") {
                    cache()->put($cache_key, $offers, now()->addDay());

                    foreach ($offers['data'] as $offer) {
                        $offer['flight_schedule_id'] = $flight->id;
                        $offer['flight_availablity_id'] = $this->availability->id;
                        $offer['departure'] = $flight->departure;
                        $offer['arrival'] = $flight->arrival;
                        $offer['cabin'] = $this->availability->cabin;
                        $offer['class'] = $this->availability->class;


                        if (array_key_exists('display_name', $offer)) {
                            if ($this->availability->display_name != $offer['display_name']) {
                                $this->availability->display_name = $offer['display_name'];
                                $this->availability->save();
                            }
                        }

                        if (array_key_exists('name', $offer)) {
                            if ($this->availability->name != $offer['name']) {
                                $this->availability->name = $offer['name'];
                                $this->availability->save();
                            }
                        }

                        unset($offer['display_name']);
                        unset($offer['name']);

                        $flight_schedules = \App\Models\FlightSchedule::where('aero_token_id', $flight->aero_token->id)
                            ->whereDate('departure', '>=', date('Y-m-d'))
                            ->pluck('id')
                            ->toArray();

                        \App\Models\OneWayOffer::whereIn('flight_schedule_id', $flight_schedules)
                            ->where([
                                'from' => $offer['from'],
                                'to' => $offer['to'],
                                'cabin' => $offer['cabin'],
                                'class' => $offer['class'],
                                'passenger_type' => $offer['passenger_type'],
                            ])
                            ->update([
                                'fare_basis' => $offer['fare_basis'],
                                'fare_price' => $offer['fare_price'],
                                'tax' => $offer['tax'],
                                'price' => $offer['price'],
                                'currency' => $offer['currency'],
                                'hold_pices' => $offer['hold_pices'],
                                'hold_weight' => $offer['hold_weight'],
                                'hand_weight' => $offer['hand_weight'],
                            ]);
                    }

                } 
            }
        }
    }


    private function parse_result($result): array
    {
        $errors = [
            'ERROR' => 'ERROR',
            'SOLDOUT' => 'CLASS SOLD OUT ON THIS SERVICE',
            'NOTAVAILABLE' => 'CLASS NOT AVAILABLE ON THIS SERVICE',
            'RESTRICTEDSALE' => 'SALES ARE RESTRICTED',
            'QUANTITYLIMITED' => 'CLASS QUANTITY AVAILABLE IS LESS THAN REQUESTED',
            'NOAVAILABILITY' => 'NO AVAILABILITY INFORMATION FOR THIS CITY',
            // 'NO AV' => 'NO AV',
        ];
        $status = "OK";

        $offers = [];

        foreach ($errors as $key => $val) {
            if (str_contains(strtolower($result), strtolower($val))) {
                $status = $key;
            }
        }

        // if ($result != "ERROR - no fare available" || $result != "ERROR - no fare available") {
        if ($status == "OK") {
            $xml = "<xml>" . $result . "</xml>";

            $xmlObject = simplexml_load_string($xml);

            // try {
            if ($xmlObject) {
                if ($xmlObject->PNR->Names != null) {
                    // Reserve Paxes
                    if (is_iterable($xmlObject->PNR->Names->PAX)) {
                        foreach ($xmlObject->PNR->Names->PAX as $pax) {
                            $offers[$pax->attributes('', true)->PaxNo . ''] = [
                                'passenger_type' => $pax->attributes('', true)->PaxType . '',
                                'from' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Depart . '',
                                'to' => $xmlObject->PNR->Itinerary->Itin->attributes('', true)->Arrive . '',
                                'fare_basis' => $xmlObject->PNR->FareQuote->FQItin->attributes('', true)->FQI . '',
                            ];
                        }
                    }

                    // Fare Price 
                    if (is_iterable($xmlObject->PNR->FareQuote->FareStore)) {
                        foreach ($xmlObject->PNR->FareQuote->FareStore as $fare_store) {
                            if ($fare_store->attributes('', true)->Pax != '') {
                                $offer = $offers[$fare_store->attributes('', true)->Pax . ''];

                                $offer['currency'] = $fare_store->attributes('', true)->Cur . '';
                                $offer['price'] = $fare_store->attributes('', true)->Total . '';
                                $offer['fare_price'] = $fare_store->SegmentFS->attributes('', true)->Fare . '';
                                $offer['tax'] = (
                                    (double) $fare_store->SegmentFS->attributes('', true)->Tax1 +
                                    (double) $fare_store->SegmentFS->attributes('', true)->Tax2 +
                                    (double) $fare_store->SegmentFS->attributes('', true)->Tax3
                                );

                                $offer['hold_pices'] = $fare_store->SegmentFS->attributes('', true)->HoldPcs . '';
                                $offer['hold_weight'] = $fare_store->SegmentFS->attributes('', true)->HoldWt . '';
                                $offer['hand_weight'] = $fare_store->SegmentFS->attributes('', true)->HandWt . '';

                                $offer['display_name'] = $xmlObject->PNR->Itinerary->Itin->attributes('', true)->ClassBandDisplayName . '';
                                $offer['name'] = $xmlObject->PNR->Itinerary->Itin->attributes('', true)->ClassBand . '';

                                $offers[$fare_store->attributes('', true)->Pax . ''] = $offer;
                            }
                        }
                    }
                }
            }
        }
        //  else {
        //     $status = "ERROR";
        // }

        return [
            'status' => $status,
            'data' => $offers
        ];
    }
}
