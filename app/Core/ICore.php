<?php

namespace App\Core;

use App\Models\AeroToken;

interface ICore
{
    public function __construct(AeroToken $aero);

    // Flight Offers Search
    public function flightDatesAvialability($data);
    public function schedule($data);
    public function flightAvialability($data);
    public function findOneWayFlights($data);

    public function holdPnr($data);
    public function createPnr($data);
    public function findRoundFlights($data);
    public function findMultistopFlights($data);

    // Flight Offers Pricing

}