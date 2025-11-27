<?php

function getAircrafts()
{
    return collect(json_decode(file_get_contents(resource_path("js/Data/aircrafts.json"))));
}

function getAircraft($code)
{
    $aircraft = getAircrafts()->filter(function ($item) use ($code) {
        // dd($item);
        return (
            str_contains(strtolower($item->iataCode), strtolower($code))
        );
    })->first();
    // dd($airport);
    return $aircraft;
}

function findAircraft($code)
{
    if ($code == 'WLB') {
        $code = 319;
    }
    if ($code == 'WLD') {
        $code = 319;
    }
    if ($code == 'INH') {
        $code = 319;
    }
    $aircraft = getAircrafts()->filter(function ($item) use ($code) {
        // dd($item);
        return (
            strtolower($item->iataCode) == strtolower($code)
        );
    })->first();
    return $aircraft;
    // dd($airport);
    return [
        'iata' => $aircraft->iataCode,
        'icao' => $aircraft->icaoCode,
        'name' => $aircraft->description,
    ];
}