<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $queues = [];

        if (Schema::hasTable('aero_tokens')) {
            foreach (\App\Models\AeroToken::all() as $aero_token) {
                $queues[] = $aero_token->getQueueId();
            }
        }

        \Config::set('horizon.defaults.supervisor-2', [
            'connection' => 'redis',
            'queue' => $queues,
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => count($queues),
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 1024,
            'tries' => 3,
            'timeout' => 30,
            'nice' => 5,
        ]);
    }
}
