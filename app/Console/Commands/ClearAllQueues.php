<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ClearAllQueues extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clear-all-queues';

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
        $queues = [];

        foreach (config('horizon.defaults') as $supervisor) {
            array_push($queues, ...$supervisor['queue']);
        }

        foreach ($queues as $queue) {
            // $this->info("Queue : " . $queue);
            $this->info("php artisan queue:clear --queue=" . $queue);
            artisan("queue:clear --queue=" . $queue);
        }
    }
}
