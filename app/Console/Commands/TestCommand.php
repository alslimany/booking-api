<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-command';

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
        // ini_set('memory_limit', '-1');
        foreach (\App\Models\RoundWayOffer::all() as $round_way_offer) {
            foreach ($round_way_offer->segments->groupBy('carrier') as $carrier => $segments) {

                if (count($segments) == 2) {
                    $available_classes = \App\Models\FlightAvailablity::whereIn('flight_schedule_id', $segments->pluck('flight_schedule_id')->toArray())->get();
                    foreach ($available_classes as $availability) {
                        \App\Jobs\RoundWayOfferClassPricingJob::dispatch($availability, $carrier, $segments)->onQueue($availability->flight_schedule?->aero_token->getQueueId());
                    }
                } else {
                    \App\Jobs\UpdateRoundWayOfferJob::dispatch($segments)->onQueue('videcom-low');
                }
            }
        }
        // ini_set('memory_limit', '512M');
    }
}
