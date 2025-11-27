<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncOneWayOfferJob implements ShouldQueue
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
        // sleep(1);
        $flight = $this->availability->flight_schedule;
        if ($flight != null && $flight->aero_token != null) {
            # [OLD] #
            // $command = "I^-3Pax/A#/B#.CH10/C#.IN06/^0" . $flight->flight_number . $this->availability->class .
            // date('dM', strtotime($flight->departure)) . $flight->origin . $flight->destination . "NN2^FG^FS1^*r~x";

            // $cache_key = $flight->carrier . "I^-3Pax/A#/B#.CH10/C#.IN06/^0" . $this->availability->class . $flight->origin . $flight->destination . "NN2^FG^FS1^*r~x";

            $command = "";
            # [NEW] #
            if ($this->availability->seats >= 3) {
                $command = "I^-4Pax/A#/B#.CH10/C#.IN06/D#.IS06/^0" . $flight->flight_number . $this->availability->class .
                    date('dM', strtotime($flight->departure)) . $flight->origin . $flight->destination . "NN3^FG^FS1^*r~x";    
            }  else {
                $command = "I^-4Pax/A#/B#.CH10/C#.IN06/D#.IS06/^0" . $flight->flight_number . $this->availability->class .
                    date('dM', strtotime($flight->departure)) . $flight->origin . $flight->destination . "NN3^FG^FS1^*r~x";    
            }
            

            $cache_key = $flight->aero_token->getQueueId() . "I^-4Pax/A#/B#.CH10/C#.IN06/D#.IS06/^0" . $this->availability->class . $flight->origin . $flight->destination . "NN3^FG^FS1^*r~x";

            // $result = "";
            $offers = [];

            if (cache()->has($cache_key)) {
                $offers = cache()->get($cache_key);
            } else {
                $response = $flight->aero_token->build()->runCommand($command);
                $result = $response->response;

                $offers = $this->parse_result($result);
                if ($offers['status'] == "OK") {
                    cache()->put($cache_key, $offers, now()->addHours(6));
                }
            }

            if ($offers['status'] == "OK") {
                \App\Jobs\UpdateOneWayOfferJob::dispatch($offers['data'], $flight, $command, $this->availability)
                    // ->delay(now()->addSeconds(5))
                    ->onQueue('default');
            } else if($offers['status'] == "NOTAVAILABLE") {
                $this->availability->seats = -1;
                $this->availability->save();
            } else if($offers['status'] == "SOLDOUT") {
                $this->availability->seats = 0;
                $this->availability->save();
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
