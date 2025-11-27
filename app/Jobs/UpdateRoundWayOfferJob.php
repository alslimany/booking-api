<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateRoundWayOfferJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $segments;
    /**
     * Create a new job instance.
     */
    public function __construct($segments)
    {
        $this->segments = $segments;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        \App\Models\OneWayOffer::where([
            'flight_schedule_id' => $this->segments[0]->flight_schedule_id,
            'flight_availablity_id' => $this->segments[0]->flight_availablity_id,
        ])->get()->each(function ($offer) {
            \App\Models\RoundWayPricing::updateOrCreate([
                'command' => $offer->command,
                'passenger_type' => $offer->passenger_type,
                'round_way_segment_id' => $this->segments[0]->id,
            ], [
                'passenger_type' => $offer->passenger_type,
                'from' => $offer->from,
                'to' => $offer->to,
                'departure' => $offer->departure,
                'arrival' => $offer->arrival,
                'cabin' => $offer->cabin,
                'class' => $offer->class,
                'fare_basis' => $offer->fare_basis,
                'fare_price' => $offer->fare_price,
                'tax' => $offer->tax,
                'price' => $offer->price,
                'currency' => $offer->currency,
                'hold_pices' => $offer->hold_pices,
                'hold_weight' => $offer->hold_weight,
                'hand_weight' => $offer->hand_weight,
            ]);
        });
    }
}
