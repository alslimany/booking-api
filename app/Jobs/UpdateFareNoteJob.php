<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateFareNoteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $fare_rule;
    /**
     * Create a new job instance.
     */
    public function __construct($fare_rule)
    {
        $this->fare_rule = $fare_rule;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // sleep(1);
        if ($this->fare_rule->aero_token != null) {
            $result = $this->fare_rule->aero_token->build()->runCommand("FN" . $this->fare_rule->fare_id);
            if ($result->response != 'ERROR - No Rules Foun') {
                $fare_note = $result->response;

                if ($this->fare_rule->note != $fare_note) {
                    $this->fare_rule->status = 'changed';
                }

                $this->fare_rule->note = $fare_note;

                $this->fare_rule->save();
            }
        }
    }
}
