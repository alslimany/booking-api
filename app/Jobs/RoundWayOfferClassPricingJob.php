<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RoundWayOfferClassPricingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $availability;
    private $carrier;
    private $segments;
    /**
     * Create a new job instance.
     */
    public function __construct($availability, $carrier, $segments)
    {
        $this->availability = $availability;
        $this->carrier = $carrier;
        $this->segments = $segments;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // sleep(1);
        // Prepare Command
        $cach_key = $this->carrier . "I^-4Pax/A#/B#.CH10/C#.IN06/D#.IS06/";
        $command = "I^-4Pax/A#/B#.CH10/C#.IN06/D#.IS06/";
        foreach ($this->segments as $segment) {
            $command .= "^0" . $segment->flight_number . $this->availability->class . date('dM', strtotime($segment->departure)) . $segment->from . $segment->to . "NN3";
            $cach_key .= "^0" . $this->availability->class . $segment->from . $segment->to . "NN3";
        }
        $command .= "^FG^FS1^*r~x";
        $cach_key .= "^FG^FS1^*r~x";

        $offers = [];

        if (cache()->has($cach_key)) {
            $offers = cache()->get($cach_key);
        } else {
            if ($this->availability?->flight_schedule != null) {
                $aero_token = \App\Models\AeroToken::where('id', $this->availability->aero_token_id)->first();
                try {
                    $result = $aero_token->build()->runCommand($command);
                } catch (\Exception $connection_exception) {
                    # Retry Job
                    \App\Jobs\RoundWayOfferClassPricingJob::dispatch($this->availability, $this->carrier, $this->segments)->onQueue($aero_token->getQueueId());
                    return;
                }

                if (str_contains(strtolower($result->response), 'cannot be booked as open')) {

                    $new_command = "I^-4Pax/A#/B#.CH10/C#.IN06/D#.IS06/";
                    foreach ($this->segments as $segment) {
                        $new_command .= "^0" . $segment->flight_number . $this->availability->class . date('dM', strtotime($segment->departure)) . $segment->from . $segment->to . "NN3";
                    }
                    $new_command .= "^FG^FS1^*r~x";


                    $result = $aero_token->build()->runCommand($new_command);

                    $offers = $this->parse_result($result->response);

                    cache()->put($cach_key, $offers, now()->addHours(6));

                } else {
                    $offers = $this->parse_result($result->response);

                    cache()->put($cach_key, $offers, now()->addHours(6));
                }
            }
        }


        // $this->comment($carrier . " <> " . $command);
        $segment_id = 0;
        if ($offers != null) {
            if ($offers['status'] == "OK") {

                \App\Jobs\StoreRoundSegmentPricingsJob::dispatch($this->segments, $this->availability, $command, $offers)->onQueue('default');

                // foreach ($offers['segments'] as $segment) {
                //     // print_r($_offer);

                //     foreach ($segment as $offer) {

                //         $offer['flight_schedule_id'] = $this->segments[$segment_id]->flight_schedule_id;
                //         $offer['flight_availablity_id'] = $this->availability->id;
                //         $offer['round_way_segment_id'] = $this->segments[$segment_id]->id;
                //         $offer['departure'] = $this->segments[$segment_id]->departure;
                //         $offer['arrival'] = $this->segments[$segment_id]->arrival;
                //         $offer['cabin'] = $this->availability->cabin;
                //         $offer['class'] = $this->availability->class;
                //         // print_r($offer);

                //         \App\Models\RoundWayPricing::updateOrCreate([
                //             'command' => $command,
                //             'passenger_type' => $offer['passenger_type'],
                //             'round_way_segment_id' => $this->segments[$segment_id]->id,
                //         ], $offer);
                //     }
                //     $segment_id++;
                // }

            } elseif ($offers['status'] == "NO_FARE") {
                // $this->round_way_offer->delete();
                // cache()->forget($cach_key);
            } elseif ($offers['status'] == "NO_SERVICE") {
                // $this->round_way_offer->delete();
                // cache()->forget($cach_key);
                $this->availability->delete();
            }
        } else {
            cache()->forget($cach_key);
        }
    }

    private function parse_result($command_result): array
    {
        $status = "OK";

        $offers = [];
        if (str_contains(strtolower($command_result), 'no fare')) {
            $status = "NO_FARE";
            // $this->info($command_result);
        } else if (str_contains(strtolower($command_result), 'not available on this service')) {
            $status = "NO_SERVICE";
        } else {
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

        }

        return [
            'status' => $status,
            'segments' => $offers,
        ];
    }
}
