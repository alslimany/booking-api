<?php

namespace App\Console\Commands;

use App\Jobs\UpdateAeroTokenInformationJob;
use Illuminate\Console\Command;

class UpdateAeroTokenInformation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-aero-token-information';

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
        foreach (\App\Models\AeroToken::all() as $aero_token) {
            dispatch(new UpdateAeroTokenInformationJob($aero_token));//->onQueue($aero_token->getQueueId());
        }
    }
}
