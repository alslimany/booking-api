<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GetFlightClassbandInformation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $flight;
    /**
     * Create a new job instance.
     */
    public function __construct($flight)
    {
        $this->flight = $flight;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // $yesterday = \Carbon\Carbon::now()->subDays(1)->toDateTimeString();
        // sleep(1);
        if ($this->flight->availablities()->count() == 0) {
            $command = "A" . date('dM', strtotime($this->flight->departure)) . $this->flight->origin . $this->flight->destination . "[vars=true,singleseg=s,fgnoav=true,classbands=true]";

            $response = $this->flight->aero_token->build()->runCommand($command);

            $result = $response?->response;

            // if (cache()->has($command)) {
            //     $result = cache()->get($command);
            // } else {
            //     $response = $this->flight->aero_token->build()->runCommand($command);

            //     $result = $response?->response;

            //     cache()->put($command, $result, now()->addHour());
            // }

            if ($result != null) {
                $xml = "<xml></xml>";

                $xml = $result;

                $xmlObject = simplexml_load_string($xml);

                if ($xmlObject) {
                    $classbands = [];

                    foreach ($xmlObject->classbands->band as $classband) {
                        $text_records = [];
                        foreach ($classband->cbtextrecords->cbtext as $cbtext) {
                            $text_records[] = [
                                'id' => $cbtext->attributes('', true)->id . '',
                                'text' => $cbtext->attributes('', true)->text . '',
                                'icon' => $cbtext->attributes('', true)?->icon . '',
                            ];
                        }

                        $classbands[] = [
                            'id' => $classband->attributes('', true)->cb . '',
                            'cbname' => $classband->attributes('', true)->cbname . '',
                            'cbclasses' => $classband->attributes('', true)->cbclasses . '',
                            'cabin' => $classband->attributes('', true)->cabin . '',
                            'cbdisplayname' => $classband->attributes('', true)->cbdisplayname . '',
                            'text_records' => $text_records,
                        ];
                    }

                    $classbands = collect($classbands);

                    if (count($xmlObject->itin) > 0) {
                        foreach ($xmlObject->itin as $itin) {
                            foreach ($itin->xpath('//cb') as $cb) {

                                $cb_index = ((int) ($cb . '') - 1);
                                $class_band = $classbands[$cb_index];

                                $seats = $itin->flt->fltav->av[$cb_index] . '';

                                $availability = [
                                    'flight_schedule_id' => $this->flight->id,
                                    'name' => $class_band['cbname'],
                                    'display_name' => $class_band['cbdisplayname'],
                                    'cabin' => $class_band['cabin'],
                                    'class' => $class_band['cbclasses'],
                                    'is_international' => ($itin->international . '' == 1) ? true : false,
                                    'rules' => $class_band['text_records'],
                                    'carrier' => $this->flight->iata,
                                ];

                                // $uuid = $availability['flight_schedule_id'] . "_" . $availability['name'] . "_" .
                                //     $availability['display_name'] . $availability['cabin'] . $availability['class'];
                                $uuid = $availability['flight_schedule_id'] . $availability['cabin'] . $availability['class'];


                                if ($seats == "") {
                                    $availability['seats'] = 0;
                                    $availability['price'] = 0;
                                    $availability['currency'] = '';
                                    $availability['tax'] = 0;
                                    $availability['miles'] = 0;
                                    $availability['fare_available'] = false;
                                    $availability['fare_id'] = 0;
                                    $availability['aircraft_code'] = 0;

                                } else {
                                    // $availability['seats'] = (int) ($itin->flt->fltav->av[$cb_index] . '');
                                    $availability['price'] = (double) $itin->flt->fltav->pri[$cb_index] . '';
                                    $availability['currency'] = (double) $itin->flt->fltav->cur[$cb_index] . '';
                                    $availability['tax'] = (double) $itin->flt->fltav->tax[$cb_index] . '';
                                    $availability['miles'] = (double) $itin->flt->fltav->miles[$cb_index] . '';
                                    $availability['fare_available'] = ($itin->flt->fltav->fav[$cb_index] . '' == 1) ? true : false;
                                    $availability['fare_id'] = (int) $itin->flt->fltav->fid[$cb_index] . '';
                                    $availability['aircraft_code'] = (string) $itin->flt->fltdet->eqp[$cb_index];

                                    // $this->flight->aircraft_code = (string) $itin->flt->fltdet->eqp[$cb_index];
                                    // $this->flight->save();
                                    // \App\Models\FlightSchedule::where('id', $this->flight->id)->update([
                                    //     'aircraft_code' => (string) $itin->flt->fltdet->eqp[$cb_index],
                                    // ]);
                                }

                                $av_model = \App\Models\FlightAvailablity::updateOrCreate([
                                    'uuid' => md5($uuid),
                                ], $availability);

                                \App\Models\FlightSchedule::where('id', $this->flight->id)->update([
                                    'aircraft_code' => $availability['aircraft_code'],
                                ]);

                                $rule = \App\Models\FareRule::where([
                                    'carrier' => $availability['carrier'],
                                    'fare_id' => $availability['fare_id'],
                                ])->first();

                                if ($rule != null) {

                                } else {
                                    \App\Models\FareRule::create([
                                        'carrier' => $availability['carrier'],
                                        'fare_id' => $availability['fare_id'],
                                        'aero_token_id' => $this->flight->aero_token_id,
                                        'rules' => $class_band['text_records'],
                                        'note' => '[]',
                                        'status' => 'new',
                                    ]);
                                }
                            }


                        }
                    } else {

                    }
                }
            }
        }

        // if ($this->flight->availabilities()->where('seat', '>', 0)->count() == 0) {
        //     $this->flight->has_offer = false;
        //     $this->flight->save();
        // }

    }
}
