<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncRoundOfferFareCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-round-offer-fare-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // [1] => Fetch outbound flights
        $outbound_flights = \App\Models\FlightSchedule::where('has_offers', true)
            ->whereDate('departure', '>', date('Y-m-d'))
            ->orderBy('departure', 'asc')
            ->get();

        foreach ($outbound_flights as $outbound_flight) {
            // if ($outbound_flight->iata == "YI") {
                // if ($outbound_flight->origin == "JED" || $outbound_flight->destination == "JED") {
                    $this->info("Round Flight [" . $outbound_flight->flight_number . " " . $outbound_flight->origin . " -> " . $outbound_flight->destination . " - " . date('Y-m-d', strtotime($outbound_flight->departure)) . "]");

                    \App\Jobs\SyncRoundOfferSegmentsJob::dispatch($outbound_flight)->onQueue('default');       
                // }
            // }
        }
    }
}
