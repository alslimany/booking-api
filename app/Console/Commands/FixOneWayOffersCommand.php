<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class FixOneWayOffersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-one-way-offers-command';

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
        foreach (\App\Models\OneWayOffer::whereDate('departure', '>=', date('Y-m-d'))->get() as $offer) {
            if ($offer->flight_schedule != null) {
                if ($offer->currency != $offer->flight_schedule->aero_token->data['currency_code']) {
                    $offer->delete();
                }
            }
        }
    }
}
