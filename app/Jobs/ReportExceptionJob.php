<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Laravel\Horizon\Contracts\Silenced;
use Throwable;

class ReportExceptionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Throwable $e;
    /**
     * Create a new job instance.
     */
    public function __construct(Throwable $e)
    {
        $this->e = $e;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $url = "http://median.centra.atom.ly/api/exceptions";


            Http::post($url, [
                'application_id' => '9d793550-1f59-4759-b550-44635a816835',
                'description' => $this->e->getMessage(),
                'severity' => [
                    'location' => $this->e->getFile(),
                    'line' => $this->e->getLine(),
                    'stack_trace' => $this->e->getTrace(),
                ],
            ]);
        } catch (Throwable $e) {

        }

    }
}
