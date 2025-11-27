<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckFlightCanellationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $flight_schedule;

    /**
     * Create a new job instance.
     */
    public function __construct($flight_schedule)
    {
        $this->flight_schedule = $flight_schedule;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // sleep(1);
        $command = "A" . date('dM', strtotime($this->flight_schedule->departure)) . $this->flight_schedule->origin . $this->flight_schedule->destination . "[vars=true,singleseg=s,fgnoav=true,classbands=true]";

        if ($this->flight_schedule->aero_token != null) {
            $result = $this->flight_schedule->aero_token->build()->runCommand($command);

            if ($result->response != null) {
                $xml = $result->response;
    
                $flight_found = false;
                if (!str_contains(strtolower($xml), 'error')) {
                    if (isValidXml($xml)) {
                        $xmlObject = simplexml_load_string($xml);
    
                        if ($xmlObject) {
                            foreach ($xmlObject->itin as $row) {
    
                                $origin = $row->flt->dep . '';
                                $destination = $row->flt->arr . '';
                                $departure = date('Y-m-d H:i:s', strtotime($row->flt->time->ddaylcl . ' ' . $row->flt->time->dtimlcl));
                                $arrival = date('Y-m-d H:i:s', strtotime($row->flt->time->adaylcl . ' ' . $row->flt->time->atimlcl));
                                $duration = $row->flt->time->duration . '';
                                $flight_number = $row->flt->fltdet->airid . '' . $row->flt->fltdet->fltno;
    
    
                                if (
                                    $this->flight_schedule->origin == $origin &&
                                    $this->flight_schedule->destination == $destination &&
                                    $this->flight_schedule->flight_number == $flight_number &&
                                    $this->flight_schedule->departure == $departure &&
                                    $this->flight_schedule->arrival == $arrival
                                    // && $this->flight_schedule->duration == $duration
                                ) {
                                    $flight_found = true;
                                }
                            }
    
    
                            if (!$flight_found) {
                                $this->flight_schedule->canceled_at = now();
                                $this->flight_schedule->save();
                                $this->flight_schedule->delete();
                            } else {
                                $this->flight_schedule->duration == $duration;
                                $this->flight_schedule->canceled_at = null;
                                $this->flight_schedule->save();
                            }
                        }
                    }
    
                }
            }
        }
    }
}
