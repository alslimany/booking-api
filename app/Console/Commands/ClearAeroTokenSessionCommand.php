<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ClearAeroTokenSessionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clear-aero-token-session-command';

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
        foreach (\App\Models\AeroToken::where('data->mode', '=', 'user_auth')->get() as $token) {
            
            // $this->call('queue:clear --queue=' . $token->getQueueId());

            $token->build()->runCommand('QX');
            
            $auth_user = $token->data['auth_user'];
            $auth_pass = $token->data['auth_pass'];
            $url = $token->data['url'];

            if (cache()->has($token->iata)) {
                $session = cache()->get($token->iata);
                // $rq = Http::get($url . '/VARS/Agent/logout.aspx?' . $session, [
                    
                // ]);
                $rq = Http::post($url . '/VARS/Agent/res/EmulatorWS.asmx/DeleteCarouselEntry?' . $session, [
                    
                ]);
            }

            // cache()->forget($token->iata);
            
            
        }
    }
}
