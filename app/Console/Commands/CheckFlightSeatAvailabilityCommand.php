<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckFlightSeatAvailabilityCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-flight-seat-availability-command {--days=0} {--queue=none}';

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
        $days = $this->option('days');
        $flights = [];

        $queue = $this->option('queue');
        if ($days > 0) {
            $flights = \App\Models\FlightSchedule::whereBetween('departure', [date('Y-m-d H:i:s'), date('Y-m-d H:i:s', strtotime(now() . " + " . $days . " days"))])
                // ->where('updated_at', '<', \Carbon\Carbon::now()->subHours(5))
                // ->hasLowerSeats()
                ->orderBy('departure')
                ->get();
        } else {
            $flights = \App\Models\FlightSchedule::whereDate('departure', '>=', date('Y-m-d H:i:s'))
                // ->where('updated_at', '<', \Carbon\Carbon::now()->subHours(5))
                // ->hasLowerSeats()
                ->orderBy('departure')
                ->get();
        }

        foreach ($flights as $flight) {
            if ($flight->aero_token != null) {
                \App\Jobs\CheckFlightSeatAvailabilityJob::dispatch($flight)->onQueue($flight->aero_token?->getQueueId());
                $this->info("=== " . date('dM', strtotime($flight->departure)) . " $flight->flight_number || $flight->origin -> $flight->destination");
            }
            
            // if ($queue == "none") {
            //     $app_queue = \App::make('queue.connection');

            //     if ($app_queue->size($flight->iata) < 10) {
            //         \App\Jobs\CheckFlightSeatAvailabilityJob::dispatch($flight)->onQueue($flight->iata);
            //     } elseif ($app_queue->size('videcom-high') < 10) {
            //         \App\Jobs\CheckFlightSeatAvailabilityJob::dispatch($flight)->onQueue('videcom-high');
            //     } else {
            //         \App\Jobs\CheckFlightSeatAvailabilityJob::dispatch($flight)->onQueue('videcom-low');
            //     }
            // } else {
            //     \App\Jobs\CheckFlightSeatAvailabilityJob::dispatch($flight)->onQueue($queue);
            // }
            // \App\Jobs\CheckFlightSeatAvailabilityJob::dispatch($flight)->onQueue($flight->iata);
           
        }
    }
}
