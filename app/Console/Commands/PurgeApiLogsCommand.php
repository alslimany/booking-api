<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PurgeApiLogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:purge-api-logs {--days=30}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge Api Logs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days');

        $date = date('Y-m-d H:i:s', strtotime(now() . ' - ' . $days . ' days'));

        $this->info("ApiLog purge before " . $date . " started");
        
        \App\Models\ApiLog::where('created_at', '<', $date)
            ->delete();

        $this->info("ApiLog before " . $date . " has been removed.");

    }
}
