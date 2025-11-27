<?php

namespace App\Console\Commands;

use App\Models\OneWayOffer;
use Illuminate\Console\Command;

class SyncOneWayOfferFaresCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-one-way-offer-fares-command';

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
        $qty = 0;
        // ini_set('memory_limit', '-1');
        foreach (\App\Models\FlightSchedule::where('has_offers', true)->whereDate('departure', '>=', date('Y-m-d'))->where('canceled_at', null)->where('iata', 'BM')->orderBy('departure', 'desc')->get() as $flight) {
            // foreach (\App\Models\FlightSchedule::where('id', 17808)->get() as $flight) {

            foreach ($flight->availablities()->get() as $availability) {
                // sleep(2);
                // $this->info("Class $availability->class");
                // \App\Jobs\SyncOneWayOfferJob::dispatch(($availability))->onQueue('videcom-low');
                if ($availability->one_way_offers()->count() < 4) {
                    if ($flight->aero_token != null) {
                        // $this->info("Flight " . date('dM', strtotime($flight->departure)) . " $flight->flight_number || $flight->origin -> $flight->destination");
                        if ($availability->seats >= 0) {
                            if ($qty < 100) {
                                $qty++;
                                \App\Jobs\SyncOneWayOfferJob::dispatch(($availability))->onQueue($flight->aero_token->getQueueId())->delay(now()->addSeconds(5));
                                // }
                                $count = $availability->one_way_offers()->count();

                                $this->info("$flight->id " . date('dM', strtotime($flight->departure)) . " $flight->flight_number || $flight->origin -> $flight->destination [$count]");
                            }
                        }
                    }
                }
            }
        }
        // ini_set('memory_limit', '512M');
    }

    private function getCabinName($cabin)
    {
        switch ($cabin) {
            case 'Y':
                return 'ECONOMY';
            case 'C':
                return 'BUSINESS';
            default:
                return 'ECONOMY';
        }
    }

}
