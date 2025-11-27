<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckFlightSeatAvailabilityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $flight;
    /**
     * Create a new job instance.
     */
    public function __construct(\App\Models\FlightSchedule $flight)
    {
        $this->flight = $flight;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // sleep(1);
        $now = \Carbon\Carbon::now();
        // $updated_at = \Carbon\Carbon::parse($this->flight->updated_at);
        // if ($updated_at->diffInHours($now) >= 1) {
        $command = "A" . date("dM", strtotime($this->flight->departure)) . $this->flight->origin . $this->flight->destination . "~x";

        $result = $this->flight->aero_token->build()->runCommand($command);

        if ($result->response != null) {
            $xml = $result->response;

            $flight_found = false;

            if (str_contains(strtolower($xml), 'notsinedinexception')) {
                // Retry Job
                // throw new \Exception("VIDECOM_USER_NOT_SIGNED_IN:" . $this->flight->aero_token->iata);

                // cache()->forget($this->flight->aero_token->iata);
                // dispatch(new CheckFlightSeatAvailabilityJob($this->flight))->onQueue('default');
                dispatch(new CheckFlightSeatAvailabilityJob($this->flight))->onQueue($this->flight->aero_token->getQueueId());
            } else {
                if (isValidXml($xml)) {
                    $xmlObject = simplexml_load_string($xml);

                    if ($xmlObject) {
                        foreach ($xmlObject->Journeys->Journey as $row) {
                            $origin = $row->DepartureAirportCityCode . '';
                            $destination = $row->ArrivalAirportCityCode . '';
                            $departure = date('Y-m-d', strtotime($row->DepartureDate));

                            if (
                                date('Y-m-d', strtotime($this->flight->departure)) == date('Y-m-d', strtotime($departure))
                                &&
                                $this->flight->origin == $origin
                                &&
                                $this->flight->destination == $destination
                            ) {
                                $flight_found = true;
                                foreach ($row->Legs->BookFlightSegmentType as $segment) {
                                    $flight_number = $segment->attributes('', true)->FlightNumber . '';

                                    if ($this->flight->flight_number == $flight_number) {
                                        $is_available = true;

                                        foreach ($segment->Availability->Class as $av_class) {
                                            // $this->flight->availablities()->where([
                                            //     'cabin' => $av_class->attributes('', true)->cab . '',
                                            //     'class' => $av_class->attributes('', true)->id . ''
                                            // ])->first();
                                            if (
                                                $this->flight->availablities()->where([
                                                    'cabin' => $av_class->attributes('', true)->cab . '',
                                                    'class' => $av_class->attributes('', true)->id . ''
                                                ])->count() > 0
                                            ) {
                                                $this->flight->availablities()->where([
                                                    'cabin' => $av_class->attributes('', true)->cab . '',
                                                    'class' => $av_class->attributes('', true)->id . ''
                                                ])->update([
                                                            'seats' => $av_class->attributes('', true)->av . '',
                                                        ]);
                                            } else {
                                                $new_availability = [
                                                    'flight_schedule_id' => $this->flight->id,
                                                    'name' => $av_class->attributes('', true)->cab . '',
                                                    'display_name' => $av_class->attributes('', true)->id . '',
                                                    'cabin' => $av_class->attributes('', true)->cab . '',
                                                    'class' => $av_class->attributes('', true)->id . '',
                                                    'is_international' => false,
                                                    'rules' => '[]',
                                                    'carrier' => $this->flight->iata,
                                                ];

                                                $new_availability['seats'] = $av_class->attributes('', true)->av . '';
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
                                                    dispatch(new \App\Jobs\SyncOneWayOfferJob($av_model))->onQueue($this->flight->aero_token->getQueueId());
                                                }
                                            }



                                            // if ((int) $av_class->attributes('', true)->av > 0) {
                                            //     $is_available = true;
                                            // }
                                        }

                                        $this->flight->has_offers = $is_available;
                                    }
                                }
                            } else {

                                foreach ($row->Legs->BookFlightSegmentType as $segment) {
                                    $flight_number = $segment->attributes('', true)->FlightNumber . '';

                                    $other_flight = \App\Models\FlightSchedule::where([
                                        'origin' => $origin,
                                        'destination' => $destination,
                                        'flight_number' => $flight_number
                                    ])
                                        ->whereDate('departure', date('Y-m-d', strtotime($departure)))
                                        ->first();


                                    if ($other_flight != null) {
                                        $is_available = true;
                                        foreach ($segment->Availability->Class as $av_class) {
                                            $other_flight->availablities()->where([
                                                'cabin' => $av_class->attributes('', true)->cab . '',
                                                'class' => $av_class->attributes('', true)->id . ''
                                            ])->update([
                                                        'seats' => $av_class->attributes('', true)->av . '',
                                                    ]);

                                            // if ((int) $av_class->attributes('', true)->av > 0) {
                                            //     $is_available = true;
                                            // }
                                        }

                                        $other_flight->has_offers = $is_available;
                                    }

                                }
                            }
                        }

                        if ($this->flight->availablities()->where('seats', '>', 0)->count() == 0) {
                            $this->flight->has_offers = true;
                        }

                        if (!$flight_found) {
                            $this->flight->has_offers = false;
                        }


                        $this->flight->save();

                        // if ($flight_found) {
                        //     // $this->flight->availablities()->update(['seats' => 0]);
                        //     if ($this->flight->availablities()->where('seats', '>', 0)->count() == 0) {
                        //         $this->flight->has_offers = true;
                        //     } else {
                        //         $this->flight->has_offers = false;
                        //     }
                        // } else {
                        //     $this->flight->availablities()->update(['seats' => 0]);
                        //     $this->flight->has_offers = false;
                        // }

                        // $this->flight->save();
                    }
                }

            }
        }
        // }

    }
}
