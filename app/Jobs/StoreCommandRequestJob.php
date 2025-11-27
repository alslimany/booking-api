<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class StoreCommandRequestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $aeroToken, $cmd, $rq, $user;
    /**
     * Create a new job instance.
     */
    public function __construct($aeroToken, $cmd, $rq, $user)
    {
        $this->aeroToken = $aeroToken;
        $this->cmd = $cmd;
        $this->rq = $rq;
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        \App\Models\CommandRequest::create([
            'aero_token_id' => $this->aeroToken->id,
            'user_id' => $this->user,
            'command' => $this->cmd,
            'result' => $this->rq,
        ]);
    }
}
