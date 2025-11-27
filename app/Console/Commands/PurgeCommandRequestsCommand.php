<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeCommandRequestsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:purge-command-requests {--days=30}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge old command requests';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days');
        $date = date('Y-m-d H:i:s', strtotime(now() . ' - ' . $days . ' days'));

        $this->info("CommandRequest purge before " . $date . " started");

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        $counter = 0;
        \App\Models\CommandRequest::where('created_at', '<', $date)
            ->chunkById(100, function ($records) use (&$counter) {
                $counter += 100;
                foreach ($records as $record) {
                    $record->delete();
                }

                $this->info("Deleted = " . $counter);

            });

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $count = \App\Models\CommandRequest::where('created_at', '<', $date)->count();
        $this->info("Found " . $count);

        $this->info("CommandRequest before " . $date . " has been removed.");
    }
}
