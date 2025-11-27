<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ClearAllFlightData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clear-all-flight-data';

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
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();

        \App\Models\RoundWayPricing::truncate();
        \App\Models\RoundWaySegment::truncate();
        \App\Models\RoundWayOffer::truncate();
        \App\Models\OneWayOffer::truncate();
        \App\Models\FlightAvailablity::truncate();
        \App\Models\FlightSchedule::truncate();

        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
    }
}
