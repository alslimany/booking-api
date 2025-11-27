<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncRoundOfferSegmentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $outbound_flight;

    /**
     * Create a new job instance.
     */
    public function __construct($outbound_flight)
    {
        $this->outbound_flight = $outbound_flight;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // [2] => Fetch inbound flights
        $inbound_flights = \App\Models\FlightSchedule::where('has_offers', true)
            ->whereDate('departure', '>', date('Y-m-d', strtotime($this->outbound_flight->departure)))
            ->where('origin', $this->outbound_flight->destination)
            ->where('destination', $this->outbound_flight->origin)
            ->orderBy('departure', 'asc')
            ->get();
        foreach ($inbound_flights as $inbound_flight) {
            // [3] => Register flight offer
            $round_offer = \App\Models\RoundWayOffer::updateOrCreate(
                [
                    'uuid' => md5($this->outbound_flight->uuid . '' . $inbound_flight->uuid)
                ],
                [
                    'from' => $this->outbound_flight->origin,
                    'to' => $this->outbound_flight->destination,
                    'departure' => $this->outbound_flight->departure,
                    'return' => $inbound_flight->departure,
                    'number_of_stops' => 0,
                ]
            );

            // if ($round_offer->wasRecentlyCreated) {

            $round_offer->segments()->updateOrCreate([
                'flight_number' => $this->outbound_flight->flight_number,
                'departure' => $this->outbound_flight->departure,
            ], [
                'flight_schedule_id' => $this->outbound_flight->id,
                'type' => 'outbound',
                'from' => $this->outbound_flight->origin,
                'to' => $this->outbound_flight->destination,
                'arrival' => $this->outbound_flight->arrival,
                'carrier' => $this->outbound_flight->iata,
                'duration' => $this->outbound_flight->duration,
            ]);

            $round_offer->segments()->updateOrCreate([
                'flight_number' => $inbound_flight->flight_number,
                'departure' => $inbound_flight->departure,
            ], [
                'flight_schedule_id' => $inbound_flight->id,
                'type' => 'inbound',
                'from' => $inbound_flight->origin,
                'to' => $inbound_flight->destination,
                'arrival' => $inbound_flight->arrival,
                'carrier' => $inbound_flight->iata,
                'duration' => $inbound_flight->duration,
            ]);

            \App\Jobs\SyncRoundWayOfferJob::dispatch($round_offer)->onQueue('videcom-high');
            // }
        }
    }
}
