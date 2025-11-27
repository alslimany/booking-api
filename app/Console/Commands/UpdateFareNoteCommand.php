<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpdateFareNoteCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-fare-note';

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
        foreach (\App\Models\FareRule::all() as $fare_rule) {
            $this->info("Fare " . $fare_rule->fare_id);
            \App\Jobs\UpdateFareNoteJob::dispatch($fare_rule)->onQueue($fare_rule->aero_token->getQueueId());
        }
    }
}
