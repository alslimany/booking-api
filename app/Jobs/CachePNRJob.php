<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CachePNRJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $pnr;
    private $iata;
    /**
     * Create a new job instance.
     */
    public function __construct($pnr, $iata)
    {
        $this->pnr = $pnr;
        $this->iata = $iata;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // sleep(1);
        $aero_token = \App\Models\AeroToken::where('iata', $this->iata)->first();

        $query_pnr_command = "*" . $this->pnr;
        $query_pnr_command .= "^*R~x";


        # artisan('horizon:pause');
        $pnr = [];
        try {
            $pnr = cache()->remember($query_pnr_command, now()->addSeconds(60), function () use ($query_pnr_command, $aero_token, $request) {
                $result = $aero_token->build()->runCommand($query_pnr_command);

                $xmlObject = simplexml_load_string("<xml>" . $result->response . "</xml>");

                $pnr = $this->parse_pnr($xmlObject);

                // return $command;

                if ($pnr['is_issued'] && !$pnr['is_voidable']) {
                    $command = "*" . $request->pnr;
                    $index = 1;
                    $segment_cancellation = "X";

                    foreach ($pnr['itineraries'] as $it) {
                        $command .= "^FCR" . $index . "^FCC" . $index;
                        $index++;
                    }

                    if (count($pnr['itineraries']) > 1) {
                        $segment_cancellation .= "1-" . count($pnr['itineraries']);
                    } else {
                        $segment_cancellation .= "1";
                    }

                    $command .= "^" . $segment_cancellation . "^FSM";

                    $command .= "^*R~X";
                    // return $command;
                    $pnr_fsm_result = $aero_token->build()->runCommand($command);

                    $mps_object = simplexml_load_string("<xml>" . $pnr_fsm_result->response . "</xml>");

                    $pnr_fsm = $this->parse_mps_pnr($mps_object);

                    $pnr['mps'] = $pnr_fsm['mps'];
                    if (isset($pnr_fsm['refund_amount'])) {
                        $pnr['refund_amount'] = $pnr_fsm['refund_amount'];
                    }
                }

                return $pnr;
            });

        } catch (\Exception $ex) {

        } finally {
            # artisan('horizon:continue');
        }
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

                    // $flight_schedule = \App\Models\FlightSchedule::where('origin', $itinerary->attributes('', true)->Depart)
                    //     ->where('destination', $itinerary->attributes('', true)->Arrive)
                    //     ->where('flight_number', ( $itinerary->attributes('', true)->AirID .'' . $itinerary->attributes('', true)->FltNo ))
                    //     ->whereDate('departure', date('Y-m-d', strtotime( $itinerary->attributes('', true)->DepDate)))
                    //     ->first();

                    //     $fare_qoute = [
                    //         'segment_id' => $xmlObject->FareQuote->FQItin[$itinerary_index]->attributes('', true)->Seg . "",
                    //         'total' => $xmlObject->FareQuote->FQItin[$itinerary_index]->attributes('', true)->Total . "",
                    //         'fare' => $xmlObject->FareQuote->FQItin[$itinerary_index]->attributes('', true)->Fare . "",
                    //         'tax1' => $xmlObject->FareQuote->FQItin[$itinerary_index]->attributes('', true)->Tax1 . "",
                    //         'tax2' => $xmlObject->FareQuote->FQItin[$itinerary_index]->attributes('', true)->Tax2 . "",
                    //         'tax3' => $xmlObject->FareQuote->FQItin[$itinerary_index]->attributes('', true)->Tax3 . "",
                    //         'currency' => $xmlObject->FareQuote->FQItin[$itinerary_index]->attributes('', true)->Cur . "",   
                    //     ];

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

}
