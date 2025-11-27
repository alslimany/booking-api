<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncRoundWayOfferJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $round_way_offer;
    /**
     * Create a new job instance.
     */
    public function __construct($round_way_offer)
    {
        $this->round_way_offer = $round_way_offer;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        foreach ($this->round_way_offer->segments->groupBy('carrier') as $carrier => $segments) {

            if (count($segments) == 2) {
                // $aero_token = \App\Models\AeroToken::where('iata', $carrier)->first();

                // $available_classes = \App\Models\FlightAvailablity::whereIn('flight_schedule_id', $segments->pluck('flight_schedule_id')->toArray())
                //     ->distinct('class')
                //     ->pluck('class')
                //     ->toArray();
                $available_classes = \App\Models\FlightAvailablity::whereIn('flight_schedule_id', $segments->pluck('flight_schedule_id')->toArray())->get();

                // \App\Jobs\RoundWayOfferClassPricingJob::dispatch($available_classes, $carrier, $segments)->onQueue('videcom-low');

                foreach ($available_classes as $availability) {
                    // \App\Jobs\RoundWayOfferClassPricingJob::dispatch($availability, $carrier, $segments)->onQueue($availability->flight_schedule->aero_token->getQueueId());
                    \App\Jobs\RoundWayOfferClassPricingJob::dispatch($availability, $carrier, $segments)->onQueue('videcom-low');
                }
            } else {
                \App\Jobs\UpdateRoundWayOfferJob::dispatch($segments)->onQueue('videcom-low');
            }
        }
    }

    private function parse_result($command_result): array
    {
        $status = "OK";

        $offers = [];

        if ($command_result != "ERROR - no fare available") {
            $xml = "<xml>" . $command_result . "</xml>";

            $xmlObject = simplexml_load_string($xml);

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

                                $offers[$segment_id][$pax_no] = [
                                    'class' => $xmlObject->PNR->Itinerary->Itin[$segment_id]->attributes('', true)->Class . '',
                                    'passenger_type' => $pax->attributes('', true)->PaxType . '',
                                    'from' => $xmlObject->PNR->Itinerary->Itin[$segment_id]->attributes('', true)->Depart . '',
                                    'to' => $xmlObject->PNR->Itinerary->Itin[$segment_id]->attributes('', true)->Arrive . '',
                                    'fare_basis' => $xmlObject->PNR->FareQuote->FQItin[$segment_id]->attributes('', true)->FQI . '',
                                    'currency' => $xmlObject->PNR->FareQuote->FareStore[$pax_no]->attributes('', true)->Cur . '',
                                    'price' => $xmlObject->PNR->FareQuote->FareStore[$pax_no]->attributes('', true)->Total . '',
                                    'fare_price' => $xmlObject->PNR->FareQuote->FareStore[$pax_no]->SegmentFS[$segment_id]->attributes('', true)->Fare . '',
                                    'tax' => (
                                        (double) $xmlObject->PNR->FareQuote->FareStore[$pax_no]->SegmentFS[$segment_id]->attributes('', true)->Tax1 +
                                        (double) $xmlObject->PNR->FareQuote->FareStore[$pax_no]->SegmentFS[$segment_id]->attributes('', true)->Tax2 +
                                        (double) $xmlObject->PNR->FareQuote->FareStore[$pax_no]->SegmentFS[$segment_id]->attributes('', true)->Tax3
                                    ),
                                    'hold_pices' => $xmlObject->PNR->FareQuote->FareStore[$pax_no]->SegmentFS[$segment_id]->attributes('', true)->HoldPcs . '',
                                    'hold_weight' => $xmlObject->PNR->FareQuote->FareStore[$pax_no]->SegmentFS[$segment_id]->attributes('', true)->HoldWt . '',
                                    'hand_weight' => $xmlObject->PNR->FareQuote->FareStore[$pax_no]->SegmentFS[$segment_id]->attributes('', true)->HandWt . '',
                                ];
                            }
                        }
                    }
                }
            }


        } else {
            $status = "NO_FARE";
            // $this->info($command_result);
        }

        return [
            'status' => $status,
            'segments' => $offers,
        ];
    }
}
