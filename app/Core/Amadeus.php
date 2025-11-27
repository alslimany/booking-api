<?php

namespace App\Core;

use App\Models\AeroToken;
use Illuminate\Support\Facades\Http;

class Amadeus implements ICore
{
    private $aeroToken;
    private $url;
    private $client;

    public function __construct(AeroToken $aero)
    {
        $this->aeroToken = $aero;
        $this->url = $aero->data['url'];

        $grant_type = "client_credentials";
        $client_id = $aero->data['client_id'];
        $client_secret = $aero->data['client_secret'];

        $response = Http::post($this->url, [
            'grant_type' => $grant_type,
            'client_id' => $client_id,
            'client_secret' => $client_secret,
        ]);

        dd($response->json());
    }

    public function findOneWayFlights($data)
    {

    }

    public function findRoundFlights($data)
    {

    }
    public function findMultistopFlights($data)
    {

    }

    public function flightContacts($data)
    {

    }
    /**
     * @inheritDoc
     */
    public function createPnr($data)
    {
    }

    /**
     * @inheritDoc
     */
    public function flightAvialability($data)
    {
    }

    /**
     * @inheritDoc
     */
    public function flightDatesAvialability($data)
    {
    }

    /**
     * @inheritDoc
     */
    public function holdPnr($data)
    {
    }

    /**
     * @inheritDoc
     */
    public function schedule($data)
    {
    }
}