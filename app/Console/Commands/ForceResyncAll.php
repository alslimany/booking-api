<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ForceResyncAll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:force-resync-all';

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
        Artisan::call('app:sync-flight-schedule-command');
        Artisan::call('app:sync-flight-schedule-avialability');
        Artisan::call('app:check-flight-seat-availability-command');
        Artisan::call('app:flights-prune-command');
        Artisan::call('app:sync-one-way-offer-fares-command');
        Artisan::call('app:flights-prune-command');
        Artisan::call('app:sync-round-offer-fare-command');
        Artisan::call('app:flights-prune-command');
    }
}
