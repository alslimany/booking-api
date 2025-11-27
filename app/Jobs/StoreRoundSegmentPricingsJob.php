<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class StoreRoundSegmentPricingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $segments;
    private $availability;
    private $command;
    private $offers;

    /**
     * Create a new job instance.
     */
    public function __construct($segments, $availability, $command, $offers)
    {
        $this->segments = $segments;
        $this->availability = $availability;
        $this->command = $command;
        $this->offers = $offers;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $segment_id = 0;
        foreach ($this->offers['segments'] as $segment) {
            // print_r($_offer);

            foreach ($segment as $offer) {

                $offer['flight_schedule_id'] = $this->segments[$segment_id]->flight_schedule_id;
                $offer['flight_availablity_id'] = $this->availability->id;
                $offer['round_way_segment_id'] = $this->segments[$segment_id]->id;
                $offer['departure'] = $this->segments[$segment_id]->departure;
                $offer['arrival'] = $this->segments[$segment_id]->arrival;
                $offer['cabin'] = $this->availability->cabin;
                $offer['class'] = $this->availability->class;
                // print_r($offer);

                \App\Models\RoundWayPricing::updateOrCreate([
                    'command' => $this->command,
                    'passenger_type' => $offer['passenger_type'],
                    'round_way_segment_id' => $this->segments[$segment_id]->id,
                ], $offer);
            }
            $segment_id++;
        }
    }
}
