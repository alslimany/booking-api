<?php

function getAirports()
{
    return collect(json_decode(file_get_contents(resource_path("js/Data/_airports.json"))));
}

function getAirport($code)
{
    $airport = getAirports()->filter(function($item) use ($code) {
        // dd($item);
        return (
            str_contains(strtolower($item->IATA), strtolower($code))
        );
    })->first();
    // dd($airport);
    return $airport;
}

function getDbAirport($code) {
    return \App\Models\Airport::where('iata', $code)->first();
}