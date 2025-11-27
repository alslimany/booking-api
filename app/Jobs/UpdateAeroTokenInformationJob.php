<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateAeroTokenInformationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $aero_token;

    /**
     * Create a new job instance.
     */
    public function __construct($aero_token)
    {
        $this->aero_token = $aero_token;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $token_info = [];

        $result = $this->aero_token->build()->runCommand("ZUA~X");

        $xml = "<xml>" . $result?->response . "</xml>";

        $xmlObject = simplexml_load_string($xml);

        $token_info = [
            'id' => $this->aero_token->id,
            'iata' => $this->aero_token->iata,
            'name' => $this->aero_token->name,
            'office_code' => (string) $xmlObject->zua->officecode . '',
            'office_name' => (string) $xmlObject->zua->officename . '',
            'office_eq' => (string) $xmlObject->zua->officeq . '',
            'office_currency' => (string) $xmlObject->zua->officecurrency . '',
            'agent_currency' => (string) $xmlObject->zua->agentcurrency . '',

            'options' => [
                'view_fares' => (boolean) $xmlObject->zua->showfares . '',
                'edit_flights' => (boolean) $xmlObject->zua->editflights . '',
                'edit_products' => (boolean) $xmlObject->zua->editproducts . '',
                'cargo_flags' => $xmlObject->zua->cargoflags . '',
                'cargo_office' => $xmlObject->zua->cargooffice . '',
                'ticket_quantity_limitaion' => $xmlObject->zua->ticketqtylimit,
            ],

            'balance' => [
                'remaining' => (double) $xmlObject->zua->ticketmoneylimit->attributes()->{'limit'},
                'currency' => (string) $xmlObject->zua->ticketmoneylimit->attributes()->{'cur'},
                'status' => (string) $xmlObject->zua->ticketmoneylimit->attributes()->{'Status'},
            ],
        ];


        // $aero_infos = cache()->get('aero_infos_cach', []);
        // $aero_infos[$this->aero_token->id] = $token_info;
        // cache()->put('aero_infos_cach', $aero_infos);

        $token = \App\Models\AeroToken::find($this->aero_token->id);

        $data = $token->data;
        $data['balance'] = $token_info['balance']['remaining'];

        $token->update([
            'data' => $data,
        ]);

    }
}
