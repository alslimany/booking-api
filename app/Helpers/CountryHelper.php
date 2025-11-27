<?php

function getCountries()
{
    return collect(json_decode(file_get_contents(resource_path("js/Data/countries.json"))));
}

// function getAirline($code)
// {
//     $airline = getAirlines()->filter(function($item) use ($code) {
//         // dd($item);
//         return (
//             str_contains(strtolower($item->iata), strtolower($code))
//         );
//     })->first();
//     // dd($airline);
//     return $airline;
// }