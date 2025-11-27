<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateOneWayOfferJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $offers;
    private $flight;
    private $command;
    private $availability;
    /**
     * Create a new job instance.
     */
    public function __construct($offers, $flight, $command, $availability)
    {
        $this->offers = $offers;
        $this->flight = $flight;
        $this->command = $command;
        $this->availability = $availability;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // if (count($this->offers) == 0) {
        //     $this->availability->seats = 0;
        //     $this->availability->save();
        // }

        foreach ($this->offers as $offer) {
            $offer['flight_schedule_id'] = $this->flight->id;
            $offer['flight_availablity_id'] = $this->availability->id;
            $offer['departure'] = $this->flight->departure;
            $offer['arrival'] = $this->flight->arrival;
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

            \App\Models\OneWayOffer::updateOrCreate([
                'flight_schedule_id' => $this->flight->id,
                'command' => $this->command,
                'passenger_type' => $offer['passenger_type'],
            ], $offer);
        }

        // $av->touch();
    }
}
